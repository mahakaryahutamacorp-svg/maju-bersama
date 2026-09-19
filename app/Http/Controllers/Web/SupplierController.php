<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    /**
     * Display a listing of suppliers.
     */
    public function index(Request $request): View
    {
        $user = $request->user()->load('branch');
        $search = $request->input('search');
        $filterBranch = $request->input('branch_id');
        $status = $request->input('status');

        $query = Supplier::with('branch')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($status !== null && $status !== '', function ($q) use ($status) {
                $q->where('is_active', (bool) $status);
            })
            ->when($user->isMaster() && $filterBranch, function ($q) use ($filterBranch) {
                $q->where('branch_id', $filterBranch);
            })
            ->latest('id');

        $suppliers = $query->paginate(15)->withQueryString();

        $branches = $user->isMaster()
            ? Branch::orderBy('name')->get(['id', 'name', 'code'])
            : collect();

        return view('backoffice.suppliers.index', [
            'currentUser'  => $user,
            'isMaster'     => $user->isMaster(),
            'suppliers'    => $suppliers,
            'branches'     => $branches,
            'search'       => $search,
            'filterBranch' => $filterBranch,
            'status'       => $status,
        ]);
    }

    /**
     * Show the form for creating a new supplier.
     */
    public function create(Request $request): View
    {
        $user = $request->user()->load('branch');

        $branches = $user->isMaster()
            ? Branch::orderBy('name')->get(['id', 'name', 'code'])
            : collect();

        return view('backoffice.suppliers.create', [
            'currentUser' => $user,
            'isMaster'    => $user->isMaster(),
            'branches'    => $branches,
        ]);
    }

    /**
     * Store a newly created supplier in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:50'],
            'address'        => ['nullable', 'string', 'max:1000'],
            'is_active'      => ['nullable', 'boolean'],
            'branch_id'      => ['nullable', 'exists:branches,id'],
        ]);

        // Automatically assign branch_id from authenticated user, or allow master override
        $branchId = $user->branch_id;
        if ($user->isMaster() && ! empty($validated['branch_id'])) {
            $branchId = (int) $validated['branch_id'];
        }

        if (! $branchId) {
            $branchId = Branch::value('id');
        }

        Supplier::create([
            'branch_id'      => $branchId,
            'name'           => $validated['name'],
            'contact_person' => $validated['contact_person'] ?? null,
            'phone'          => $validated['phone'] ?? null,
            'address'        => $validated['address'] ?? null,
            'is_active'      => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('backoffice.suppliers.index')
            ->with('success', "Supplier '{$validated['name']}' berhasil ditambahkan.");
    }

    /**
     * Show the form for editing the specified supplier.
     */
    public function edit(Request $request, Supplier $supplier): View
    {
        $user = $request->user()->load('branch');

        $branches = $user->isMaster()
            ? Branch::orderBy('name')->get(['id', 'name', 'code'])
            : collect();

        return view('backoffice.suppliers.edit', [
            'currentUser' => $user,
            'isMaster'    => $user->isMaster(),
            'supplier'    => $supplier,
            'branches'    => $branches,
        ]);
    }

    /**
     * Update the specified supplier in storage.
     */
    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:50'],
            'address'        => ['nullable', 'string', 'max:1000'],
            'is_active'      => ['nullable', 'boolean'],
            'branch_id'      => ['nullable', 'exists:branches,id'],
        ]);

        $payload = [
            'name'           => $validated['name'],
            'contact_person' => $validated['contact_person'] ?? null,
            'phone'          => $validated['phone'] ?? null,
            'address'        => $validated['address'] ?? null,
            'is_active'      => $request->boolean('is_active'),
        ];

        if ($user->isMaster() && ! empty($validated['branch_id'])) {
            $payload['branch_id'] = (int) $validated['branch_id'];
        }

        $supplier->update($payload);

        return redirect()
            ->route('backoffice.suppliers.index')
            ->with('success', "Supplier '{$supplier->name}' berhasil diperbarui.");
    }

    /**
     * Remove the specified supplier from storage.
     */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->purchaseOrders()->exists()) {
            return redirect()
                ->route('backoffice.suppliers.index')
                ->with('error', "Supplier '{$supplier->name}' tidak dapat dihapus karena telah terikat dengan Purchase Order.");
        }

        $name = $supplier->name;
        $supplier->delete();

        return redirect()
            ->route('backoffice.suppliers.index')
            ->with('success', "Supplier '{$name}' berhasil dihapus.");
    }
}
