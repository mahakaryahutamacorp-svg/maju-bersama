<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        return view('inventory', [
            'products' => Product::with(['category', 'branch'])
                ->where('branch_id', $request->user()->branch_id)
                ->orderBy('name')
                ->get(),
        ]);
    }
}