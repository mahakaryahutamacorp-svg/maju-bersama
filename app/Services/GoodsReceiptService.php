<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\GoodsReceipt;
use App\Models\Inventory;
use App\Models\JournalHeader;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service to process goods receipt (penerimaan barang dari supplier ke Gudang Pusat).
 *
 * Atomically:
 * 1. Creates GoodsReceipt and GoodsReceiptItem records.
 * 2. Increments stock in inventories table and products cached stock with lockForUpdate().
 * 3. Records double-entry balanced accounting journal (Dr 1210 Persediaan, Cr 1110 Kas / 2110 Hutang Dagang).
 */
class GoodsReceiptService
{
    public const ACCOUNT_INVENTORY = '1210';
    public const ACCOUNT_CASH = '1110';
    public const ACCOUNT_PAYABLE = '2110';

    /**
     * Process goods receipt atomically.
     *
     * @param array{
     *     branch_id?: int|null,
     *     reference_number?: string|null,
     *     supplier_name?: string|null,
     *     date?: string|null,
     *     payment_type?: string|null,
     *     notes?: string|null,
     *     items: array<int, array{
     *         product_id: int,
     *         quantity: int,
     *         unit_price: float|string,
     *         subtotal?: float|string|null
     *     }>
     * } $data
     * @param User $actor
     * @return GoodsReceipt
     */
    public function processReceipt(array $data, User $actor): GoodsReceipt
    {
        if (empty($data['items']) || ! is_array($data['items'])) {
            throw ValidationException::withMessages([
                'items' => ['At least one receipt item is required.'],
            ]);
        }

        $paymentType = strtolower($data['payment_type'] ?? 'cash');
        if (! in_array($paymentType, ['cash', 'credit'], true)) {
            throw ValidationException::withMessages([
                'payment_type' => ['Payment type must be either cash or credit.'],
            ]);
        }

        return DB::transaction(function () use ($data, $actor, $paymentType): GoodsReceipt {
            // Resolve central branch
            $branch = $this->resolveCentralBranch($data['branch_id'] ?? null);

            // Resolve accounting ledgers
            $inventoryAccount = $this->resolveInventoryAccount();
            $creditAccount = $this->resolveCreditAccount($paymentType);

            // Generate or use reference number
            $referenceNumber = ! empty($data['reference_number'])
                ? (string) $data['reference_number']
                : $this->generateReferenceNumber();

            $date = $data['date'] ?? now()->toDateString();

            // Calculate totals and prepare item data
            $totalAmount = '0.00';
            $processedItems = [];

            foreach ($data['items'] as $index => $itemData) {
                $productId = (int) ($itemData['product_id'] ?? 0);
                $quantity = (int) ($itemData['quantity'] ?? 0);
                $unitPrice = (string) ($itemData['unit_price'] ?? '0.00');

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => ['Quantity must be greater than zero.'],
                    ]);
                }

                $subtotal = isset($itemData['subtotal'])
                    ? (string) $itemData['subtotal']
                    : bcmul($unitPrice, (string) $quantity, 2);

                $totalAmount = bcadd($totalAmount, $subtotal, 2);

                $processedItems[] = [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ];
            }

            // Langkah A: Simpan data ke goods_receipts
            $receipt = GoodsReceipt::create([
                'branch_id' => $branch->id,
                'reference_number' => $referenceNumber,
                'supplier_name' => $data['supplier_name'] ?? null,
                'date' => $date,
                'total_amount' => $totalAmount,
                'payment_type' => $paymentType,
                'notes' => $data['notes'] ?? null,
            ]);

            // Langkah B: Update kuantitas stok di inventories dan sync cache di products (dengan locking)
            foreach ($processedItems as $item) {
                $product = Product::withoutGlobalScopes()
                    ->where('id', $item['product_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => ["Product ID {$item['product_id']} not found."],
                    ]);
                }

                // Lock and increment inventories row for Pusat branch
                $inventory = $this->lockInventory($branch->id, $product->id);
                $inventory->increment('quantity', $item['quantity']);

                // Synchronize product cached stock column
                $product->increment('stock', $item['quantity']);

                // Create GoodsReceiptItem record
                $receipt->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            // Langkah C: Buat Jurnal Ganda Otomatis (JournalHeader & JournalLine)
            $this->recordJournal(
                $receipt,
                $branch->id,
                $actor->id,
                $inventoryAccount->id,
                $creditAccount->id,
                $totalAmount
            );

