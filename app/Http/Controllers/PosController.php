<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('pos', [
            'products' => Product::with('category')
                ->orderBy('name')
                ->get(),
            'previewToken' => $user->createToken('pos_preview')->plainTextToken,
            'currentUser' => $user->load('branch'),
        ]);
    }
}