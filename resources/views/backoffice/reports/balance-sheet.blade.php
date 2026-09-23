<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neraca Standar (Balance Sheet) | Maju Bersama ERP</title>
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

        $totalAssets = (float) ($report['total_assets'] ?? 0);
        $totalLiabilities = (float) ($report['total_liabilities'] ?? 0);
        $totalEquity = (float) ($report['total_equity'] ?? 0);
        $totalPasiva = (float) ($report['total_liabilities_and_equity'] ?? 0);
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
                        <span class="rounded bg-slate-800 px-1.5 py-0.5 text-[10px] font-mono text-slate-300">Balance Sheet</span>
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
                    <span class="p-1 rounded bg-indigo-100 text-indigo-800">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </span>
                    <h2 class="text-lg font-bold text-slate-900">Neraca Standar (Balance Sheet)</h2>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Laporan posisi keuangan kumulatif: Total Aktiva, Kewajiban, dan Ekuitas Modal</p>
            </div>

            <!-- Form Filter Per Tanggal & Cabang -->
            <form method="GET" action="{{ route('reports.balance-sheet') }}" class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-1.5 bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs">
                    <label for="end_date" class="text-slate-500 font-medium">Per Tanggal:</label>
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
                    <span>Tampilkan</span>
                </button>

                <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-100 hover:text-slate-900 focus:outline-none transition">
                    <span>🖨️ Cetak</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Konten Kertas Laporan (Format Vertikal Standar Akuntansi) -->
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
                            NERACA (BALANCE SHEET)
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
                        <span class="text-slate-500">Posisi Keuangan Per:</span>
                        <span class="font-bold text-slate-900 ml-1">
                            {{ \Carbon\Carbon::parse($endDate)->translatedFormat('d F Y') }}
                        </span>
                    </div>
                    <div class="sm:text-right">
                        <span class="text-slate-500">Unit / Entitas:</span>
                        <span class="font-bold text-slate-900 ml-1">{{ $report['branch_name'] }}</span>
                    </div>
                </div>
            </header>

            <!-- STRUKTUR HIERARKIS NERACA (AKTIVA DI ATAS, PASIVA DI BAWAH) -->
            <div class="space-y-8">

                <!-- ============================================== -->
                <!-- BAGIAN 1: AKTIVA / ASET -->
                <!-- ============================================== -->
                <section>
                    <div class="bg-slate-900 text-white px-4 py-2 rounded-lg flex items-center justify-between mb-3 shadow-xs">
                        <h3 class="text-xs font-extrabold uppercase tracking-wider">A K T I V A  (A S E T)</h3>
                        <span class="text-[10px] font-mono text-slate-300 uppercase">Aset Lancar &amp; Tetap</span>
                    </div>

                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-slate-400 border-b border-slate-200 text-left">
                                <th class="py-1.5 font-medium pl-3 w-28">Kode Akun</th>
                                <th class="py-1.5 font-medium">Nama Akun Aset</th>
                                <th class="py-1.5 font-medium text-right pr-3 w-48">Nilai Buku (IDR)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($report['assets']['accounts'] as $acc)
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-2 pl-3 font-mono text-slate-500">{{ $acc['code'] }}</td>
                                    <td class="py-2 font-medium text-slate-800">{{ $acc['name'] }}</td>
                                    <td class="py-2 pr-3 text-right font-mono-num font-semibold text-slate-900">{{ $formatRupiah($acc['amount']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-4 text-center text-slate-400 italic">Tidak ada saldo akun aktiva.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <!-- BARIS TEBAL: TOTAL AKTIVA -->
                    <div class="mt-3 rounded-xl border-2 border-slate-900 bg-slate-100/90 px-4 py-3 flex items-center justify-between">
                        <div>
                            <span class="text-sm font-extrabold uppercase tracking-wider text-slate-950">TOTAL AKTIVA (ASSETS)</span>
                            <p class="text-[11px] text-slate-500">Total seluruh aset lancar dan aset tetap perusahaan</p>
                        </div>
                        <div class="text-right">
                            <span class="font-mono-num text-base sm:text-lg font-extrabold text-slate-950">
                                {{ $formatRupiah($totalAssets) }}
                            </span>
                        </div>
                    </div>
                </section>

                <!-- ============================================== -->
                <!-- BAGIAN 2: PASIVA (KEWAJIBAN & EKUITAS) -->
                <!-- ============================================== -->
                <section class="space-y-6">
                    <div class="bg-slate-900 text-white px-4 py-2 rounded-lg flex items-center justify-between shadow-xs">
                        <h3 class="text-xs font-extrabold uppercase tracking-wider">P A S I V A  (K E W A J I B A N  &amp;  E K U I T A S)</h3>
                        <span class="text-[10px] font-mono text-slate-300 uppercase">Liabilitas &amp; Modal</span>
                    </div>

                    <!-- 2A: KEWAJIBAN / HUTANG -->
                    <div>
                        <div class="bg-slate-100/80 px-3 py-1.5 rounded border-l-4 border-amber-600 mb-2">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800">1. Kewajiban (Liabilities)</h4>
                        </div>
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="text-slate-400 border-b border-slate-200 text-left">
                                    <th class="py-1.5 font-medium pl-3 w-28">Kode Akun</th>
                                    <th class="py-1.5 font-medium">Nama Akun Kewajiban</th>
                                    <th class="py-1.5 font-medium text-right pr-3 w-48">Jumlah (IDR)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($report['liabilities']['accounts'] as $acc)
                                    <tr class="hover:bg-slate-50/80 transition-colors">
                                        <td class="py-2 pl-3 font-mono text-slate-500">{{ $acc['code'] }}</td>
                                        <td class="py-2 font-medium text-slate-800">{{ $acc['name'] }}</td>
                                        <td class="py-2 pr-3 text-right font-mono-num font-semibold text-slate-900">{{ $formatRupiah($acc['amount']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="py-3 text-center text-slate-400 italic">Tidak ada saldo kewajiban / hutang.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-slate-300 font-bold bg-slate-50/60">
                                    <td colspan="2" class="py-2 pl-3 text-slate-800 uppercase">Subtotal Kewajiban</td>
                                    <td class="py-2 pr-3 text-right font-mono-num text-slate-900">{{ $formatRupiah($totalLiabilities) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- 2B: MODAL / EKUITAS -->
                    <div>
                        <div class="bg-slate-100/80 px-3 py-1.5 rounded border-l-4 border-indigo-600 mb-2">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800">2. Modal &amp; Ekuitas (Equity)</h4>
                        </div>
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="text-slate-400 border-b border-slate-200 text-left">
                                    <th class="py-1.5 font-medium pl-3 w-28">Kode Akun</th>
                                    <th class="py-1.5 font-medium">Nama Akun Modal</th>
                                    <th class="py-1.5 font-medium text-right pr-3 w-48">Jumlah (IDR)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($report['equity']['accounts'] as $acc)
                                    <tr class="hover:bg-slate-50/80 transition-colors {{ !empty($acc['is_calculated']) ? 'bg-amber-50/40 font-semibold' : '' }}">
                                        <td class="py-2 pl-3 font-mono text-slate-500">{{ $acc['code'] }}</td>
                                        <td class="py-2 text-slate-800 flex items-center gap-1.5">
                                            <span>{{ $acc['name'] }}</span>
                                            @if (!empty($acc['is_calculated']))
                                                <span class="text-[9px] bg-amber-100 text-amber-800 font-bold px-1.5 py-0.5 rounded">Net Income</span>
                                            @endif
                                        </td>
                                        <td class="py-2 pr-3 text-right font-mono-num font-semibold {{ (!empty($acc['is_calculated']) && $acc['amount'] < 0) ? 'text-rose-700' : 'text-slate-900' }}">
                                            {{ $formatRupiah($acc['amount']) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="py-3 text-center text-slate-400 italic">Tidak ada saldo ekuitas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-slate-300 font-bold bg-slate-50/60">
                                    <td colspan="2" class="py-2 pl-3 text-slate-800 uppercase">Subtotal Ekuitas</td>
                                    <td class="py-2 pr-3 text-right font-mono-num text-slate-900">{{ $formatRupiah($totalEquity) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- BARIS TEBAL: TOTAL KEWAJIBAN & EKUITAS -->
                    <div class="rounded-xl border-2 border-slate-900 bg-slate-100/90 px-4 py-3 flex items-center justify-between">
                        <div>
                            <span class="text-sm font-extrabold uppercase tracking-wider text-slate-950">TOTAL KEWAJIBAN &amp; EKUITAS</span>
                            <p class="text-[11px] text-slate-500">Penjumlahan Total Kewajiban ditambah Total Ekuitas Modal</p>
                        </div>
                        <div class="text-right">
                            <span class="font-mono-num text-base sm:text-lg font-extrabold text-slate-950">
                                {{ $formatRupiah($totalPasiva) }}
                            </span>
                        </div>
                    </div>
                </section>

                <!-- STATUS KESEIMBANGAN NERACA -->
                <div class="rounded-xl px-5 py-3.5 flex items-center justify-between border-2 {{ $isBalanced ? 'bg-emerald-50/80 border-emerald-400 text-emerald-950' : 'bg-rose-50/80 border-rose-400 text-rose-950' }}">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-extrabold uppercase tracking-wide">
                            {{ $isBalanced ? 'STATUS KESEIMBANGAN: SEIMBANG (BALANCED)' : 'STATUS KESEIMBANGAN: TIDAK SEIMBANG' }}
                        </span>
                        <span class="rounded px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $isBalanced ? 'bg-emerald-200 text-emerald-900' : 'bg-rose-200 text-rose-900' }}">
                            {{ $isBalanced ? 'Aset = Pasiva' : 'Selisih ' . $formatRupiah($report['difference']) }}
                        </span>
                    </div>
                    <div class="text-xs font-mono">
                        <span>Aktiva: {{ $formatRupiah($totalAssets) }}</span>
                        <span class="mx-1">&bull;</span>
                        <span>Pasiva: {{ $formatRupiah($totalPasiva) }}</span>
                    </div>
                </div>

            </div>

            <!-- LEMBAR PENGESAHAN / TANDA TANGAN -->
            <footer class="mt-12 pt-8 border-t border-slate-200 grid grid-cols-2 gap-8 text-xs text-center text-slate-600">
                <div>
                    <p class="text-slate-400">Dibuat Oleh:</p>
                    <div class="h-16"></div>
                    <p class="font-bold text-slate-900 border-t border-slate-300 mx-auto w-40 pt-1.5">{{ $currentUser->name }}</p>
                    <p class="text-[10px] text-slate-400">Bagian Akuntansi / Pembukuan</p>
                </div>
                <div>
                    <p class="text-slate-400">Disetujui Oleh:</p>
                    <div class="h-16"></div>
                    <p class="font-bold text-slate-900 border-t border-slate-300 mx-auto w-40 pt-1.5">( ............................................ )</p>
                    <p class="text-[10px] text-slate-400">Pimpinan / Direktur</p>
                </div>
            </footer>

            <div class="mt-8 text-center text-[10px] text-slate-400 border-t border-slate-100 pt-3">
                Dicetak pada: {{ now()->translatedFormat('d F Y, H:i:s') }} WIB &bull; Sistem ERP Maju Bersama POS-Accounting
            </div>

        </div>
    </main>
</body>
</html>
