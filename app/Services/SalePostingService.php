<?php

namespace App\Services;

use App\Models\ChartOfAccount;
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
    private const ACCOUNT_INVENTORY = '1210';
    private const ACCOUNT_REVENUE = '4110';
    private const ACCOUNT_COGS = '5100';

    /**
     * @param  array{items:array<int,array{product_id:int,quantity:int}>,payment_method?:string|null,receipt_number?:string|null}  $data
     */
    public function post(array $data, User $actor): Sale
    {
        $items = $this->normaliseItems($data['items']);
        $paymentMethod = $data['payment_method'] ?? 'cash';
        $receiptNumber = $data['receipt_number'] ?? $this->generateReceiptNumber();

        return DB::transaction(function () use ($items, $actor, $receiptNumber, $paymentMethod): Sale {
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

            $totalCents = 0;
            $costCents = 0;
            $saleItems = [];

            foreach ($items as $item) {
                $product = $products->get($item['product_id']);
                $inventory = $this->lockInventory((int) $product->branch_id, (int) $product->id, (int) $product->stock);

                if ($inventory->quantity < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => ["Insufficient stock for {$product->name}."],
                    ]);
                }

                $inventory->decrement('quantity', $item['quantity']);
                $product->decrement('stock', $item['quantity']);

                $priceCents = $this->toCents($product->selling_price);
                $unitCostCents = $this->toCents($product->purchase_price);

                $subtotalCents = $priceCents * $item['quantity'];
                $totalCents += $subtotalCents;
                $costCents += $unitCostCents * $item['quantity'];

                $saleItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $priceCents,
                    'subtotal' => $subtotalCents,
                ];
            }

            $sale = Sale::create([
                'branch_id' => $actor->branch_id,
                'created_by' => $actor->id,
                'receipt_number' => $receiptNumber,
                'total_amount' => $totalCents,
                'payment_method' => $paymentMethod,
                'status' => 'completed',
            ]);

            $sale->items()->createMany($saleItems);

            $this->recordJournal($sale, $accounts, $totalCents, $costCents, $actor);

            return $sale->load('items.product');
        });
    }

    /**
     * Write the four line entry that a retail sale produces:
     *
     *   Dr Cash                 (asset increases by the amount collected)
     *     Cr Revenue            (income earned)
     *   Dr Cost of goods sold   (expense recognised)
     *     Cr Inventory          (asset released from the warehouse)
     *
     * @param  Collection<string,ChartOfAccount>  $accounts
     */
    private function recordJournal(
        Sale $sale,
        Collection $accounts,
        int $totalCents,
        int $costCents,
        User $actor,
    ): void {
        $journal = JournalHeader::create([
            'branch_id' => $sale->branch_id,
            'user_id' => $actor->id,
            'transaction_date' => now()->toDateString(),
            'reference_number' => $sale->receipt_number,
            'description' => 'POS Sale',
        ]);

        $total = $this->fromCents($totalCents);
        $cost = $this->fromCents($costCents);

        $lines = [
            [
                'chart_of_account_id' => $accounts[self::ACCOUNT_CASH]->id,
                'debit' => $total,
                'credit' => 0,
                'memo' => 'POS cash sale',
            ],
            [
                'chart_of_account_id' => $accounts[self::ACCOUNT_REVENUE]->id,
                'debit' => 0,
                'credit' => $total,
                'memo' => 'POS sales revenue',
            ],
        ];

        // A sale of zero-cost items still balances, but posting empty COGS lines only
        // adds noise to the ledger, so they are skipped.
        if (bccomp($cost, '0.00', 2) === 1) {
            $lines[] = [
                'chart_of_account_id' => $accounts[self::ACCOUNT_COGS]->id,
                'debit' => $cost,
                'credit' => 0,
                'memo' => 'Cost of goods sold',
            ];

            $lines[] = [
                'chart_of_account_id' => $accounts[self::ACCOUNT_INVENTORY]->id,
                'debit' => 0,
                'credit' => $cost,
                'memo' => 'Inventory released on sale',
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

        $accounts = ChartOfAccount::query()
            ->whereIn('code', $required)
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

    private function toCents(string|int|float $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function fromCents(int $cents): string
    {
        return bcdiv((string) $cents, '100', 2);
    }
}
