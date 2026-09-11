<?php

namespace App\Http\Controllers;

use App\Models\JournalHeader;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function journal(): View
    {
        $headers = JournalHeader::with([
            'journalLines.chartOfAccount',
            'user',
        ])
            ->latest('transaction_date')
            ->latest('id')
            ->get();

        return view('report.journal', compact('headers'));
    }
}