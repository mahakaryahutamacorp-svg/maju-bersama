<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Laba Rugi (Income Statement) | Maju Bersama ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; }
            .print-break { page-break-after: always; }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    @php
        $revTotal = (float) ($report['revenue']['total'] ?? 0);
        $cogsTotal = (float) ($report['cogs']['total'] ?? 0);
        $grossProfit = (float) ($report['gross_profit'] ?? 0);
        $expTotal = (float) ($report['operating_expenses']['total'] ?? 0);
        $netProfit = (float) ($report['net_profit'] ?? 0);
        $gpm = (float) ($report['gross_profit_margin'] ?? 0);
        $npm = (float) ($report['net_profit_margin'] ?? 0);
        $isProfitable = $netProfit >= 0;
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
                <a href="/reports/accounting/ledger" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Buku Besar
                </a>
                <a href="/reports/accounting/trial-balance" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Neraca Saldo
                </a>
                <a href="/reports/accounting/income-statement" class="rounded-lg bg-sky-600 px-3.5 py-2 font-semibold text-white shadow-sm">
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
        <!-- Judul & Breadcrumb -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Laporan Keuangan</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Laporan Laba Rugi (Income Statement)</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Cakupan: <span class="font-semibold text-slate-800">{{ $report['branch']['name'] }}</span>
                    @if ($startDate || $endDate)
                        &middot; Periode: <span class="font-semibold text-slate-800">{{ $startDate ? \Carbon\Carbon::parse($startDate)->format('d M Y') : 'Awal' }}</span> s/d <span class="font-semibold text-slate-800">{{ $endDate ? \Carbon\Carbon::parse($endDate)->format('d M Y') : 'Hari ini' }}</span>
                    @endif
                </p>
            </div>

            <!-- Status Laba / Rugi Badge -->
            <div class="flex items-center gap-3">
                <div class="rounded-xl border {{ $isProfitable ? 'border-emerald-200 bg-emerald-50/70' : 'border-rose-200 bg-rose-50/70' }} px-4 py-2.5 text-right">
                    <p class="text-xs font-semibold uppercase tracking-wider {{ $isProfitable ? 'text-emerald-700' : 'text-rose-700' }}">
                        {{ $isProfitable ? 'Status: Surplus (Laba)' : 'Status: Defisit (Rugi)' }}
                    </p>
                    <p class="text-lg font-bold font-mono {{ $isProfitable ? 'text-emerald-950' : 'text-rose-950' }}">
                        Rp {{ number_format($netProfit, 0, ',', '.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Highlight Kartu Eksekutif & Rasio Profitabilitas -->
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- 1. Pendapatan Penjualan -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Pendapatan Usaha</span>
                    <span class="rounded-md bg-sky-50 px-2 py-0.5 text-[11px] font-bold text-sky-700">100% Basis</span>
                </div>
                <p class="mt-3 text-2xl font-bold font-mono text-slate-950">
                    Rp {{ number_format($revTotal, 0, ',', '.') }}
                </p>
                <p class="mt-1 text-xs text-slate-500">Total pendapatan bruto periode</p>
            </div>

            <!-- 2. Laba Kotor & Gross Profit Margin -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Laba Kotor</span>
                    <!-- Badge Rasio Gross Profit Margin -->
                    <span class="inline-flex items-center gap-1 rounded-full border border-sky-200 bg-sky-50 px-2.5 py-0.5 text-xs font-extrabold text-sky-800">
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        GPM: {{ number_format($gpm, 2, ',', '.') }}%
                    </span>
                </div>
                <p class="mt-3 text-2xl font-bold font-mono text-slate-950">
                    Rp {{ number_format($grossProfit, 0, ',', '.') }}
                </p>
                <p class="mt-1 text-xs text-slate-500">Margin kotor setelah HPP</p>
            </div>

            <!-- 3. Beban Operasional -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Beban Operasional</span>
                    <span class="rounded-md bg-amber-50 px-2 py-0.5 text-[11px] font-bold text-amber-700">
                        {{ $revTotal > 0 ? number_format(($expTotal / $revTotal) * 100, 1, ',', '.') : '0' }}% Omzet
                    </span>
                </div>
                <p class="mt-3 text-2xl font-bold font-mono text-slate-950">
                    Rp {{ number_format($expTotal, 0, ',', '.') }}
                </p>
                <p class="mt-1 text-xs text-slate-500">Beban operasional &amp; umum</p>
            </div>

            <!-- 4. Laba Bersih & Net Profit Margin -->
            <div class="rounded-2xl border {{ $isProfitable ? 'border-emerald-300 bg-emerald-50/40' : 'border-rose-300 bg-rose-50/40' }} p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider {{ $isProfitable ? 'text-emerald-800' : 'text-rose-800' }}">
                        {{ $isProfitable ? 'Laba Bersih' : 'Rugi Bersih' }}
                    </span>
                    <!-- Badge Rasio Net Profit Margin -->
                    <span class="inline-flex items-center gap-1 rounded-full border {{ $isProfitable ? 'border-emerald-300 bg-emerald-100 text-emerald-900' : 'border-rose-300 bg-rose-100 text-rose-900' }} px-2.5 py-0.5 text-xs font-extrabold">
                        @if ($isProfitable)
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        @else
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/></svg>
                        @endif
                        NPM: {{ number_format($npm, 2, ',', '.') }}%
                    </span>
                </div>
                <p class="mt-3 text-2xl font-bold font-mono {{ $isProfitable ? 'text-emerald-950' : 'text-rose-950' }}">
                    Rp {{ number_format($netProfit, 0, ',', '.') }}
                </p>
                <p class="mt-1 text-xs {{ $isProfitable ? 'text-emerald-700' : 'text-rose-700' }}">
                    Laba bersih final periode berjalan
                </p>
            </div>
        </section>

        <!-- Form Filter -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm no-print">
            <form method="GET" action="/reports/accounting/income-statement" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Pilihan Cabang (Hanya untuk Master) -->
                @if ($isMaster)
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Cabang</label>
                        <select name="branch_id" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                            <option value="">Semua Cabang (Konsolidasi)</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" {{ (string) $branchId === (string) $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <!-- Dari Tanggal -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Dari Tanggal</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                </div>

                <!-- Sampai Tanggal -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Sampai Tanggal</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                </div>

                <!-- Tombol Submit & Reset -->
                <div class="flex items-end gap-2 {{ !$isMaster ? 'sm:col-span-2 lg:col-span-2' : '' }}">
                    <button type="submit" class="w-full rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                        Terapkan
                    </button>
                    <a href="/reports/accounting/income-statement" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <!-- Lembar Laporan Keuangan Laba Rugi -->
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <!-- Header Dokumen Laporan -->
            <div class="border-b border-slate-200 bg-slate-900 px-6 py-5 text-white">
                <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                    <div>
                        <h3 class="text-lg font-bold tracking-tight">Rincian Laporan Laba Rugi Komprehensif</h3>
                        <p class="text-xs text-slate-400">
                            Format Standar Akuntansi Keuangan Entitas Tanpa Akuntabilitas Publik (SAK ETAP)
                        </p>
                    </div>
                    <div class="text-xs font-mono text-slate-300 sm:text-right">
                        <span>Basis: Akrual</span> &middot; <span>Mata Uang: IDR (Rp)</span>
                    </div>
                </div>
            </div>

            <div class="p-6 space-y-6">
                <!-- ================= BAGIAN I: PENDAPATAN OPERASIONAL ================= -->
                <div>
                    <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                        <div class="flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-slate-900 text-xs font-bold text-amber-400">1</span>
                            <h4 class="text-sm font-bold uppercase tracking-wider text-slate-900">Pendapatan Usaha (Revenue)</h4>
                        </div>
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Nominal (Rp)</span>
                    </div>

                    <table class="min-w-full divide-y divide-slate-100 text-sm mt-2">
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($report['revenue']['accounts'] as $acc)
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="py-2.5 px-3 font-mono text-xs font-semibold text-slate-600 w-28">
                                        {{ $acc['code'] }}
                                    </td>
                                    <td class="py-2.5 px-3 text-slate-800 font-medium">
                                        {{ $acc['name'] }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono text-slate-900 font-semibold w-48">
                                        Rp {{ number_format($acc['amount'], 0, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-3 px-3 text-xs text-slate-400 italic">
                                        Tidak ada akun pendapatan yang tercatat pada periode ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <!-- Subtotal Pendapatan -->
                        <tfoot class="border-t border-slate-300">
                            <tr class="bg-slate-50/80 font-bold text-sm">
                                <td colspan="2" class="py-3 px-3 text-slate-900 uppercase tracking-wider">
                                    Total Pendapatan Usaha (A)
                                </td>
                                <td class="py-3 px-3 text-right font-mono text-sky-950 text-base">
                                    Rp {{ number_format($revTotal, 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- ================= BAGIAN II: HARGA POKOK PENJUALAN (HPP) ================= -->
                <div>
                    <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                        <div class="flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-slate-900 text-xs font-bold text-amber-400">2</span>
                            <h4 class="text-sm font-bold uppercase tracking-wider text-slate-900">Harga Pokok Penjualan (HPP / COGS)</h4>
                        </div>
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Nominal (Rp)</span>
                    </div>

                    <table class="min-w-full divide-y divide-slate-100 text-sm mt-2">
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($report['cogs']['accounts'] as $acc)
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="py-2.5 px-3 font-mono text-xs font-semibold text-slate-600 w-28">
                                        {{ $acc['code'] }}
                                    </td>
                                    <td class="py-2.5 px-3 text-slate-800 font-medium">
                                        {{ $acc['name'] }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono text-slate-900 font-semibold w-48">
                                        Rp {{ number_format($acc['amount'], 0, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-3 px-3 text-xs text-slate-400 italic">
                                        Tidak ada pencatatan HPP pada periode ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <!-- Subtotal HPP -->
                        <tfoot class="border-t border-slate-300">
                            <tr class="bg-slate-50/80 font-bold text-sm">
                                <td colspan="2" class="py-3 px-3 text-slate-900 uppercase tracking-wider">
                                    Total Harga Pokok Penjualan (B)
                                </td>
                                <td class="py-3 px-3 text-right font-mono text-amber-950 text-base">
                                    Rp {{ number_format($cogsTotal, 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- ================= BARIS LABA KOTOR (GROSS PROFIT) ================= -->
                <div class="rounded-xl border border-sky-200 bg-gradient-to-r from-sky-50 via-sky-50/60 to-white p-4 shadow-sm">
                    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sky-600 text-white font-bold">
                                =
                            </div>
                            <div>
                                <h5 class="text-base font-extrabold uppercase tracking-wide text-sky-950">
                                    LABA KOTOR (GROSS PROFIT) [A - B]
                                </h5>
                                <p class="text-xs text-sky-800">
                                    Pendapatan dikurangi Biaya Pokok Penjualan
                                </p>
                            </div>
                        </div>

                        <!-- Tampilan Rasio Gross Profit Margin -->
                        <div class="flex items-center gap-4 text-right">
                            <div class="rounded-lg border border-sky-300 bg-white px-3 py-1.5 shadow-xs">
                                <span class="block text-[10px] font-bold uppercase tracking-wider text-sky-600">Gross Profit Margin</span>
                                <span class="text-sm font-extrabold font-mono text-sky-950">{{ number_format($gpm, 2, ',', '.') }}%</span>
                            </div>
                            <div>
                                <span class="text-xl font-extrabold font-mono text-sky-950 sm:text-2xl">
                                    Rp {{ number_format($grossProfit, 0, ',', '.') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================= BAGIAN III: BEBAN OPERASIONAL ================= -->
                <div>
                    <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                        <div class="flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-slate-900 text-xs font-bold text-amber-400">3</span>
                            <h4 class="text-sm font-bold uppercase tracking-wider text-slate-900">Beban Operasional &amp; Umum (Operating Expenses)</h4>
                        </div>
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Nominal (Rp)</span>
                    </div>

                    <table class="min-w-full divide-y divide-slate-100 text-sm mt-2">
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($report['operating_expenses']['accounts'] as $acc)
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="py-2.5 px-3 font-mono text-xs font-semibold text-slate-600 w-28">
                                        {{ $acc['code'] }}
                                    </td>
                                    <td class="py-2.5 px-3 text-slate-800 font-medium">
                                        {{ $acc['name'] }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono text-slate-900 font-semibold w-48">
                                        Rp {{ number_format($acc['amount'], 0, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-3 px-3 text-xs text-slate-400 italic">
                                        Tidak ada beban operasional yang tercatat pada periode ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <!-- Subtotal Beban Operasional -->
                        <tfoot class="border-t border-slate-300">
                            <tr class="bg-slate-50/80 font-bold text-sm">
                                <td colspan="2" class="py-3 px-3 text-slate-900 uppercase tracking-wider">
                                    Total Beban Operasional (C)
                                </td>
                                <td class="py-3 px-3 text-right font-mono text-slate-950 text-base">
                                    Rp {{ number_format($expTotal, 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- ================= BARIS LABA BERSIH (NET PROFIT) FINAL ================= -->
                <div class="rounded-2xl border-2 {{ $isProfitable ? 'border-emerald-500 bg-gradient-to-r from-emerald-950 to-slate-950' : 'border-rose-500 bg-gradient-to-r from-rose-950 to-slate-950' }} p-6 text-white shadow-lg">
                    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="rounded px-2.5 py-0.5 text-xs font-extrabold uppercase tracking-wider {{ $isProfitable ? 'bg-emerald-500 text-slate-950' : 'bg-rose-500 text-white' }}">
                                    {{ $isProfitable ? 'LABA BERSIH' : 'RUGI BERSIH' }}
                                </span>
                                <h4 class="text-lg font-bold tracking-tight text-white">
                                    HASIL BERSIH TAHUN/PERIODE BERJALAN [Laba Kotor - C]
                                </h4>
                            </div>
                            <p class="mt-1 text-xs text-slate-300">
                                Keuntungan bersih final perusahaan setelah memperhitungkan seluruh pendapatan, beban pokok, dan operasional.
                            </p>
                        </div>

                        <!-- Nominal & Rasio Net Profit Margin -->
                        <div class="flex flex-wrap items-center gap-4 text-right">
                            <!-- Rasio Net Profit Margin Badge -->
                            <div class="rounded-xl border border-white/20 bg-white/10 px-4 py-2 backdrop-blur-sm">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-300">Net Profit Margin (NPM)</p>
                                <p class="text-base font-extrabold font-mono {{ $isProfitable ? 'text-emerald-400' : 'text-rose-400' }}">
                                    {{ number_format($npm, 2, ',', '.') }}%
                                </p>
                            </div>

                            <!-- Nominal Nilai Laba/Rugi -->
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-300">Total Nilai Bersih</p>
                                <p class="text-2xl font-extrabold font-mono text-amber-300 sm:text-3xl">
                                    Rp {{ number_format($netProfit, 0, ',', '.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Panel Analisis Margin & Profitabilitas Rapi -->
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Analisis Rasio Profitabilitas Perusahaan</h3>
                    <p class="text-xs text-slate-500">Evaluasi efisiensi operasional dan konversi pendapatan menjadi laba</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                    KPI Keuangan
                </span>
            </div>

            <div class="mt-6 grid gap-6 md:grid-cols-2">
                <!-- Analisis Gross Profit Margin -->
                <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-600">Gross Profit Margin (GPM)</span>
                        <span class="text-base font-extrabold font-mono text-sky-700">{{ number_format($gpm, 2, ',', '.') }}%</span>
                    </div>
                    <!-- Bar Visual -->
                    <div class="mt-2 h-3 w-full overflow-hidden rounded-full bg-slate-200">
                        <div class="h-full rounded-full bg-sky-600 transition-all duration-500" style="width: {{ min(max($gpm, 0), 100) }}%"></div>
                    </div>
                    <div class="mt-3 flex items-center justify-between text-xs text-slate-500">
                        <span>Rumus: (Laba Kotor / Pendapatan) &times; 100%</span>
                        <span class="font-mono text-slate-700">Rp {{ number_format($grossProfit, 0, ',', '.') }} / Rp {{ number_format($revTotal, 0, ',', '.') }}</span>
                    </div>
                    <p class="mt-2 text-xs text-slate-600">
                        Menunjukkan bahwa setiap Rp 100 pendapatan menghasilkan <strong>Rp {{ number_format($gpm, 2, ',', '.') }}</strong> laba kotor sebelum dikurangi beban operasional.
                    </p>
                </div>

                <!-- Analisis Net Profit Margin -->
                <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-600">Net Profit Margin (NPM)</span>
                        <span class="text-base font-extrabold font-mono {{ $isProfitable ? 'text-emerald-700' : 'text-rose-700' }}">
                            {{ number_format($npm, 2, ',', '.') }}%
                        </span>
                    </div>
                    <!-- Bar Visual -->
                    <div class="mt-2 h-3 w-full overflow-hidden rounded-full bg-slate-200">
                        <div class="h-full rounded-full {{ $isProfitable ? 'bg-emerald-600' : 'bg-rose-600' }} transition-all duration-500" style="width: {{ min(max($npm, 0), 100) }}%"></div>
                    </div>
                    <div class="mt-3 flex items-center justify-between text-xs text-slate-500">
                        <span>Rumus: (Laba Bersih / Pendapatan) &times; 100%</span>
                        <span class="font-mono text-slate-700">Rp {{ number_format($netProfit, 0, ',', '.') }} / Rp {{ number_format($revTotal, 0, ',', '.') }}</span>
                    </div>
                    <p class="mt-2 text-xs text-slate-600">
                        @if ($isProfitable)
                            Menunjukkan efisiensi akhir perusahaan, di mana setiap Rp 100 penjualan menyisakan <strong>Rp {{ number_format($npm, 2, ',', '.') }}</strong> keuntungan bersih kas/ekuitas.
                        @else
                            Perusahaan mengalami defisit operasional sebesar <strong>{{ number_format(abs($npm), 2, ',', '.') }}%</strong> dari total pendapatan periode ini.
                        @endif
                    </p>
                </div>
            </div>
        </section>

        <!-- Area Tanda Tangan Cetak (Hanya Tampil Saat Print) -->
        <div class="hidden print:block pt-10">
            <div class="grid grid-cols-3 gap-8 text-center text-xs">
                <div>
                    <p class="text-slate-500">Disusun Oleh,</p>
                    <div class="h-20"></div>
                    <p class="font-bold text-slate-900 border-t border-slate-400 pt-1">Staff Akuntansi</p>
                </div>
                <div>
                    <p class="text-slate-500">Diperiksa Oleh,</p>
                    <div class="h-20"></div>
                    <p class="font-bold text-slate-900 border-t border-slate-400 pt-1">Supervisor Keuangan</p>
                </div>
                <div>
                    <p class="text-slate-500">Disetujui Oleh,</p>
                    <div class="h-20"></div>
                    <p class="font-bold text-slate-900 border-t border-slate-400 pt-1">Pimpinan / Direksi</p>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
