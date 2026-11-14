<?php

namespace App\Http\Controllers;

use App\Models\StockTransfer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockTransferPrintController extends Controller
{
    /**
     * Tampilkan Surat Jalan / Delivery Note fisik siap cetak untuk transfer stok.
     */
    public function print(string $reference, Request $request): View
    {
        $stockTransfer = StockTransfer::where('reference_number', $reference)
            ->orWhere('id', $reference)
            ->with([
                'items.product' => function ($query) {
                    $query->withoutGlobalScopes();
                },
                'fromBranch',
                'toBranch',
                'user',
            ])
            ->firstOrFail();

        // Admin cabang hanya boleh melihat transfer yang melibatkan cabangnya; selain itu dianggap tidak ada.
        $user = $request->user();
        $involvedBranchIds = [(int) $stockTransfer->source_branch_id, (int) $stockTransfer->destination_branch_id];
        abort_unless(
            $user->isMaster() || ($user->branch_id !== null && in_array((int) $user->branch_id, $involvedBranchIds, true)),
            404
        );

        return view('backoffice.stock-transfers.print', compact('stockTransfer'));
    }
}
