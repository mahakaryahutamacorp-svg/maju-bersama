<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    /**
     * Tampilkan daftar pelanggan.
     */
    public function index(Request $request): View
    {
        $user = $request->user()->load('branch');
        $isMaster = $user->isMaster();
        $search = $request->input('search');
        $selectedGroupId = $request->input('customer_group_id');
        $selectedBranchId = $isMaster ? $request->input('branch_id') : $user->branch_id;

        $query = Customer::query()
            ->with(['customerGroup', 'branch'])
            ->when(! $isMaster, fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($isMaster && $selectedBranchId, fn ($q) => $q->where('branch_id', $selectedBranchId))
            ->when($selectedGroupId, fn ($q) => $q->where('customer_group_id', $selectedGroupId))
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest('id');

        $customers = $query->paginate(15)->withQueryString();
        $customerGroups = CustomerGroup::orderBy('name')->get();
        $branches = $isMaster ? Branch::orderBy('name')->get() : collect([$user->branch]);

        return view('backoffice.customers.index', [
            'currentUser'      => $user,
            'isMaster'         => $isMaster,
            'customers'        => $customers,
            'customerGroups'   => $customerGroups,
            'branches'         => $branches,
            'search'           => $search,
            'selectedGroupId'  => $selectedGroupId,
            'selectedBranchId' => $selectedBranchId,
        ]);
    }

    /**
     * Formulir pendaftaran pelanggan baru.
     */
    public function create(Request $request): View
    {
        $user = $request->user()->load('branch');
        $isMaster = $user->isMaster();

        $customerGroups = CustomerGroup::orderBy('name')->get();
        $branches = $isMaster ? Branch::orderBy('name')->get() : collect([$user->branch]);

        return view('backoffice.customers.create', [
            'currentUser'    => $user,
            'isMaster'       => $isMaster,
            'customerGroups' => $customerGroups,
            'branches'       => $branches,
        ]);
    }

    /**
     * Simpan data pelanggan baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isMaster = $user->isMaster();

        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'phone'             => ['nullable', 'string', 'max:50'],
            'email'             => ['nullable', 'email', 'max:255'],
            'address'           => ['nullable', 'string', 'max:500'],
            'customer_group_id' => ['nullable', 'integer', 'exists:customer_groups,id'],
            'branch_id'         => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $branchId = $isMaster && ! empty($validated['branch_id'])
            ? (int) $validated['branch_id']
            : ($user->branch_id ?: Branch::value('id'));

        $customer = Customer::create([
            'branch_id'         => $branchId,
            'name'              => $validated['name'],
            'phone'             => $validated['phone'] ?? null,
            'email'             => $validated['email'] ?? null,
            'address'           => $validated['address'] ?? null,
            'customer_group_id' => $validated['customer_group_id'] ?? null,
        ]);

        return redirect()
            ->route('backoffice.customers.index')
            ->with('success', "Pelanggan '{$customer->name}' berhasil didaftarkan!");
    }

    /**
     * Formulir ubah data pelanggan.
     */
    public function edit(Request $request, int $id): View
    {
        $user = $request->user()->load('branch');
        $isMaster = $user->isMaster();

        $customer = Customer::withoutGlobalScopes()->with(['customerGroup', 'branch'])->findOrFail($id);

        if (! $isMaster && (int) $customer->branch_id !== (int) $user->branch_id) {
            abort(403, 'Akses ditolak untuk data pelanggan cabang lain.');
        }

        $customerGroups = CustomerGroup::orderBy('name')->get();
        $branches = $isMaster ? Branch::orderBy('name')->get() : collect([$customer->branch]);

        return view('backoffice.customers.edit', [
            'currentUser'    => $user,
            'isMaster'       => $isMaster,
            'customer'       => $customer,
            'customerGroups' => $customerGroups,
            'branches'       => $branches,
        ]);
    }

    /**
     * Perbarui data pelanggan.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $isMaster = $user->isMaster();

        $customer = Customer::withoutGlobalScopes()->findOrFail($id);

        if (! $isMaster && (int) $customer->branch_id !== (int) $user->branch_id) {
            abort(403, 'Akses ditolak untuk mengubah pelanggan cabang lain.');
        }

        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'phone'             => ['nullable', 'string', 'max:50'],
            'email'             => ['nullable', 'email', 'max:255'],
            'address'           => ['nullable', 'string', 'max:500'],
            'customer_group_id' => ['nullable', 'integer', 'exists:customer_groups,id'],
            'branch_id'         => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        if ($isMaster && ! empty($validated['branch_id'])) {
            $customer->branch_id = (int) $validated['branch_id'];
        }

        $customer->update([
            'name'              => $validated['name'],
            'phone'             => $validated['phone'] ?? null,
            'email'             => $validated['email'] ?? null,
            'address'           => $validated['address'] ?? null,
            'customer_group_id' => $validated['customer_group_id'] ?? null,
        ]);

        return redirect()
            ->route('backoffice.customers.index')
            ->with('success', "Data pelanggan '{$customer->name}' berhasil diperbarui!");
    }

    /**
     * Hapus data pelanggan.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $isMaster = $user->isMaster();

        $customer = Customer::withoutGlobalScopes()->findOrFail($id);

        if (! $isMaster && (int) $customer->branch_id !== (int) $user->branch_id) {
            abort(403, 'Akses ditolak.');
        }

        $name = $customer->name;
        $customer->delete();

        return redirect()
            ->route('backoffice.customers.index')
            ->with('success', "Pelanggan '{$name}' berhasil dihapus!");
    }
}
