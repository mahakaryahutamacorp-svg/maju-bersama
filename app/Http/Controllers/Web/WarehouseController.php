<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    /**
     * Display a listing of warehouses.
     * Master: sees all warehouses across all branches.
     * Branch Admin: sees only warehouses for their own branch.
     */
    public function index(Request $request): View
    {
        $user = $request->user()->load('branch');
        $search = $request->input('search');
        $filterBranch = $request->input('branch_id');

        // HasBranchScope on Warehouse handles tenant filtering automatically
        $query = Warehouse::with('branch')
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            }))
            ->when($user->isMaster() && $filterBranch, fn ($q) => $q->where('branch_id', $filterBranch))
            ->orderBy('branch_id')
            ->orderBy('name');

        $warehouses = $query->paginate(15)->withQueryString();

        $branches = $user->isMaster()
            ? Branch::orderBy('name')->get(['id', 'name', 'code'])
            : collect();

        return view('backoffice.warehouses.index', [
            'currentUser'  => $user,
            'isMaster'     => $user->isMaster(),
            'warehouses'   => $warehouses,
            'branches'     => $branches,
            'search'       => $search,
            'filterBranch' => $filterBranch,
        ]);
    }

    /**
     * Show the form for creating a new warehouse.
     */
    public function create(Request $request): View
    {
        $user = $request->user()->load('branch');

        // Master can pick any branch; Branch Admin can only use their own branch
        $branches = $user->isMaster()
            ? Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code'])
            : collect([$user->branch]);

        return view('backoffice.warehouses.create', [
            'currentUser' => $user,
            'isMaster'    => $user->isMaster(),
            'branches'    => $branches,
        ]);
    }

    /**
     * Store a newly created warehouse.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Branch admin may only create warehouses for their own branch
        $allowedBranchId = $user->isMaster()
            ? $request->input('branch_id')
            : $user->branch_id;

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'code'      => [
                'required', 'string', 'max:20',
                Rule::unique('warehouses', 'code')->where('branch_id', $allowedBranchId),
            ],
            'name'      => ['required', 'string', 'max:100'],
        ], [
            'branch_id.required' => 'Cabang wajib dipilih.',
            'code.required'      => 'Kode gudang wajib diisi.',
            'code.unique'        => 'Kode gudang ini sudah digunakan pada cabang tersebut.',
            'name.required'      => 'Nama gudang wajib diisi.',
        ]);

        // Enforce branch scope for non-master users
        $validated['branch_id'] = $allowedBranchId;
        $validated['code']      = strtoupper(trim($validated['code']));
        $validated['is_active'] = true;

        $warehouse = Warehouse::create($validated);

        return redirect()
            ->route('backoffice.warehouses.index')
            ->with('success', "Gudang '{$warehouse->name}' ({$warehouse->code}) berhasil ditambahkan!");
    }

    /**
     * Show the form for editing the specified warehouse.
     */
    public function edit(Request $request, int $id): View
    {
        $user = $request->user()->load('branch');

        // withoutGlobalScopes allows master to reach any warehouse; non-master is still guarded by HasBranchScope
        $warehouse = $user->isMaster()
            ? Warehouse::with('branch')->findOrFail($id)
            : Warehouse::with('branch')->findOrFail($id); // scope auto-applied

        $branches = $user->isMaster()
            ? Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code'])
            : collect([$user->branch]);

        return view('backoffice.warehouses.edit', [
            'currentUser' => $user,
            'isMaster'    => $user->isMaster(),
            'warehouse'   => $warehouse,
            'branches'    => $branches,
        ]);
    }

    /**
     * Update the specified warehouse.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();

        $warehouse = $user->isMaster()
            ? Warehouse::findOrFail($id)
            : Warehouse::findOrFail($id); // HasBranchScope auto-guards

        $allowedBranchId = $user->isMaster()
            ? $request->input('branch_id', $warehouse->branch_id)
            : $warehouse->branch_id;

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'code'      => [
                'required', 'string', 'max:20',
                Rule::unique('warehouses', 'code')
                    ->where('branch_id', $allowedBranchId)
                    ->ignore($warehouse->id),
            ],
            'name'      => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'branch_id.required' => 'Cabang wajib dipilih.',
            'code.required'      => 'Kode gudang wajib diisi.',
            'code.unique'        => 'Kode gudang ini sudah digunakan pada cabang tersebut.',
            'name.required'      => 'Nama gudang wajib diisi.',
        ]);

        $validated['branch_id'] = $allowedBranchId;
        $validated['code']      = strtoupper(trim($validated['code']));
        $validated['is_active'] = $request->has('is_active')
            ? (bool) $request->input('is_active')
            : $warehouse->is_active;

        $warehouse->update($validated);

        return redirect()
            ->route('backoffice.warehouses.index')
            ->with('success', "Gudang '{$warehouse->name}' berhasil diperbarui!");
    }

    /**
     * Remove the specified warehouse.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();

        $warehouse = $user->isMaster()
            ? Warehouse::findOrFail($id)
            : Warehouse::findOrFail($id); // HasBranchScope auto-guards

        $name = $warehouse->name;
        $warehouse->delete();

        return redirect()
            ->route('backoffice.warehouses.index')
            ->with('success', "Gudang '{$name}' berhasil dihapus!");
    }
}
