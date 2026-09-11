<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(): View
    {
        $previewUser = User::query()->where('email', 'admin@pusat.test')->firstOrFail();

        return view('pos', [
            'products' => Product::with('category')
                ->where('branch_id', $previewUser->branch_id)
                ->orderBy('name')
                ->get(),
            'previewToken' => $previewUser->createToken('pos_preview')->plainTextToken,
        ]);
    }
}