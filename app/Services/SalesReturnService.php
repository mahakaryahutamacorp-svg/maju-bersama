<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashRegisterShift;
use App\Models\ChartOfAccount;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesReturnService
{
    public const ACCOUNT_REVENUE = '4110';
    public const ACCOUNT_INVENTORY = '1210';
    public const ACCOUNT_COGS = '5100';
    public const ACCOUNT_CASH = '1110';

    public function __construct(
        protected ?JournalPostingService $journalPostingService = null
    ) {
        $this->journalPostingService = $journalPostingService ?? app(JournalPostingService::class);
    }

    /**
     * Process a customer sales return atomically:
     * 1. Create SalesReturn and SalesReturnItem records.
     * 2. Increment physical stock in inventories and sync product cached stock (goods returned to store).
     * 3. Link to active cashier shift if applicable.
     * 4. Record 4-line balanced double-entry accounting journal:
     *    - Debit: 4110 Pendapatan Penjualan (total_amount)
     *    - Credit: [chart_of_account_id] Kas/Bank (total_amount)
     *    - Debit: 1210 Persediaan Barang (total_cost)
     *    - Credit: 5100 Harga Pokok Penjualan (total_cost)
     *
     * @param array $data
     * @param array $items
     * @param User|null $actor
     * @return SalesReturn
     * @throws ValidationException
     */
    public function processReturn(array $data, array $items = [], ?User $actor = null): SalesReturn
    {
        $returnItems = !empty($items) ? $items : ($data['items'] ?? []);

        if (empty($returnItems) || !is_array($returnItems)) {
            throw ValidationException::withMessages([
                'items' => ['Setidaknya satu item retur harus disertakan.'],
            ]);
        }

        return DB::transaction(function () use ($data, $returnItems, $actor): SalesReturn {
            // 1. Resolve Branch
            $branchId = $data['branch_id']
                ?? $actor?->branch_id
                ?? Branch::query()->value('id');

            $branch = Branch::withoutGlobalScopes()->find($branchId);
            if (!$branch) {
                throw ValidationException::withMessages([
                    'branch_id' => ['Cabang tidak ditemukan.'],
                ]);
            }

            // 2. Resolve Refund Account (Kas / Bank)
            $chartOfAccountId = (int) ($data['chart_of_account_id'] ?? 0);
            $refundAccount = ChartOfAccount::find($chartOfAccountId);

            if (!$refundAccount) {
                $refundAccount = ChartOfAccount::where('code', self::ACCOUNT_CASH)->first()
                    ?? ChartOfAccount::create([
                        'code'      => self::ACCOUNT_CASH,
                        'name'      => 'Kas',
                        'type'      => 'asset',
                        'is_active' => true,
                    ]);
            }

            // 3. Resolve Optional Sale Reference
            $saleId = !empty($data['sale_id']) ? (int) $data['sale_id'] : null;
            if ($saleId) {
                $saleExists = Sale::withoutGlobalScopes()->where('id', $saleId)->exists();
                if (!$saleExists) {
                    throw ValidationException::withMessages([
                        'sale_id' => ['Referensi penjualan tidak ditemukan.'],
                    ]);
                }
            }

            // 4. Resolve Active Cash Register Shift (if cash refund & actor is active cashier)
            $userId = $actor?->id ?? $data['user_id'] ?? auth()->id();
            $shiftId = $data['cash_register_shift_id'] ?? null;

            if (!$shiftId && $userId) {
                $activeShift = CashRegisterShift::where('user_id', $userId)
                    ->where('status', 'open')
                    ->latest('opened_at')
                    ->first();
                $shiftId = $activeShift?->id;
            }

            // 5. Reference Number
            $referenceNumber = !empty($data['reference_number'])
                ? (string) $data['reference_number']
                : $this->generateReferenceNumber();

            $returnDate = $data['return_date'] ?? now()->toDateString();
            $customerName = !empty($data['customer_name']) ? (string) $data['customer_name'] : 'Pelanggan Umum';
            $refundMethod = strtolower($data['refund_method'] ?? 'cash');
            $status = $data['status'] ?? 'completed';
            $reason = $data['reason'] ?? $data['notes'] ?? null;

            // 6. Process Items, Increment Stock & Calculate Totals (Refund Amount & COGS)
            $totalAmount = '0.00';
            $totalCost = '0.00';
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

                // Refund unit price (selling price or custom refund price)
                $unitPrice = isset($itemData['unit_price'])
                    ? (string) $itemData['unit_price']
                    : (string) ($product->selling_price ?? '0.00');

                // Cost price (HPP)
                $unitCost = isset($itemData['unit_cost'])
                    ? (string) $itemData['unit_cost']
                    : (string) ($product->purchase_price ?? '0.00');

                $subtotal = isset($itemData['subtotal'])
                    ? (string) $itemData['subtotal']
                    : bcmul($unitPrice, (string) $quantity, 2);

                $subtotalCost = bcmul($unitCost, (string) $quantity, 2);

                $totalAmount = bcadd($totalAmount, $subtotal, 2);
                $totalCost = bcadd($totalCost, $subtotalCost, 2);

                // Lock inventory record for branch & product
                $inventory = $this->lockInventory($branch->id, $product->id);

                // Increment inventory stock (goods return to store)
                $inventory->increment('quantity', $quantity);

                // Increment product cached stock
                $product->increment('stock', $quantity);

                $processedItems[] = [
                    'product_id'    => $product->id,
                    'quantity'      => $quantity,
                    'unit_price'    => $unitPrice,
                    'unit_cost'     => $unitCost,
                    'subtotal'      => $subtotal,
                    'subtotal_cost' => $subtotalCost,
                ];
            }

            // 7. Save SalesReturn Header
            $salesReturn = SalesReturn::create([
                'branch_id'              => $branch->id,
                'sale_id'                => $saleId,
                'user_id'                => $userId,
                'cash_register_shift_id' => $shiftId,
                'customer_name'          => $customerName,
                'return_date'            => $returnDate,
                'reference_number'       => $referenceNumber,
                'refund_method'          => $refundMethod,
                'chart_of_account_id'    => $refundAccount->id,
                'total_amount'           => $totalAmount,
                'total_cost'             => $totalCost,
                'status'                 => $status,
                'reason'                 => $reason,
            ]);

            // 8. Save Items
            $salesReturn->items()->createMany($processedItems);

            // 9. Record 4-Line Accounting Journal
            $journal = $this->recordJournal(
                $salesReturn,
                $branch->id,
                $userId,
                $refundAccount,
                $totalAmount,
                $totalCost,
                $reason
            );

            if ($journal) {
                $salesReturn->update(['journal_header_id' => $journal->id]);
            }

            return $salesReturn->load([
                'items.product',
                'branch',
                'user',
                'sale',
                'chartOfAccount',
                'cashRegisterShift',
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
     * Record 4-line balanced double-entry accounting journal:
     * 1. Dr. 4110 Pendapatan Penjualan = total_amount (mengurangi omset penjualan)
     * 2. Cr. [chart_of_account_id] Kas/Bank = total_amount (pengeluaran dana refund)
     * 3. Dr. 1210 Persediaan Barang = total_cost (nilai aset persediaan bertambah)
     * 4. Cr. 5100 Harga Pokok Penjualan = total_cost (mengurangi beban HPP)
     */
    protected function recordJournal(
        SalesReturn $salesReturn,
        int $branchId,
        ?int $userId,
        ChartOfAccount $refundAccount,
        string $totalAmount,
        string $totalCost,
        ?string $reason
    ) {
        if (bccomp($totalAmount, '0.00', 2) === 0 && bccomp($totalCost, '0.00', 2) === 0) {
            return null;
        }

        $revenueAccount = ChartOfAccount::where('code', self::ACCOUNT_REVENUE)->first()
            ?? ChartOfAccount::create([
                'code'      => self::ACCOUNT_REVENUE,
                'name'      => 'Pendapatan Penjualan',
                'type'      => 'revenue',
                'is_active' => true,
            ]);

        $inventoryAccount = ChartOfAccount::where('code', self::ACCOUNT_INVENTORY)->first()
            ?? ChartOfAccount::create([
                'code'      => self::ACCOUNT_INVENTORY,
                'name'      => 'Persediaan',
                'type'      => 'asset',
                'is_active' => true,
            ]);

        $cogsAccount = ChartOfAccount::whereIn('code', ['5100', '5110'])->first()
            ?? ChartOfAccount::create([
                'code'      => self::ACCOUNT_COGS,
                'name'      => 'Harga Pokok Penjualan',
                'type'      => 'expense',
                'is_active' => true,
            ]);

        $customerLabel = $salesReturn->customer_name ?: 'Pelanggan Umum';
        $description = !empty($reason)
            ? "Retur Penjualan {$customerLabel} Ref: {$salesReturn->reference_number} - {$reason}"
            : "Retur Penjualan {$customerLabel} Ref: {$salesReturn->reference_number}";

        $lines = [];

        // 1. Dr. Pendapatan Penjualan
        $lines[] = [
            'chart_of_account_id' => $revenueAccount->id,
            'debit'               => $totalAmount,
            'credit'              => 0,
            'memo'                => "Pengurangan pendapatan atas retur penjualan {$salesReturn->reference_number}",
        ];

        // 2. Cr. Kas / Bank
        $lines[] = [
            'chart_of_account_id' => $refundAccount->id,
            'debit'               => 0,
            'credit'              => $totalAmount,
            'memo'                => "Pengeluaran refund pelanggan via {$refundAccount->name}",
        ];

        // 3. Dr. Persediaan Barang & 4. Cr. HPP
        if (bccomp($totalCost, '0.00', 2) > 0) {
            $lines[] = [
                'chart_of_account_id' => $inventoryAccount->id,
                'debit'               => $totalCost,
                'credit'              => 0,
                'memo'                => "Penerimaan kembali persediaan barang retur ke gudang",
            ];

            $lines[] = [
                'chart_of_account_id' => $cogsAccount->id,
                'debit'               => 0,
                'credit'              => $totalCost,
                'memo'                => "Pengurangan harga pokok penjualan (HPP) atas retur barang",
            ];
        }

        return $this->journalPostingService->post([
            'branch_id'        => $branchId,
            'user_id'          => $userId,
            'transaction_date' => $salesReturn->return_date,
            'reference_number' => $salesReturn->reference_number,
            'description'      => $description,
            'lines'            => $lines,
        ]);
    }

    /**
     * Generate unique reference number (SR-{Ymd}-{random}).
     */
    protected function generateReferenceNumber(): string
    {
        do {
            $ref = 'SR-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        } while (SalesReturn::withoutGlobalScopes()->where('reference_number', $ref)->exists());

        return $ref;
    }
}
