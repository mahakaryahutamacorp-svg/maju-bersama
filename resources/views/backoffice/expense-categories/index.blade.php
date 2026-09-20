<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Kategori Biaya Operasional | Maju Bersama ERP</title>
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
                    <h1 class="text-xl font-bold tracking-tight">Keuangan &amp; Akuntansi</h1>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <nav class="hidden items-center gap-4 text-sm text-slate-300 md:flex">
                    <a href="/pos" class="hover:text-white">POS Kasir</a>
                    <a href="/inventory" class="hover:text-white">Inventory</a>
                    <a href="{{ route('backoffice.expenses.index') }}" class="hover:text-white">Biaya Operasional</a>
                    <a href="{{ route('backoffice.expense-categories.index') }}" class="text-amber-400 font-semibold">Kategori Biaya</a>
                    <a href="/reports/accounting/ledger" class="hover:text-white">Buku Besar</a>
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

    <!-- Sub-Navbar Modul Pengeluaran -->
    <div class="border-b border-slate-200 bg-white shadow-xs">
        <div class="mx-auto flex max-w-7xl items-center justify-between overflow-x-auto px-6 py-2.5 lg:px-8">
            <div class="flex items-center gap-2 text-sm font-medium">
                <a href="{{ route('backoffice.expenses.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    &larr; Riwayat Kas Keluar
                </a>
                <a href="{{ route('backoffice.expenses.create') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    + Catat Kas Keluar
                </a>
                <a href="{{ route('backoffice.expense-categories.index') }}" class="rounded-lg bg-amber-500 px-3.5 py-2 font-bold text-slate-950 shadow-xs">
                    Kategori Biaya
                </a>
                <a href="/reports/accounting/ledger" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Buku Besar Akuntansi
                </a>
            </div>
            <a href="{{ route('backoffice.expense-categories.create') }}" id="btn-tambah-kategori" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3.5 py-2 text-xs font-bold text-amber-400 shadow-xs hover:bg-slate-800 transition-colors">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Tambah Kategori Biaya
            </a>
        </div>
    </div>

    <main class="mx-auto max-w-7xl space-y-6 px-6 py-8 lg:px-8">
        <!-- Flash Messages -->
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900 shadow-xs">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span class="text-sm font-semibold">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        <!-- Header Halaman & Pencarian -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-amber-600">Master Data Akuntansi</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">Kategori Biaya Operasional</h2>
                <p class="text-xs text-slate-500 mt-0.5">Pemetaan jenis pengeluaran toko ke akun bagan akun (COA) beban.</p>
            </div>
            <form method="GET" action="{{ route('backoffice.expense-categories.index') }}" class="flex items-center gap-2">
                <div class="relative w-64 sm:w-80">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Cari kategori atau kode COA..."
                        class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-9 pr-3 text-xs text-slate-900 outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 shadow-xs"
                    >
                    <svg class="absolute left-3 top-2.5 h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-slate-800">
                    Cari
                </button>
                @if ($search)
                    <a href="{{ route('backoffice.expense-categories.index') }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100">
                        Reset
                    </a>
                @endif
            </form>
        </div>

        <!-- Tabel Kategori Biaya -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-xs">
                    <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="py-3.5 pl-6 pr-3">Nama Kategori</th>
                            <th class="px-4 py-3.5">Akun Beban (Chart of Account)</th>
                            <th class="px-4 py-3.5 text-center">Status</th>
                            <th class="px-4 py-3.5 text-center">Total Transaksi</th>
                            <th class="py-3.5 pl-3 pr-6 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                        @forelse ($categories as $cat)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-4 pl-6 pr-3 font-bold text-slate-900">
                                    <div class="flex items-center gap-2.5">
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-600 border border-rose-100">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                        </div>
                                        <span>{{ $cat->name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    @if ($cat->chartOfAccount)
                                        <div class="flex items-center gap-2">
                                            <span class="rounded bg-indigo-50 border border-indigo-200 px-2 py-0.5 font-mono text-[11px] font-bold text-indigo-700">
                                                {{ $cat->chartOfAccount->code }}
                                            </span>
                                            <span class="text-slate-800 font-semibold">{{ $cat->chartOfAccount->name }}</span>
                                        </div>
                                    @else
                                        <span class="text-slate-400 italic">Belum dikaitkan</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-center">
                                    @if ($cat->is_active)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700 border border-emerald-200">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            Aktif
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-500 border border-slate-200">
                                            <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                            Non-Aktif
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-center font-bold text-slate-800">
                                    {{ $cat->expenses_count }}x pengeluaran
                                </td>
                                <td class="py-4 pl-3 pr-6 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('backoffice.expense-categories.edit', $cat->id) }}" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:border-slate-300">
                                            Edit
                                        </a>
                                        <form method="POST" action="{{ route('backoffice.expense-categories.destroy', $cat->id) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus/menonaktifkan kategori ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-12 text-center text-slate-400">
                                    <p class="text-sm font-semibold">Belum ada kategori biaya yang terdaftar.</p>
                                    <p class="text-xs mt-1">Tambahkan kategori baru untuk mulai mencatat pengeluaran kas.</p>
                                    <a href="{{ route('backoffice.expense-categories.create') }}" class="mt-4 inline-block rounded-xl bg-amber-500 px-4 py-2 text-xs font-bold text-slate-950 shadow-xs hover:bg-amber-400">
                                        + Tambah Kategori Pertama
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($categories->hasPages())
                <div class="border-t border-slate-200 bg-slate-50 px-6 py-4">
                    {{ $categories->links() }}
                </div>
            @endif
        </div>
    </main>
</body>
</html>
