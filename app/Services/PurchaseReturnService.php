<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\GoodsReceipt;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReturnService
{
    public const ACCOUNT_INVENTORY = '1210';
    public const ACCOUNT_PAYABLE = '2110';

    public function __construct(
        protected ?JournalPostingService $journalPostingService = null
    ) {
        $this->journalPostingService = $journalPostingService ?? app(JournalPostingService::class);
    }

    /**
     * Process a purchase return atomically:
     * 1. Create PurchaseReturn and PurchaseReturnItem records.
     * 2. Decrement physical stock in inventories and sync product cached stock.
     * 3. Record balanced double-entry accounting journal:
     *    - Debit: 2110 Hutang Dagang (mengurangi hutang ke supplier)
     *    - Credit: 1210 Persediaan Barang (mengurangi aset persediaan)
     *
     * @param array $data
     * @param array $items
     * @param User|null $actor
     * @return PurchaseReturn
     * @throws ValidationException
     */
    public function processReturn(array $data, array $items = [], ?User $actor = null): PurchaseReturn
    {
        $returnItems = !empty($items) ? $items : ($data['items'] ?? []);

        if (empty($returnItems) || !is_array($returnItems)) {
            throw ValidationException::withMessages([
                'items' => ['Setidaknya satu item retur harus disertakan.'],
            ]);
        }

        return DB::transaction(function () use ($data, $returnItems, $actor): PurchaseReturn {
            // 1. Resolve Supplier
            $supplierId = (int) ($data['supplier_id'] ?? 0);
            $supplier = Supplier::withoutGlobalScopes()->find($supplierId);

            if (!$supplier) {
                throw ValidationException::withMessages([
                    'supplier_id' => ['Supplier tidak ditemukan.'],
                ]);
            }

            // 2. Resolve Branch
            $branchId = $data['branch_id']
                ?? $actor?->branch_id
                ?? $supplier->branch_id
                ?? Branch::query()->value('id');

            $branch = Branch::withoutGlobalScopes()->find($branchId);
            if (!$branch) {
                throw ValidationException::withMessages([
                    'branch_id' => ['Cabang tidak ditemukan.'],
                ]);
            }

            // 3. Optional Goods Receipt reference
            $goodsReceiptId = !empty($data['goods_receipt_id']) ? (int) $data['goods_receipt_id'] : null;
            if ($goodsReceiptId) {
                $goodsReceiptExists = GoodsReceipt::withoutGlobalScopes()->where('id', $goodsReceiptId)->exists();
                if (!$goodsReceiptExists) {
                    throw ValidationException::withMessages([
                        'goods_receipt_id' => ['Penerimaan barang tidak ditemukan.'],
                    ]);
                }
            }

            // 4. Reference Number
            $referenceNumber = !empty($data['reference_number'])
                ? (string) $data['reference_number']
                : $this->generateReferenceNumber();

            $returnDate = $data['return_date'] ?? now()->toDateString();
            $status = $data['status'] ?? 'completed';
            $notes = $data['notes'] ?? null;

            // 5. Process Items, Decrement Inventory & Synchronize Product Stock
            $totalAmount = '0.00';
            $processedItems = [];

            foreach ($returnItems as $index => $itemData) {
                $productId = (int) ($itemData['product_id'] ?? 0);
                $quantity = (int) ($itemData['quantity'] ?? 0);

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => ['Jumlah retur harus lebih besar dari 0.'],
                    ]);
                }

                $product = Product::withoutGlobalScopes()
                    ->where('id', $productId)
                    ->lockForUpdate()
                    ->first();

                if (!$product) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => ["Produk ID {$productId} tidak ditemukan."],
                    ]);
                }

                $unitPrice = isset($itemData['unit_price'])
                    ? (string) $itemData['unit_price']
                    : (string) ($product->purchase_price ?? '0.00');

                $subtotal = isset($itemData['subtotal'])
                    ? (string) $itemData['subtotal']
                    : bcmul($unitPrice, (string) $quantity, 2);

                $totalAmount = bcadd($totalAmount, $subtotal, 2);

                // Lock inventory record for branch & product
                $inventory = $this->lockInventory($branch->id, $product->id);

                // Decrement inventory stock
                $inventory->decrement('quantity', $quantity);

                // Decrement product cached stock
                $product->decrement('stock', $quantity);

                $processedItems[] = [
                    'product_id' => $product->id,
                    'quantity'   => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal'   => $subtotal,
                ];
            }

            // 6. Save Header
            $purchaseReturn = PurchaseReturn::create([
                'branch_id'         => $branch->id,
                'supplier_id'       => $supplier->id,
                'goods_receipt_id'  => $goodsReceiptId,
                'return_date'       => $returnDate,
                'reference_number'  => $referenceNumber,
                'total_amount'      => $totalAmount,
                'status'            => $status,
                'notes'             => $notes,
            ]);

            // 7. Save Items
            $purchaseReturn->items()->createMany($processedItems);

            // 8. Record Double-Entry Journal via JournalPostingService
            $journal = $this->recordJournal(
                $purchaseReturn,
                $supplier,
                $branch->id,
                $actor?->id ?? $data['user_id'] ?? auth()->id(),
                $totalAmount,
                $notes
            );

            if ($journal) {
                $purchaseReturn->update(['journal_header_id' => $journal->id]);
            }

            return $purchaseReturn->load([
                'items.product',
                'supplier',
                'branch',
                'goodsReceipt',
                'journalHeader.journalLines.chartOfAccount',
            ]);
        });
    }

    /**
     * Lock or create an inventory row for a branch and product.
     */
    protected function lockInventory(int $branchId, int $productId): Inventory
    {
        $warehouse = Warehouse::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('name', 'Gudang Utama')
            ->first()
            ?? Warehouse::withoutGlobalScopes()
                ->where('branch_id', $branchId)
                ->orderBy('id')
                ->first();

        Inventory::withoutGlobalScopes()->firstOrCreate(
            ['branch_id' => $branchId, 'product_id' => $productId],
            ['quantity' => 0, 'warehouse_id' => $warehouse?->id]
        );

        return Inventory::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Record double-entry balanced accounting journal:
     * Dr. 2110 Hutang Dagang (Mengurangi hutang ke supplier)
     *   Cr. 1210 Persediaan Barang (Mengurangi nilai aset barang di gudang)
     */
    protected function recordJournal(
        PurchaseReturn $purchaseReturn,
        Supplier $supplier,
        int $branchId,
        ?int $userId,
        string $totalAmount,
        ?string $notes
    ) {
        if (bccomp($totalAmount, '0.00', 2) === 0) {
            return null;
        }

        $payableAccount = ChartOfAccount::where('code', self::ACCOUNT_PAYABLE)->first()
            ?? ChartOfAccount::create([
                'code'      => self::ACCOUNT_PAYABLE,
                'name'      => 'Hutang Dagang',
                'type'      => 'liability',
                'is_active' => true,
            ]);

        $inventoryAccount = ChartOfAccount::where('code', self::ACCOUNT_INVENTORY)->first()
            ?? ChartOfAccount::create([
                'code'      => self::ACCOUNT_INVENTORY,
                'name'      => 'Persediaan',
                'type'      => 'asset',
                'is_active' => true,
            ]);

        $description = !empty($notes)
            ? "Retur Pembelian ke Supplier {$supplier->name} - {$notes}"
            : "Retur Pembelian ke Supplier {$supplier->name}";

        return $this->journalPostingService->post([
            'branch_id'        => $branchId,
            'user_id'          => $userId,
            'transaction_date' => $purchaseReturn->return_date,
            'reference_number' => $purchaseReturn->reference_number,
            'description'      => $description,
            'lines'            => [
                [
                    'chart_of_account_id' => $payableAccount->id,
                    'debit'               => $totalAmount,
                    'credit'              => 0,
                    'memo'                => "Pengurangan hutang dagang atas retur pembelian ke {$supplier->name}",
                ],
                [
                    'chart_of_account_id' => $inventoryAccount->id,
                    'debit'               => 0,
                    'credit'              => $totalAmount,
                    'memo'                => "Pengurangan persediaan barang keluar gudang karena retur ke {$supplier->name}",
                ],
            ],
        ]);
    }

    /**
     * Generate unique reference number (PRT-{Ymd}-{random}).
     */
    protected function generateReferenceNumber(): string
    {
        do {
            $ref = 'PRT-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        } while (PurchaseReturn::withoutGlobalScopes()->where('reference_number', $ref)->exists());

        return $ref;
    }
}
