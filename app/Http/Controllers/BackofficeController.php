<?php

namespace App\Http\Controllers;

use App\Models\JournalHeader;
use App\Models\Branch;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BackofficeController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isMaster = $user->isMaster();
        $branchId = $user->branch_id;
        $branchIds = $isMaster
            ? Branch::query()->pluck('id')
            : collect([$branchId]);

        $branchSummaries = Branch::query()
            ->withCount('products')
            ->withSum('products', 'stock')
            ->with('parent')
            ->when(! $isMaster, fn ($query) => $query->whereKey($branchId))
            ->orderBy('parent_id')
            ->orderBy('name')
            ->get();

        $recentJournals = JournalHeader::query()
            ->with(['branch', 'user'])
            ->whereIn('branch_id', $branchIds)
            ->latest('transaction_date')
            ->latest('id')
            ->limit(12)
            ->get();

        return view('backoffice', [
            'currentUser' => $user->load('branch'),
            'isMaster' => $isMaster,
            'branchSummaries' => $branchSummaries,
            'recentJournals' => $recentJournals,
            'stats' => [
                'branches' => $branchSummaries->count(),
                'products' => Product::whereIn('branch_id', $branchIds)->count(),
                'stock' => Product::whereIn('branch_id', $branchIds)->sum('stock'),
                'journals' => JournalHeader::whereIn('branch_id', $branchIds)->count(),
                'sales' => JournalHeader::whereIn('branch_id', $branchIds)
                    ->where('description', 'POS Sale')
                    ->count(),
            ],
        ]);
    }
}