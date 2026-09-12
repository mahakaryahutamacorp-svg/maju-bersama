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
        $user = $request->user();

        return view('inventory', [
            'products' => Product::with(['category', 'branch'])
                ->when(! $user->isMaster(), fn ($query) => $query->where('branch_id', $user->branch_id))
                ->orderBy('name')
                ->get(),
        ]);
    }
}