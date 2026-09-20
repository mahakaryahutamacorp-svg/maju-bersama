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

        return view('backoffice.stock-transfers.print', compact('stockTransfer'));
    }
}
