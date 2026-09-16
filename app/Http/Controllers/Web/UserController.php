<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Display a listing of staff and cashiers.
     */
    public function index(Request $request): View
    {
        $currentUser = $request->user()->load('branch');
        $isMaster = $currentUser->isMaster();

        $branchId = $isMaster ? $request->input('branch_id') : $currentUser->branch_id;
        $role = $request->input('role');
        $search = $request->input('search');

        $query = User::with('branch')
            ->when(! $isMaster, fn ($q) => $q->where('branch_id', $currentUser->branch_id))
            ->when($isMaster && $branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($role, fn ($q) => $q->where('role', $role))
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name');

        $users = $query->paginate(15)->withQueryString();

        $branches = $isMaster ? Branch::orderBy('name')->get() : collect([$currentUser->branch]);

        return view('backoffice.users.index', [
            'currentUser' => $currentUser,
            'isMaster' => $isMaster,
            'users' => $users,
            'branches' => $branches,
            'selectedBranchId' => $branchId,
            'selectedRole' => $role,
            'search' => $search,
        ]);
    }

    /**
     * Show the form for creating a new staff/cashier.
     */
    public function create(Request $request): View
    {
        $currentUser = $request->user()->load('branch');
        $isMaster = $currentUser->isMaster();

        $branches = $isMaster ? Branch::orderBy('name')->get() : collect([$currentUser->branch]);

        return view('backoffice.users.create', [
            'currentUser' => $currentUser,
            'isMaster' => $isMaster,
            'branches' => $branches,
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentUser = $request->user();
        $isMaster = $currentUser->isMaster();

        // Branch admin cannot set role to master or select other branches
        $allowedRoles = $isMaster ? ['master', 'admin', 'cashier'] : ['admin', 'cashier'];

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'string', Rule::in($allowedRoles)],
        ];

        if ($isMaster) {
            $rules['branch_id'] = ['nullable', 'integer', 'exists:branches,id'];
        } else {
            // If branch admin attempted to pass a different branch_id, abort with 403
            if ($request->has('branch_id') && (int) $request->input('branch_id') !== (int) $currentUser->branch_id) {
                abort(403, 'Admin cabang hanya berwenang membuat staf untuk cabangnya sendiri.');
            }
        }

        $validated = $request->validate($rules);

        $targetBranchId = $isMaster
            ? ($validated['branch_id'] ?? null)
            : $currentUser->branch_id;

        $newUser = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'branch_id' => $targetBranchId,
            'is_active' => true,
        ]);

        return redirect()
            ->route('backoffice.users.index')
            ->with('success', "Akun '{$newUser->name}' ({$newUser->role}) berhasil didaftarkan!");
    }

    /**
     * Show the form for editing an existing user.
     */
    public function edit(Request $request, int $id): View
    {
        $currentUser = $request->user()->load('branch');
        $isMaster = $currentUser->isMaster();

        $user = User::with('branch')->findOrFail($id);

        if (! $isMaster && (int) $user->branch_id !== (int) $currentUser->branch_id) {
            abort(403, 'Anda tidak memiliki otorisasi untuk mengubah data pengguna cabang lain.');
        }

        $branches = $isMaster ? Branch::orderBy('name')->get() : collect([$user->branch]);

        return view('backoffice.users.edit', [
            'currentUser' => $currentUser,
            'isMaster' => $isMaster,
            'user' => $user,
            'branches' => $branches,
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $currentUser = $request->user();
        $isMaster = $currentUser->isMaster();

        $user = User::findOrFail($id);

        if (! $isMaster && (int) $user->branch_id !== (int) $currentUser->branch_id) {
            abort(403, 'Anda tidak memiliki otorisasi untuk mengubah data pengguna cabang lain.');
        }

        $allowedRoles = $isMaster ? ['master', 'admin', 'cashier'] : ['admin', 'cashier'];

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['required', 'string', Rule::in($allowedRoles)],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($isMaster) {
            $rules['branch_id'] = ['nullable', 'integer', 'exists:branches,id'];
        } else {
            if ($request->has('branch_id') && (int) $request->input('branch_id') !== (int) $currentUser->branch_id) {
                abort(403, 'Admin cabang tidak dapat memindahkan pengguna ke cabang lain.');
            }
        }

        $validated = $request->validate($rules);

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'is_active' => $request->has('is_active') ? (bool) $request->input('is_active') : $user->is_active,
        ];

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        if ($isMaster && array_key_exists('branch_id', $validated)) {
            $updateData['branch_id'] = $validated['branch_id'];
        }

        $user->update($updateData);

        return redirect()
            ->route('backoffice.users.index')
            ->with('success', "Data pengguna '{$user->name}' berhasil diperbarui!");
    }

    /**
     * Remove or deactivate the specified user.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $currentUser = $request->user();
        $isMaster = $currentUser->isMaster();

        $user = User::findOrFail($id);

        if (! $isMaster && (int) $user->branch_id !== (int) $currentUser->branch_id) {
            abort(403, 'Anda tidak memiliki otorisasi untuk menghapus pengguna cabang lain.');
        }

        if ($user->id === $currentUser->id) {
            return redirect()
                ->route('backoffice.users.index')
                ->with('error', 'Anda tidak dapat menghapus akun Anda sendiri saat sedang login.');
        }

        $name = $user->name;
        $user->delete();

        return redirect()
            ->route('backoffice.users.index')
            ->with('success', "Pengguna '{$name}' berhasil dihapus!");
    }
}
