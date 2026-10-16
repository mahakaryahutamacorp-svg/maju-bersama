<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use Illuminate\Http\Request;

class PreviewController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->isMaster()) {
            abort(403, 'Akses terbatas: Halaman Pratinjau Sistem hanya dapat diakses oleh akun Master Administrator.');
        }

        $branches = Branch::query()
            ->with(['users' => fn ($query) => $query->orderBy('id')])
            ->orderBy('id')
            ->get();

        $chartOfAccounts = ChartOfAccount::all();

        return view('preview', compact('branches', 'chartOfAccounts'));
    }
}
