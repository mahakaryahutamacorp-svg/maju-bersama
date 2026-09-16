<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BranchController extends Controller
{
    /**
     * Check if user is master. If not, abort with 403 Forbidden.
     */
    protected function authorizeMaster(Request $request): void
    {
        if (! $request->user()?->isMaster()) {
            abort(403, 'Akses ditolak. Fitur Manajemen Cabang hanya dapat diakses oleh Administrator Pusat / Master.');
        }
    }

    /**
     * Display a listing of branches.
     */
    public function index(Request $request): View
    {
        $this->authorizeMaster($request);

        $search = $request->input('search');

        $branches = Branch::with(['parent'])
            ->withCount(['products', 'users'])
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('backoffice.branches.index', [
            'currentUser' => $request->user(),
            'isMaster' => true,
            'branches' => $branches,
            'search' => $search,
        ]);
    }

    /**
     * Show the form for creating a new branch.
     */
    public function create(Request $request): View
    {
        $this->authorizeMaster($request);

        $parentBranches = Branch::whereNull('parent_id')->orderBy('name')->get();

        return view('backoffice.branches.create', [
            'currentUser' => $request->user(),
            'isMaster' => true,
            'parentBranches' => $parentBranches,
        ]);
    }

    /**
     * Store a newly created branch.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeMaster($request);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:branches,code'],
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:branches,id'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'timezone' => ['nullable', 'string', 'max:50'],
        ], [
            'code.required' => 'Kode cabang wajib diisi.',
            'code.unique' => 'Kode cabang sudah terdaftar.',
            'name.required' => 'Nama cabang wajib diisi.',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['timezone'] = $validated['timezone'] ?? 'Asia/Jakarta';
        $validated['is_active'] = true;

        $branch = Branch::create($validated);

        return redirect()
            ->route('backoffice.branches.index')
            ->with('success', "Cabang baru '{$branch->name}' ({$branch->code}) berhasil didaftarkan!");
    }

    /**
     * Show the form for editing the specified branch.
     */
    public function edit(Request $request, int $id): View
    {
        $this->authorizeMaster($request);

        $branch = Branch::findOrFail($id);
        $parentBranches = Branch::where('id', '!=', $branch->id)
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return view('backoffice.branches.edit', [
            'currentUser' => $request->user(),
            'isMaster' => true,
            'branch' => $branch,
            'parentBranches' => $parentBranches,
        ]);
    }

    /**
     * Update the specified branch.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->authorizeMaster($request);

        $branch = Branch::findOrFail($id);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('branches', 'code')->ignore($branch->id)],
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:branches,id', Rule::notIn([$branch->id])],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'code.required' => 'Kode cabang wajib diisi.',
            'code.unique' => 'Kode cabang sudah digunakan.',
            'name.required' => 'Nama cabang wajib diisi.',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = $request->has('is_active') ? (bool) $request->input('is_active') : $branch->is_active;

        $branch->update($validated);

        return redirect()
            ->route('backoffice.branches.index')
            ->with('success', "Data cabang '{$branch->name}' berhasil diperbarui!");
    }

    /**
     * Remove the specified branch.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->authorizeMaster($request);

        $branch = Branch::withCount(['products', 'users'])->findOrFail($id);

        if ($branch->products_count > 0 || $branch->users_count > 0) {
            return redirect()
                ->route('backoffice.branches.index')
                ->with('error', "Cabang '{$branch->name}' tidak dapat dihapus karena masih memiliki {$branch->products_count} produk dan {$branch->users_count} staf terdaftar.");
        }

        $name = $branch->name;
        $branch->delete();

        return redirect()
            ->route('backoffice.branches.index')
            ->with('success', "Cabang '{$name}' berhasil dihapus!");
    }
}
