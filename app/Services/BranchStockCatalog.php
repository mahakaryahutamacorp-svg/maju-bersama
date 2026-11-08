<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Daftar produk satu cabang beserta stok sistemnya, dipakai halaman Stock Opname dan Input Stok Awal.
 */
class BranchStockCatalog
{
    public function isMaster(User $user): bool
    {
        return in_array($user->role, ['master', 'superadmin'], true);
    }

    public function resolveBranch(User $user, int|string|null $requestedBranchId): ?Branch
    {
        if ($this->isMaster($user) && $requestedBranchId) {
            $branch = Branch::find($requestedBranchId);
            if ($branch) {
                return $branch;
            }
        }

        return $user->branch
            ?? Branch::whereNull('parent_id')->first()
            ?? Branch::where('code', 'PUSAT')->first()
            ?? Branch::first();
    }

    /**
     * @return Collection<int,Branch>
     */
    public function selectableBranches(User $user, Branch $currentBranch): Collection
    {
        return $this->isMaster($user) ? Branch::orderBy('name')->get() : collect([$currentBranch]);
    }

    /**
     * @return Collection<int,array{id:int,sku:string,name:string,category:string,unit:string,purchase_price:float,selling_price:float,stock:int}>
     */
    public function productsFor(Branch $branch): Collection
    {
        $products = Product::withoutGlobalScopes()
            ->with('category:id,name')
            ->where('branch_id', $branch->id)
            ->orderBy('name')
            ->get();

        if ($products->isEmpty()) {
            $products = Product::withoutGlobalScopes()->with('category:id,name')->orderBy('name')->get();
        }

        $quantities = Inventory::withoutGlobalScopes()
            ->where('branch_id', $branch->id)
            ->whereIn('product_id', $products->pluck('id'))
            ->pluck('quantity', 'product_id');

        return $products->map(fn (Product $product) => [
            'id' => $product->id,
            'sku' => (string) $product->sku,
            'name' => (string) $product->name,
            'category' => (string) ($product->category?->name ?? ''),
            'unit' => (string) ($product->unit ?? ''),
            'purchase_price' => (float) $product->purchase_price,
            'selling_price' => (float) $product->selling_price,
            'stock' => (int) ($quantities[$product->id] ?? $product->stock),
        ])->values();
    }
}
