<?php

namespace App\Services;

use App\Models\CashRegisterShift;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\JournalHeader;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Posts a point of sale transaction.
 *
 * Stock deduction, the sale records and the four line double entry journal are all
 * written inside one database transaction. Either every one of them is committed, or
 * none of them is, so stock can never be deducted without its journal counterpart.
 */
class SalePostingService
{
    private const ACCOUNT_CASH = '1110';

    private const ACCOUNT_AR = '1130';

    private const ACCOUNT_INVENTORY = '1210';

    private const ACCOUNT_REVENUE = '4110';

    private const ACCOUNT_DISCOUNT = '4130';

    private const ACCOUNT_COGS = '5100';

    /**
     * @param  array{items:array<int,array{product_id:int,quantity:int}>,payment_method?:string|null,receipt_number?:string|null,discount_amount?:float|int|numeric|null}  $data
     */
    public function post(array $data, User $actor): Sale
    {
        $items = $this->normaliseItems($data['items']);
        $paymentMethod = $data['payment_method'] ?? 'cash';
        $receiptNumber = $data['receipt_number'] ?? $this->generateReceiptNumber();

        return DB::transaction(function () use ($items, $actor, $receiptNumber, $paymentMethod, $data): Sale {
            $accounts = $this->resolveAccounts();

            $products = Product::query()
                ->whereIn('id', $items->pluck('product_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== $items->count()) {
                throw ValidationException::withMessages([
                    'items' => ['One or more products are not available at your branch.'],
                ]);
            }

            $totalPrice = '0.00';
            $totalCost = '0.00';
            $saleItems = [];

            $customerId = $data['customer_id'] ?? null;
            $customerGroupId = null;
            if ($customerId) {
                $customer = Customer::find($customerId);
                $customerGroupId = $customer?->customer_group_id;
            }

            foreach ($items as $item) {
                $product = $products->get($item['product_id']);
                $branchId = (int) ($actor->branch_id ?: $product->branch_id);
                $initialQty = ((int) $product->branch_id === $branchId) ? (int) $product->stock : 0;
                $inventory = $this->lockInventory($branchId, (int) $product->id, $initialQty);

                if ($inventory->quantity < $item['quantity'] || (int) $inventory->quantity <= 0 || (int) $product->stock < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => ["Stok tidak mencukupi untuk {$product->name}. Stok tersedia: {$inventory->quantity}."],
                    ]);
                }

                $inventory->decrement('quantity', $item['quantity']);
                $product->decrement('stock', $item['quantity']);

                $unitPrice = $this->money($customerGroupId !== null
                    ? $product->getPriceForGroup($customerGroupId)
                    : $product->selling_price);

                $unitCost = $this->money($product->purchase_price);
                $quantity = (string) (int) $item['quantity'];

                $subtotal = bcmul($unitPrice, $quantity, 2);
                $cost = bcmul($unitCost, $quantity, 2);

                $totalPrice = bcadd($totalPrice, $subtotal, 2);
                $totalCost = bcadd($totalCost, $cost, 2);

                $saleItems[] = [
                    'product_id' => $product->id,
                    'quantity' => (int) $item['quantity'],
                    'price' => $unitPrice,
                    'subtotal' => $subtotal,
                ];
            }

            // Validasi & kalkulasi diskon transaksi nominal
            $discountAmount = $this->money($data['discount_amount'] ?? 0);

            if (bccomp($discountAmount, '0.00', 2) < 0) {
                throw ValidationException::withMessages([
                    'discount_amount' => ['Diskon tidak boleh bernilai negatif.'],
                ]);
            }

            if (bccomp($discountAmount, $totalPrice, 2) > 0) {
                throw ValidationException::withMessages([
                    'discount_amount' => ['Diskon tidak boleh lebih besar dari Subtotal.'],
                ]);
            }

            $grandTotal = bcsub($totalPrice, $discountAmount, 2);

            // Tautkan secara otomatis ke sesi shift kasir yang sedang aktif
            $activeShift = CashRegisterShift::where('user_id', $actor->id)
                ->where('status', 'open')
                ->latest('opened_at')
                ->first();

            $paymentStatus = in_array(strtolower($paymentMethod), ['tempo', 'piutang', 'kredit']) ? 'UNPAID' : 'PAID';
            $paidAmount = $paymentStatus === 'PAID' ? $grandTotal : $this->money($data['paid_amount'] ?? 0);

            $sale = Sale::create([
                'branch_id' => $actor->branch_id,
                'customer_id' => $customerId,
                'cash_register_shift_id' => $activeShift?->id ?? ($data['cash_register_shift_id'] ?? null),
                'created_by' => $actor->id,
                'receipt_number' => $receiptNumber,
                'total_amount' => $grandTotal,
                'discount_amount' => $discountAmount,
                'paid_amount' => $paidAmount,
                'payment_status' => $paymentStatus,
                'payment_method' => $paymentMethod,
                'due_date' => $data['due_date'] ?? null,
                'status' => 'completed',
            ]);

            $sale->items()->createMany($saleItems);

            $this->recordJournal($sale, $accounts, $grandTotal, $discountAmount, $totalPrice, $totalCost, $actor);

            return $sale->load('items.product');
        });
    }

    /**
     * Write the four line entry that a retail sale produces:
     *
     *   Dr Cash                 (asset increases by the amount collected - Grand Total)
     *   Dr Sales Discount       (contra-revenue recognised - discount amount)
     *     Cr Revenue            (income earned - gross subtotal)
     *   Dr Cost of goods sold   (expense recognised)
     *     Cr Inventory          (asset released from the warehouse)
     *
     * @param  Collection<string,ChartOfAccount>  $accounts
     */
    private function recordJournal(
        Sale $sale,
        Collection $accounts,
        string $grandTotal,
        string $discountAmount,
        string $subtotal,
        string $cost,
        User $actor,
    ): void {
        $journal = JournalHeader::create([
            'branch_id' => $sale->branch_id,
            'user_id' => $actor->id,
            'transaction_date' => now()->toDateString(),
            'reference_number' => $sale->receipt_number,
            'description' => 'POS Sale',
        ]);

        $isTempo = in_array(strtolower($sale->payment_method ?? ''), ['tempo', 'piutang', 'kredit']);
        $debitAccountId = $isTempo ? $accounts[self::ACCOUNT_AR]->id : $accounts[self::ACCOUNT_CASH]->id;
        $debitMemo = $isTempo ? 'Piutang penjualan POS (Tempo)' : 'Penjualan tunai POS';

        $lines = [
            [
                'chart_of_account_id' => $debitAccountId,
                'debit' => $grandTotal,
                'credit' => 0,
                'memo' => $debitMemo,
            ],
        ];

        // Jika ada diskon, debit akun Potongan Penjualan
        if (bccomp($discountAmount, '0.00', 2) === 1) {
            $lines[] = [
                'chart_of_account_id' => $accounts[self::ACCOUNT_DISCOUNT]->id,
                'debit' => $discountAmount,
                'credit' => 0,
                'memo' => 'Potongan penjualan POS',
            ];
        }

        // Kredit pendapatan penjualan sebesar subtotal kotor
        $lines[] = [
            'chart_of_account_id' => $accounts[self::ACCOUNT_REVENUE]->id,
            'debit' => 0,
            'credit' => $subtotal,
            'memo' => 'Pendapatan penjualan POS',
        ];

        // A sale of zero-cost items still balances, but posting empty COGS lines only
        // adds noise to the ledger, so they are skipped.
        if (bccomp($cost, '0.00', 2) === 1) {
            $lines[] = [
                'chart_of_account_id' => $accounts[self::ACCOUNT_COGS]->id,
                'debit' => $cost,
                'credit' => 0,
                'memo' => 'Harga pokok penjualan (HPP)',
            ];

            $lines[] = [
                'chart_of_account_id' => $accounts[self::ACCOUNT_INVENTORY]->id,
                'debit' => 0,
                'credit' => $cost,
                'memo' => 'Barang keluar untuk penjualan',
            ];
        }

        $journal->journalLines()->createMany($lines);
    }

    /**
     * @return Collection<string,ChartOfAccount>
     */
    private function resolveAccounts(): Collection
    {
        $required = [
            self::ACCOUNT_CASH,
            self::ACCOUNT_INVENTORY,
            self::ACCOUNT_REVENUE,
            self::ACCOUNT_COGS,
        ];

        // Pastikan akun Potongan Penjualan & Piutang Usaha tersedia di sistem akuntansi
        ChartOfAccount::firstOrCreate(
            ['code' => self::ACCOUNT_DISCOUNT],
            ['name' => 'Potongan Penjualan', 'type' => 'revenue']
        );

        ChartOfAccount::firstOrCreate(
            ['code' => self::ACCOUNT_AR],
            ['name' => 'Piutang Usaha', 'type' => 'asset']
        );

        $accounts = ChartOfAccount::query()
            ->whereIn('code', [...$required, self::ACCOUNT_DISCOUNT, self::ACCOUNT_AR])
            ->get()
            ->keyBy('code');

        foreach ($required as $code) {
            if (! $accounts->has($code)) {
                throw ValidationException::withMessages([
                    'items' => ["Required POS ledger account ({$code}) is not configured."],
                ]);
            }
        }

        return $accounts;
    }

    /**
     * Read the stock row for locking, seeding it from the legacy products.stock column
     * the first time a product is sold after the inventory table was introduced.
     * Also ensures warehouse_id is populated for new rows (Phase-2 forward compat).
     */
    private function lockInventory(int $branchId, int $productId, int $fallbackQuantity): Inventory
    {
        $warehouse = $this->resolveWarehouseForBranch($branchId);

        Inventory::withoutGlobalScopes()->firstOrCreate(
            ['branch_id' => $branchId, 'product_id' => $productId],
            ['quantity' => $fallbackQuantity, 'warehouse_id' => $warehouse?->id],
        );

        return Inventory::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Resolve the "Gudang Utama" warehouse for a given branch.
     * Returns null gracefully when the warehouses table has no row yet
     * (e.g. during test bootstrap before the migration has seeded data).
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
     * @param  array<int,array{product_id:int|string,quantity:int|string}>  $items
     * @return Collection<int,array{product_id:int,quantity:int}>
     */
    private function normaliseItems(array $items): Collection
    {
        return collect($items)
            ->groupBy('product_id')
            ->map(fn (Collection $lines) => [
                'product_id' => (int) $lines->first()['product_id'],
                'quantity' => (int) $lines->sum(fn (array $line) => (int) $line['quantity']),
            ])
            ->values();
    }

    public function generateReceiptNumber(): string
    {
        do {
            $receipt = 'INV-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (Sale::withoutGlobalScopes()->where('receipt_number', $receipt)->exists());

        return $receipt;
    }

    /**
     * Normalise a price or amount to a DECIMAL(15,2) string, rounding half away from zero.
     */
    private function money(string|int|float|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return '0.00';
        }

        $value = is_float($amount)
            ? number_format($amount, 3, '.', '')
            : trim((string) $amount);

        $half = str_starts_with($value, '-') ? '-0.005' : '0.005';

        return bcadd(bcadd($value, $half, 3), '0', 2);
    }

    private function toCents(string|int|float $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function fromCents(int $cents): string
    {
        return bcdiv((string) $cents, '100', 2);
    }
}
