<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katalog Produk | Maju Bersama ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <!-- Header Utama -->
    <header class="border-b border-slate-800 bg-slate-950 text-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
            <div class="flex items-center gap-4">
                <a href="/backoffice" class="block">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-400">Maju Bersama ERP</p>
                    <h1 class="text-xl font-bold tracking-tight">Manajemen Master Data</h1>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <nav class="hidden items-center gap-4 text-sm text-slate-300 md:flex">
                    <a href="/pos" class="hover:text-white">POS Kasir</a>
                    <a href="/inventory" class="hover:text-white">Inventory</a>
                    <a href="/reports/journal" class="hover:text-white">Jurnal</a>
                    <a href="/backoffice" class="hover:text-white">Backoffice</a>
                </nav>
                <div class="rounded-full border border-sky-400/30 bg-sky-400/10 px-3 py-1 text-xs font-medium text-sky-200">
                    {{ $currentUser->branch?->name ?? 'Pusat' }} ({{ $currentUser->role }})
                </div>
                <form method="POST" action="/logout" class="hidden sm:block">
                    @csrf
                    <button type="submit" class="text-sm text-slate-300 hover:text-white">Logout</button>
                </form>
            </div>
        </div>
    </header>

    <!-- Sub-Navbar Master Data -->
    <div class="border-b border-slate-200 bg-white shadow-sm">
        <div class="mx-auto flex max-w-7xl items-center justify-between overflow-x-auto px-6 py-2.5 lg:px-8">
            <div class="flex items-center gap-2 text-sm font-medium">
                <a href="{{ route('backoffice.products.index') }}" class="rounded-lg bg-sky-600 px-3.5 py-2 font-semibold text-white shadow-sm">
                    Katalog Produk
                </a>
                <a href="{{ route('backoffice.products.create') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    + Tambah Produk
                </a>
                <a href="{{ route('backoffice.categories.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Kategori
                </a>
                <a href="{{ route('backoffice.users.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Staf &amp; Kasir
                </a>
                @if ($isMaster)
                    <a href="{{ route('backoffice.branches.index') }}" class="rounded-lg px-3.5 py-2 text-amber-700 hover:bg-amber-50">
                        Manajemen Cabang
                    </a>
                @endif
            </div>
            <a href="{{ route('backoffice.products.create') }}" class="hidden sm:inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-500">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Tambah Produk
            </a>
        </div>
    </div>

    <!-- Main Container -->
    <main class="mx-auto max-w-7xl px-6 py-8 lg:px-8">
        <!-- Flash Message Alerts -->
        @if (session('success'))
            <div class="mb-6 flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 shadow-sm">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                    <span class="text-sm font-medium">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 flex items-center justify-between rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800 shadow-sm">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 text-rose-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                    <span class="text-sm font-medium">{{ session('error') }}</span>
                </div>
            </div>
        @endif

        <!-- Filter & Search Bar -->
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('backoffice.products.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-12">
                <div class="lg:col-span-4">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Pencarian</label>
                    <div class="relative">
                        <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama produk atau SKU..." class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                    </div>
                </div>

                <div class="lg:col-span-3">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Kategori</label>
                    <select name="category_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                        <option value="">Semua Kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" {{ (string)$selectedCategoryId === (string)$category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @if ($isMaster)
                    <div class="lg:col-span-3">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Filter Cabang</label>
                        <select name="branch_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                            <option value="">Seluruh Cabang</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" {{ (string)$selectedBranchId === (string)$branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }} ({{ $branch->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div class="lg:col-span-3">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Cabang Aktif</label>
                        <div class="rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 text-sm text-slate-700 font-medium">
                            {{ $currentUser->branch?->name }}
                        </div>
                    </div>
                @endif

                <div class="flex items-end gap-2 lg:col-span-2">
                    <button type="submit" class="w-full rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 shadow-sm">
                        Filter
                    </button>
                    @if ($search || $selectedCategoryId || $selectedBranchId)
                        <a href="{{ route('backoffice.products.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50" title="Reset filter">
                            ↺
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Products Table -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 class="font-bold text-slate-900">Daftar Produk</h2>
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">Total: {{ $products->total() }} produk</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-6 py-3">Produk &amp; SKU</th>
                            <th class="px-6 py-3">Kategori</th>
                            <th class="px-6 py-3">Cabang</th>
                            <th class="px-6 py-3 text-right">Harga Beli (HPP)</th>
                            <th class="px-6 py-3 text-right">Harga Jual</th>
                            <th class="px-6 py-3 text-right">Stok</th>
                            <th class="px-6 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($products as $product)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-slate-900">{{ $product->name }}</div>
                                    <div class="font-mono text-xs text-sky-700 mt-0.5">{{ $product->sku }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">
                                        {{ $product->category?->name ?? '-' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-xs font-medium text-slate-700">{{ $product->branch?->name ?? 'Pusat' }}</div>
                                    <div class="font-mono text-[10px] text-slate-400">{{ $product->branch?->code }}</div>
                                </td>
                                <td class="px-6 py-4 text-right font-medium text-slate-700">
                                    Rp {{ number_format($product->purchase_price, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="font-semibold text-emerald-700">
                                        Rp {{ number_format($product->getPrice(), 0, ',', '.') }}
                                    </div>
                                    @if ($product->productPrices->count() > 1)
                                        <div class="mt-0.5">
                                            <span class="inline-flex items-center rounded-md bg-sky-50 px-1.5 py-0.5 text-[10px] font-semibold text-sky-700 border border-sky-200" title="{{ $product->productPrices->count() }} tingkat harga aktif">
                                                (+ Multi Harga)
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right font-semibold text-slate-800">
                                    {{ number_format($product->stock, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="inline-flex items-center gap-1.5">
                                        <a href="{{ route('backoffice.products.edit', $product->id) }}" class="rounded-lg bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 hover:bg-sky-100">
                                            Edit
                                        </a>
                                        <form method="POST" action="{{ route('backoffice.products.destroy', $product->id) }}" onsubmit="return confirm('Apakah Anda yakin ingin mengarsipkan produk ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-slate-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="h-10 w-10 text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                                        <p class="font-medium text-slate-600">Tidak ada produk yang ditemukan.</p>
                                        <p class="text-xs text-slate-400 mt-0.5">Silakan tambahkan produk baru atau ubah kata kunci filter Anda.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($products->hasPages())
                <div class="border-t border-slate-100 px-6 py-4">
                    {{ $products->links() }}
                </div>
            @endif
        </div>
    </main>
</body>
</html>
