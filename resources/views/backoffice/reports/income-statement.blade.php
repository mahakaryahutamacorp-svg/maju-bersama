<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laba Rugi Standar | Maju Bersama ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        .font-mono-num { font-family: 'JetBrains Mono', monospace; font-variant-numeric: tabular-nums; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; padding: 0 !important; }
            .print-container { box-shadow: none !important; border: none !important; width: 100% !important; max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
            .print-break { page-break-after: always; }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased flex flex-col">
    @php
        $formatRupiah = function ($val) {
            $isNeg = $val < 0;
            $formatted = number_format(abs($val), 2, ',', '.');
            return ($isNeg ? '(Rp ' . $formatted . ')' : 'Rp ' . $formatted);
        };

        $revTotal = (float) ($report['revenue']['total'] ?? 0);
        $cogsTotal = (float) ($report['cogs']['total'] ?? 0);
        $grossProfit = (float) ($report['gross_profit'] ?? 0);
        $expTotal = (float) ($report['operating_expenses']['total'] ?? 0);
        $netProfit = (float) ($report['net_profit'] ?? 0);
        $isProfitable = $netProfit >= 0;
    @endphp

    <!-- Header Bar Utama (Aplikasi) -->
    <header class="border-b border-slate-800 bg-slate-950 text-white shrink-0 no-print">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3.5 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                <a href="/backoffice" class="block group">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-amber-400 group-hover:text-amber-300 transition">Maju Bersama ERP</p>
                    <div class="flex items-center gap-2">
                        <h1 class="text-base font-bold tracking-tight">Pusat Laporan</h1>
                        <span class="rounded bg-slate-800 px-1.5 py-0.5 text-[10px] font-mono text-slate-300">Income Statement</span>
                    </div>
                </a>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('reports.index') }}" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800 hover:text-white transition">
                    ← Kembali ke Pusat Laporan
                </a>
                <div class="rounded-full border border-sky-400/30 bg-sky-400/10 px-3 py-1 text-xs font-medium text-sky-200 hidden sm:inline-flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    <span>{{ $currentUser->branch?->name ?? 'Semua Cabang' }}</span>
                </div>
            </div>
        </div>
    </header>

    <!-- Filter Control Bar (Sticky & No-Print) -->
    <div class="border-b border-slate-200 bg-white shadow-xs shrink-0 no-print">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-3.5 sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8">
            <div>
                <div class="flex items-center gap-2">
                    <span class="p-1 rounded bg-emerald-100 text-emerald-800">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </span>
                    <h2 class="text-lg font-bold text-slate-900">Laba Rugi Standar</h2>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Laporan ringkasan pendapatan, beban pokok penjualan, dan beban operasional</p>
            </div>

            <!-- Form Filter Rentang Tanggal & Cabang -->
            <form method="GET" action="{{ route('reports.income-statement') }}" class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-1.5 bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs">
                    <label for="start_date" class="text-slate-500 font-medium">Dari:</label>
                    <input type="date" id="start_date" name="start_date" value="{{ $startDate }}" class="bg-transparent text-slate-800 font-semibold focus:outline-none">
                </div>

                <div class="flex items-center gap-1.5 bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs">
                    <label for="end_date" class="text-slate-500 font-medium">Sampai:</label>
                    <input type="date" id="end_date" name="end_date" value="{{ $endDate }}" class="bg-transparent text-slate-800 font-semibold focus:outline-none">
                </div>

                @if ($isMaster)
                    <div class="flex items-center gap-1.5 bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs">
                        <label for="branch_id" class="text-slate-500 font-medium">Cabang:</label>
                        <select id="branch_id" name="branch_id" class="bg-transparent text-slate-800 font-semibold focus:outline-none">
                            <option value="">Semua Cabang (Konsolidasi)</option>
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}" {{ (string)$selectedBranchId === (string)$b->id ? 'selected' : '' }}>
                                    {{ $b->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3.5 py-2 text-xs font-bold text-white shadow-xs hover:bg-slate-800 focus:outline-none transition">
                    <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    <span>Filter</span>
                </button>

                <!-- Tombol Sekunder Cetak -->
                <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-100 hover:text-slate-900 focus:outline-none transition">
                    <span>🖨️ Cetak</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Konten Kertas Laporan (Card Putih / Layout Cetak) -->
    <main class="flex-1 mx-auto w-full max-w-4xl p-4 sm:p-6 lg:p-8 flex flex-col justify-start">
        <div class="print-container rounded-2xl border border-slate-300 bg-white shadow-lg p-6 sm:p-10 lg:p-12 text-slate-900">
            
            <!-- KOP PERUSAHAAN (MAJU BERSAMA GRUP) -->
            <header class="border-b-2 border-slate-900 pb-5 mb-6">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold tracking-[0.2em] text-amber-600 uppercase">Sistem Keuangan &amp; Akuntansi Terintegrasi</p>
                        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-950 uppercase mt-0.5">MAJU BERSAMA GRUP</h1>
                        <p class="text-xs text-slate-500 mt-1">Multi-Store Agriculture &amp; FMCG Retail Network</p>
                    </div>
                    <div class="sm:text-right">
                        <span class="inline-block rounded-md bg-slate-900 text-white font-mono text-xs px-2.5 py-1 font-bold tracking-wider uppercase">
                            LAPORAN LABA RUGI
                        </span>
                        <p class="text-xs font-semibold text-slate-700 mt-1.5">Standar Akuntansi Keuangan</p>
                    </div>
                </div>

                <!-- Informasi Periode & Cabang -->
                <div class="mt-4 pt-3 border-t border-slate-200 grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                    <div>
                        <span class="text-slate-500">Periode Pelaporan:</span>
                        <span class="font-bold text-slate-900 ml-1">
                            {{ \Carbon\Carbon::parse($startDate)->translatedFormat('d F Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->translatedFormat('d F Y') }}
                        </span>
                    </div>
                    <div class="sm:text-right">
                        <span class="text-slate-500">Unit / Entitas:</span>
                        <span class="font-bold text-slate-900 ml-1">{{ $report['branch_name'] }}</span>
                    </div>
                </div>
            </header>

            <!-- TABEL FINANSIAL HIERARKIS -->
            <div class="space-y-6">

                <!-- 1. BLOK PENDAPATAN (REVENUE) -->
                <section>
                    <div class="bg-slate-100/80 px-3 py-1.5 rounded border-l-4 border-emerald-600 mb-2">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">1. Pendapatan Usaha (Revenue)</h3>
                    </div>
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-slate-400 border-b border-slate-200 text-left">
                                <th class="py-1.5 font-medium pl-3 w-24">Kode Akun</th>
                                <th class="py-1.5 font-medium">Nama Akun</th>
                                <th class="py-1.5 font-medium text-right pr-3 w-44">Jumlah (IDR)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($report['revenue']['accounts'] as $acc)
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-2 pl-3 font-mono text-slate-500">{{ $acc['code'] }}</td>
                                    <td class="py-2 font-medium text-slate-800">{{ $acc['name'] }}</td>
                                    <td class="py-2 pr-3 text-right font-mono-num font-semibold text-slate-900">{{ $formatRupiah($acc['amount']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-3 text-center text-slate-400 italic">Tidak ada transaksi akun pendapatan pada periode ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-slate-300 font-bold bg-slate-50/50">
                                <td colspan="2" class="py-2.5 pl-3 text-slate-900 uppercase">Total Pendapatan Usaha</td>
                                <td class="py-2.5 pr-3 text-right font-mono-num text-sm text-emerald-800">
                                    {{ $formatRupiah($revTotal) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </section>

                <!-- 2. BLOK BEBAN POKOK PENJUALAN (HPP / COGS) -->
                <section>
                    <div class="bg-slate-100/80 px-3 py-1.5 rounded border-l-4 border-amber-600 mb-2">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">2. Harga Pokok Penjualan (COGS)</h3>
                    </div>
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-slate-400 border-b border-slate-200 text-left">
                                <th class="py-1.5 font-medium pl-3 w-24">Kode Akun</th>
                                <th class="py-1.5 font-medium">Nama Akun</th>
                                <th class="py-1.5 font-medium text-right pr-3 w-44">Jumlah (IDR)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($report['cogs']['accounts'] as $acc)
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-2 pl-3 font-mono text-slate-500">{{ $acc['code'] }}</td>
                                    <td class="py-2 font-medium text-slate-800">{{ $acc['name'] }}</td>
                                    <td class="py-2 pr-3 text-right font-mono-num font-semibold text-slate-900">{{ $formatRupiah($acc['amount']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-3 text-center text-slate-400 italic">Tidak ada transaksi akun HPP pada periode ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-slate-300 font-bold bg-slate-50/50">
                                <td colspan="2" class="py-2.5 pl-3 text-slate-900 uppercase">Total Beban Pokok Penjualan</td>
                                <td class="py-2.5 pr-3 text-right font-mono-num text-sm text-slate-900">
                                    {{ $formatRupiah($cogsTotal) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </section>

                <!-- 3. BARIS LABA KOTOR (GROSS PROFIT) -->
                <div class="rounded-xl border-2 border-slate-300 bg-slate-100/90 px-4 py-3 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-900">Laba Kotor (Gross Profit)</span>
                        <p class="text-[11px] text-slate-500">Pendapatan Bersih dikurangi Harga Pokok Penjualan</p>
                    </div>
                    <div class="text-right">
                        <span class="font-mono-num text-base font-extrabold {{ $grossProfit >= 0 ? 'text-slate-950' : 'text-rose-700' }}">
                            {{ $formatRupiah($grossProfit) }}
                        </span>
                        @if ($revTotal > 0)
                            <p class="text-[10px] font-mono text-slate-500">Margin: {{ $report['gross_margin'] }}%</p>
                        @endif
                    </div>
                </div>

                <!-- 4. BLOK BEBAN OPERASIONAL (OPERATING EXPENSES) -->
                <section>
                    <div class="bg-slate-100/80 px-3 py-1.5 rounded border-l-4 border-rose-600 mb-2">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">3. Beban Operasional (Operating Expenses)</h3>
                    </div>
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-slate-400 border-b border-slate-200 text-left">
                                <th class="py-1.5 font-medium pl-3 w-24">Kode Akun</th>
                                <th class="py-1.5 font-medium">Nama Akun</th>
                                <th class="py-1.5 font-medium text-right pr-3 w-44">Jumlah (IDR)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($report['operating_expenses']['accounts'] as $acc)
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-2 pl-3 font-mono text-slate-500">{{ $acc['code'] }}</td>
                                    <td class="py-2 font-medium text-slate-800">{{ $acc['name'] }}</td>
                                    <td class="py-2 pr-3 text-right font-mono-num font-semibold text-slate-900">{{ $formatRupiah($acc['amount']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-3 text-center text-slate-400 italic">Tidak ada transaksi akun beban operasional pada periode ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-slate-300 font-bold bg-slate-50/50">
                                <td colspan="2" class="py-2.5 pl-3 text-slate-900 uppercase">Total Beban Operasional</td>
                                <td class="py-2.5 pr-3 text-right font-mono-num text-sm text-rose-800">
                                    {{ $formatRupiah($expTotal) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </section>

                <!-- 5. BARIS LABA BERSIH (NET PROFIT) - Highlight Hijau / Merah Tipis -->
                <div class="rounded-xl px-5 py-4 flex items-center justify-between border-2 {{ $isProfitable ? 'bg-emerald-50/80 border-emerald-400 text-emerald-950' : 'bg-rose-50/80 border-rose-400 text-rose-950' }}">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-extrabold uppercase tracking-wide">
                                {{ $isProfitable ? 'LABA BERSIH (NET PROFIT)' : 'RUGI BERSIH (NET LOSS)' }}
                            </span>
                            <span class="rounded px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $isProfitable ? 'bg-emerald-200/70 text-emerald-900' : 'bg-rose-200/70 text-rose-900' }}">
                                {{ $isProfitable ? 'Surplus' : 'Defisit' }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-600 mt-0.5">Laba Kotor dikurangi Total Beban Operasional</p>
                    </div>
                    <div class="text-right">
                        <span class="font-mono-num text-lg sm:text-xl font-extrabold {{ $isProfitable ? 'text-emerald-900' : 'text-rose-900' }}">
                            {{ $formatRupiah($netProfit) }}
                        </span>
                        @if ($revTotal > 0)
                            <p class="text-[11px] font-mono {{ $isProfitable ? 'text-emerald-700' : 'text-rose-700' }}">
                                Net Margin: {{ $report['net_margin'] }}%
                            </p>
                        @endif
                    </div>
                </div>

            </div>

            <!-- LEMBAR PENGESAHAN / TANDA TANGAN (PRINT & ARSIP) -->
            <footer class="mt-12 pt-8 border-t border-slate-200 grid grid-cols-2 gap-8 text-xs text-center text-slate-600">
                <div>
                    <p class="text-slate-400">Dibuat Oleh:</p>
                    <div class="h-16"></div>
                    <p class="font-bold text-slate-900 border-t border-slate-300 mx-auto w-40 pt-1.5">{{ $currentUser->name }}</p>
                    <p class="text-[10px] text-slate-400">Bagian Keuangan / Akuntansi</p>
                </div>
                <div>
                    <p class="text-slate-400">Disetujui Oleh:</p>
                    <div class="h-16"></div>
                    <p class="font-bold text-slate-900 border-t border-slate-300 mx-auto w-40 pt-1.5">( ............................................ )</p>
                    <p class="text-[10px] text-slate-400">Pimpinan / Kepala Cabang</p>
                </div>
            </footer>

            <div class="mt-8 text-center text-[10px] text-slate-400 border-t border-slate-100 pt-3">
                Dicetak pada: {{ now()->translatedFormat('d F Y, H:i:s') }} WIB &bull; Sistem ERP Maju Bersama POS-Accounting
            </div>

        </div>
    </main>
</body>
</html>
