<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(): View
    {
        return view('inventory', [
            'products' => Product::with(['category', 'branch'])
                ->orderBy('name')
                ->get(),
        ]);
    }
}