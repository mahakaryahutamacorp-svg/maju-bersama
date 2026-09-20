<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mutasi Kas &amp; Bank | Maju Bersama ERP</title>
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
                    <h1 class="text-xl font-bold tracking-tight">Kas &amp; Bank (Treasury)</h1>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <nav class="hidden items-center gap-4 text-sm text-slate-300 md:flex">
                    <a href="/pos" class="hover:text-white">POS Kasir</a>
                    <a href="/inventory" class="hover:text-white">Inventory</a>
                    <a href="{{ route('backoffice.cash-transfers.index') }}" class="text-amber-400 font-semibold">Mutasi Kas &amp; Bank</a>
                    <a href="{{ route('backoffice.expenses.index') }}" class="hover:text-white">Biaya Operasional</a>
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

    <!-- Sub-Navbar Modul Kas & Bank -->
    <div class="border-b border-slate-200 bg-white shadow-xs">
        <div class="mx-auto flex max-w-7xl items-center justify-between overflow-x-auto px-6 py-2.5 lg:px-8">
            <div class="flex items-center gap-2 text-sm font-medium">
                <a href="{{ route('backoffice.cash-transfers.index') }}" class="rounded-lg bg-amber-500 px-3.5 py-2 font-bold text-slate-950 shadow-xs">
                    Riwayat Mutasi Kas
                </a>
                <a href="{{ route('backoffice.cash-transfers.create') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    + Catat Mutasi Kas
                </a>
                <a href="{{ route('backoffice.expenses.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Biaya Operasional
                </a>
                <a href="/reports/accounting/ledger" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Buku Besar Akuntansi
                </a>
            </div>
            <a href="{{ route('backoffice.cash-transfers.create') }}" id="btn-tambah-mutasi" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3.5 py-2 text-xs font-bold text-amber-400 shadow-xs hover:bg-slate-800 transition-colors">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                Catat Mutasi Kas
            </a>
        </div>
    </div>

    <main class="mx-auto max-w-7xl space-y-6 px-6 py-8 lg:px-8">
        <!-- Flash Messages -->
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900 shadow-xs">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span class="text-sm font-semibold">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        <!-- Kartu Metrik KPI -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Mutasi Dana</p>
                    <div class="rounded-xl bg-amber-500/10 p-2 text-amber-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-2xl font-black font-mono text-slate-900">
                    Rp {{ number_format($totalTransferAmount, 0, ',', '.') }}
                </p>
                <p class="mt-1 text-xs text-slate-400">Total akumulasi dana kas berpindah terfilter</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Frekuensi Mutasi</p>
                    <div class="rounded-xl bg-sky-500/10 p-2 text-sky-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-2xl font-black font-mono text-slate-900">
                    {{ number_format($transferCount) }} transaksi
                </p>
                <p class="mt-1 text-xs text-slate-400">Mutasi kas &amp; bank tercatat di sistem</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs sm:col-span-2 lg:col-span-1">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Otomatisasi Jurnal</p>
                    <div class="rounded-xl bg-emerald-500/10 p-2 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-2xl font-black text-emerald-600">
                    100% Balanced
                </p>
                <p class="mt-1 text-xs text-slate-400">Setiap mutasi otomatis membukukan Debit &amp; Kredit</p>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
            <form method="GET" action="{{ route('backoffice.cash-transfers.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Search Keyword -->
                <div>
                    <label for="search" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Cari Kata Kunci</label>
                    <input
                        type="text"
                        name="search"
                        id="search"
                        value="{{ $search }}"
                        placeholder="No. Referensi / Catatan / Akun..."
                        class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-2xs outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                    >
                </div>

                <!-- Filter Akun Kas / Bank -->
                <div>
                    <label for="account_id" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Akun Kas / Bank</label>
                    <select
                        name="account_id"
                        id="account_id"
                        class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-2xs outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                    >
                        <option value="">Semua Akun Kas/Bank</option>
                        @foreach ($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ $selectedAccountId == $acc->id ? 'selected' : '' }}>
                                [{{ $acc->code }}] {{ $acc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Tanggal Mulai -->
                <div>
                    <label for="start_date" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Dari Tanggal</label>
                    <input
                        type="date"
                        name="start_date"
                        id="start_date"
                        value="{{ $startDate }}"
                        class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-2xs outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                    >
                </div>

                <!-- Tanggal Sampai & Tombol Submit -->
                <div>
                    <label for="end_date" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Sampai Tanggal</label>
                    <div class="flex items-center gap-2">
                        <input
                            type="date"
                            name="end_date"
                            id="end_date"
                            value="{{ $endDate }}"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-2xs outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                        >
                        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-slate-800 transition">
                            Filter
                        </button>
                        @if ($search || $startDate || $endDate || $selectedAccountId)
                            <a href="{{ route('backoffice.cash-transfers.index') }}" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 transition" title="Reset Filter">
                                Reset
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </section>

        <!-- Tabel Riwayat Mutasi Kas -->
        <section class="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
            <div class="border-b border-slate-100 px-6 py-4 flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Riwayat Mutasi Kas &amp; Bank</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Daftar pemindahan dana kas beserta rincian debit/kredit akuntansinya.</p>
                </div>
                <div class="text-xs font-semibold text-slate-500">
                    Menampilkan <span class="font-bold text-slate-900">{{ $transfers->firstItem() ?? 0 }}</span> - <span class="font-bold text-slate-900">{{ $transfers->lastItem() ?? 0 }}</span> dari <span class="font-bold text-slate-900">{{ $transfers->total() }}</span> mutasi
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-6 py-3.5">Tanggal</th>
                            <th class="px-6 py-3.5">No. Referensi</th>
                            <th class="px-6 py-3.5">Sumber Dana (Akun Asal)</th>
                            <th class="px-3 py-3.5 text-center">Arah</th>
                            <th class="px-6 py-3.5">Tujuan Dana (Akun Tujuan)</th>
                            <th class="px-6 py-3.5 text-right">Nominal Mutasi</th>
                            <th class="px-6 py-3.5">Catatan / Keterangan</th>
                            <th class="px-6 py-3.5 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($transfers as $transfer)
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <!-- Tanggal -->
                                <td class="px-6 py-4 whitespace-nowrap text-xs font-medium text-slate-600">
                                    {{ $transfer->transfer_date->format('d M Y') }}
                                </td>

                                <!-- No. Referensi -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a href="{{ route('backoffice.cash-transfers.show', $transfer->id) }}" class="font-mono text-xs font-bold text-sky-700 hover:text-sky-900 hover:underline">
                                        {{ $transfer->reference_number }}
                                    </a>
                                    @if ($isMaster && $transfer->branch)
                                        <div class="mt-0.5 text-[10px] text-slate-400">
                                            {{ $transfer->branch->name }}
                                        </div>
                                    @endif
                                </td>

                                <!-- Akun Asal (Badge Rose/Kredit) -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-bold text-rose-800">
                                        <span class="font-mono text-[11px] text-rose-500">[{{ $transfer->fromAccount?->code }}]</span>
                                        {{ $transfer->fromAccount?->name ?? 'N/A' }}
                                    </span>
                                </td>

                                <!-- Arah Transfer -->
                                <td class="px-3 py-4 whitespace-nowrap text-center text-slate-400">
                                    <svg class="h-4 w-4 inline-block text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                    </svg>
                                </td>

                                <!-- Akun Tujuan (Badge Emerald/Debit) -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-800">
                                        <span class="font-mono text-[11px] text-emerald-600">[{{ $transfer->toAccount?->code }}]</span>
                                        {{ $transfer->toAccount?->name ?? 'N/A' }}
                                    </span>
                                </td>

                                <!-- Nominal Mutasi (Rata Kanan & Tabular Nums) -->
                                <td class="px-6 py-4 whitespace-nowrap text-right tabular-nums font-mono font-black text-slate-900 text-sm">
                                    Rp {{ number_format((float) $transfer->amount, 0, ',', '.') }}
                                </td>

                                <!-- Catatan -->
                                <td class="px-6 py-4 text-xs text-slate-500 max-w-xs truncate" title="{{ $transfer->notes }}">
                                    {{ $transfer->notes ?: '-' }}
                                </td>

                                <!-- Aksi -->
                                <td class="px-6 py-4 whitespace-nowrap text-center text-xs">
                                    <a
                                        href="{{ route('backoffice.cash-transfers.show', $transfer->id) }}"
                                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 hover:text-sky-700 transition"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-slate-400">
                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                        </svg>
                                    </div>
                                    <p class="mt-3 text-sm font-semibold text-slate-600">Belum ada data mutasi kas &amp; bank</p>
                                    <p class="mt-1 text-xs text-slate-400">Gunakan tombol di atas untuk mencatat perpindahan dana antar rekening.</p>
                                    <div class="mt-4">
                                        <a href="{{ route('backoffice.cash-transfers.create') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-amber-500 px-4 py-2 text-xs font-bold text-slate-950 shadow-xs hover:bg-amber-400 transition">
                                            + Catat Mutasi Pertama
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if ($transfers->hasPages())
                <div class="border-t border-slate-100 px-6 py-4">
                    {{ $transfers->links() }}
                </div>
            @endif
        </section>
    </main>
</body>
</html>
