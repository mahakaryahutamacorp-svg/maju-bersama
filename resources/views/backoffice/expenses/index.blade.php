<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biaya Operasional &amp; Kas Keluar | Maju Bersama ERP</title>
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
                    <a href="{{ route('backoffice.expenses.index') }}" class="text-amber-400 font-semibold">Biaya Operasional</a>
                    <a href="{{ route('backoffice.expense-categories.index') }}" class="hover:text-white">Kategori Biaya</a>
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
                <a href="{{ route('backoffice.expenses.index') }}" class="rounded-lg bg-amber-500 px-3.5 py-2 font-bold text-slate-950 shadow-xs">
                    Riwayat Kas Keluar
                </a>
                <a href="{{ route('backoffice.expenses.create') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    + Catat Kas Keluar
                </a>
                <a href="{{ route('backoffice.expense-categories.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Kategori Biaya
                </a>
                <a href="/reports/accounting/ledger" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Buku Besar Akuntansi
                </a>
            </div>
            <a href="{{ route('backoffice.expenses.create') }}" id="btn-tambah-biaya" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3.5 py-2 text-xs font-bold text-amber-400 shadow-xs hover:bg-slate-800 transition-colors">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Catat Pengeluaran Kas
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

        <!-- Kartu Metrik KPI -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Kas Keluar</p>
                    <div class="rounded-xl bg-rose-500/10 p-2 text-rose-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                </div>
                <p class="mt-3 text-2xl font-black font-mono text-slate-900">
                    Rp {{ number_format($totalExpenses, 0, ',', '.') }}
                </p>
                <p class="mt-1 text-xs text-slate-400">Total akumulasi biaya operasional terfilter</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Frekuensi Pengeluaran</p>
                    <div class="rounded-xl bg-indigo-500/10 p-2 text-indigo-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                </div>
                <p class="mt-3 text-2xl font-black font-mono text-slate-900">
                    {{ number_format($expenseCount) }} transaksi
                </p>
                <p class="mt-1 text-xs text-slate-400">Voucher kas keluar tercatat di sistem</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs sm:col-span-2 lg:col-span-1">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Jurnal Otomatis</p>
                    <div class="rounded-xl bg-emerald-500/10 p-2 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <p class="mt-3 text-2xl font-black font-mono text-emerald-600">
                    100% Balanced
                </p>
                <p class="mt-1 text-xs text-slate-400">Terintegrasi otomatis ke Buku Besar</p>
            </div>
        </div>

        <!-- Filter & Pencarian Bar -->
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <form method="GET" action="{{ route('backoffice.expenses.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5 items-end">
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Dari Tanggal</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 outline-none focus:border-amber-500">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Sampai Tanggal</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 outline-none focus:border-amber-500">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Kategori Biaya</label>
                    <select name="expense_category_id" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 outline-none focus:border-amber-500">
                        <option value="">Semua Kategori</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" {{ $selectedCategoryId == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Cari Keterangan / Ref</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="No. Ref / Catatan..." class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 outline-none focus:border-amber-500">
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="w-full rounded-xl bg-slate-900 py-2.5 text-xs font-bold text-white shadow-xs hover:bg-slate-800">
                        Terapkan Filter
                    </button>
                    @if ($search || $startDate || $endDate || $selectedCategoryId)
                        <a href="{{ route('backoffice.expenses.index') }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Tabel Riwayat Pengeluaran Kas -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-xs">
                    <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="py-3.5 pl-6 pr-3">Tanggal &amp; No. Referensi</th>
                            <th class="px-4 py-3.5">Kategori &amp; Akun Beban</th>
                            <th class="px-4 py-3.5">Sumber Dana (Kas/Bank)</th>
                            <th class="px-4 py-3.5 text-right">Nominal Pengeluaran</th>
                            <th class="px-4 py-3.5">Catatan / Keterangan</th>
                            <th class="py-3.5 pl-3 pr-6 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                        @forelse ($expenses as $exp)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-4 pl-6 pr-3">
                                    <span class="block font-bold text-slate-900">{{ $exp->expense_date?->format('d/m/Y') }}</span>
                                    <span class="font-mono text-[11px] text-slate-500">{{ $exp->reference_number }}</span>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-block rounded-md bg-rose-50 border border-rose-200 px-2 py-0.5 font-bold text-rose-700">
                                        {{ $exp->expenseCategory?->name ?? 'Biaya' }}
                                    </span>
                                    @if ($exp->expenseCategory?->chartOfAccount)
                                        <span class="block text-[11px] text-slate-500 mt-0.5">
                                            Debet: [{{ $exp->expenseCategory->chartOfAccount->code }}] {{ $exp->expenseCategory->chartOfAccount->name }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-1.5">
                                        <span class="rounded bg-sky-50 border border-sky-200 px-1.5 py-0.5 font-mono text-[10px] font-bold text-sky-700">
                                            {{ $exp->account?->code ?? '-' }}
                                        </span>
                                        <span class="font-semibold text-slate-800">{{ $exp->account?->name ?? 'Kas Toko' }}</span>
                                    </div>
                                    <span class="block text-[10px] text-slate-400 mt-0.5">Kredit dana kas</span>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <span class="text-sm font-black font-mono text-rose-600">
                                        Rp {{ number_format($exp->amount, 0, ',', '.') }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 max-w-xs truncate text-slate-600" title="{{ $exp->notes }}">
                                    {{ $exp->notes ?? '-' }}
                                </td>
                                <td class="py-4 pl-3 pr-6 text-right">
                                    <a href="{{ route('backoffice.expenses.show', $exp->id) }}" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition-colors shadow-2xs">
                                        Lihat Voucher &rarr;
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-12 text-center text-slate-400">
                                    <p class="text-sm font-semibold">Belum ada catatan biaya operasional.</p>
                                    <p class="text-xs mt-1">Klik tombol di bawah untuk mencatat pengeluaran uang kas pertama.</p>
                                    <a href="{{ route('backoffice.expenses.create') }}" class="mt-4 inline-block rounded-xl bg-amber-500 px-4 py-2 text-xs font-bold text-slate-950 shadow-xs hover:bg-amber-400">
                                        + Catat Kas Keluar Baru
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($expenses->hasPages())
                <div class="border-t border-slate-200 bg-slate-50 px-6 py-4">
                    {{ $expenses->links() }}
                </div>
            @endif
        </div>
    </main>
</body>
</html>
