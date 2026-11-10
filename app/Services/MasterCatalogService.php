<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Nama, SKU, harga modal, harga jual, dan harga bertingkat berlaku sama untuk semua cabang
 * dalam satu grup: cabang induk teratas beserta seluruh turunannya. Baris produk tetap
 * disimpan per cabang dan saling terhubung lewat SKU.
 *
 * Grup yang memiliki cabang acuan hanya menerima data tersebut dari cabang acuan; cabang
 * lain dalam grup itu mengikuti.
 */
class MasterCatalogService
{
    public const MASTER_FIELDS = ['name', 'sku', 'purchase_price', 'selling_price'];

    public const SOURCE_BRANCH_CODES = ['GUDANG-PUPUK'];

    /** @var Collection<int, Branch>|null */
    private ?Collection $branches = null;

    /**
     * @return list<int>
     */
    public function groupBranchIds(int $branchId): array
    {
        $rootId = $this->rootBranchId($branchId);
        $childrenByParent = $this->branches()->groupBy(fn (Branch $branch) => (int) $branch->parent_id);

        $ids = [];
        $queue = [$rootId];
        while ($queue !== []) {
            $id = array_shift($queue);
            if (in_array($id, $ids, true)) {
                continue;
            }

            $ids[] = $id;
            foreach ($childrenByParent->get($id, collect()) as $child) {
                $queue[] = (int) $child->id;
            }
        }

        return $ids;
    }

    /**
     * @return Collection<int, list<int>> keyed by the root branch id
     */
    public function groups(): Collection
    {
        return $this->branches()
            ->filter(fn (Branch $branch) => $this->rootBranchId((int) $branch->id) === (int) $branch->id)
            ->mapWithKeys(fn (Branch $root) => [(int) $root->id => $this->groupBranchIds((int) $root->id)]);
    }

    /**
     * Rows in the other branches of the group that share the SKU, archived rows included,
     * so an archived row keeps matching values if it is ever restored.
     *
     * @return Collection<int, Product>
     */
    public function siblingsOf(Product $product, ?string $sku = null): Collection
    {
        return Product::withoutGlobalScopes()
            ->whereIn('branch_id', $this->groupBranchIds((int) $product->branch_id))
            ->where('branch_id', '!=', $product->branch_id)
            ->where('sku', $sku ?? $product->sku)
            ->get();
    }

    public function sourceBranchId(int $branchId): ?int
    {
        foreach ($this->groupBranchIds($branchId) as $id) {
            if ($this->isSourceBranch($id)) {
                return $id;
            }
        }

        return null;
    }

    public function sourceBranch(int $branchId): ?Branch
    {
        $sourceId = $this->sourceBranchId($branchId);

        return $sourceId === null ? null : $this->branches()->get($sourceId);
    }

    public function isSourceBranch(?int $branchId): bool
    {
        $code = $branchId === null ? null : $this->branches()->get($branchId)?->code;

        return $code !== null && in_array(strtoupper((string) $code), self::SOURCE_BRANCH_CODES, true);
    }

    /**
     * True when the branch belongs to a group with a source branch and is not that source.
     */
    public function followsSource(int $branchId): bool
    {
        $sourceId = $this->sourceBranchId($branchId);

        return $sourceId !== null && $sourceId !== $branchId;
    }

    /**
     * @return list<int>
     */
    public function followerBranchIds(): array
    {
        return $this->branches()->keys()
            ->filter(fn (int $id) => $this->followsSource($id))
            ->values()
            ->all();
    }

    /**
     * Whether $user may create products in, or change the master fields of, $branchId.
     */
    public function canManageMasterFieldsIn(User $user, int $branchId): bool
    {
        if ($this->followsSource($branchId)) {
            return false;
        }

        if ($user->isMaster()) {
            return true;
        }

        return $user->isBranchAdmin() && (int) $user->branch_id === $branchId && $this->isSourceBranch($branchId);
    }

    /**
     * The active row in the source branch that $product follows, if any.
     */
    public function sourceProductFor(Product $product): ?Product
    {
        $sourceId = $this->sourceBranchId((int) $product->branch_id);
        if ($sourceId === null || $sourceId === (int) $product->branch_id) {
            return null;
        }

        return Product::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('branch_id', $sourceId)
            ->where('sku', $product->sku)
            ->first();
    }

    /**
     * Registers every active product of the group's source branch in $branch with stock 0,
     * skipping SKUs the branch already has (archived rows included).
     */
    public function seedBranchFromSource(Branch $branch): int
    {
        $this->branches = null;
        if (! $this->followsSource((int) $branch->id)) {
            return 0;
        }

        return DB::transaction(function () use ($branch): int {
            $existingSkus = Product::withoutGlobalScopes()
                ->where('branch_id', $branch->id)
                ->pluck('sku')
                ->flip();

            $created = 0;
            Product::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('branch_id', $this->sourceBranchId((int) $branch->id))
                ->with('productPrices')
                ->orderBy('id')
                ->get()
                ->reject(fn (Product $source) => $existingSkus->has($source->sku))
                ->each(function (Product $source) use ($branch, &$created): void {
                    $this->copyPrices($source->productPrices, $this->createCopy($source, (int) $branch->id));
                    $created++;
                });

            return $created;
        });
    }

