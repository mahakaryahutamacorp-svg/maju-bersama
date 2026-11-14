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
use App\Support\DecimalQuantity;
use App\Support\Money;
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
     * @throws ValidationException
     */
    public function processReturn(array $data, array $items = [], ?User $actor = null): PurchaseReturn
    {
        $returnItems = ! empty($items) ? $items : ($data['items'] ?? []);

        if (empty($returnItems) || ! is_array($returnItems)) {
            throw ValidationException::withMessages([
                'items' => ['Setidaknya satu item retur harus disertakan.'],
            ]);
        }

        return DB::transaction(function () use ($data, $returnItems, $actor): PurchaseReturn {
            // 1. Resolve Supplier
            $supplierId = (int) ($data['supplier_id'] ?? 0);
            $supplier = Supplier::withoutGlobalScopes()->find($supplierId);

            if (! $supplier) {
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
            if (! $branch) {
                throw ValidationException::withMessages([
                    'branch_id' => ['Cabang tidak ditemukan.'],
                ]);
            }

            // 3. Optional Goods Receipt reference
            $goodsReceiptId = ! empty($data['goods_receipt_id']) ? (int) $data['goods_receipt_id'] : null;
            $goodsReceipt = null;
            if ($goodsReceiptId) {
                $goodsReceipt = GoodsReceipt::withoutGlobalScopes()
                    ->with('items')
                    ->lockForUpdate()
                    ->find($goodsReceiptId);

                if (! $goodsReceipt) {
                    throw ValidationException::withMessages([
                        'goods_receipt_id' => ['Penerimaan barang tidak ditemukan.'],
                    ]);
                }

                if ((int) $goodsReceipt->branch_id !== (int) $branch->id || ! $this->receiptBelongsToSupplier($goodsReceipt, $supplier)) {
                    throw ValidationException::withMessages([
                        'goods_receipt_id' => ['Penerimaan barang tidak cocok dengan supplier atau cabang retur ini.'],
                    ]);
                }
            }

            // 4. Reference Number
            $referenceNumber = ! empty($data['reference_number'])
                ? (string) $data['reference_number']
                : $this->generateReferenceNumber();

            $returnDate = $data['return_date'] ?? now()->toDateString();
            $status = $data['status'] ?? 'completed';
            $notes = $data['notes'] ?? null;

            // 5. Process Items, Decrement Inventory & Synchronize Product Stock
            $totalAmount = '0.00';
            $processedItems = [];
            $products = [];

            foreach ($returnItems as $index => $itemData) {
                $productId = (int) ($itemData['product_id'] ?? 0);
                $quantity = DecimalQuantity::normalize($itemData['quantity'] ?? 0);

                if (DecimalQuantity::compare($quantity, '0') <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => ['Jumlah retur harus lebih besar dari 0.'],
                    ]);
                }

                $product = Product::withoutGlobalScopes()
                    ->where('id', $productId)
                    ->lockForUpdate()
                    ->first();

                if (! $product) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => ["Produk ID {$productId} tidak ditemukan."],
                    ]);
                }

                $unitPrice = isset($itemData['unit_price'])
                    ? Money::of($itemData['unit_price'])
                    : Money::of($product->purchase_price ?? '0.00');

                $subtotal = isset($itemData['subtotal'])
                    ? Money::of($itemData['subtotal'])
                    : Money::mul($unitPrice, $quantity);

                $totalAmount = bcadd($totalAmount, $subtotal, 2);

                $processedItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ];
                $products[$product->id] = $product;
            }

            $this->assertReturnableQuantities($processedItems, $products, $branch->id, $goodsReceipt);

            foreach ($processedItems as $item) {
                $this->lockInventory($branch->id, $item['product_id'])->decrement('quantity', $item['quantity']);
                $products[$item['product_id']]->decrement('stock', $item['quantity']);
            }

            // 6. Save Header
            $purchaseReturn = PurchaseReturn::create([
                'branch_id' => $branch->id,
                'supplier_id' => $supplier->id,
                'goods_receipt_id' => $goodsReceiptId,
                'return_date' => $returnDate,
                'reference_number' => $referenceNumber,
                'total_amount' => $totalAmount,
                'status' => $status,
                'notes' => $notes,
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

    private function receiptBelongsToSupplier(GoodsReceipt $goodsReceipt, Supplier $supplier): bool
    {
        if ($goodsReceipt->supplier_id !== null) {
            return (int) $goodsReceipt->supplier_id === (int) $supplier->id;
        }

        return strcasecmp(trim((string) $goodsReceipt->supplier_name), trim((string) $supplier->name)) === 0;
    }

    /**
     * Retur tidak boleh melebihi sisa kuantitas penerimaan yang ditautkan, dan tidak boleh membuat stok minus.
     *
     * @param  list<array{product_id: int, quantity: string}>  $processedItems
     * @param  array<int, Product>  $products
     *
     * @throws ValidationException
     */
    private function assertReturnableQuantities(array $processedItems, array $products, int $branchId, ?GoodsReceipt $goodsReceipt): void
    {
        $requested = [];
        foreach ($processedItems as $index => $item) {
            $requested[$item['product_id']] ??= ['quantity' => '0', 'index' => $index];
            $requested[$item['product_id']]['quantity'] = DecimalQuantity::add($requested[$item['product_id']]['quantity'], $item['quantity']);
        }

        foreach ($requested as $productId => $request) {
            $name = $products[$productId]->name;
            $field = "items.{$request['index']}.quantity";

            if ($goodsReceipt) {
                $received = DecimalQuantity::sum(
                    $goodsReceipt->items->where('product_id', $productId)->pluck('quantity')
                );

                if (DecimalQuantity::compare($received, '0') <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$request['index']}.product_id" => ["{$name} tidak ada pada penerimaan {$goodsReceipt->reference_number}."],
                    ]);
                }

                $alreadyReturned = DecimalQuantity::normalize(
                    PurchaseReturn::withoutGlobalScopes()
                        ->join('purchase_return_items', 'purchase_return_items.purchase_return_id', '=', 'purchase_returns.id')
                        ->where('purchase_returns.goods_receipt_id', $goodsReceipt->id)
                        ->where('purchase_returns.status', '!=', 'cancelled')
                        ->where('purchase_return_items.product_id', $productId)
                        ->sum('purchase_return_items.quantity')
                );
                $returnable = DecimalQuantity::sub($received, $alreadyReturned);

                if (DecimalQuantity::compare($request['quantity'], $returnable) > 0) {
                    $returnableLabel = DecimalQuantity::present(DecimalQuantity::compare($returnable, '0') < 0 ? '0' : $returnable);
                    throw ValidationException::withMessages([
                        $field => ["Jumlah retur {$name} melebihi jumlah yang diterima pada {$goodsReceipt->reference_number}. Maksimal yang bisa diretur: {$returnableLabel}."],
                    ]);
                }
            }

            $onHand = DecimalQuantity::normalize(
                Inventory::withoutGlobalScopes()
                    ->where('branch_id', $branchId)
                    ->where('product_id', $productId)
                    ->lockForUpdate()
                    ->value('quantity') ?? '0'
            );

            if (DecimalQuantity::compare($request['quantity'], $onHand) > 0) {
                $onHandLabel = DecimalQuantity::present(DecimalQuantity::compare($onHand, '0') < 0 ? '0' : $onHand);
                throw ValidationException::withMessages([
                    $field => ["Jumlah retur {$name} melebihi stok tersedia ({$onHandLabel})."],
                ]);
            }
        }
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
                'code' => self::ACCOUNT_PAYABLE,
                'name' => 'Hutang Dagang',
                'type' => 'liability',
                'is_active' => true,
            ]);

        $inventoryAccount = ChartOfAccount::where('code', self::ACCOUNT_INVENTORY)->first()
            ?? ChartOfAccount::create([
                'code' => self::ACCOUNT_INVENTORY,
                'name' => 'Persediaan',
                'type' => 'asset',
                'is_active' => true,
            ]);

        $description = ! empty($notes)
            ? "Retur Pembelian ke Supplier {$supplier->name} - {$notes}"
            : "Retur Pembelian ke Supplier {$supplier->name}";

        return $this->journalPostingService->post([
            'branch_id' => $branchId,
            'user_id' => $userId,
            'transaction_date' => $purchaseReturn->return_date,
            'reference_number' => $purchaseReturn->reference_number,
            'description' => $description,
            'lines' => [
                [
                    'chart_of_account_id' => $payableAccount->id,
                    'debit' => $totalAmount,
                    'credit' => 0,
                    'memo' => "Pengurangan hutang dagang atas retur pembelian ke {$supplier->name}",
                ],
                [
                    'chart_of_account_id' => $inventoryAccount->id,
                    'debit' => 0,
                    'credit' => $totalAmount,
                    'memo' => "Pengurangan persediaan barang keluar gudang karena retur ke {$supplier->name}",
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
            $ref = 'PRT-'.date('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        } while (PurchaseReturn::withoutGlobalScopes()->where('reference_number', $ref)->exists());

        return $ref;
    }
}
