<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'products' => Product::with(['category', 'branch'])
                ->when(! $request->user()->isMaster(), fn ($query) => $query->where('branch_id', $request->user()->branch_id))
                ->get(),
        ]);
    }
}