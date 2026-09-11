<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PreviewController extends Controller
{
    public function index()
    {
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
