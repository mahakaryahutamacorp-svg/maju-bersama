<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * Display a listing of products.
     * Branch isolation is automatically applied by HasBranchScope trait.
     * Supports customer_id parameter to return group-adjusted prices for POS.
     */
    public function index(Request $request): JsonResponse
    {
        $customerId = $request->input('customer_id');
        $customerGroupId = null;

        if ($customerId) {
            $customer = Customer::find($customerId);
            $customerGroupId = $customer?->customer_group_id;
        }

        $search = $request->input('search') ?? $request->input('q') ?? $request->input('barcode');

        $query = Product::with(['category', 'branch', 'productPrices']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->get()->map(function (Product $product) use ($customerGroupId) {
            $basePrice = (float) $product->selling_price;
            $product->base_price = $basePrice;
            $product->original_selling_price = $basePrice;

            if ($customerGroupId !== null) {
                $effectivePrice = (float) $product->getPriceForGroup($customerGroupId);
                $product->selling_price = $effectivePrice;
                $product->price = $effectivePrice;
            } else {
                $product->price = $basePrice;
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
        $product = $this->productService->createProduct(
            $request->validated(),
            $request->user()->branch_id
        );

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

        $basePrice = (float) $product->selling_price;
        $product->base_price = $basePrice;
        $product->original_selling_price = $basePrice;

        $customerId = $request->input('customer_id');
        if ($customerId) {
            $customer = Customer::find($customerId);
            if ($customer && $customer->customer_group_id) {
                $effectivePrice = (float) $product->getPriceForGroup($customer->customer_group_id);
                $product->selling_price = $effectivePrice;
                $product->price = $effectivePrice;
            } else {
                $product->price = $basePrice;
            }
        } else {
            $product->price = $basePrice;
        }

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

        $basePrice = (float) $product->selling_price;
        $product->base_price = $basePrice;
        $product->original_selling_price = $basePrice;

        $customerId = $request->input('customer_id');
        if ($customerId) {
            $customer = Customer::find($customerId);
            if ($customer && $customer->customer_group_id) {
                $effectivePrice = (float) $product->getPriceForGroup($customer->customer_group_id);
                $product->selling_price = $effectivePrice;
                $product->price = $effectivePrice;
            } else {
                $product->price = $basePrice;
            }
        } else {
            $product->price = $basePrice;
        }

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
        $updatedProduct = $this->productService->updateProduct(
            $product,
            $request->validated()
        );

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
        $this->productService->deleteProduct($product);

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }
}