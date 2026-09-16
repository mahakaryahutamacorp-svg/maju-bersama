<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index(Request $request): View
    {
        $user = $request->user()->load('branch');
        $search = $request->input('search');

        $query = Category::withCount('products')
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name');

        $categories = $query->paginate(15)->withQueryString();

        return view('backoffice.categories.index', [
            'currentUser' => $user,
            'isMaster' => $user->isMaster(),
            'categories' => $categories,
            'search' => $search,
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
        ], [
            'name.required' => 'Nama kategori wajib diisi.',
            'name.unique' => 'Nama kategori ini sudah terdaftar.',
        ]);

        $category = Category::create($validated);

        return redirect()
            ->route('backoffice.categories.index')
            ->with('success', "Kategori '{$category->name}' berhasil ditambahkan!");
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($category->id)],
        ], [
            'name.required' => 'Nama kategori wajib diisi.',
            'name.unique' => 'Nama kategori ini sudah digunakan.',
        ]);

        $category->update($validated);

        return redirect()
            ->route('backoffice.categories.index')
            ->with('success', "Kategori berhasil diperbarui menjadi '{$category->name}'!");
    }

    /**
     * Remove the specified category.
     */
    public function destroy(int $id): RedirectResponse
    {
        $category = Category::withCount('products')->findOrFail($id);

        if ($category->products_count > 0) {
            return redirect()
                ->route('backoffice.categories.index')
                ->with('error', "Kategori '{$category->name}' tidak dapat dihapus karena masih digunakan oleh {$category->products_count} produk.");
        }

        $name = $category->name;
        $category->delete();

        return redirect()
            ->route('backoffice.categories.index')
            ->with('success', "Kategori '{$name}' berhasil dihapus!");
    }
}
