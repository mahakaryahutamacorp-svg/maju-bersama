<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller
{
    /**
     * Tampilkan daftar master kategori biaya operasional.
     */
    public function index(Request $request): View
    {
        $user = $request->user()->load('branch');
        $search = $request->input('search');

        $query = ExpenseCategory::with('chartOfAccount')
            ->withCount('expenses')
            ->when($search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('chartOfAccount', function ($sq) use ($search) {
                      $sq->where('code', 'like', "%{$search}%")
                         ->orWhere('name', 'like', "%{$search}%");
                  });
            })
            ->orderBy('name');

        $categories = $query->paginate(15)->withQueryString();

        return view('backoffice.expense-categories.index', [
            'currentUser' => $user,
            'isMaster'    => $user->isMaster(),
            'categories'  => $categories,
            'search'      => $search,
        ]);
    }

    /**
     * Tampilkan form pembuatan kategori biaya baru.
     */
    public function create(Request $request): View
    {
        $user = $request->user()->load('branch');

        // Ambil akun bertipe expense (kode awalan 6xxx atau 5xxx)
        $expenseAccounts = ChartOfAccount::where('type', 'expense')
            ->orWhere('code', 'like', '6%')
            ->orWhere('code', 'like', '5%')
            ->orderBy('code')
            ->get();

        if ($expenseAccounts->isEmpty()) {
            $expenseAccounts = ChartOfAccount::orderBy('code')->get();
        }

        return view('backoffice.expense-categories.create', [
            'currentUser'     => $user,
            'isMaster'        => $user->isMaster(),
            'expenseAccounts' => $expenseAccounts,
        ]);
    }

    /**
     * Simpan kategori biaya baru ke database.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'chart_of_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'is_active'           => ['nullable', 'boolean'],
        ]);

        $category = ExpenseCategory::create([
            'branch_id'           => $request->user()->branch_id,
            'name'                => $validated['name'],
            'chart_of_account_id' => $validated['chart_of_account_id'],
            'is_active'           => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('backoffice.expense-categories.index')
            ->with('success', "Kategori biaya '{$category->name}' berhasil ditambahkan.");
    }

    /**
     * Tampilkan form edit kategori biaya.
     */
    public function edit(int $id, Request $request): View
    {
        $user = $request->user()->load('branch');
        $category = ExpenseCategory::with('chartOfAccount')->findOrFail($id);

        $expenseAccounts = ChartOfAccount::where('type', 'expense')
            ->orWhere('code', 'like', '6%')
            ->orWhere('code', 'like', '5%')
            ->orderBy('code')
            ->get();

        if ($expenseAccounts->isEmpty()) {
            $expenseAccounts = ChartOfAccount::orderBy('code')->get();
        }

        return view('backoffice.expense-categories.edit', [
            'currentUser'     => $user,
            'isMaster'        => $user->isMaster(),
            'category'        => $category,
            'expenseAccounts' => $expenseAccounts,
        ]);
    }

    /**
     * Perbarui data kategori biaya.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $category = ExpenseCategory::findOrFail($id);

        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'chart_of_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'is_active'           => ['nullable', 'boolean'],
        ]);

        $category->update([
            'name'                => $validated['name'],
            'chart_of_account_id' => $validated['chart_of_account_id'],
            'is_active'           => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('backoffice.expense-categories.index')
            ->with('success', "Kategori biaya '{$category->name}' berhasil diperbarui.");
    }

    /**
     * Hapus kategori biaya.
     */
    public function destroy(int $id, Request $request): RedirectResponse
    {
        $category = ExpenseCategory::withCount('expenses')->findOrFail($id);

        if ($category->expenses_count > 0) {
            // Jika sudah memiliki riwayat transaksi, nonaktifkan saja untuk integritas audit
            $category->update(['is_active' => false]);
            return redirect()
                ->route('backoffice.expense-categories.index')
                ->with('success', "Kategori '{$category->name}' memiliki riwayat transaksi pengeluaran, status diubah menjadi Non-Aktif.");
        }

        $name = $category->name;
        $category->delete();

        return redirect()
            ->route('backoffice.expense-categories.index')
            ->with('success', "Kategori biaya '{$name}' berhasil dihapus.");
    }
}
