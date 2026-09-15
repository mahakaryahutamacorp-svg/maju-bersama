<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Inventory;
use App\Models\JournalHeader;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves stock between branches.
 *
 * Every mutation performed here (source stock decrease, destination stock increase,
 * transfer records and the accounting journal) happens inside a single database
 * transaction. If any step fails the whole operation is rolled back, so stock can
 * never be deducted without the matching journal entry being written.
 */
class StockTransferService
{
    /**
     * Inventory account used on both sides of an inter-branch stock movement.
     */
    private const ACCOUNT_INVENTORY = '1210';

    /**
     * @param  array{source_branch_id:int,destination_branch_id:int,items:array<int,array{product_id:int,quantity:int}>,notes?:string|null,transfer_date?:string|null}  $data
     */
    public function transfer(array $data, User $actor): StockTransfer
    {
        $items = $this->normaliseItems($data['items']);

        if ((int) $data['source_branch_id'] === (int) $data['destination_branch_id']) {
            throw ValidationException::withMessages([
                'destination_branch_id' => ['Source and destination branch must be different.'],
            ]);
        }

        $this->assertActorMayTransferFrom($actor, (int) $data['source_branch_id']);

        return DB::transaction(function () use ($data, $items, $actor): StockTransfer {
            $sourceBranch = Branch::query()->findOrFail($data['source_branch_id']);
            $destinationBranch = Branch::query()->findOrFail($data['destination_branch_id']);

            $inventoryAccount = ChartOfAccount::query()
                ->where('code', self::ACCOUNT_INVENTORY)
                ->first();

            if (! $inventoryAccount) {
                throw ValidationException::withMessages([
                    'items' => ['Inventory ledger account (1210) is not configured.'],
                ]);
            }

            $transfer = StockTransfer::create([
                'reference_number' => $this->generateReferenceNumber(),
                'source_branch_id' => $sourceBranch->id,
                'destination_branch_id' => $destinationBranch->id,
                'created_by' => $actor->id,
                'transfer_date' => $data['transfer_date'] ?? now()->toDateString(),
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
            ]);

            $totalValue = '0.00';

            foreach ($items as $item) {
                $totalValue = bcadd(
                    $totalValue,
                    $this->moveSingleProduct($transfer, $sourceBranch, $destinationBranch, $item),
                    2,
                );
            }

            $this->recordJournals($transfer, $inventoryAccount->id, $totalValue, $actor);

            return $transfer->load([
                'items.sourceProduct',
                'items.destinationProduct',
                'sourceBranch',
                'destinationBranch',
            ]);
        });
    }

    /**
     * Move one product line and return the monetary value moved, as a decimal string.
     *
     * @param  array{product_id:int,quantity:int}  $item
     */
    private function moveSingleProduct(
        StockTransfer $transfer,
        Branch $sourceBranch,
        Branch $destinationBranch,
        array $item,
    ): string {
        $sourceProduct = $this->lockProduct($item['product_id'], $sourceBranch->id);
        $sourceInventory = $this->lockInventory($sourceBranch->id, $sourceProduct->id);

        if ($sourceInventory->quantity < $item['quantity']) {
            throw ValidationException::withMessages([
                'items' => ["Insufficient stock at source branch for {$sourceProduct->name}."],
            ]);
        }

        $destinationProduct = $this->resolveDestinationProduct($sourceProduct, $destinationBranch);
        $destinationInventory = $this->lockInventory($destinationBranch->id, $destinationProduct->id);

        $sourceInventory->decrement('quantity', $item['quantity']);
        $destinationInventory->increment('quantity', $item['quantity']);

        // Keep the legacy products.stock column aligned with the inventory rows so
        // that screens which have not been migrated yet keep showing correct numbers.
        $sourceProduct->decrement('stock', $item['quantity']);
        $destinationProduct->increment('stock', $item['quantity']);

        $unitCost = (string) $sourceProduct->purchase_price;

        $transfer->items()->create([
            'source_product_id' => $sourceProduct->id,
            'destination_product_id' => $destinationProduct->id,
            'quantity' => $item['quantity'],
            'unit_cost' => $unitCost,
        ]);

        return bcmul($unitCost, (string) $item['quantity'], 2);
    }

