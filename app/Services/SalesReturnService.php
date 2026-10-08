<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashRegisterShift;
use App\Models\ChartOfAccount;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesReturnService
{
    public const ACCOUNT_REVENUE = JournalPostingService::ACCOUNT_REVENUE;

    public const ACCOUNT_INVENTORY = JournalPostingService::ACCOUNT_INVENTORY;

    public const ACCOUNT_COGS = JournalPostingService::ACCOUNT_COGS;

    public const ACCOUNT_CASH = JournalPostingService::ACCOUNT_CASH;

    public const ACCOUNT_AR = JournalPostingService::ACCOUNT_AR;

    public function __construct(
        protected ?JournalPostingService $journalPostingService = null
    ) {
        $this->journalPostingService = $journalPostingService ?? app(JournalPostingService::class);
    }

    /**
     * Process a customer sales return atomically:
     * restore branch stock, persist the return, and reverse the POS journal
     * (Dr Pendapatan / Cr Kas-Piutang, Dr Persediaan / Cr HPP).
     *
     * @throws ValidationException
     */
    public function processReturn(array $data, array $items = [], ?User $actor = null): SalesReturn
    {
        $returnItems = ! empty($items) ? $items : ($data['items'] ?? []);

        if (empty($returnItems) || ! is_array($returnItems)) {
            throw ValidationException::withMessages([
                'items' => ['Setidaknya satu item retur harus disertakan.'],
            ]);
        }

        return DB::transaction(function () use ($data, $returnItems, $actor): SalesReturn {
            $actor = $actor ?? auth()->user();

            if ($actor instanceof User && $actor->isCashier() && empty($data['sale_id'])) {
                throw ValidationException::withMessages([
                    'sale_id' => ['Kasir wajib meretur berdasarkan nota penjualan cabang sendiri.'],
                ]);
            }

            $sale = $this->resolveSale(
                ! empty($data['sale_id']) ? (int) $data['sale_id'] : null,
                $actor
            );

            $branch = $this->resolveBranch($data, $actor, $sale);
            $refundAccount = $this->resolveRefundAccount($data, $sale);
            $userId = $actor?->id ?? $data['user_id'] ?? auth()->id();
            $shiftId = $this->resolveShiftId($data, $userId);

            $referenceNumber = ! empty($data['reference_number'])
                ? (string) $data['reference_number']
                : $this->generateReferenceNumber();

            $returnDate = $data['return_date'] ?? now()->toDateString();
            $customerName = ! empty($data['customer_name'])
                ? (string) $data['customer_name']
                : ($sale?->customer?->name ?? 'Pelanggan Umum');
            $refundMethod = strtolower((string) ($data['refund_method'] ?? $sale?->payment_method ?? 'cash'));
            $status = $data['status'] ?? 'completed';
            $reason = $data['reason'] ?? $data['notes'] ?? null;

            [$totalAmount, $totalCost, $processedItems] = $this->processItems(
                $returnItems,
                $branch->id,
                $sale
            );

            $salesReturn = SalesReturn::create([
                'branch_id' => $branch->id,
                'sale_id' => $sale?->id,
                'user_id' => $userId,
                'cash_register_shift_id' => $shiftId,
                'customer_name' => $customerName,
                'return_date' => $returnDate,
                'reference_number' => $referenceNumber,
                'refund_method' => $refundMethod,
                'chart_of_account_id' => $refundAccount->id,
                'total_amount' => $totalAmount,
                'total_cost' => $totalCost,
                'status' => $status,
                'reason' => $reason,
            ]);

            $salesReturn->items()->createMany($processedItems);

            $customerLabel = $salesReturn->customer_name ?: 'Pelanggan Umum';
            $description = ! empty($reason)
                ? "Retur Penjualan {$customerLabel} Ref: {$salesReturn->reference_number} - {$reason}"
                : "Retur Penjualan {$customerLabel} Ref: {$salesReturn->reference_number}";

            $journal = $this->journalPostingService->postSalesReturn([
                'branch_id' => $branch->id,
                'user_id' => $userId,
                'transaction_date' => $salesReturn->return_date,
                'reference_number' => $salesReturn->reference_number,
                'description' => $description,
                'refund_account_id' => $refundAccount->id,
                'total_amount' => $totalAmount,
                'total_cost' => $totalCost,
            ]);

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

    protected function resolveBranch(array $data, ?User $actor, ?Sale $sale): Branch
    {
        if ($actor instanceof User && ! $actor->isMaster()) {
            $branchId = (int) $actor->branch_id;
        } else {
            $branchId = (int) ($sale?->branch_id ?? $data['branch_id'] ?? $actor?->branch_id ?? 0);
        }

        $branch = Branch::withoutGlobalScopes()->find($branchId);

        if (! $branch) {
            throw ValidationException::withMessages([
                'branch_id' => ['Cabang tidak ditemukan.'],
            ]);
        }

        return $branch;
    }

    protected function resolveSale(?int $saleId, ?User $actor): ?Sale
    {
        if (! $saleId) {
            return null;
        }

        $query = Sale::withoutGlobalScopes()->with(['items', 'customer']);

        if ($actor instanceof User && ! $actor->isMaster()) {
            $query->where('branch_id', $actor->branch_id);
        }

        $sale = $query->find($saleId);

        if (! $sale) {
            throw ValidationException::withMessages([
                'sale_id' => ['Nota penjualan tidak ditemukan di cabang Anda.'],
            ]);
        }

        if ($actor instanceof User && ! $actor->isMaster() && (int) $sale->branch_id !== (int) $actor->branch_id) {
            throw ValidationException::withMessages([
                'sale_id' => ['Kasir hanya dapat meretur nota dari cabangnya sendiri.'],
            ]);
        }

        return $sale;
    }

    protected function resolveRefundAccount(array $data, ?Sale $sale): ChartOfAccount
    {
        if ($this->isReceivableRefund($data, $sale)) {
            return ChartOfAccount::firstOrCreate(
                ['code' => self::ACCOUNT_AR],
                ['name' => 'Piutang Usaha', 'type' => 'asset', 'is_active' => true]
            );
        }

        $chartOfAccountId = (int) ($data['chart_of_account_id'] ?? 0);
        $refundAccount = $chartOfAccountId > 0 ? ChartOfAccount::find($chartOfAccountId) : null;

        if ($refundAccount) {
            return $refundAccount;
        }

        return ChartOfAccount::where('code', self::ACCOUNT_CASH)->first()
            ?? ChartOfAccount::create([
                'code' => self::ACCOUNT_CASH,
                'name' => 'Kas',
                'type' => 'asset',
                'is_active' => true,
            ]);
    }

    protected function isReceivableRefund(array $data, ?Sale $sale): bool
    {
        $refundMethod = strtolower((string) ($data['refund_method'] ?? ''));
        $saleMethod = strtolower((string) ($sale?->payment_method ?? ''));
        $receivableMethods = ['tempo', 'piutang', 'kredit'];

        return in_array($refundMethod, $receivableMethods, true)
            || in_array($saleMethod, $receivableMethods, true);
    }

    protected function resolveShiftId(array $data, mixed $userId): mixed
    {
        if (! empty($data['cash_register_shift_id'])) {
            return $data['cash_register_shift_id'];
        }

        if (! $userId) {
            return null;
        }

        return CashRegisterShift::where('user_id', $userId)
            ->where('status', 'open')
            ->latest('opened_at')
            ->value('id');
    }

    /**
     * @return array{0: string, 1: string, 2: array<int, array<string, mixed>>}
     */
    protected function processItems(array $returnItems, int $branchId, ?Sale $sale): array
    {
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
                ->where('branch_id', $branchId)
                ->lockForUpdate()
                ->first();

            if (! $product) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => ["Produk ID {$productId} tidak ditemukan di cabang ini."],
                ]);
            }

            if ($sale) {
                $this->assertReturnableQuantity($sale, $productId, $quantity, $index);
            }

            $saleItem = $sale?->items->firstWhere('product_id', $productId);
            $unitPrice = isset($itemData['unit_price'])
                ? (string) $itemData['unit_price']
                : (string) ($saleItem?->price ?? $product->selling_price ?? '0.00');
            $unitCost = isset($itemData['unit_cost'])
                ? (string) $itemData['unit_cost']
                : (string) ($product->purchase_price ?? '0.00');

            $subtotal = isset($itemData['subtotal'])
                ? (string) $itemData['subtotal']
                : bcmul($unitPrice, (string) $quantity, 2);
            $subtotalCost = bcmul($unitCost, (string) $quantity, 2);

            $totalAmount = bcadd($totalAmount, $subtotal, 2);
            $totalCost = bcadd($totalCost, $subtotalCost, 2);

            $inventory = $this->lockInventory($branchId, $product->id);
            $inventory->increment('quantity', $quantity);
            $product->increment('stock', $quantity);

            $processedItems[] = [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'unit_cost' => $unitCost,
                'subtotal' => $subtotal,
                'subtotal_cost' => $subtotalCost,
            ];
        }

        return [$totalAmount, $totalCost, $processedItems];
    }

    protected function assertReturnableQuantity(Sale $sale, int $productId, int $quantity, int $index): void
    {
        $soldQty = (int) $sale->items->where('product_id', $productId)->sum('quantity');

        if ($soldQty <= 0) {
            throw ValidationException::withMessages([
                "items.{$index}.product_id" => ['Produk ini tidak ada pada nota penjualan yang dipilih.'],
            ]);
        }

        $alreadyReturned = (int) SalesReturnItem::query()
            ->where('product_id', $productId)
            ->whereIn(
                'sales_return_id',
                SalesReturn::withoutGlobalScopes()->where('sale_id', $sale->id)->select('id')
            )
            ->sum('quantity');

        $remaining = $soldQty - $alreadyReturned;

        if ($quantity > $remaining) {
            throw ValidationException::withMessages([
                "items.{$index}.quantity" => ["Jumlah retur melebihi sisa kuantitas nota ({$remaining})."],
            ]);
        }
    }

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

    protected function generateReferenceNumber(): string
    {
        do {
            $ref = 'SR-'.date('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        } while (SalesReturn::withoutGlobalScopes()->where('reference_number', $ref)->exists());

        return $ref;
    }
}
