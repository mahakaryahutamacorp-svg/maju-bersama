<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    /**
     * Display a listing of branches.
     * Only superadmin can access.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->isMaster()) {
            return response()->json([
                'message' => 'Unauthorized. Only superadmin can access branches.',
            ], 403);
        }

        $branches = Branch::with(['parent', 'children'])
            ->withCount(['products', 'users'])
            ->orderBy('code')
            ->get();

        return response()->json([
            'message' => 'Branches retrieved successfully.',
            'data' => BranchResource::collection($branches),
        ]);
    }

    /**
     * Store a newly created branch.
     * Only superadmin can create branches.
     */
    public function store(StoreBranchRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Set default is_active to true if not provided
        if (!isset($validated['is_active'])) {
            $validated['is_active'] = true;
        }

        $branch = Branch::create($validated);

        return response()->json([
            'message' => 'Branch created successfully.',
            'data' => new BranchResource($branch),
        ], 201);
    }

    /**
     * Display the specified branch.
     * Only superadmin can view branch details.
     */
    public function show(Request $request, Branch $branch): JsonResponse
    {
        if (!$request->user()->isMaster()) {
            return response()->json([
                'message' => 'Unauthorized. Only superadmin can access branches.',
            ], 403);
        }

        $branch->load(['parent', 'children', 'products', 'users']);
        $branch->loadCount(['products', 'users']);

        return response()->json([
            'message' => 'Branch retrieved successfully.',
            'data' => new BranchResource($branch),
        ]);
    }

    /**
     * Update the specified branch.
     * Only superadmin can update branches.
     */
    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        $validated = $request->validated();
        $branch->update($validated);

        return response()->json([
            'message' => 'Branch updated successfully.',
            'data' => new BranchResource($branch->fresh()),
        ]);
    }

    /**
     * Remove the specified branch (soft delete).
     * Only superadmin can delete branches.
     */
    public function destroy(Request $request, Branch $branch): JsonResponse
    {
        if (!$request->user()->isMaster()) {
            return response()->json([
                'message' => 'Unauthorized. Only superadmin can delete branches.',
            ], 403);
        }

        // Check if branch has active users
        if ($branch->users()->where('is_active', true)->exists()) {
            return response()->json([
                'message' => 'Cannot delete branch. Branch has active users.',
            ], 422);
        }

        // Check if branch has products
        if ($branch->products()->exists()) {
            return response()->json([
                'message' => 'Cannot delete branch. Branch has associated products.',
            ], 422);
        }

        $branch->delete();

        return response()->json([
            'message' => 'Branch deleted successfully.',
        ]);
    }
}
