<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Gudang | Maju Bersama ERP</title>
    <meta name="description" content="Kelola daftar gudang persediaan per cabang pada sistem Maju Bersama ERP.">
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
                    <h1 class="text-xl font-bold tracking-tight">Multi Gudang</h1>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <nav class="hidden items-center gap-4 text-sm text-slate-300 md:flex">
                    <a href="/pos" class="hover:text-white">POS Kasir</a>
                    <a href="/inventory" class="hover:text-white">Inventory</a>
                    <a href="/backoffice/products" class="hover:text-white">Produk</a>
                    <a href="/backoffice" class="hover:text-white">Backoffice</a>
                </nav>
                @if ($isMaster)
                    <div class="rounded-full border border-amber-400/40 bg-amber-400/10 px-3 py-1 text-xs font-semibold text-amber-300">
                        👑 Akses Master Pusat
                    </div>
                @else
                    <div class="rounded-full border border-sky-400/40 bg-sky-400/10 px-3 py-1 text-xs font-semibold text-sky-300">
                        🏪 {{ $currentUser->branch->name }}
                    </div>
                @endif
            </div>
        </div>
    </header>

    <!-- Sub-Navbar -->
    <div class="border-b border-slate-200 bg-white shadow-sm">
        <div class="mx-auto flex max-w-7xl items-center justify-between overflow-x-auto px-6 py-2.5 lg:px-8">
            <div class="flex items-center gap-2 text-sm font-medium">
                <a href="{{ route('backoffice.products.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Katalog Produk
                </a>
                <a href="{{ route('backoffice.categories.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Kategori
                </a>
                <a href="{{ route('backoffice.users.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Staf & Kasir
                </a>
                @if ($isMaster)
                    <a href="{{ route('backoffice.branches.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                        Manajemen Cabang
                    </a>
                @endif
                <a href="{{ route('backoffice.warehouses.index') }}" class="rounded-lg bg-teal-600 px-3.5 py-2 font-bold text-white shadow-sm">
                    Multi Gudang
                </a>
            </div>
            <a href="{{ route('backoffice.warehouses.create') }}" id="btn-tambah-gudang" class="hidden sm:inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-500 transition-colors">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Tambah Gudang
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <main class="mx-auto max-w-7xl px-6 py-8 lg:px-8" x-data="{ showFilters: false }">

        <!-- Flash Messages -->
        @if (session('success'))
            <div class="mb-6 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 shadow-sm">
                <svg class="h-5 w-5 flex-shrink-0 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                <span class="text-sm font-medium">{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 flex items-center gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800 shadow-sm">
                <svg class="h-5 w-5 flex-shrink-0 text-rose-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                <span class="text-sm font-medium">{{ session('error') }}</span>
            </div>
        @endif

        <!-- Intro Banner -->
        <div class="mb-6 rounded-2xl border border-teal-200 bg-gradient-to-r from-teal-50 to-cyan-50 p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-teal-600">Modul Inventory</p>
                    <h2 class="mt-1 text-lg font-bold text-slate-900">Manajemen Multi Gudang</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        @if ($isMaster)
                            Pantau seluruh gudang di semua cabang. Master dapat mengatur gudang lintas cabang.
                        @else
                            Kelola gudang-gudang dalam cabang <strong>{{ $currentUser->branch->name }}</strong>. Contoh: Etalase Depan, Gudang Belakang, Laci Kasir.
                        @endif
                    </p>
                </div>
                <a href="{{ route('backoffice.warehouses.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-teal-500 transition-colors">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Tambah Gudang Baru
                </a>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div class="mb-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('backoffice.warehouses.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Cari Gudang</label>
                    <input id="input-search-gudang" type="text" name="search" value="{{ $search }}"
                        placeholder="Nama atau kode gudang..."
                        class="w-full rounded-xl border border-slate-300 px-4 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500">
                </div>
                @if ($isMaster)
                    <div class="sm:w-60">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Filter Cabang</label>
                        <select id="select-filter-branch" name="branch_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500">
                            <option value="">Semua Cabang</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" {{ $filterBranch == $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }} ({{ $branch->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="flex items-center gap-2">
                    <button id="btn-cari-gudang" type="submit" class="rounded-xl bg-slate-900 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-800 transition-colors">
                        Cari
                    </button>
                    @if ($search || $filterBranch)
                        <a href="{{ route('backoffice.warehouses.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 transition-colors">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Warehouses Table -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h2 class="font-bold text-slate-900">Daftar Gudang</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        @if ($isMaster) Seluruh cabang @else {{ $currentUser->branch->name }} @endif
                    </p>
                </div>
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                    Total: {{ $warehouses->total() }} gudang
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-6 py-3">Kode</th>
                            <th class="px-6 py-3">Nama Gudang</th>
                            @if ($isMaster)
                                <th class="px-6 py-3">Cabang</th>
                            @endif
                            <th class="px-6 py-3 text-center">Status</th>
                            <th class="px-6 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($warehouses as $warehouse)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-6 py-4 font-mono font-bold text-teal-700">
                                    {{ $warehouse->code }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2.5">
                                        <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-teal-50 text-base">🏭</span>
                                        <div>
                                            <div class="font-semibold text-slate-900">{{ $warehouse->name }}</div>
                                            <div class="text-xs text-slate-400">Dibuat {{ $warehouse->created_at->diffForHumans() }}</div>
                                        </div>
                                    </div>
                                </td>
                                @if ($isMaster)
                                    <td class="px-6 py-4">
                                        <span class="font-medium text-slate-700">{{ $warehouse->branch->name }}</span>
                                        <span class="ml-1.5 font-mono text-[10px] text-slate-400">{{ $warehouse->branch->code }}</span>
                                    </td>
                                @endif
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $warehouse->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $warehouse->is_active ? 'Aktif' : 'Non-aktif' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="inline-flex items-center gap-1.5">
                                        <a href="{{ route('backoffice.warehouses.edit', $warehouse->id) }}" class="rounded-lg bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 hover:bg-sky-100 transition-colors">
                                            Edit
                                        </a>
                                        <form method="POST" action="{{ route('backoffice.warehouses.destroy', $warehouse->id) }}"
                                            onsubmit="return confirm('Hapus gudang \'{{ $warehouse->name }}\'? Tindakan ini tidak dapat dibatalkan.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition-colors">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isMaster ? 5 : 4 }}" class="px-6 py-14 text-center">
                                    <div class="mx-auto max-w-xs">
                                        <p class="text-3xl">🏭</p>
                                        <p class="mt-3 font-semibold text-slate-700">Belum ada gudang</p>
                                        <p class="mt-1 text-sm text-slate-400">
                                            @if ($search || $filterBranch)
                                                Tidak ada gudang yang cocok dengan filter pencarian.
                                            @else
                                                Tambahkan gudang pertama untuk cabang ini.
                                            @endif
                                        </p>
                                        @if (!$search && !$filterBranch)
                                            <a href="{{ route('backoffice.warehouses.create') }}" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-teal-500">
                                                + Tambah Gudang
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($warehouses->hasPages())
                <div class="border-t border-slate-100 px-6 py-4">
                    {{ $warehouses->links() }}
                </div>
            @endif
        </div>
    </main>
</body>
</html>