            return $receipt->load(['items.product', 'branch']);
        });
    }

    /**
     * Lock or create an inventory row for a branch and product.
     */
    private function lockInventory(int $branchId, int $productId): Inventory
    {
        Inventory::withoutGlobalScopes()->firstOrCreate(
            ['branch_id' => $branchId, 'product_id' => $productId],
            ['quantity' => 0]
        );

        return Inventory::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Create double-entry journal for goods receipt:
     * Dr. 1210 Persediaan           = total_amount
     *   Cr. 1110 Kas / 2110 Hutang  = total_amount
     */
    private function recordJournal(
        GoodsReceipt $receipt,
        int $branchId,
        int $actorId,
        int $inventoryAccountId,
        int $creditAccountId,
        string $totalAmount
    ): void {
        if (bccomp($totalAmount, '0.00', 2) === 0) {
            return;
        }

        $supplierText = $receipt->supplier_name ? " dari {$receipt->supplier_name}" : '';
        $paymentLabel = $receipt->payment_type === 'credit' ? 'Kredit (Hutang Dagang)' : 'Tunai (Kas)';

        $journal = JournalHeader::create([
            'branch_id' => $branchId,
            'user_id' => $actorId,
            'transaction_date' => $receipt->date,
            'reference_number' => $receipt->reference_number,
            'description' => "Penerimaan Barang {$receipt->reference_number}{$supplierText} [{$paymentLabel}]",
        ]);

        $journal->journalLines()->createMany([
            [
                'chart_of_account_id' => $inventoryAccountId,
                'debit' => $totalAmount,
                'credit' => 0,
                'memo' => 'Penerimaan persediaan barang masuk gudang',
            ],
            [
                'chart_of_account_id' => $creditAccountId,
                'debit' => 0,
                'credit' => $totalAmount,
                'memo' => $receipt->payment_type === 'credit'
                    ? 'Pengakuan hutang dagang kepada supplier'
                    : 'Pengeluaran kas untuk pembelian persediaan',
            ],
        ]);
    }

    /**
     * Resolve the central (Pusat) branch.
     */
    private function resolveCentralBranch(?int $branchId): Branch
    {
        if ($branchId !== null) {
            return Branch::query()->findOrFail($branchId);
        }

        $branch = Branch::query()->whereNull('parent_id')->first()
            ?? Branch::query()->where('code', 'PUSAT')->first()
            ?? Branch::query()->first();

        if (! $branch) {
            throw ValidationException::withMessages([
                'branch_id' => ['Central branch is not configured.'],
            ]);
        }

        return $branch;
    }

    /**
     * Resolve inventory asset account (1210).
     */
    private function resolveInventoryAccount(): ChartOfAccount
    {
        $account = ChartOfAccount::query()->where('code', self::ACCOUNT_INVENTORY)->first();
        if (! $account) {
            $account = ChartOfAccount::create([
                'code' => self::ACCOUNT_INVENTORY,
                'name' => 'Persediaan',
                'type' => 'asset',
            ]);
        }

        return $account;
    }

    /**
     * Resolve credit side account: 1110 (Kas) for cash, 2110 (Hutang Dagang) for credit.
     */
    private function resolveCreditAccount(string $paymentType): ChartOfAccount
    {
        if ($paymentType === 'credit') {
            $account = ChartOfAccount::query()->where('code', self::ACCOUNT_PAYABLE)->first();
            if (! $account) {
                $account = ChartOfAccount::create([
                    'code' => self::ACCOUNT_PAYABLE,
                    'name' => 'Hutang Dagang',
                    'type' => 'liability',
                ]);
            }
            return $account;
        }

        $account = ChartOfAccount::query()->where('code', self::ACCOUNT_CASH)->first();
        if (! $account) {
            $account = ChartOfAccount::create([
                'code' => self::ACCOUNT_CASH,
                'name' => 'Kas',
                'type' => 'asset',
            ]);
        }

        return $account;
    }

    /**
     * Generate unique reference number.
     */
    private function generateReferenceNumber(): string
    {
        do {
            $ref = 'GR-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        } while (GoodsReceipt::where('reference_number', $ref)->exists());

        return $ref;
    }
}
