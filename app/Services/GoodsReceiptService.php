<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\GoodsReceipt;
use App\Models\Inventory;
use App\Models\JournalHeader;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service to process goods receipt (penerimaan barang dari supplier ke Gudang Pusat).
 *
 * Atomically:
 * 1. Creates GoodsReceipt and GoodsReceiptItem records.
 * 2. Increments stock in inventories table and products cached stock with lockForUpdate().
 * 3. Records double-entry balanced accounting journal (Dr 1210 Persediaan, Cr 1110 Kas / 2110 Hutang Dagang).
 * 4. If linked to Purchase Order: updates PO items received_quantity, transitions PO status, and records Hutang Usaha.
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
     *     purchase_order_id?: int|null,
     *     reference_number?: string|null,
     *     supplier_name?: string|null,
     *     date?: string|null,
     *     payment_type?: string|null,
     *     notes?: string|null,
     *     items: array<int, array{
     *         product_id: int,
     *         quantity: int,
     *         unit_price?: float|string|null,
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

        // 1. Check if purchase_order_id is provided and validate PO
        $purchaseOrderId = ! empty($data['purchase_order_id']) ? (int) $data['purchase_order_id'] : null;
        $purchaseOrder = null;

        if ($purchaseOrderId) {
            $purchaseOrder = PurchaseOrder::withoutGlobalScopes()
                ->with(['items', 'supplier'])
                ->find($purchaseOrderId);

            if (! $purchaseOrder) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => ['Purchase Order tidak ditemukan.'],
                ]);
            }

            if (in_array($purchaseOrder->status, ['completed', 'cancelled'], true)) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => ["Purchase Order tidak dapat diproses karena berstatus {$purchaseOrder->status}."],
                ]);
            }
        }

        $paymentType = $purchaseOrder
            ? 'credit'
            : strtolower($data['payment_type'] ?? 'cash');

        if (! in_array($paymentType, ['cash', 'credit'], true)) {
            throw ValidationException::withMessages([
                'payment_type' => ['Payment type must be either cash or credit.'],
            ]);
        }

        return DB::transaction(function () use ($data, $actor, $paymentType, $purchaseOrder): GoodsReceipt {
            // Resolve central branch
            $branch = $this->resolveCentralBranch($data['branch_id'] ?? $purchaseOrder?->branch_id);

            // Resolve accounting ledgers
            $inventoryAccount = $this->resolveInventoryAccount();
            $creditAccount = $this->resolveCreditAccount($paymentType);

            // Generate or use reference number
            $referenceNumber = ! empty($data['reference_number'])
                ? (string) $data['reference_number']
                : $this->generateReferenceNumber();

            $date = $data['date'] ?? now()->toDateString();
            $supplierName = $data['supplier_name'] ?? $purchaseOrder?->supplier?->name;

            // Calculate totals and prepare item data
            $totalAmount = '0.00';
            $processedItems = [];

            foreach ($data['items'] as $index => $itemData) {
                $productId = (int) ($itemData['product_id'] ?? 0);
                $quantity = (int) ($itemData['quantity'] ?? 0);

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => ['Quantity must be greater than zero.'],
                    ]);
                }

                // If linked to PO, resolve unit_price from PO item or itemData
                $poItem = null;
                if ($purchaseOrder) {
                    $poItem = $purchaseOrder->items->firstWhere('product_id', $productId);
                }

                $unitPrice = $poItem
                    ? (string) $poItem->unit_price
                    : (string) ($itemData['unit_price'] ?? '0.00');

                $subtotal = isset($itemData['subtotal']) && ! $poItem
                    ? (string) $itemData['subtotal']
                    : bcmul($unitPrice, (string) $quantity, 2);

                $totalAmount = bcadd($totalAmount, $subtotal, 2);

                $processedItems[] = [
                    'product_id' => $productId,
                    'quantity'   => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal'   => $subtotal,
                    'po_item'    => $poItem,
                ];
            }

            // Langkah A: Simpan data ke goods_receipts
            $receipt = GoodsReceipt::create([
                'branch_id'         => $branch->id,
                'purchase_order_id' => $purchaseOrder?->id,
                'reference_number'  => $referenceNumber,
                'supplier_name'     => $supplierName,
                'date'              => $date,
                'total_amount'      => $totalAmount,
                'payment_type'      => $paymentType,
                'notes'             => $data['notes'] ?? null,
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
                    'quantity'   => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal'   => $item['subtotal'],
                ]);

                // Perbarui received_quantity pada purchase_order_items jika terkait PO
                if ($item['po_item']) {
                    $item['po_item']->increment('received_quantity', $item['quantity']);
                }
            }

            // Langkah C: Pembaruan Status PO
            if ($purchaseOrder) {
                $purchaseOrder->refresh();
                $totalOrdered = (int) $purchaseOrder->items->sum('quantity');
                $totalReceived = (int) $purchaseOrder->items->sum('received_quantity');

                if ($totalReceived >= $totalOrdered) {
                    $purchaseOrder->update(['status' => 'completed']);
                } elseif ($totalReceived > 0) {
                    $purchaseOrder->update(['status' => 'partial']);
                }
            }

            // Langkah D: Buat Jurnal Ganda Otomatis (JournalHeader & JournalLine)
            $this->recordJournal(
                $receipt,
                $branch->id,
                $actor->id,
                $inventoryAccount->id,
                $creditAccount->id,
                $totalAmount,
                $purchaseOrder
            );

            return $receipt->load(['items.product', 'branch']);
        });
    }

    /**
     * Lock or create an inventory row for a branch and product.
     * warehouse_id is populated for new rows (Phase-2 forward compat).
     */
    private function lockInventory(int $branchId, int $productId): Inventory
    {
        $warehouse = $this->resolveWarehouseForBranch($branchId);

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
     * Resolve the "Gudang Utama" warehouse for a given branch.
     * Returns null gracefully if no warehouse exists yet (e.g. in tests).
     */
    private function resolveWarehouseForBranch(int $branchId): ?Warehouse
    {
        return Warehouse::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('name', 'Gudang Utama')
            ->first()
            ?? Warehouse::withoutGlobalScopes()
                ->where('branch_id', $branchId)
                ->orderBy('id')
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
        string $totalAmount,
        ?PurchaseOrder $purchaseOrder = null
    ): void {
        if (bccomp($totalAmount, '0.00', 2) === 0) {
            return;
        }

        if ($purchaseOrder) {
            $supplierName = $purchaseOrder->supplier?->name ?? $receipt->supplier_name ?? 'Supplier';
            $description = "Penerimaan barang dari PO: {$purchaseOrder->reference_number} - Supplier: {$supplierName}";
            $debitMemo = 'Penerimaan persediaan barang masuk gudang dari PO';
            $creditMemo = 'Pencatatan hutang usaha atas penerimaan PO';
        } else {
            $supplierText = $receipt->supplier_name ? " dari {$receipt->supplier_name}" : '';
            $paymentLabel = $receipt->payment_type === 'credit' ? 'Kredit (Hutang Dagang)' : 'Tunai (Kas)';
            $description = "Penerimaan Barang {$receipt->reference_number}{$supplierText} [{$paymentLabel}]";
            $debitMemo = 'Penerimaan persediaan barang masuk gudang';
            $creditMemo = $receipt->payment_type === 'credit'
                ? 'Pengakuan hutang dagang kepada supplier'
                : 'Pengeluaran kas untuk pembelian persediaan';
        }

        $journal = JournalHeader::create([
            'branch_id' => $branchId,
            'user_id' => $actorId,
            'transaction_date' => $receipt->date,
            'reference_number' => $receipt->reference_number,
            'description' => $description,
        ]);

        $journal->journalLines()->createMany([
            [
                'chart_of_account_id' => $inventoryAccountId,
                'debit' => $totalAmount,
                'credit' => 0,
                'memo' => $debitMemo,
            ],
            [
                'chart_of_account_id' => $creditAccountId,
                'debit' => 0,
                'credit' => $totalAmount,
                'memo' => $creditMemo,
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
