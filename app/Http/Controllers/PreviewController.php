<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PreviewController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->isMaster()) {
            abort(403, 'Akses terbatas: Halaman Pratinjau Sistem hanya dapat diakses oleh akun Master Administrator.');
        }

        $branches = DB::table('branches')
            ->get()
            ->map(function ($branch) {
                $branch->users = User::where('branch_id', $branch->id)->get();

                return $branch;
            });

        $chartOfAccounts = ChartOfAccount::all();

        return view('preview', compact('branches', 'chartOfAccounts'));
    }
}
