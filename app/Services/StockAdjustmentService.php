<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Inventory;
use App\Models\JournalHeader;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service to process stock adjustments (stock opname).
 *
 * Atomically:
 * 1. Creates StockAdjustment and StockAdjustmentItem records.
 * 2. Updates stock in inventories table and products cached stock with lockForUpdate().
 * 3. Records double-entry balanced accounting journal:
 *    - Loss (difference < 0): Dr 5120 Beban Selisih Persediaan, Cr 1210 Persediaan.
 *    - Gain (difference > 0): Dr 1210 Persediaan, Cr 4120 Pendapatan Lain-lain.
 */
class StockAdjustmentService
{
    public const ACCOUNT_INVENTORY = '1210';
    public const ACCOUNT_EXPENSE_ADJUSTMENT = '5120';
    public const ACCOUNT_REVENUE_ADJUSTMENT = '4120';

    /**
     * Process stock adjustment atomically.
     *
     * @param array{
     *     branch_id?: int|null,
     *     reference_number?: string|null,
     *     date?: string|null,
     *     notes?: string|null,
     *     items: array<int, array{
     *         product_id: int,
     *         actual_qty: int,
     *         expected_qty?: int|null,
     *         unit_cost?: float|string|null
     *     }>
     * } $data
     * @param User $actor
     * @return StockAdjustment
     */
    public function processAdjustment(array $data, User $actor): StockAdjustment
    {
        if (empty($data['items']) || ! is_array($data['items'])) {
            throw ValidationException::withMessages([
                'items' => ['At least one adjustment item is required.'],
            ]);
        }

        return DB::transaction(function () use ($data, $actor): StockAdjustment {
            // Resolve branch
            $branch = $this->resolveBranch($data['branch_id'] ?? null, $actor);

            // Generate or use reference number
            $referenceNumber = ! empty($data['reference_number'])
                ? (string) $data['reference_number']
                : $this->generateReferenceNumber();

            $date = $data['date'] ?? now()->toDateString();

            $totalLossValue = '0.00';
            $totalGainValue = '0.00';
            $processedItems = [];

            // Lock and calculate items
            foreach ($data['items'] as $index => $itemData) {
                $productId = (int) ($itemData['product_id'] ?? 0);
                $actualQty = (int) ($itemData['actual_qty'] ?? 0);

                if ($actualQty < 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.actual_qty" => ['Actual quantity cannot be negative.'],
                    ]);
                }

                $product = Product::withoutGlobalScopes()
                    ->where('id', $productId)
                    ->lockForUpdate()
                    ->first();

                if (! $product) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => ["Product ID {$productId} not found."],
                    ]);
                }

                // Lock inventory record for branch & product
                $inventory = $this->lockInventory($branch->id, $product->id);

                $expectedQty = isset($itemData['expected_qty'])
                    ? (int) $itemData['expected_qty']
                    : (int) $inventory->quantity;

                $differenceQty = $actualQty - $expectedQty;

                // Determine unit cost (from input, purchase_price, or selling_price)
                $unitCost = '0.00';
                if (isset($itemData['unit_cost']) && is_numeric($itemData['unit_cost'])) {
                    $unitCost = number_format((float) $itemData['unit_cost'], 2, '.', '');
                } elseif ($product->purchase_price && (float) $product->purchase_price > 0) {
                    $unitCost = number_format((float) $product->purchase_price, 2, '.', '');
                } elseif ($product->selling_price && (float) $product->selling_price > 0) {
                    $unitCost = number_format((float) $product->selling_price, 2, '.', '');
                }

                $subtotalValue = bcmul($unitCost, (string) $differenceQty, 2);

                if ($differenceQty < 0) {
                    $loss = bcmul($unitCost, (string) abs($differenceQty), 2);
                    $totalLossValue = bcadd($totalLossValue, $loss, 2);
                } elseif ($differenceQty > 0) {
                    $gain = bcmul($unitCost, (string) $differenceQty, 2);
                    $totalGainValue = bcadd($totalGainValue, $gain, 2);
                }

                // Update inventory quantity atomically
                $inventory->quantity = $actualQty;
                $inventory->save();

                // Synchronize product cached stock column
                $product->increment('stock', $differenceQty);

                $processedItems[] = [
                    'product_id' => $product->id,
                    'expected_qty' => $expectedQty,
                    'actual_qty' => $actualQty,
                    'difference_qty' => $differenceQty,
                    'unit_cost' => $unitCost,
                    'subtotal_value' => $subtotalValue,
                ];
            }

            // Langkah A: Simpan master StockAdjustment
            $adjustment = StockAdjustment::create([
                'branch_id' => $branch->id,
                'reference_number' => $referenceNumber,
                'date' => $date,
                'notes' => $data['notes'] ?? null,
                'total_loss_value' => $totalLossValue,
                'total_gain_value' => $totalGainValue,
            ]);

            // Simpan items
            foreach ($processedItems as $item) {
                $adjustment->items()->create($item);
            }

            // Langkah C: Buat Jurnal Ganda Otomatis
            $this->recordJournal(
                $adjustment,
                $branch->id,
                $actor->id,
                $totalLossValue,
                $totalGainValue
            );

            return $adjustment->load(['items.product', 'branch']);
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
     * Resolve the operating branch.
     */
    private function resolveBranch(?int $branchId, User $actor): Branch
    {
        if ($branchId) {
            $branch = Branch::find($branchId);
            if ($branch) {
                return $branch;
            }
        }

        if ($actor->branch_id) {
            $branch = Branch::find($actor->branch_id);
            if ($branch) {
                return $branch;
            }
        }

        $central = Branch::whereNull('parent_id')->first()
            ?? Branch::where('code', 'PUSAT')->first()
            ?? Branch::first();

        if (! $central) {
            $central = Branch::create([
                'name' => 'Kantor Pusat',
                'code' => 'PUSAT',
                'address' => 'Pusat Operasional',
                'phone' => '08123456789',
            ]);
        }

        return $central;
    }

    /**
     * Create double-entry journal for stock adjustment.
     *
     * If Loss > 0:
     *   Dr. 5120 Beban Selisih Persediaan
     *     Cr. 1210 Persediaan
     *
     * If Gain > 0:
     *   Dr. 1210 Persediaan
     *     Cr. 4120 Pendapatan Lain-lain
     */
    private function recordJournal(
        StockAdjustment $adjustment,
        int $branchId,
        int $actorId,
        string $totalLossValue,
        string $totalGainValue
    ): void {
        $hasLoss = bccomp($totalLossValue, '0.00', 2) > 0;
        $hasGain = bccomp($totalGainValue, '0.00', 2) > 0;

        if (! $hasLoss && ! $hasGain) {
            return;
        }

        $inventoryAccount = $this->resolveAccount(self::ACCOUNT_INVENTORY, 'Persediaan', 'asset');
        $expenseAccount = $this->resolveAccount(self::ACCOUNT_EXPENSE_ADJUSTMENT, 'Beban Selisih Persediaan', 'expense');
        $revenueAccount = $this->resolveAccount(self::ACCOUNT_REVENUE_ADJUSTMENT, 'Pendapatan Lain-lain', 'revenue');

        $journal = JournalHeader::create([
            'branch_id' => $branchId,
            'user_id' => $actorId,
            'transaction_date' => $adjustment->date->format('Y-m-d'),
            'reference_number' => $adjustment->reference_number,
            'description' => "Penyesuaian Stok (Opname) #{$adjustment->reference_number}" . ($adjustment->notes ? " - {$adjustment->notes}" : ''),
        ]);

        // Loss journal entries: Dr Expense, Cr Inventory
        if ($hasLoss) {
            $journal->lines()->create([
                'chart_of_account_id' => $expenseAccount->id,
                'debit' => $totalLossValue,
                'credit' => '0.00',
            ]);

            $journal->lines()->create([
                'chart_of_account_id' => $inventoryAccount->id,
                'debit' => '0.00',
                'credit' => $totalLossValue,
            ]);
        }

        // Gain journal entries: Dr Inventory, Cr Revenue
        if ($hasGain) {
            $journal->lines()->create([
                'chart_of_account_id' => $inventoryAccount->id,
                'debit' => $totalGainValue,
                'credit' => '0.00',
            ]);

            $journal->lines()->create([
                'chart_of_account_id' => $revenueAccount->id,
                'debit' => '0.00',
                'credit' => $totalGainValue,
            ]);
        }
    }

    /**
     * Resolve chart of account or create standard fallback.
     */
    private function resolveAccount(string $code, string $defaultName, string $type): ChartOfAccount
    {
        return ChartOfAccount::firstOrCreate(
            ['code' => $code],
            ['name' => $defaultName, 'type' => $type]
        );
    }

    /**
     * Generate unique reference number: ADJ-YYYYMMDD-XXXXX
     */
    private function generateReferenceNumber(): string
    {
        $prefix = 'ADJ-' . now()->format('Ymd') . '-';
        do {
            $candidate = $prefix . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        } while (StockAdjustment::where('reference_number', $candidate)->exists());

        return $candidate;
    }
}
