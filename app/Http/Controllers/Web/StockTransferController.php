<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockTransfer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockTransferController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isMaster = $user->isMaster();

        $sourceBranches = Branch::query()
            ->when(! $isMaster, fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get();

        $destinationBranches = Branch::query()
            ->when(! $isMaster, fn ($query) => $query->whereKeyNot($user->branch_id))
            ->orderBy('name')
            ->get();

        // Stock levels come from the inventories table, which holds exactly one row
        // per branch/product pair.
        $stockLevels = Inventory::withoutGlobalScopes()
            ->with(['product:id,branch_id,sku,name,purchase_price'])
            ->when(! $isMaster, fn ($query) => $query->where('branch_id', $user->branch_id))
            ->where('quantity', '>', 0)
            ->get()
            ->filter(fn (Inventory $inventory) => $inventory->product !== null)
            ->map(fn (Inventory $inventory) => [
                'branch_id' => (int) $inventory->branch_id,
                'product_id' => (int) $inventory->product_id,
                'sku' => $inventory->product->sku,
                'name' => $inventory->product->name,
                'quantity' => (int) $inventory->quantity,
            ])
            ->values();

        $transfers = StockTransfer::with(['sourceBranch', 'destinationBranch', 'items'])
            ->when(! $isMaster, function ($query) use ($user) {
                $query->where(function ($scoped) use ($user) {
                    $scoped->where('source_branch_id', $user->branch_id)
                        ->orWhere('destination_branch_id', $user->branch_id);
                });
            })
            ->latest('transfer_date')
            ->latest('id')
            ->limit(25)
            ->get();

        return view('stock-transfer', [
            'currentUser' => $user->load('branch'),
            'isMaster' => $isMaster,
            'sourceBranches' => $sourceBranches,
            'destinationBranches' => $destinationBranches,
            'stockLevels' => $stockLevels,
            'transfers' => $transfers,
            'apiToken' => $user->createToken('stock_transfer_ui')->plainTextToken,
        ]);
    }
}
