<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan | Maju Bersama ERP</title>
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

        $sales = $report['sales'] ?? collect([]);
        $totalSales = $report['total_sales'] ?? 0;
        $totalTransactions = $report['total_transactions'] ?? 0;
        $totalItemsSold = $report['total_items_sold'] ?? 0;
        $averagePerTransaction = $report['average_per_transaction'] ?? 0;
        $cashSales = $report['cash_sales'] ?? 0;
        $nonCashSales = $report['non_cash_sales'] ?? 0;
    @endphp

    <!-- Header Bar Utama (Aplikasi) -->
    <header class="border-b border-slate-800 bg-slate-950 text-white shrink-0 no-print">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3.5 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                <a href="/backoffice" class="block group">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-amber-400 group-hover:text-amber-300 transition">Maju Bersama ERP</p>
                    <div class="flex items-center gap-2">
                        <h1 class="text-base font-bold tracking-tight">Pusat Laporan</h1>
                        <span class="rounded bg-slate-800 px-1.5 py-0.5 text-[10px] font-mono text-slate-300">Sales Report</span>
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
                    <span class="p-1 rounded bg-sky-100 text-sky-800">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </span>
                    <h2 class="text-lg font-bold text-slate-900">Laporan Penjualan Operasional</h2>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Daftar transaksi kasir POS, faktur penjualan, metode bayar, dan total penjualan bersih</p>
            </div>

            <!-- Form Filter Rentang Tanggal & Cabang -->
            <form method="GET" action="{{ route('reports.sales') }}" class="flex flex-wrap items-center gap-2">
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

                <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-100 hover:text-slate-900 focus:outline-none transition">
                    <span>🖨️ Cetak</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Main Content Paper Container -->
    <main class="flex-1 mx-auto w-full max-w-6xl p-4 sm:p-6 lg:p-8 flex flex-col justify-start">
        <div class="print-container rounded-2xl border border-slate-300 bg-white shadow-lg p-6 sm:p-8 lg:p-10 text-slate-900">
            
            <!-- KOP PERUSAHAAN (MAJU BERSAMA GRUP) -->
            <header class="border-b-2 border-slate-900 pb-5 mb-6">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold tracking-[0.2em] text-amber-600 uppercase">Sistem Penjualan &amp; Kasir Terintegrasi</p>
                        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-950 uppercase mt-0.5">MAJU BERSAMA GRUP</h1>
                        <p class="text-xs text-slate-500 mt-1">Multi-Store Agriculture &amp; FMCG Retail Network</p>
                    </div>
                    <div class="sm:text-right">
                        <span class="inline-block rounded-md bg-slate-900 text-white font-mono text-xs px-2.5 py-1 font-bold tracking-wider uppercase">
                            LAPORAN PENJUALAN
                        </span>
                        <p class="text-xs font-semibold text-slate-700 mt-1.5">Ringkasan &amp; Rincian Transaksi</p>
                    </div>
                </div>

                <!-- Metadata Laporan -->
                <div class="mt-4 pt-3 border-t border-slate-200 grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                    <div>
                        <span class="text-slate-500">Periode:</span>
                        <span class="font-semibold text-slate-800 ml-1">{{ \Carbon\Carbon::parse($startDate)->isoFormat('D MMMM Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->isoFormat('D MMMM Y') }}</span>
                    </div>
                    <div class="sm:text-right">
                        <span class="text-slate-500">Entitas / Cabang:</span>
                        <span class="font-semibold text-slate-800 ml-1">
                            @if ($selectedBranchId)
                                {{ $branches->firstWhere('id', $selectedBranchId)?->name ?? 'Cabang Terpilih' }}
                            @else
                                Seluruh Cabang (Konsolidasi)
                            @endif
                        </span>
                    </div>
                </div>
            </header>

            <!-- KPI Summary Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
                <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-3.5">
                    <p class="text-[11px] font-semibold uppercase text-slate-500">Total Transaksi</p>
                    <p class="text-xl font-extrabold text-slate-900 mt-1">{{ number_format($totalTransactions, 0, ',', '.') }}</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">{{ number_format($totalItemsSold, 0, ',', '.') }} item terjual</p>
                </div>

                <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-3.5">
                    <p class="text-[11px] font-semibold uppercase text-emerald-800">Total Penjualan Bersih</p>
                    <p class="text-xl font-extrabold text-emerald-700 font-mono-num mt-1">{{ $formatRupiah($totalSales) }}</p>
                    <p class="text-[10px] text-emerald-600 mt-0.5">Omzet Periode Ini</p>
                </div>

                <div class="rounded-xl border border-sky-200 bg-sky-50/50 p-3.5">
                    <p class="text-[11px] font-semibold uppercase text-sky-800">Penjualan Tunai (Cash)</p>
                    <p class="text-lg font-bold text-sky-700 font-mono-num mt-1">{{ $formatRupiah($cashSales) }}</p>
                    <p class="text-[10px] text-sky-600 mt-0.5">Kasir Fisik</p>
                </div>

                <div class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-3.5">
                    <p class="text-[11px] font-semibold uppercase text-indigo-800">Non-Tunai / QRIS</p>
                    <p class="text-lg font-bold text-indigo-700 font-mono-num mt-1">{{ $formatRupiah($nonCashSales) }}</p>
                    <p class="text-[10px] text-indigo-600 mt-0.5">Transfer / QRIS / EDC</p>
                </div>
            </div>

            <!-- Tabel Transaksi Penjualan -->
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-900 text-white uppercase text-[10px] tracking-wider">
                            <th class="py-3 px-3">Tanggal</th>
                            <th class="py-3 px-3">No. Faktur</th>
                            <th class="py-3 px-3">Pelanggan</th>
                            <th class="py-3 px-3">Metode</th>
                            <th class="py-3 px-3 text-right">Total Jual</th>
                            <th class="py-3 px-3 text-center">Status Pembayaran</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($sales as $sale)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="py-2.5 px-3 font-mono-num text-slate-700 whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($sale->created_at)->isoFormat('DD/MM/YYYY HH:mm') }}
                                </td>
                                <td class="py-2.5 px-3 font-mono font-semibold text-slate-900 whitespace-nowrap">
                                    {{ $sale->receipt_number }}
                                </td>
                                <td class="py-2.5 px-3 text-slate-800">
                                    <div class="font-medium">Pelanggan Umum (Walk-in)</div>
                                    <div class="text-[10px] text-slate-400">Kasir: {{ $sale->user->name ?? 'Kasir POS' }} &bull; {{ $sale->branch->name ?? '-' }}</div>
                                </td>
                                <td class="py-2.5 px-3 whitespace-nowrap">
                                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold uppercase {{ strtolower($sale->payment_method ?? '') === 'cash' ? 'bg-sky-100 text-sky-800' : 'bg-indigo-100 text-indigo-800' }}">
                                        {{ $sale->payment_method ?? 'CASH' }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono-num font-bold text-slate-900 whitespace-nowrap">
                                    {{ $formatRupiah((float) $sale->total_amount) }}
                                </td>
                                <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ in_array(strtolower($sale->status ?? ''), ['completed', 'success'], true) ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-amber-100 text-amber-800 border border-amber-200' }}">
                                        {{ $sale->status ?? 'completed' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400 italic">
                                    Tidak ada data penjualan pada periode dan kriteria cabang yang dipilih.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($sales->isNotEmpty())
                        <tfoot>
                            <tr class="bg-slate-100 font-bold border-t-2 border-slate-900 text-slate-900">
                                <td colspan="4" class="py-3 px-3 uppercase text-right tracking-wider">
                                    Total Penjualan Bersih ({{ number_format($totalTransactions, 0, ',', '.') }} Transaksi)
                                </td>
                                <td class="py-3 px-3 text-right font-mono-num text-sm text-emerald-800">
                                    {{ $formatRupiah($totalSales) }}
                                </td>
                                <td class="py-3 px-3"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            <!-- Catatan Kaki / Pengesahan Cetak -->
            <footer class="mt-10 pt-6 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between text-[11px] text-slate-400 gap-4">
                <div>
                    Dicetak secara otomatis oleh sistem Maju Bersama POS-Accounting pada {{ now()->isoFormat('D MMMM Y HH:mm:ss') }}
                </div>
                <div class="sm:text-right">
                    Halaman 1 dari 1 &bull; Dokumen Sah Backoffice
                </div>
            </footer>

        </div>
    </main>
</body>
</html>
