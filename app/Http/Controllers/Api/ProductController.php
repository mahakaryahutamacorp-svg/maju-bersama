<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Services\MasterCatalogService;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    protected ProductService $productService;

    protected MasterCatalogService $masterCatalog;

    public function __construct(ProductService $productService, MasterCatalogService $masterCatalog)
    {
        $this->productService = $productService;
        $this->masterCatalog = $masterCatalog;
    }

    /**
     * Display a listing of products.
     * Branch isolation is automatically applied by HasBranchScope trait.
     * Supports customer_id parameter to return group-adjusted prices for POS.
     */
    public function index(Request $request): JsonResponse
    {
        $customerId = $request->input('customer_id');
        $customerGroupId = $request->input('customer_group_id');

        if ($customerId && ! $customerGroupId) {
            $customer = Customer::find($customerId);
            $customerGroupId = $customer?->customer_group_id;
        }

        $branchId = $request->user()?->branch_id ?? $request->input('branch_id');
        $search = $request->input('search') ?? $request->input('q');

        $query = Product::with(['category', 'branch', 'productPrices']);

        $requestedBranchId = $request->input('branch_id');
        if ($requestedBranchId) {
            $query->where('branch_id', $requestedBranchId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->get();

        $skus = $products->pluck('sku')->filter()->unique()->values()->all();
        $otherStockMap = [];
        if (! empty($skus)) {
            $otherBranchesQuery = Product::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('stock', '>', 0)
                ->whereIn('sku', $skus)
                ->with('branch:id,name');

            if ($branchId) {
                $otherBranchesQuery->where('branch_id', '!=', $branchId);
            }

            $otherProducts = $otherBranchesQuery->get(['id', 'branch_id', 'sku', 'stock']);

            foreach ($otherProducts as $op) {
                $branchName = $op->branch?->name ?? 'Cabang #'.$op->branch_id;
                $otherStockMap[$op->sku][] = [
                    'branch_name' => $branchName,
                    'stock' => (int) $op->stock,
                ];
            }
        }

        $hideTax = (bool) $request->user()?->isCashier();

        $products = $products->map(function (Product $product) use ($customerGroupId, $otherStockMap, $hideTax) {
            $basePrice = (float) $product->selling_price;
            $product->base_price = $basePrice;
            $product->original_selling_price = $basePrice;

            if ($customerGroupId !== null) {
                $effectivePrice = (float) $product->getPriceForGroup((int) $customerGroupId);
                $product->selling_price = $effectivePrice;
                $product->price = $effectivePrice;
            } else {
                $product->selling_price = $basePrice;
                $product->price = $basePrice;
            }

            $product->other_branch_stock = $otherStockMap[$product->sku] ?? [];

            if ($hideTax) {
                $product->makeHidden(['is_taxable']);
            }

            return $product;
        });

        return response()->json([
            'data' => $products,
        ]);
    }

    /**
     * Store a newly created product.
     * Branch ID is automatically set from authenticated user.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $branchId = (int) $request->user()->branch_id;
        if (! $this->masterCatalog->canManageMasterFieldsIn($request->user(), $branchId)) {
            throw ValidationException::withMessages([
                'branch_id' => 'Produk di grup cabang ini hanya bisa ditambahkan dari cabang acuan.',
            ]);
        }

        $product = DB::transaction(function () use ($request, $branchId): Product {
            $product = $this->productService->createProduct($request->validated(), $branchId);
            $this->masterCatalog->syncToGroup($product, restoreArchived: true);

            return $product;
        });

        return response()->json([
            'message' => 'Product created successfully.',
            'data' => $product->load(['category', 'branch']),
        ], 201);
    }

    /**
     * Display the specified product.
     * Branch isolation is automatically applied by HasBranchScope trait.
     * Supports customer_id parameter to return group-adjusted prices for POS.
     */
    public function show(Request $request, Product $product): JsonResponse
    {
        $product->load(['category', 'branch', 'productPrices']);

        $branchId = $request->user()?->branch_id ?? $request->input('branch_id');
        $basePrice = (float) $product->selling_price;
        $product->base_price = $basePrice;
        $product->original_selling_price = $basePrice;

        $customerId = $request->input('customer_id');
        $customerGroupId = $request->input('customer_group_id');
        if ($customerId && ! $customerGroupId) {
            $customer = Customer::find($customerId);
            $customerGroupId = $customer?->customer_group_id;
        }

        if ($customerGroupId) {
            $effectivePrice = (float) $product->getPriceForGroup((int) $customerGroupId);
            $product->selling_price = $effectivePrice;
            $product->price = $effectivePrice;
        } else {
            $product->selling_price = $basePrice;
            $product->price = $basePrice;
        }

        $otherBranches = Product::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('stock', '>', 0)
            ->where('sku', $product->sku)
            ->when($branchId, fn ($q) => $q->where('branch_id', '!=', $branchId))
            ->with('branch:id,name')
            ->get(['id', 'branch_id', 'sku', 'stock']);

        $product->other_branch_stock = $otherBranches->map(fn ($op) => [
            'branch_name' => $op->branch?->name ?? 'Cabang #'.$op->branch_id,
            'stock' => (int) $op->stock,
        ])->values()->all();

        return response()->json([
            'data' => $product,
        ]);
    }

    /**
     * Scan / lookup product by barcode with customer group pricing support.
     */
    public function byBarcode(Request $request, string $barcode): JsonResponse
    {
        $product = Product::with(['category', 'branch', 'productPrices'])
            ->where('sku', $barcode)
            ->firstOrFail();

        $branchId = $request->user()?->branch_id ?? $request->input('branch_id');
        $basePrice = (float) $product->selling_price;
        $product->base_price = $basePrice;
        $product->original_selling_price = $basePrice;

        $customerId = $request->input('customer_id');
        $customerGroupId = $request->input('customer_group_id');
        if ($customerId && ! $customerGroupId) {
            $customer = Customer::find($customerId);
            $customerGroupId = $customer?->customer_group_id;
        }

        if ($customerGroupId) {
            $effectivePrice = (float) $product->getPriceForGroup((int) $customerGroupId);
            $product->selling_price = $effectivePrice;
            $product->price = $effectivePrice;
        } else {
            $product->selling_price = $basePrice;
            $product->price = $basePrice;
        }

        $otherBranches = Product::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('stock', '>', 0)
            ->where('sku', $product->sku)
            ->when($branchId, fn ($q) => $q->where('branch_id', '!=', $branchId))
            ->with('branch:id,name')
            ->get(['id', 'branch_id', 'sku', 'stock']);

        $product->other_branch_stock = $otherBranches->map(fn ($op) => [
            'branch_name' => $op->branch?->name ?? 'Cabang #'.$op->branch_id,
            'stock' => (int) $op->stock,
        ])->values()->all();

        return response()->json([
            'data' => $product,
        ]);
    }

    /**
     * Update the specified product.
     * Branch isolation is automatically applied by HasBranchScope trait.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $validated = $request->validated();

        $updatedProduct = DB::transaction(function () use ($request, $product, $validated): Product {
            $previousSku = (string) $product->sku;
            if (isset($validated['sku'])) {
                $this->masterCatalog->assertSkuAvailable($product, (string) $validated['sku']);
            }

            $updatedProduct = $this->productService->updateProduct($product, $validated);

            if ($this->masterCatalog->canManageMasterFieldsIn($request->user(), (int) $updatedProduct->branch_id)) {
                $this->masterCatalog->syncToGroup($updatedProduct, $previousSku);
            }

            return $updatedProduct;
        });

        return response()->json([
            'message' => 'Product updated successfully.',
            'data' => $updatedProduct->load(['category', 'branch']),
        ]);
    }

    /**
     * Remove the specified product.
     * Branch isolation is automatically applied by HasBranchScope trait.
     */
    public function destroy(Product $product): JsonResponse
    {
        if ($this->masterCatalog->followsSource((int) $product->branch_id)) {
            throw ValidationException::withMessages([
                'product' => 'Produk di grup cabang ini hanya bisa dihapus dari cabang acuan.',
            ]);
        }

        DB::transaction(function () use ($product): void {
            $this->productService->deleteProduct($product, (bool) request()->user()?->isMaster());

            if ($this->masterCatalog->isSourceBranch((int) $product->branch_id)) {
                $this->masterCatalog->hideInGroup($product);
            }
        });

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }
}