    /**
     * Archives the active rows sharing the SKU in the other branches of the group. Stock and
     * prices are kept so the rows can be restored with their quantities.
     */
    public function hideInGroup(Product $source): int
    {
        $ids = $this->siblingsOf($source)
            ->whereNull('deleted_at')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        return Product::withoutGlobalScopes()->whereIn('id', $ids)->update([
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function assertSkuAvailable(Product $product, string $newSku): void
    {
        if ($newSku === (string) $product->sku) {
            return;
        }

        $linkedIds = $this->siblingsOf($product)->pluck('id')->push($product->id);

        $taken = Product::withoutGlobalScopes()
            ->whereIn('branch_id', $this->groupBranchIds((int) $product->branch_id))
            ->where('sku', $newSku)
            ->whereNotIn('id', $linkedIds)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'sku' => 'SKU ini sudah dipakai produk lain di salah satu cabang dalam grup yang sama.',
            ]);
        }
    }

    /**
     * Copies the master values of $source to every branch in its group and creates the
     * product in branches that do not have it yet. Archived rows are updated, and restored
     * only when $restoreArchived is set (a newly created source product).
     *
     * @return array{created: int, updated: int}
     */
    public function syncToGroup(Product $source, ?string $previousSku = null, bool $restoreArchived = false): array
    {
        return DB::transaction(function () use ($source, $previousSku, $restoreArchived): array {
            $prices = $source->productPrices()->get();
            $siblings = $this->siblingsOf($source, $previousSku ?? $source->sku)->keyBy('branch_id');
            $created = 0;

            foreach ($this->groupBranchIds((int) $source->branch_id) as $branchId) {
                if ($branchId === (int) $source->branch_id) {
                    continue;
                }

                $product = $siblings->get($branchId);
                if ($product === null) {
                    $product = $this->createCopy($source, $branchId);
                    $created++;
                } elseif ($restoreArchived && $product->deleted_at !== null) {
                    $product->forceFill([
                        ...$source->only(self::MASTER_FIELDS),
                        'deleted_at' => null,
                        'catalog_hold_reason' => null,
                    ])->save();

                    Inventory::withoutGlobalScopes()->firstOrCreate(
                        ['branch_id' => $branchId, 'product_id' => $product->id],
                        ['quantity' => $product->stock, 'warehouse_id' => $this->defaultWarehouseId($branchId)],
                    );
                } else {
                    $product->forceFill($source->only(self::MASTER_FIELDS))->save();
                }

                $this->copyPrices($prices, $product);
            }

            return ['created' => $created, 'updated' => $siblings->count()];
        });
    }

    /**
     * Branches that would receive a new row, and branches whose row differs from $source.
     *
     * @return array{create: list<int>, update: list<int>}
     */
    public function plan(Product $source): array
    {
        $sourcePrices = $this->priceSignature($source->productPrices()->get());
        $siblings = $this->siblingsOf($source)->load('productPrices')->keyBy('branch_id');
        $create = [];
        $update = [];

        foreach ($this->groupBranchIds((int) $source->branch_id) as $branchId) {
            if ($branchId === (int) $source->branch_id) {
                continue;
            }

            $sibling = $siblings->get($branchId);
            if ($sibling === null) {
                $create[] = $branchId;

                continue;
            }

            if (! $this->sameMasterValues($source, $sibling) || $this->priceSignature($sibling->productPrices) !== $sourcePrices) {
                $update[] = $branchId;
            }
        }

        return ['create' => $create, 'update' => $update];
    }

    private function createCopy(Product $source, int $branchId): Product
    {
        $product = Product::withoutGlobalScopes()->create([
            ...$source->only(self::MASTER_FIELDS),
            'branch_id' => $branchId,
            'category_id' => $source->category_id,
            'unit' => $source->unit,
            'is_taxable' => $source->is_taxable,
            'stock' => 0,
        ]);

        Inventory::withoutGlobalScopes()->firstOrCreate(
            ['branch_id' => $branchId, 'product_id' => $product->id],
            ['quantity' => 0, 'warehouse_id' => $this->defaultWarehouseId($branchId)],
        );

        return $product;
    }

    /**
     * @param  Collection<int, ProductPrice>  $prices
     */
    private function copyPrices(Collection $prices, Product $target): void
    {
        ProductPrice::where('product_id', $target->id)->delete();

        foreach ($prices as $price) {
            ProductPrice::create([
                'product_id' => $target->id,
                'price_level_id' => $price->price_level_id,
                'customer_group_id' => $price->customer_group_id,
                'price' => $price->price,
            ]);
        }
    }

    private function sameMasterValues(Product $a, Product $b): bool
    {
        return trim((string) $a->name) === trim((string) $b->name)
            && bccomp((string) $a->purchase_price, (string) $b->purchase_price, 2) === 0
            && bccomp((string) $a->selling_price, (string) $b->selling_price, 2) === 0;
    }

    /**
     * @param  Collection<int, ProductPrice>  $prices
     */
    private function priceSignature(Collection $prices): string
    {
        return $prices
            ->map(fn (ProductPrice $price) => sprintf(
                '%s|%s|%s',
                $price->price_level_id ?? '-',
                $price->customer_group_id ?? '-',
                number_format((float) $price->price, 2, '.', ''),
            ))
            ->sort()
            ->implode(';');
    }

    private function defaultWarehouseId(int $branchId): ?int
    {
        $warehouse = Warehouse::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->orderByRaw("CASE WHEN name = 'Gudang Utama' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();

        return $warehouse?->id;
    }

    private function rootBranchId(int $branchId): int
    {
        $branches = $this->branches();
        $rootId = $branchId;

        for ($depth = 0; $depth < 10; $depth++) {
            $parentId = (int) ($branches->get($rootId)?->parent_id ?? 0);
            if ($parentId === 0 || ! $branches->has($parentId)) {
                break;
            }
            $rootId = $parentId;
        }

        return $rootId;
    }

    /**
     * @return Collection<int, Branch>
     */
    private function branches(): Collection
    {
        return $this->branches ??= Branch::query()->get(['id', 'parent_id', 'code', 'name'])->keyBy('id');
    }
}
