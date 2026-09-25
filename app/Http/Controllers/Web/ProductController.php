<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\CustomerGroup;
use App\Models\PriceLevel;
use App\Models\Product;
use App\Models\ProductBranchPrice;
use App\Models\ProductPrice;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $productService
    ) {}

    /**
     * Display a listing of products.
     */
    public function index(Request $request): View
    {
        $user = $request->user()->load('branch');
        $isMaster = $user->isMaster();

        $branchId = $isMaster ? $request->input('branch_id') : $user->branch_id;
        $categoryId = $request->input('category_id');
        $search = $request->input('search');

        $query = Product::withoutGlobalScopes()
            ->with(['category', 'branch', 'productPrices'])
            ->when(! $isMaster, fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($isMaster && $branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->orderBy('name');

        $products = $query->paginate(15)->withQueryString();

        $branches = $isMaster ? Branch::orderBy('name')->get() : collect([$user->branch]);
        $categories = Category::orderBy('name')->get();

        return view('backoffice.products.index', [
            'currentUser' => $user,
            'isMaster' => $isMaster,
            'products' => $products,
            'branches' => $branches,
            'categories' => $categories,
            'selectedBranchId' => $branchId,
            'selectedCategoryId' => $categoryId,
            'search' => $search,
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(Request $request): View
    {
        $user = $request->user()->load('branch');
        $isMaster = $user->isMaster();

        $branches = $isMaster ? Branch::orderBy('name')->get() : collect([$user->branch]);
        $allBranches = Branch::where('is_active', true)->orderBy('name')->get();
        if ($allBranches->isEmpty()) {
            $allBranches = Branch::orderBy('name')->get();
        }
        $categories = Category::orderBy('name')->get();
        $customerGroups = CustomerGroup::orderBy('id')->get();
        $priceLevels = PriceLevel::where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        return view('backoffice.products.create', [
            'currentUser' => $user,
            'isMaster' => $isMaster,
            'branches' => $branches,
            'allBranches' => $allBranches,
            'categories' => $categories,
            'customerGroups' => $customerGroups,
            'priceLevels' => $priceLevels,
        ]);
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isMaster = $user->isMaster();

        $defaultPriceLevel = PriceLevel::where('is_default', true)->first();

        // If form submitted 'prices' array, populate 'selling_price' from default level
        if ($defaultPriceLevel && $request->filled("prices.{$defaultPriceLevel->id}")) {
            $request->merge(['selling_price' => $request->input("prices.{$defaultPriceLevel->id}")]);
        } elseif ($request->filled('selling_price') && $defaultPriceLevel) {
            $prices = $request->input('prices', []);
            if (!isset($prices[$defaultPriceLevel->id])) {
                $prices[$defaultPriceLevel->id] = $request->input('selling_price');
                $request->merge(['prices' => $prices]);
            }
        }

        // If customer_group_prices provided and selling_price not yet set
        if (! $request->filled('selling_price')) {
            $cgp = $request->input('customer_group_prices', $request->input('group_prices', []));
            if (! empty($cgp)) {
                $firstVal = collect($cgp)->filter(fn ($v) => $v !== null && $v !== '')->first();
                if ($firstVal) {
                    $request->merge(['selling_price' => $firstVal]);
                }
            }
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:50'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'unit' => ['nullable', 'string', 'max:50'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'prices' => ['nullable', 'array'],
            'prices.*' => ['nullable', 'numeric', 'min:0'],
            'customer_group_prices' => ['nullable', 'array'],
            'customer_group_prices.*' => ['nullable', 'numeric', 'min:0'],
            'group_prices' => ['nullable', 'array'],
            'group_prices.*' => ['nullable', 'numeric', 'min:0'],
            'branch_prices' => ['nullable', 'array'],
            'branch_prices.*' => ['nullable', 'numeric', 'min:0'],
        ];

        if ($isMaster) {
            $rules['branch_id'] = ['nullable', 'integer', 'exists:branches,id'];
        }

        $validated = $request->validate($rules);

        // Enforce branch tenancy
        $targetBranchId = $isMaster && ! empty($validated['branch_id'])
            ? (int) $validated['branch_id']
            : (int) ($user->branch_id ?: Branch::whereNull('parent_id')->value('id') ?: Branch::value('id'));

        $product = $this->productService->createProduct($validated, $targetBranchId);

        // Sync tiered product prices (PriceLevel compatibility)
        $this->syncProductPrices($product, $request->input('prices', []));

        // Sync CustomerGroup tiered prices
        $this->syncCustomerGroupPrices($product, $request->input('customer_group_prices', $request->input('group_prices', [])));

        // Sync Branch-level price overrides
        $this->syncBranchPrices($product, $request->input('branch_prices', []));

        return redirect()
            ->route('backoffice.products.index')
            ->with('success', "Produk '{$product->name}' (SKU: {$product->sku}) berhasil ditambahkan!");
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Request $request, int $id): View
    {
        $user = $request->user()->load('branch');
        $isMaster = $user->isMaster();

        $product = Product::withoutGlobalScopes()->with(['category', 'branch', 'productPrices', 'branchPrices'])->findOrFail($id);

        // Security: Non-master cannot edit products belonging to other branches
        if (! $isMaster && (int) $product->branch_id !== (int) $user->branch_id) {
            abort(403, 'Anda tidak memiliki otorisasi untuk mengubah produk cabang lain.');
        }

        $branches = $isMaster ? Branch::orderBy('name')->get() : collect([$product->branch]);
        $allBranches = Branch::where('is_active', true)->orderBy('name')->get();
        if ($allBranches->isEmpty()) {
            $allBranches = Branch::orderBy('name')->get();
        }
        $categories = Category::orderBy('name')->get();
        $customerGroups = CustomerGroup::orderBy('id')->get();
        $priceLevels = PriceLevel::where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        return view('backoffice.products.edit', [
            'currentUser' => $user,
            'isMaster' => $isMaster,
            'product' => $product,
            'branches' => $branches,
            'allBranches' => $allBranches,
            'categories' => $categories,
            'customerGroups' => $customerGroups,
            'priceLevels' => $priceLevels,
        ]);
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $isMaster = $user->isMaster();

        $product = Product::withoutGlobalScopes()->findOrFail($id);

        // Security: Non-master cannot edit products belonging to other branches
        if (! $isMaster && (int) $product->branch_id !== (int) $user->branch_id) {
            abort(403, 'Anda tidak memiliki otorisasi untuk mengubah produk cabang lain.');
        }

        $defaultPriceLevel = PriceLevel::where('is_default', true)->first();

        // If form submitted 'prices' array, populate 'selling_price' from default level
        if ($defaultPriceLevel && $request->filled("prices.{$defaultPriceLevel->id}")) {
            $request->merge(['selling_price' => $request->input("prices.{$defaultPriceLevel->id}")]);
        } elseif ($request->filled('selling_price') && $defaultPriceLevel) {
            $prices = $request->input('prices', []);
            if (!isset($prices[$defaultPriceLevel->id])) {
                $prices[$defaultPriceLevel->id] = $request->input('selling_price');
                $request->merge(['prices' => $prices]);
            }
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:50'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'unit' => ['nullable', 'string', 'max:50'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'prices' => ['nullable', 'array'],
            'prices.*' => ['nullable', 'numeric', 'min:0'],
            'customer_group_prices' => ['nullable', 'array'],
            'customer_group_prices.*' => ['nullable', 'numeric', 'min:0'],
            'group_prices' => ['nullable', 'array'],
            'group_prices.*' => ['nullable', 'numeric', 'min:0'],
            'branch_prices' => ['nullable', 'array'],
            'branch_prices.*' => ['nullable', 'numeric', 'min:0'],
        ];

        $validated = $request->validate($rules);

        $this->productService->updateProduct($product, $validated);

        // Sync tiered product prices (PriceLevel compatibility)
        $this->syncProductPrices($product, $request->input('prices', []));

        // Sync CustomerGroup tiered prices
        $this->syncCustomerGroupPrices($product, $request->input('customer_group_prices', $request->input('group_prices', [])));

        // Sync Branch-level price overrides
        $this->syncBranchPrices($product, $request->input('branch_prices', []));

        return redirect()
            ->route('backoffice.products.index')
            ->with('success', "Data produk '{$product->name}' berhasil diperbarui!");
    }

    /**
     * Synchronize price overrides for branches.
     */
    protected function syncBranchPrices(Product $product, array $prices): void
    {
        foreach ($prices as $branchId => $price) {
            $branchId = (int) $branchId;
            if ($price !== null && $price !== '') {
                ProductBranchPrice::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'branch_id' => $branchId,
                    ],
                    [
                        'price' => $price,
                    ]
                );
            } else {
                ProductBranchPrice::where('product_id', $product->id)
                    ->where('branch_id', $branchId)
                    ->delete();
            }
        }
    }

    /**
     * Synchronize price tiers for customer groups.
     */
    protected function syncCustomerGroupPrices(Product $product, array $prices): void
    {
        foreach ($prices as $groupId => $price) {
            $customerGroupId = (int) $groupId;
            if ($price !== null && $price !== '') {
                ProductPrice::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'customer_group_id' => $customerGroupId,
                    ],
                    [
                        'price' => $price,
                    ]
                );
            } else {
                ProductPrice::where('product_id', $product->id)
                    ->where('customer_group_id', $customerGroupId)
                    ->delete();
            }
        }
    }

    /**
     * Synchronize price tiers for a product.
     */
    protected function syncProductPrices(Product $product, array $prices): void
    {
        $defaultLevel = PriceLevel::where('is_default', true)->first();

        // Always ensure default level has a price from selling_price fallback
        if ($defaultLevel && (!isset($prices[$defaultLevel->id]) || $prices[$defaultLevel->id] === '' || $prices[$defaultLevel->id] === null)) {
            if ($product->selling_price !== null) {
                $prices[$defaultLevel->id] = $product->selling_price;
            }
        }

        foreach ($prices as $levelId => $price) {
            $priceLevelId = (int) $levelId;
            if ($price !== null && $price !== '') {
                ProductPrice::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'price_level_id' => $priceLevelId,
                    ],
                    [
                        'price' => $price,
                    ]
                );
            } else {
                // Do not delete default level price
                if ($defaultLevel && $priceLevelId === (int) $defaultLevel->id) {
                    continue;
                }

                ProductPrice::where('product_id', $product->id)
                    ->where('price_level_id', $priceLevelId)
                    ->delete();
            }
        }
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $isMaster = $user->isMaster();

        try {
            $product = Product::withoutGlobalScopes()->findOrFail($id);

            if (! $isMaster && (int) $product->branch_id !== (int) $user->branch_id) {
                abort(403, 'Anda tidak memiliki otorisasi untuk menghapus produk cabang lain.');
            }

            $name = $product->name;
            $product->delete();

            return redirect()
                ->route('backoffice.products.index')
                ->with('success', "Produk '{$name}' berhasil dihapus!");
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return redirect()
                ->route('backoffice.products.index')
                ->with('error', 'Terjadi kesalahan saat menghapus produk: ' . $e->getMessage());
        }
    }

    /**
     * Endpoint API untuk pengecekan cepat duplikasi nama / SKU produk saat input.
     */
    public function checkDuplicate(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('name', $request->input('query', '')));

        if (mb_strlen($query) < 2) {
            return response()->json(['matches' => []]);
        }

        $matches = Product::withoutGlobalScopes()
            ->with('branch:id,name,code')
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('sku', 'like', "%{$query}%");
            })
            ->limit(8)
            ->get(['id', 'branch_id', 'sku', 'name', 'unit', 'purchase_price', 'selling_price'])
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'unit' => $p->unit ?? 'PCS',
                    'branch_name' => $p->branch?->name ?? 'Pusat',
                    'purchase_price' => (float) $p->purchase_price,
                    'selling_price' => (float) $p->selling_price,
                    'edit_url' => route('backoffice.products.edit', $p->id),
                ];
            });

        return response()->json(['matches' => $matches]);
    }
}
