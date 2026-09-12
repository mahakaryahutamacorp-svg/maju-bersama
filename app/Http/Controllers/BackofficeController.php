<?php

namespace App\Http\Controllers;

use App\Models\JournalHeader;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BackofficeController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = $request->user()->branch_id;

        return view('backoffice', [
            'currentUser' => $request->user()->load('branch'),
            'stats' => [
                'products' => Product::where('branch_id', $branchId)->count(),
                'stock' => Product::where('branch_id', $branchId)->sum('stock'),
                'journals' => JournalHeader::where('branch_id', $branchId)->count(),
                'sales' => JournalHeader::where('branch_id', $branchId)
                    ->where('description', 'POS Sale')
                    ->count(),
            ],
        ]);
    }
}