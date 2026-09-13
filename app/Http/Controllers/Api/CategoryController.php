<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of all categories.
     * Categories are global (shared across all branches), so all users can view them.
     */
    public function index(): JsonResponse
    {
        $categories = Category::orderBy('name')->get();

        return response()->json([
            'data' => $categories,
        ]);
    }

    /**
     * Store a newly created category.
     * Only superadmin/master can create categories (global resource).
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        if (!$request->user()->isMaster()) {
            return response()->json([
                'message' => 'Unauthorized. Only superadmin can create categories.',
            ], 403);
        }

        $category = Category::create($request->validated());

        return response()->json([
            'message' => 'Category created successfully.',
            'data' => $category,
        ], 201);
    }

    /**
     * Display the specified category.
     */
    public function show(Category $category): JsonResponse
    {
        return response()->json([
            'data' => $category,
        ]);
    }

    /**
     * Update the specified category.
     * Only superadmin/master can update categories (global resource).
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        if (!$request->user()->isMaster()) {
            return response()->json([
                'message' => 'Unauthorized. Only superadmin can update categories.',
            ], 403);
        }

        $category->update($request->validated());

        return response()->json([
            'message' => 'Category updated successfully.',
            'data' => $category,
        ]);
    }

    /**
     * Remove the specified category.
     * Only superadmin/master can delete categories (global resource).
     */
    public function destroy(Request $request, Category $category): JsonResponse
    {
        if (!$request->user()->isMaster()) {
            return response()->json([
                'message' => 'Unauthorized. Only superadmin can delete categories.',
            ], 403);
        }

        // Check if category has products
        if ($category->products()->exists()) {
            return response()->json([
                'message' => 'Cannot delete category. Category has associated products.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully.',
        ]);
    }
}