    /**
     * Merge duplicate product lines so a product is only locked once per transfer.
     *
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

    private function assertActorMayTransferFrom(User $actor, int $sourceBranchId): void
    {
        if ($actor->isMaster()) {
            return;
        }

        if ((int) $actor->branch_id !== $sourceBranchId) {
            throw ValidationException::withMessages([
                'source_branch_id' => ['You may only transfer stock out of your own branch.'],
            ]);
        }
    }

    private function lockProduct(int $productId, int $branchId): Product
    {
        $product = Product::withoutGlobalScopes()
            ->where('id', $productId)
            ->where('branch_id', $branchId)
            ->lockForUpdate()
            ->first();

        if (! $product) {
            throw ValidationException::withMessages([
                'items' => ['One or more products do not belong to the source branch.'],
            ]);
        }

        return $product;
    }

    /**
     * Fetch the stock row for a branch/product pair, creating it when absent.
     *
     * The composite unique key on (branch_id, product_id) is what guarantees a single
     * row per pair, so this can never introduce duplicated inventory rows.
     */
    private function lockInventory(int $branchId, int $productId): Inventory
    {
        Inventory::withoutGlobalScopes()->firstOrCreate(
            ['branch_id' => $branchId, 'product_id' => $productId],
            ['quantity' => 0],
        );

        return Inventory::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Find the matching article in the destination branch catalogue, mirroring the
     * source product when that branch does not carry it yet.
     */
    private function resolveDestinationProduct(Product $sourceProduct, Branch $destinationBranch): Product
    {
        $existing = Product::withoutGlobalScopes()
            ->where('branch_id', $destinationBranch->id)
            ->where('sku', $sourceProduct->sku)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            return $existing;
        }

        return Product::withoutGlobalScopes()->create([
            'branch_id' => $destinationBranch->id,
            'category_id' => $sourceProduct->category_id,
            'sku' => $sourceProduct->sku,
            'name' => $sourceProduct->name,
            'purchase_price' => $sourceProduct->purchase_price,
            'selling_price' => $sourceProduct->selling_price,
            'stock' => 0,
        ]);
    }

    /**
     * Inter-branch movements are written as two mirrored journals so that each branch
     * ledger balances on its own: the source credits inventory against an inter-branch
     * receivable, the destination debits inventory against an inter-branch payable.
     */
    private function recordJournals(
        StockTransfer $transfer,
        int $inventoryAccountId,
        string $totalValue,
        User $actor,
    ): void {
        if (bccomp($totalValue, '0.00', 2) === 0) {
            return;
        }

        $sourceJournal = JournalHeader::create([
            'branch_id' => $transfer->source_branch_id,
            'user_id' => $actor->id,
            'transaction_date' => $transfer->transfer_date,
            'reference_number' => $transfer->reference_number,
            'description' => 'Stock transfer out',
        ]);

        $sourceJournal->journalLines()->createMany([
            [
                'chart_of_account_id' => $inventoryAccountId,
                'debit' => $totalValue,
                'credit' => 0,
                'memo' => 'Inter-branch receivable '.$transfer->destinationBranch->name,
            ],
            [
                'chart_of_account_id' => $inventoryAccountId,
                'debit' => 0,
                'credit' => $totalValue,
                'memo' => 'Inventory shipped to '.$transfer->destinationBranch->name,
            ],
        ]);

        $destinationJournal = JournalHeader::create([
            'branch_id' => $transfer->destination_branch_id,
            'user_id' => $actor->id,
            'transaction_date' => $transfer->transfer_date,
            'reference_number' => $transfer->reference_number,
            'description' => 'Stock transfer in',
        ]);

        $destinationJournal->journalLines()->createMany([
            [
                'chart_of_account_id' => $inventoryAccountId,
                'debit' => $totalValue,
                'credit' => 0,
                'memo' => 'Inventory received from '.$transfer->sourceBranch->name,
            ],
            [
                'chart_of_account_id' => $inventoryAccountId,
                'debit' => 0,
                'credit' => $totalValue,
                'memo' => 'Inter-branch payable '.$transfer->sourceBranch->name,
            ],
        ]);
    }

    private function generateReferenceNumber(): string
    {
        do {
            $reference = 'TRF-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (StockTransfer::query()->where('reference_number', $reference)->exists());

        return $reference;
    }
}
