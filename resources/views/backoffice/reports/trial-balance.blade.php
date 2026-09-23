<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neraca Saldo (Trial Balance) | Maju Bersama ERP</title>
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

        $totalDebit = (float) ($report['total_debit'] ?? 0);
        $totalCredit = (float) ($report['total_credit'] ?? 0);
        $isBalanced = (bool) ($report['is_balanced'] ?? false);
    @endphp

    <!-- Header Bar Utama (Aplikasi) -->
    <header class="border-b border-slate-800 bg-slate-950 text-white shrink-0 no-print">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3.5 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                <a href="/backoffice" class="block group">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-amber-400 group-hover:text-amber-300 transition">Maju Bersama ERP</p>
                    <div class="flex items-center gap-2">
                        <h1 class="text-base font-bold tracking-tight">Pusat Laporan</h1>
                        <span class="rounded bg-slate-800 px-1.5 py-0.5 text-[10px] font-mono text-slate-300">Trial Balance</span>
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                        </svg>
                    </span>
                    <h2 class="text-lg font-bold text-slate-900">Neraca Saldo (Trial Balance)</h2>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Verifikasi keseimbangan saldo debit dan kredit seluruh akun buku besar</p>
            </div>

            <!-- Form Filter Rentang Tanggal & Cabang -->
            <form method="GET" action="{{ route('reports.trial-balance') }}" class="flex flex-wrap items-center gap-2">
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

    <!-- Konten Kertas Laporan (Card Putih / Layout Cetak) -->
    <main class="flex-1 mx-auto w-full max-w-5xl p-4 sm:p-6 lg:p-8 flex flex-col justify-start">
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
                            NERACA SALDO (TRIAL BALANCE)
                        </span>
                        <div class="mt-2 flex sm:justify-end items-center gap-1.5">
                            @if ($isBalanced)
                                <span class="rounded bg-emerald-100 text-emerald-800 border border-emerald-300 text-[10px] font-bold px-2 py-0.5 uppercase tracking-wider">SEIMBANG (BALANCED)</span>
                            @else
                                <span class="rounded bg-rose-100 text-rose-800 border border-rose-300 text-[10px] font-bold px-2 py-0.5 uppercase tracking-wider">SELISIH: {{ $formatRupiah($report['difference']) }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Informasi Periode & Cabang -->
                <div class="mt-4 pt-3 border-t border-slate-200 grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                    <div>
                        <span class="text-slate-500">Rentang Periode:</span>
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

            <!-- TABEL NERACA SALDO -->
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="bg-slate-100 text-slate-800 border-b-2 border-slate-300 font-bold uppercase tracking-wider">
                            <th class="py-2.5 pl-3 text-left w-24">Kode Akun</th>
                            <th class="py-2.5 text-left">Nama Akun</th>
                            <th class="py-2.5 text-right pr-3 w-36">Debit</th>
                            <th class="py-2.5 text-right pr-3 w-36">Kredit</th>
                            <th class="py-2.5 text-right pr-3 w-40">Saldo Akhir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($report['rows'] as $row)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-2.5 pl-3 font-mono text-slate-600 font-medium">{{ $row['code'] }}</td>
                                <td class="py-2.5 text-slate-900 font-semibold">
                                    {{ $row['name'] }}
                                    <span class="text-[10px] text-slate-400 font-normal uppercase ml-1.5">({{ $row['type'] }})</span>
                                </td>
                                <td class="py-2.5 pr-3 text-right font-mono-num {{ $row['debit'] > 0 ? 'text-slate-900 font-semibold' : 'text-slate-400' }}">
                                    {{ $row['debit'] > 0 ? $formatRupiah($row['debit']) : '-' }}
                                </td>
                                <td class="py-2.5 pr-3 text-right font-mono-num {{ $row['credit'] > 0 ? 'text-slate-900 font-semibold' : 'text-slate-400' }}">
                                    {{ $row['credit'] > 0 ? $formatRupiah($row['credit']) : '-' }}
                                </td>
                                <td class="py-2.5 pr-3 text-right font-mono-num font-bold text-slate-900">
                                    {{ $formatRupiah($row['ending_balance']) }}
                                    <span class="text-[10px] text-slate-400 font-mono">({{ strtoupper(substr($row['normal_balance'], 0, 1)) }})</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-slate-400 italic">Tidak ada transaksi akun pada rentang tanggal yang dipilih.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-b-2 border-slate-900 font-extrabold bg-slate-100 text-slate-950 text-sm">
                            <td colspan="2" class="py-3 pl-3 uppercase tracking-wider">TOTAL MUTASI PERIODE</td>
                            <td class="py-3 pr-3 text-right font-mono-num text-emerald-900">{{ $formatRupiah($totalDebit) }}</td>
                            <td class="py-3 pr-3 text-right font-mono-num text-emerald-900">{{ $formatRupiah($totalCredit) }}</td>
                            <td class="py-3 pr-3 text-right">
                                @if ($isBalanced)
                                    <span class="inline-block rounded bg-emerald-600 text-white text-[11px] font-bold px-2 py-0.5 tracking-wider">SEIMBANG</span>
                                @else
                                    <span class="inline-block rounded bg-rose-600 text-white text-[11px] font-bold px-2 py-0.5 tracking-wider">SELISIH</span>
                                @endif
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- LEMBAR PENGESAHAN / TANDA TANGAN -->
            <footer class="mt-12 pt-8 border-t border-slate-200 grid grid-cols-2 gap-8 text-xs text-center text-slate-600">
                <div>
                    <p class="text-slate-400">Dibuat Oleh:</p>
                    <div class="h-16"></div>
                    <p class="font-bold text-slate-900 border-t border-slate-300 mx-auto w-40 pt-1.5">{{ $currentUser->name }}</p>
                    <p class="text-[10px] text-slate-400">Staff Akuntansi / Pembukuan</p>
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
