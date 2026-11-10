<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\GoodsReceipt;
use App\Models\Inventory;
use App\Models\JournalHeader;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\DecimalQuantity;
use App\Support\Money;
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
            $supplier = $this->resolveSupplier($data, $actor, $paymentType, $purchaseOrder);

            // Resolve branch (central branch or actor's own branch)
            $branch = $this->resolveCentralBranch($data['branch_id'] ?? $purchaseOrder?->branch_id ?? ($actor->isMaster() ? null : $actor->branch_id));

            // Resolve accounting ledgers
            $inventoryAccount = $this->resolveInventoryAccount();
            $creditAccount = $this->resolveCreditAccount($paymentType);

            // Generate or use reference number
            $referenceNumber = ! empty($data['reference_number'])
                ? (string) $data['reference_number']
                : $this->generateReferenceNumber();

            $date = $data['date'] ?? now()->toDateString();
            $supplierName = $supplier?->name ?? (isset($data['supplier_name']) ? trim((string) $data['supplier_name']) : null) ?: null;

            // Calculate totals and prepare item data
            $totalAmount = '0.00';
            $processedItems = [];

            foreach ($data['items'] as $index => $itemData) {
                $productId = (int) ($itemData['product_id'] ?? 0);
                $quantity = DecimalQuantity::normalize($itemData['quantity'] ?? 0);

                if (DecimalQuantity::compare($quantity, '0') <= 0) {
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
                    ? Money::of($poItem->unit_price)
                    : Money::of($itemData['unit_price'] ?? '0.00');

                $subtotal = Money::mul($unitPrice, $quantity);

                $totalAmount = bcadd($totalAmount, $subtotal, 2);

                $processedItems[] = [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'po_item' => $poItem,
                ];
            }

            // Langkah A: Simpan data ke goods_receipts
            $receipt = GoodsReceipt::create([
                'branch_id' => $branch->id,
                'purchase_order_id' => $purchaseOrder?->id,
                'supplier_id' => $supplier?->id,
                'reference_number' => $referenceNumber,
                'supplier_name' => $supplierName,
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

                // Perbarui received_quantity pada purchase_order_items jika terkait PO
                if ($item['po_item']) {
                    $item['po_item']->increment('received_quantity', $item['quantity']);
                }
            }

            if ($purchaseOrder) {
                $this->refreshPurchaseOrderStatus($purchaseOrder);
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
     * Koreksi penerimaan yang sudah tercatat. Stok, pesanan, dan jurnal ikut disesuaikan.
     * Hanya akun master. Nota yang sudah dilunasi atau diretur tidak boleh diubah strukturnya.
     *
     * @param array{
     *     supplier_id?: int|null,
     *     supplier_name?: string|null,
     *     date?: string|null,
     *     payment_type?: string|null,
     *     notes?: string|null,
     *     items?: array<int, array{product_id: int, quantity: int|string, unit_price?: float|string|null}>
     * } $data
     */
    public function updateReceipt(GoodsReceipt $receipt, array $data, User $actor): GoodsReceipt
    {
        $this->ensureMaster($actor);

        return DB::transaction(function () use ($receipt, $data, $actor): GoodsReceipt {
            $receipt = GoodsReceipt::query()->lockForUpdate()->findOrFail($receipt->id);
            $receipt->load(['items', 'purchaseOrder.items', 'purchaseOrder.supplier', 'supplier']);

            $purchaseOrder = $receipt->purchaseOrder;
            $paymentType = $purchaseOrder
                ? 'credit'
                : strtolower((string) ($data['payment_type'] ?? $receipt->payment_type));

            if (! in_array($paymentType, ['cash', 'credit'], true)) {
                throw ValidationException::withMessages([
                    'payment_type' => ['Metode pembayaran harus tunai (kas) atau tempo (hutang).'],
                ]);
            }

            $supplier = $this->supplierForUpdate($receipt, $data, $actor, $paymentType, $purchaseOrder);
            $processedItems = ! empty($data['items']) && is_array($data['items'])
                ? $this->buildReceiptItems($data['items'], $purchaseOrder)
                : null;

            if ($this->structureChanged($receipt, $paymentType, $supplier, $data, $processedItems)) {
                $this->assertStructureEditable($receipt);
            }

            $totalAmount = Money::of($receipt->total_amount);
            if ($processedItems !== null) {
                $this->applyItemAdjustment($receipt, $processedItems, $purchaseOrder);
                $receipt->items()->delete();
                foreach ($processedItems as $item) {
                    $receipt->items()->create([
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'subtotal' => $item['subtotal'],
                    ]);
                }
                $totalAmount = '0.00';
                foreach ($processedItems as $item) {
                    $totalAmount = bcadd($totalAmount, $item['subtotal'], 2);
                }
            }

            if ($supplier) {
                $supplierName = $supplier->name;
            } elseif (array_key_exists('supplier_name', $data)) {
                $supplierName = trim((string) $data['supplier_name']) ?: null;
            } else {
                $supplierName = $receipt->supplier_name;
            }

            $receipt->update([
                'supplier_id' => $supplier?->id,
                'supplier_name' => $supplierName,
                'date' => $data['date'] ?? $receipt->date->toDateString(),
                'total_amount' => $totalAmount,
                'payment_type' => $paymentType,
                'notes' => array_key_exists('notes', $data) ? ($data['notes'] ?: null) : $receipt->notes,
            ]);

            if ($purchaseOrder && $processedItems !== null) {
                $this->refreshPurchaseOrderStatus($purchaseOrder);
            }

            $this->syncJournal($receipt->fresh(), $purchaseOrder, $actor->id);

            return $receipt->fresh(['items.product', 'branch', 'supplier']);
        });
    }

    /**
     * Hapus penerimaan dan kembalikan stok, kuantitas pesanan, serta jurnalnya.
     */
    public function deleteReceipt(GoodsReceipt $receipt, User $actor): void
    {
        $this->ensureMaster($actor);

        DB::transaction(function () use ($receipt): void {
            $receipt = GoodsReceipt::query()->lockForUpdate()->findOrFail($receipt->id);
            $receipt->load(['items', 'purchaseOrder.items']);

            $this->assertStructureEditable($receipt);

            $purchaseOrder = $receipt->purchaseOrder;

            foreach ($receipt->items as $item) {
                $this->applyStockDelta(
                    (int) $receipt->branch_id,
                    (int) $item->product_id,
                    DecimalQuantity::sub('0', $item->quantity)
                );

                if (! $purchaseOrder) {
                    continue;
                }

                $poItem = $purchaseOrder->items->firstWhere('product_id', (int) $item->product_id);
                if (! $poItem) {
                    continue;
                }

                $next = DecimalQuantity::add($poItem->received_quantity, DecimalQuantity::sub('0', $item->quantity));
                $poItem->received_quantity = DecimalQuantity::compare($next, '0') < 0 ? '0.000' : $next;
                $poItem->save();
            }

            if ($purchaseOrder) {
                $this->refreshPurchaseOrderStatus($purchaseOrder);
            }

            $this->removeJournal($receipt);
            $receipt->items()->delete();
            $receipt->delete();
        });
    }

    /**
     * Alasan nota tidak boleh diubah isinya. Tanggal dan catatan tetap boleh diubah.
     */
    public function structureLockReason(GoodsReceipt $receipt): ?string
    {
        if ($receipt->paymentAllocations()->exists() || bccomp(Money::of($receipt->paid_amount), '0.00', 2) === 1) {
            return 'Nota ini sudah ada pelunasan. Batalkan pembayaran dulu sebelum mengubah metode, barang, atau menghapus penerimaan.';
        }

        if ($receipt->purchaseReturns()->exists()) {
            return 'Nota ini sudah punya retur pembelian. Hapus retur dulu sebelum mengubah atau menghapus penerimaan.';
        }

        $order = $receipt->relationLoaded('purchaseOrder')
            ? $receipt->purchaseOrder
            : $receipt->purchaseOrder()->first();

        if ($order && (bccomp(Money::of($order->paid_amount), '0.00', 2) === 1 || $order->paymentAllocations()->exists())) {
            return 'Pesanan barang ini sudah ada pembayaran. Batalkan pembayaran dulu sebelum mengubah atau menghapus penerimaan.';
        }

        return null;
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

        $narrative = $this->journalNarrative($receipt, $purchaseOrder);
        $description = $narrative['description'];
        $debitMemo = $narrative['debitMemo'];
        $creditMemo = $narrative['creditMemo'];

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

    private function ensureMaster(User $actor): void
    {
        if (! $actor->isMaster()) {
            abort(403, 'Hanya akun master yang dapat mengubah atau menghapus penerimaan barang.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function supplierForUpdate(
        GoodsReceipt $receipt,
        array $data,
        User $actor,
        string $paymentType,
        ?PurchaseOrder $purchaseOrder
    ): ?Supplier {
        if ($purchaseOrder) {
            return $this->resolveSupplier($data, $actor, $paymentType, $purchaseOrder);
        }

        $supplierTouched = array_key_exists('supplier_id', $data)
            || array_key_exists('supplier_name', $data)
            || $paymentType !== $receipt->payment_type;

        if (! $supplierTouched) {
            return $receipt->supplier;
        }

        return $this->resolveSupplier($data, $actor, $paymentType, null);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return list<array{product_id: int, quantity: string, unit_price: string, subtotal: string, po_item: mixed}>
     */
    private function buildReceiptItems(array $items, ?PurchaseOrder $purchaseOrder): array
    {
        $processedItems = [];

        foreach ($items as $index => $itemData) {
            $productId = (int) ($itemData['product_id'] ?? 0);
            $quantity = DecimalQuantity::normalize($itemData['quantity'] ?? 0);

            if (DecimalQuantity::compare($quantity, '0') <= 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => ['Kuantitas harus lebih dari nol.'],
                ]);
            }

            $poItem = $purchaseOrder?->items->firstWhere('product_id', $productId);
            if ($purchaseOrder && $poItem === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => ['Produk ini tidak ada pada pesanan barang yang tertaut.'],
                ]);
            }

            $unitPrice = $poItem
                ? Money::of($poItem->unit_price)
                : Money::of($itemData['unit_price'] ?? '0.00');

            $processedItems[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => Money::mul($unitPrice, $quantity),
                'po_item' => $poItem,
            ];
        }

        return $processedItems;
    }

    /**
     * @param  list<array{product_id: int, quantity: string, unit_price: string, subtotal: string}>|null  $processedItems
     * @param  array<string, mixed>  $data
     */
    private function structureChanged(
        GoodsReceipt $receipt,
        string $paymentType,
        ?Supplier $supplier,
        array $data,
        ?array $processedItems
    ): bool {
        if ($paymentType !== $receipt->payment_type) {
            return true;
        }

        if ((int) ($supplier?->id ?? 0) !== (int) ($receipt->supplier_id ?? 0)) {
            return true;
        }

        if ($supplier === null && array_key_exists('supplier_name', $data)) {
            $postedName = trim((string) $data['supplier_name']);
            if ($postedName !== trim((string) $receipt->supplier_name)) {
                return true;
            }
        }

        if ($processedItems === null) {
            return false;
        }

        $current = $receipt->items
            ->map(fn ($item) => (int) $item->product_id.'|'.DecimalQuantity::normalize($item->quantity).'|'.Money::of($item->unit_price))
            ->sort()
            ->values()
            ->all();

        $next = collect($processedItems)
            ->map(fn (array $item) => $item['product_id'].'|'.DecimalQuantity::normalize($item['quantity']).'|'.Money::of($item['unit_price']))
            ->sort()
            ->values()
            ->all();

        return $current !== $next;
    }

    private function assertStructureEditable(GoodsReceipt $receipt): void
    {
        $reason = $this->structureLockReason($receipt);
        if ($reason !== null) {
            throw ValidationException::withMessages([
                'payment_type' => [$reason],
            ]);
        }
    }

    /**
     * @param  list<array{product_id: int, quantity: string, unit_price: string, subtotal: string}>  $processedItems
     */
    private function applyItemAdjustment(GoodsReceipt $receipt, array $processedItems, ?PurchaseOrder $purchaseOrder): void
    {
        $old = [];
        foreach ($receipt->items as $item) {
            $old[$item->product_id] = DecimalQuantity::add($old[$item->product_id] ?? '0', $item->quantity);
        }

        $new = [];
        foreach ($processedItems as $item) {
            $new[$item['product_id']] = DecimalQuantity::add($new[$item['product_id']] ?? '0', $item['quantity']);
        }

        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $productId) {
            $delta = DecimalQuantity::sub($new[$productId] ?? '0', $old[$productId] ?? '0');
            $this->applyStockDelta((int) $receipt->branch_id, (int) $productId, $delta);

            if (! $purchaseOrder) {
                continue;
            }

            $poItem = $purchaseOrder->items->firstWhere('product_id', (int) $productId);
            if (! $poItem) {
                continue;
            }

            $next = DecimalQuantity::add($poItem->received_quantity, $delta);
            if (DecimalQuantity::compare($next, '0') < 0) {
                throw ValidationException::withMessages([
                    'items' => ['Kuantitas diterima pada pesanan tidak boleh kurang dari nol.'],
                ]);
            }

            $poItem->received_quantity = $next;
            $poItem->save();
        }
    }

    private function applyStockDelta(int $branchId, int $productId, string $delta): void
    {
        if (DecimalQuantity::compare($delta, '0') === 0) {
            return;
        }

        $product = Product::withoutGlobalScopes()
            ->where('id', $productId)
            ->lockForUpdate()
            ->first();

        if (! $product) {
            throw ValidationException::withMessages([
                'items' => ["Produk #{$productId} tidak ditemukan."],
            ]);
        }

        $inventory = $this->lockInventory($branchId, $productId);

        if (DecimalQuantity::compare($delta, '0') < 0) {
            $remove = DecimalQuantity::sub('0', $delta);
            if (DecimalQuantity::compare($inventory->quantity, $remove) < 0 || DecimalQuantity::compare($product->stock, $remove) < 0) {
                throw ValidationException::withMessages([
                    'items' => ["Stok {$product->name} tidak cukup untuk mengurangi penerimaan ini. Stok saat ini sudah terpakai."],
                ]);
            }

            $inventory->decrement('quantity', $remove);
            $product->decrement('stock', $remove);

            return;
        }

        $inventory->increment('quantity', $delta);
        $product->increment('stock', $delta);
    }

    private function refreshPurchaseOrderStatus(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->unsetRelation('items');
        $purchaseOrder->load('items');

        $totalOrdered = DecimalQuantity::sum($purchaseOrder->items->pluck('quantity'));
        $totalReceived = DecimalQuantity::sum($purchaseOrder->items->pluck('received_quantity'));

        if (DecimalQuantity::compare($totalReceived, '0') <= 0) {
            $purchaseOrder->update(['status' => 'pending']);
        } elseif (DecimalQuantity::compare($totalReceived, $totalOrdered) >= 0) {
            $purchaseOrder->update(['status' => 'completed']);
        } else {
            $purchaseOrder->update(['status' => 'partial']);
        }
    }

    /**
     * @return array{description: string, debitMemo: string, creditMemo: string}
     */
    private function journalNarrative(GoodsReceipt $receipt, ?PurchaseOrder $purchaseOrder): array
    {
        if ($purchaseOrder) {
            $supplierName = $purchaseOrder->supplier?->name ?? $receipt->supplier_name ?? 'Supplier';

            return [
                'description' => "Penerimaan barang dari PO: {$purchaseOrder->reference_number} - Supplier: {$supplierName}",
                'debitMemo' => 'Penerimaan persediaan barang masuk gudang dari PO',
                'creditMemo' => 'Pencatatan hutang usaha atas penerimaan PO',
            ];
        }

        $supplierText = $receipt->supplier_name ? " dari {$receipt->supplier_name}" : '';
        $paymentLabel = $receipt->payment_type === 'credit' ? 'Kredit (Hutang Dagang)' : 'Tunai (Kas)';

        return [
            'description' => "Penerimaan Barang {$receipt->reference_number}{$supplierText} [{$paymentLabel}]",
            'debitMemo' => 'Penerimaan persediaan barang masuk gudang',
            'creditMemo' => $receipt->payment_type === 'credit'
                ? 'Pengakuan hutang dagang kepada supplier'
                : 'Pengeluaran kas untuk pembelian persediaan',
        ];
    }

    private function syncJournal(GoodsReceipt $receipt, ?PurchaseOrder $purchaseOrder, int $actorId): void
    {
        $existing = JournalHeader::query()
            ->where('branch_id', $receipt->branch_id)
            ->where('reference_number', $receipt->reference_number)
            ->orderByDesc('id')
            ->first();

        if (bccomp(Money::of($receipt->total_amount), '0.00', 2) === 0) {
            if ($existing) {
                $existing->journalLines()->delete();
                $existing->delete();
            }

            return;
        }

        if (! $existing) {
            $this->recordJournal(
                $receipt,
                (int) $receipt->branch_id,
                $actorId,
                $this->resolveInventoryAccount()->id,
                $this->resolveCreditAccount($receipt->payment_type)->id,
                Money::of($receipt->total_amount),
                $purchaseOrder
            );

            return;
        }

        $narrative = $this->journalNarrative($receipt, $purchaseOrder);
        $existing->update([
            'transaction_date' => $receipt->date,
            'description' => $narrative['description'],
        ]);
        $existing->journalLines()->delete();
        $existing->journalLines()->createMany([
            [
                'chart_of_account_id' => $this->resolveInventoryAccount()->id,
                'debit' => Money::of($receipt->total_amount),
                'credit' => 0,
                'memo' => $narrative['debitMemo'],
            ],
            [
                'chart_of_account_id' => $this->resolveCreditAccount($receipt->payment_type)->id,
                'debit' => 0,
                'credit' => Money::of($receipt->total_amount),
                'memo' => $narrative['creditMemo'],
            ],
        ]);
    }

    private function removeJournal(GoodsReceipt $receipt): void
    {
        $headers = JournalHeader::query()
            ->where('branch_id', $receipt->branch_id)
            ->where('reference_number', $receipt->reference_number)
            ->get();

        foreach ($headers as $header) {
            $header->journalLines()->delete();
            $header->delete();
        }
    }

    /**
     * Pembelian kredit (tempo) wajib terikat ke supplier terdaftar agar hutangnya
     * bisa dilacak dan dilunasi di Buku Pemasok. Pembelian tunai boleh tanpa supplier.
     */
    private function resolveSupplier(array $data, User $actor, string $paymentType, ?PurchaseOrder $purchaseOrder): ?Supplier
    {
        if ($purchaseOrder) {
            if ($purchaseOrder->supplier === null) {
                throw ValidationException::withMessages([
                    'supplier_id' => ['Pesanan barang ini tidak memiliki pemasok yang valid.'],
                ]);
            }

            return $purchaseOrder->supplier;
        }

        $supplier = ! empty($data['supplier_id'])
            ? Supplier::withoutGlobalScopes()->whereKey((int) $data['supplier_id'])->where('is_active', true)->first()
            : null;

        if (! empty($data['supplier_id']) && $supplier === null) {
            throw ValidationException::withMessages([
                'supplier_id' => ['Pemasok yang dipilih tidak ditemukan atau nonaktif.'],
            ]);
        }

        if ($supplier && ! $actor->isMaster() && (int) $supplier->branch_id !== (int) $actor->branch_id) {
            throw ValidationException::withMessages([
                'supplier_id' => ['Pemasok yang dipilih milik cabang lain.'],
            ]);
        }

        if ($paymentType === 'credit' && $supplier === null) {
            throw ValidationException::withMessages([
                'supplier_id' => ['Pembelian tempo (hutang) wajib memilih pemasok terdaftar agar hutangnya tercatat di Buku Pemasok.'],
            ]);
        }

        return $supplier;
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
            $ref = 'GR-'.date('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        } while (GoodsReceipt::where('reference_number', $ref)->exists());

        return $ref;
    }
}
