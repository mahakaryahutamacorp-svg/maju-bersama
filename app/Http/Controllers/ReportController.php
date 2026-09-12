<?php

namespace App\Http\Controllers;

use App\Models\JournalHeader;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function journal(Request $request): View
    {
        $user = $request->user();

        $headers = JournalHeader::with([
            'journalLines.chartOfAccount',
            'user',
        ])
            ->when(! $user->isMaster(), fn ($query) => $query->where('branch_id', $user->branch_id))
            ->latest('transaction_date')
            ->latest('id')
            ->get();

        return view('report.journal', compact('headers'));
    }
}