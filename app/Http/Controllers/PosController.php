<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosController extends Controller
{
    /**
     * Display the POS cashier screen.
     */
    public function index(Request $request): View
    {
        $user = $request->user()->load('branch');

        $products = Product::with('category')
            ->orderBy('name')
            ->get();

        $categories = Category::orderBy('name')->get();

        $token = $user->createToken('pos_preview')->plainTextToken;

        $viewName = view()->exists('pos.index') ? 'pos.index' : 'pos';

        return view($viewName, [
            'products' => $products,
            'categories' => $categories,
            'previewToken' => $token,
            'currentUser' => $user,
        ]);
    }

    /**
     * Display thermal receipt for a completed sale.
     */
    public function receipt(Request $request, string $receiptNumber): View
    {
        $sale = Sale::with(['branch', 'creator', 'items.product'])
            ->where('receipt_number', $receiptNumber)
            ->firstOrFail();

        $cashTendered = $request->query('cash');
        $changeDue = $request->query('change');

        return view('pos.receipt', [
            'sale' => $sale,
            'cashTendered' => $cashTendered ? (float) $cashTendered : null,
            'changeDue' => $changeDue ? (float) $changeDue : null,
        ]);
    }
}