<?php

namespace App\Http\Controllers;

use App\Models\JournalHeader;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function journal(Request $request): View
    {
        $headers = JournalHeader::with([
            'journalLines.chartOfAccount',
            'user',
        ])
            ->where('branch_id', $request->user()->branch_id)
            ->latest('transaction_date')
            ->latest('id')
            ->get();

        return view('report.journal', compact('headers'));
    }
}