<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\FixedAsset;
use App\Services\FixedAssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FixedAssetController extends Controller
{
    public function __construct(
        protected FixedAssetService $fixedAssetService
    ) {}

    /**
     * Tampilkan daftar harta tetap beserta nilai perolehan, akumulasi penyusutan, dan nilai buku.
     */
    public function index(Request $request): View
    {
        $user = $request->user()->load('branch');
        $isMaster = $user->isMaster();
        $selectedBranchId = $request->filled('branch_id') && $isMaster
            ? (int) $request->input('branch_id')
            : ($isMaster ? null : $user->branch_id);

        $query = FixedAsset::query()
            ->with(['branch', 'assetAccount', 'depreciationAccount', 'expenseAccount'])
            ->withSum('depreciations', 'amount')
            ->when($selectedBranchId, fn ($q) => $q->where('branch_id', $selectedBranchId))
            ->latest('purchase_date')
            ->latest('id');

        $assets = $query->get();

        $totalAcquisition = (float) $assets->sum('purchase_price');
        $totalDepreciation = (float) $assets->sum('depreciations_sum_amount');
        $totalBookValue = $totalAcquisition - $totalDepreciation;
        $activeAssetCount = $assets->where('status', 'ACTIVE')->count();

        $branches = $isMaster ? Branch::orderBy('name')->get() : collect([$user->branch]);

        return view('backoffice.fixed-assets.index', [
            'currentUser'       => $user,
            'isMaster'          => $isMaster,
            'assets'            => $assets,
            'totalAcquisition'  => $totalAcquisition,
            'totalDepreciation' => $totalDepreciation,
            'totalBookValue'    => $totalBookValue,
            'activeAssetCount'  => $activeAssetCount,
            'branches'          => $branches,
            'selectedBranchId'  => $selectedBranchId,
            'currentYearMonth'  => now()->format('Y-m'),
        ]);
    }

    /**
     * Formulir pendaftaran aset tetap baru.
     */
    public function create(Request $request): View
    {
        $user = $request->user()->load('branch');
        $isMaster = $user->isMaster();

        $branches = $isMaster ? Branch::orderBy('name')->get() : collect([$user->branch]);
        $accounts = ChartOfAccount::orderBy('code')->get();

        // Kategori akun untuk kenyamanan pemilihan dropdown
        $assetAccounts = $accounts->where('type', 'asset');
        $expenseAccounts = $accounts->where('type', 'expense');

        return view('backoffice.fixed-assets.create', [
            'currentUser'     => $user,
            'isMaster'        => $isMaster,
            'branches'        => $branches,
            'accounts'        => $accounts,
            'assetAccounts'   => $assetAccounts,
            'expenseAccounts' => $expenseAccounts,
        ]);
    }

    /**
     * Simpan data aset tetap baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'                     => 'required|string|max:255',
            'purchase_date'            => 'required|date',
            'purchase_price'           => 'required|numeric|min:0.01',
            'salvage_value'            => 'nullable|numeric|min:0|lte:purchase_price',
            'useful_life_months'       => 'required|integer|min:1',
            'asset_account_id'         => 'required|exists:chart_of_accounts,id',
            'depreciation_account_id'  => 'required|exists:chart_of_accounts,id',
            'expense_account_id'       => 'required|exists:chart_of_accounts,id',
            'branch_id'                => 'nullable|exists:branches,id',
        ], [
            'salvage_value.lte' => 'Nilai residu tidak boleh melebihi nilai perolehan (harga beli).',
            'useful_life_months.min' => 'Umur ekonomis minimal 1 bulan.',
        ]);

        $branchId = $user->isMaster() && ! empty($validated['branch_id'])
            ? (int) $validated['branch_id']
            : ($user->branch_id ?? Branch::value('id'));

        FixedAsset::create([
            'branch_id'                => $branchId,
            'name'                     => $validated['name'],
            'purchase_date'            => $validated['purchase_date'],
            'purchase_price'           => $validated['purchase_price'],
            'salvage_value'            => $validated['salvage_value'] ?? 0,
            'useful_life_months'       => $validated['useful_life_months'],
            'asset_account_id'         => $validated['asset_account_id'],
            'depreciation_account_id'  => $validated['depreciation_account_id'],
            'expense_account_id'       => $validated['expense_account_id'],
            'status'                   => 'ACTIVE',
        ]);

        return redirect()->route('backoffice.fixed-assets.index')
            ->with('success', "Aset tetap '{$validated['name']}' berhasil didaftarkan ke sistem.");
    }

    /**
     * Jalankan proses penyusutan bulanan manual via tombol.
     */
    public function runDepreciation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'year_month' => 'required|date_format:Y-m',
            'branch_id'  => 'nullable|exists:branches,id',
        ]);

        $user = $request->user();
        $branchId = $user->isMaster() && ! empty($validated['branch_id'])
            ? (int) $validated['branch_id']
            : ($user->isMaster() ? null : $user->branch_id);

        $yearMonth = $validated['year_month'];
        $result = $this->fixedAssetService->runMonthlyDepreciation($yearMonth, $branchId);

        if ($result['processed_count'] > 0) {
            $formattedAmount = number_format($result['total_amount'], 2, ',', '.');
            return redirect()->route('backoffice.fixed-assets.index')
                ->with('success', "Penyusutan periode {$yearMonth} berhasil dijalankan untuk {$result['processed_count']} aset tetap (Total: Rp {$formattedAmount}). Jurnal akuntansi seimbang otomatis dibukukan.");
        }

        return redirect()->route('backoffice.fixed-assets.index')
            ->with('info', "Penyusutan periode {$yearMonth} sudah pernah di-run sebelumnya atau tidak ada aset aktif yang memerlukan penyusutan.");
    }
}
