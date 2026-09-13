<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
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
     */
    public function index(Request $request): JsonResponse
    {
        $products = Product::with(['category', 'branch'])->get();

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
     */
    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'data' => $product->load(['category', 'branch']),
        ]);
    }

    /**
     * Update the specified product.
     * Branch isolation is automatically applied by HasBranchScope trait.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product = $this->productService->updateProduct($product, $request->validated());

        return response()->json([
            'message' => 'Product updated successfully.',
            'data' => $product->load(['category', 'branch']),
        ]);
    }

    /**
     * Remove the specified product.
     * Branch isolation is automatically applied by HasBranchScope trait.
     */
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }
}