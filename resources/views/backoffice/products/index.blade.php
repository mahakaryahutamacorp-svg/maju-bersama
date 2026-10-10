<x-app-layout title="Katalog Produk" :breadcrumbs="[
    ['label' => 'Pengaturan'],
    ['label' => 'Katalog Produk'],
]">
    <main class="mx-auto w-full px-6 py-6 lg:px-10 max-w-7xl">
        <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Pengaturan</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Katalog Produk</h2>
            </div>
            <a href="{{ route('backoffice.products.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-sky-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-sky-700">
                + Tambah Produk
            </a>
        </div>


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
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">Pencarian</label>
                    <div class="relative">
                        <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama produk atau SKU..." class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                    </div>
                </div>

                <div class="lg:col-span-3">
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">Kategori</label>
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
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">Filter Cabang</label>
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
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">Cabang Aktif</label>
                        <div class="rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 text-sm text-slate-700 font-medium">
                            {{ $currentUser->branch?->name }}
                        </div>
                    </div>
                @endif

                <div class="flex items-end gap-2 lg:col-span-2">
                    <button type="submit" class="w-full rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 shadow-sm">
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
                    <thead class="bg-slate-900 text-xs font-semibold uppercase tracking-wider text-white">
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
                    <tbody class="divide-y divide-slate-100 [&>tr:nth-child(even)]:bg-slate-50/60">
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
                                        @if ($product->hasTransactionHistory())
                                            <button type="button" disabled title="Produk ini sudah memiliki riwayat transaksi sehingga tidak dapat dihapus." class="cursor-not-allowed rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-400">
                                                Hapus
                                            </button>
                                        @else
                                            <form method="POST" action="{{ route('backoffice.products.destroy', $product->id) }}" onsubmit="return confirm('Apakah Anda yakin ingin mengarsipkan produk ini?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100">
                                                    Hapus
                                                </button>
                                            </form>
                                        @endif
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
</x-app-layout>
