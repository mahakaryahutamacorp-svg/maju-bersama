<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buku Besar (General Ledger) | Maju Bersama ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; }
            .print-break { page-break-after: always; }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    @php
        // Helper badge warna untuk visual grouping transaksi antar cabang
        if (!function_exists('getBranchBadgeStyle')) {
            function getBranchBadgeStyle(?string $branchName): string {
                $name = strtolower(trim((string) $branchName));
                if (str_contains($name, 'pusat')) {
                    return 'bg-blue-100 text-blue-800 border-blue-300';
                } elseif (str_contains($name, '1') || str_contains($name, 'arofah')) {
                    return 'bg-emerald-100 text-emerald-800 border-emerald-300';
                } elseif (str_contains($name, '2')) {
                    return 'bg-amber-100 text-amber-800 border-amber-300';
                } elseif (str_contains($name, '3')) {
                    return 'bg-purple-100 text-purple-800 border-purple-300';
                } elseif (str_contains($name, '4')) {
                    return 'bg-rose-100 text-rose-800 border-rose-300';
                } elseif (str_contains($name, '5')) {
                    return 'bg-teal-100 text-teal-800 border-teal-300';
                } else {
                    $palette = [
                        'bg-sky-100 text-sky-800 border-sky-300',
                        'bg-indigo-100 text-indigo-800 border-indigo-300',
                        'bg-cyan-100 text-cyan-800 border-cyan-300',
                        'bg-orange-100 text-orange-800 border-orange-300',
                        'bg-violet-100 text-violet-800 border-violet-300',
                    ];
                    $index = abs(crc32($name)) % count($palette);
                    return $palette[$index];
                }
            }
        }
    @endphp

    <!-- Header Utama -->
    <header class="border-b border-slate-800 bg-slate-950 text-white no-print">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
            <div class="flex items-center gap-4">
                <a href="/backoffice" class="block">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-400">Maju Bersama ERP</p>
                    <h1 class="text-xl font-bold tracking-tight">Akuntansi &amp; Keuangan</h1>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <nav class="hidden items-center gap-4 text-sm text-slate-300 md:flex">
                    <a href="/pos" class="hover:text-white">Kasir (POS)</a>
                    <a href="/inventory" class="hover:text-white">Persediaan</a>
                    <a href="/inventory/transfer" class="hover:text-white">Transfer Stok</a>
                    <a href="/backoffice" class="hover:text-white">Panel Admin</a>
                </nav>
                <div class="rounded-full border border-sky-400/30 bg-sky-400/10 px-3 py-1 text-xs font-medium text-sky-200">
                    {{ $currentUser->branch?->name ?? 'Pusat' }} ({{ $currentUser->role }})
                </div>
                <form method="POST" action="/logout" class="hidden sm:block">
                    @csrf
                    <button type="submit" class="text-sm text-slate-300 hover:text-white">Keluar</button>
                </form>
            </div>
        </div>
    </header>

    <!-- Sub-Navbar Navigasi Laporan -->
    <div class="border-b border-slate-200 bg-white no-print">
        <div class="mx-auto flex max-w-7xl items-center justify-between overflow-x-auto px-6 py-2.5 lg:px-8">
            <div class="flex items-center gap-2 text-sm font-medium">
                <a href="/reports/accounting/ledger" class="rounded-lg bg-sky-600 px-3.5 py-2 font-semibold text-white shadow-sm">
                    Buku Besar
                </a>
                <a href="/reports/accounting/trial-balance" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Neraca Saldo
                </a>
                <a href="/reports/accounting/income-statement" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Laba Rugi
                </a>
                <a href="/reports/journal" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Jurnal Umum
                </a>
            </div>
            <button type="button" onclick="window.print()" class="flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Cetak Laporan
            </button>
        </div>
    </div>

    <main class="mx-auto max-w-7xl space-y-6 px-6 py-8 lg:px-8">
        <!-- 2. Header Laporan Dinamis (Bereaksi Terhadap Filter Toko / Konsolidasi) -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-semibold uppercase tracking-wider text-amber-600">Laporan Keuangan</span>
                    <span class="text-xs text-slate-400">&bull;</span>
                    @if ($branchId && $activeBranch)
                        <span class="inline-flex items-center gap-1.5 rounded-md border border-sky-200 bg-sky-50 px-2.5 py-0.5 text-xs font-semibold text-sky-800 shadow-xs">
                            <svg class="h-3.5 w-3.5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            Filter Toko: <strong class="uppercase">{{ $activeBranchName }}</strong>
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-md border border-purple-200 bg-purple-50 px-2.5 py-0.5 text-xs font-semibold text-purple-800 shadow-xs">
                            <svg class="h-3.5 w-3.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Konsolidasi Seluruh Cabang
                        </span>
                    @endif
                </div>

                <!-- Judul Utama Dinamis -->
                <h2 class="mt-1.5 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">
                    @if ($branchId && $activeBranch)
                        Buku Besar &mdash; <span class="text-sky-700 uppercase tracking-tight">{{ $activeBranchName }}</span>
                    @else
                        Buku Besar &mdash; <span class="text-slate-800">Konsolidasi Seluruh Cabang</span>
                    @endif
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Cakupan: <span class="font-semibold text-slate-800">{{ $activeBranchName }}</span>
                    @if ($startDate || $endDate)
                        &middot; Periode: <span class="font-semibold text-slate-800">{{ $startDate ? \Carbon\Carbon::parse($startDate)->format('d M Y') : 'Awal' }}</span> s/d <span class="font-semibold text-slate-800">{{ $endDate ? \Carbon\Carbon::parse($endDate)->format('d M Y') : 'Hari ini' }}</span>
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-3">
                <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-2.5 text-right shadow-xs">
                    <p class="text-xs font-medium uppercase tracking-wider text-sky-700">Akun Aktif</p>
                    <p class="text-lg font-bold text-sky-900">{{ count($report['accounts']) }} Akun</p>
                </div>
            </div>
        </div>

        <!-- 4. Seksi Filter (Pencarian Dinamis) -->
        <section class="rounded-lg border border-gray-200 bg-gray-50/50 p-5 shadow-sm no-print">
            <form method="GET" action="/reports/accounting/ledger" class="grid gap-4 sm:grid-cols-2 {{ $isMaster ? 'lg:grid-cols-5' : 'lg:grid-cols-4' }} items-end">
                <!-- Dropdown Cabang Dinamis -->
                @if ($isMaster)
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 mb-1.5">Cabang / Toko</label>
                        <select name="branch_id" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm outline-none transition focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                            <option value="">Semua Cabang (Konsolidasi)</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" {{ (string) $branchId === (string) $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }} {{ $branch->code ? '('.$branch->code.')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 mb-1.5">Cabang / Toko</label>
                        <input type="text" readonly value="{{ $activeBranchName }}" class="w-full rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-600 shadow-sm outline-none cursor-not-allowed">
                        <input type="hidden" name="branch_id" value="{{ $branchId }}">
                    </div>
                @endif

                <!-- Pilihan Akun -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 mb-1.5">Akun Perkiraan</label>
                    <select name="account_id" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm outline-none transition focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                        <option value="">Semua Akun Aktif</option>
                        @foreach ($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ (string) $accountId === (string) $acc->id ? 'selected' : '' }}>
                                {{ $acc->code }} - {{ $acc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Dari Tanggal -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 mb-1.5">Dari Tanggal</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm outline-none transition focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                </div>

                <!-- Sampai Tanggal -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 mb-1.5">Sampai Tanggal</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm outline-none transition focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                </div>

                <!-- Tombol Submit & Reset -->
                <div class="flex items-center gap-2">
                    <button type="submit" class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Terapkan
                    </button>
                    <a href="/reports/accounting/ledger" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-200">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <!-- Daftar Akun Buku Besar -->
        <div class="space-y-8">
            @forelse ($report['accounts'] as $item)
                @php
                    $acc = $item['account'];
                @endphp
                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <!-- Header Akun (Flexbox Dua Sisi) -->
                    <div class="flex flex-col gap-4 border-b border-gray-200 bg-slate-50/60 px-6 py-4 xl:flex-row xl:items-center xl:justify-between">
                        <!-- Sisi Kiri -->
                        <div class="flex items-start gap-3">
                            <span class="inline-flex items-center rounded-md bg-slate-800 px-2 py-1 font-mono text-sm font-semibold text-white shadow-sm shrink-0">
                                {{ $acc['code'] }}
                            </span>
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 class="text-lg font-bold text-gray-900 leading-snug">{{ $acc['name'] }}</h3>
                                    <span class="inline-block rounded border border-gray-200 bg-white px-2 py-0.5 text-[11px] font-medium text-gray-600 uppercase">
                                        Saldo Normal: {{ $acc['normal_balance'] }}
                                    </span>
                                </div>
                                <p class="mt-0.5 text-xs text-gray-500">
                                    Tipe Akun: <span class="font-medium text-gray-700 uppercase">{{ $acc['type'] }}</span>
                                </p>
                            </div>
                        </div>

                        <!-- Sisi Kanan (Mini Stats 4 Kolom) -->
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
                            <!-- Saldo Awal -->
                            <div class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-right shadow-xs">
                                <span class="block text-[11px] font-medium uppercase tracking-wider text-gray-500">Saldo Awal</span>
                                <span class="block text-xs font-semibold tabular-nums {{ $item['beginning_balance'] < 0 ? 'text-red-600' : 'text-gray-900' }}">
                                    @if ($item['beginning_balance'] < 0)
                                        (Rp {{ number_format(abs($item['beginning_balance']), 0, ',', '.') }})
                                    @else
                                        Rp {{ number_format($item['beginning_balance'], 0, ',', '.') }}
                                    @endif
                                </span>
                            </div>

                            <!-- Total Debit -->
                            <div class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-right shadow-xs">
                                <span class="block text-[11px] font-medium uppercase tracking-wider text-gray-500">Total Debit</span>
                                <span class="block text-xs font-semibold tabular-nums text-sky-700">
                                    Rp {{ number_format($item['total_debit'], 0, ',', '.') }}
                                </span>
                            </div>

                            <!-- Total Kredit -->
                            <div class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-right shadow-xs">
                                <span class="block text-[11px] font-medium uppercase tracking-wider text-gray-500">Total Kredit</span>
                                <span class="block text-xs font-semibold tabular-nums text-amber-700">
                                    Rp {{ number_format($item['total_credit'], 0, ',', '.') }}
                                </span>
                            </div>

                            <!-- Saldo Akhir -->
                            <div class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-right shadow-sm">
                                <span class="block text-[11px] font-semibold uppercase tracking-wider text-blue-600">Saldo Akhir</span>
                                <span class="block text-xs font-bold tabular-nums {{ $item['ending_balance'] < 0 ? 'text-red-600' : 'text-blue-700' }}">
                                    @if ($item['ending_balance'] < 0)
                                        (Rp {{ number_format(abs($item['ending_balance']), 0, ',', '.') }})
                                    @else
                                        Rp {{ number_format($item['ending_balance'], 0, ',', '.') }}
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Tabel Transaksi (Visual Grouping & Badge Cabang) -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                            <thead class="bg-gray-100 text-xs font-semibold uppercase tracking-wider text-gray-600 border-b border-gray-200">
                                <tr>
                                    <th class="whitespace-nowrap px-5 py-3.5">Tanggal</th>
                                    <th class="whitespace-nowrap px-5 py-3.5">No. Referensi</th>
                                    <th class="px-5 py-3.5">Keterangan</th>
                                    <th class="px-5 py-3.5">Memo / Catatan</th>
                                    <th class="whitespace-nowrap px-5 py-3.5">Cabang / Toko</th>
                                    <th class="whitespace-nowrap px-5 py-3.5 text-right">Debit</th>
                                    <th class="whitespace-nowrap px-5 py-3.5 text-right">Kredit</th>
                                    <th class="whitespace-nowrap px-5 py-3.5 text-right">Saldo Berjalan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <!-- Baris Saldo Awal -->
                                <tr class="bg-amber-50/40 border-b border-gray-100 text-xs font-medium text-gray-600">
                                    <td class="whitespace-nowrap px-5 py-3 font-mono tabular-nums">{{ $startDate ? \Carbon\Carbon::parse($startDate)->format('d/m/Y') : '-' }}</td>
                                    <td class="px-5 py-3 font-mono text-gray-400">—</td>
                                    <td colspan="3" class="px-5 py-3 font-semibold text-gray-800">
                                        SALDO AWAL {{ $startDate ? '(SEBELUM ' . \Carbon\Carbon::parse($startDate)->format('d M Y') . ')' : '' }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right font-mono tabular-nums text-gray-400">—</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right font-mono tabular-nums text-gray-400">—</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right font-mono text-xs font-bold tabular-nums {{ $item['beginning_balance'] < 0 ? 'text-red-600' : 'text-gray-900' }}">
                                        @if ($item['beginning_balance'] < 0)
                                            (Rp {{ number_format(abs($item['beginning_balance']), 0, ',', '.') }})
                                        @else
                                            Rp {{ number_format($item['beginning_balance'], 0, ',', '.') }}
                                        @endif
                                    </td>
                                </tr>

                                @forelse ($item['lines'] as $line)
                                    <tr class="border-b border-gray-100 transition hover:bg-gray-50 text-sm">
                                        <td class="whitespace-nowrap px-5 py-3.5 font-mono text-xs text-gray-600 tabular-nums">
                                            {{ \Carbon\Carbon::parse($line['date'])->format('d/m/Y') }}
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3.5 font-mono text-xs font-semibold text-sky-700">
                                            {{ $line['reference_number'] }}
                                        </td>
                                        <td class="px-5 py-3.5 text-gray-800 font-medium text-sm">
                                            {{ $line['description'] }}
                                        </td>
                                        <td class="px-5 py-3.5 text-xs text-gray-500">
                                            {{ $line['memo'] ?? '—' }}
                                        </td>
                                        <!-- Badge Cabang dengan identitas visual unik -->
                                        <td class="whitespace-nowrap px-5 py-3.5 text-xs">
                                            <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold shadow-xs {{ getBranchBadgeStyle($line['branch_name']) }}">
                                                <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>
                                                {{ $line['branch_name'] }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3.5 text-right font-mono text-sm tabular-nums {{ $line['debit'] > 0 ? 'font-medium text-gray-900' : 'text-gray-300' }}">
                                            {{ $line['debit'] > 0 ? 'Rp ' . number_format($line['debit'], 0, ',', '.') : '—' }}
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3.5 text-right font-mono text-sm tabular-nums {{ $line['credit'] > 0 ? 'font-medium text-gray-900' : 'text-gray-300' }}">
                                            {{ $line['credit'] > 0 ? 'Rp ' . number_format($line['credit'], 0, ',', '.') : '—' }}
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3.5 text-right font-mono text-sm tabular-nums font-semibold {{ $line['balance'] < 0 ? 'text-red-600 font-bold' : 'text-gray-900' }}">
                                            @if ($line['balance'] < 0)
                                                (Rp {{ number_format(abs($line['balance']), 0, ',', '.') }})
                                            @else
                                                Rp {{ number_format($line['balance'], 0, ',', '.') }}
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-5 py-6 text-center text-xs text-gray-400 italic">
                                            Tidak ada mutasi transaksi pada periode yang dipilih.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <!-- Footer Ringkasan Akun -->
                            <tfoot class="border-t-2 border-gray-300 bg-slate-50 text-xs font-bold text-gray-900">
                                <tr>
                                    <td colspan="5" class="px-5 py-3.5 text-right uppercase tracking-wider text-gray-700">
                                        TOTAL MUTASI &amp; SALDO AKHIR {{ $acc['name'] }}:
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right font-mono text-sm tabular-nums text-sky-800">
                                        Rp {{ number_format($item['total_debit'], 0, ',', '.') }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right font-mono text-sm tabular-nums text-amber-800">
                                        Rp {{ number_format($item['total_credit'], 0, ',', '.') }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right font-mono text-sm tabular-nums bg-gray-100 font-extrabold {{ $item['ending_balance'] < 0 ? 'text-red-600' : 'text-gray-900' }}">
                                        @if ($item['ending_balance'] < 0)
                                            (Rp {{ number_format(abs($item['ending_balance']), 0, ',', '.') }})
                                        @else
                                            Rp {{ number_format($item['ending_balance'], 0, ',', '.') }}
                                        @endif
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center shadow-sm">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <h3 class="mt-4 text-base font-semibold text-gray-900">Tidak ada data buku besar</h3>
                    <p class="mt-1 text-sm text-gray-500">Belum ada transaksi atau saldo pada rentang tanggal dan cabang yang dipilih.</p>
                </div>
            @endforelse
        </div>

        <!-- Grand Total Summary -->
        @if (count($report['accounts']) > 0)
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Grand Total Mutasi Periode</p>
                        <p class="text-sm text-gray-600">Akumulasi seluruh debit dan kredit akun aktif pada periode ini</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-4 text-sm font-bold">
                        <div class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-2">
                            <span class="text-gray-500 font-medium text-xs block uppercase">Grand Total Debit</span>
                            <span class="font-mono text-sky-700 text-base font-bold tabular-nums">Rp {{ number_format($report['grand_total_debit'], 0, ',', '.') }}</span>
                        </div>
                        <div class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-2">
                            <span class="text-gray-500 font-medium text-xs block uppercase">Grand Total Kredit</span>
                            <span class="font-mono text-amber-700 text-base font-bold tabular-nums">Rp {{ number_format($report['grand_total_credit'], 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </main>
</body>
</html>
