<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductService;
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
            ->with(['category', 'branch'])
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
        $categories = Category::orderBy('name')->get();

        return view('backoffice.products.create', [
            'currentUser' => $user,
            'isMaster' => $isMaster,
            'branches' => $branches,
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isMaster = $user->isMaster();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:50'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
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

        $product = Product::withoutGlobalScopes()->with(['category', 'branch'])->findOrFail($id);

        // Security: Non-master cannot edit products belonging to other branches
        if (! $isMaster && (int) $product->branch_id !== (int) $user->branch_id) {
            abort(403, 'Anda tidak memiliki otorisasi untuk mengubah produk cabang lain.');
        }

        $branches = $isMaster ? Branch::orderBy('name')->get() : collect([$product->branch]);
        $categories = Category::orderBy('name')->get();

        return view('backoffice.products.edit', [
            'currentUser' => $user,
            'isMaster' => $isMaster,
            'product' => $product,
            'branches' => $branches,
            'categories' => $categories,
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

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:50'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
        ];

        $validated = $request->validate($rules);

        $this->productService->updateProduct($product, $validated);

        return redirect()
            ->route('backoffice.products.index')
            ->with('success', "Data produk '{$product->name}' berhasil diperbarui!");
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $isMaster = $user->isMaster();

        $product = Product::withoutGlobalScopes()->findOrFail($id);

        if (! $isMaster && (int) $product->branch_id !== (int) $user->branch_id) {
            abort(403, 'Anda tidak memiliki otorisasi untuk menghapus produk cabang lain.');
        }

        $name = $product->name;
        $product->delete();

        return redirect()
            ->route('backoffice.products.index')
            ->with('success', "Produk '{$name}' berhasil dihapus!");
    }
}
