<x-app-layout title="Laporan Arus Kas (Cash Flow)" :breadcrumbs="[
    ['label' => 'Pusat Laporan', 'url' => route('reports.index')],
    ['label' => 'Arus Kas'],
]">
    <x-slot:head>
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
    </x-slot:head>

    @php
        $formatRupiah = function ($val) {
            $isNeg = $val < 0;
            $formatted = number_format(abs($val), 2, ',', '.');
            return ($isNeg ? '(Rp ' . $formatted . ')' : 'Rp ' . $formatted);
        };

        $openingBalance = (float) ($report['opening_balance'] ?? 0);
        $totalCashIn = (float) ($report['cash_in']['total'] ?? 0);
        $totalCashOut = (float) ($report['cash_out']['total'] ?? 0);
        $netCashFlow = (float) ($report['net_cash_flow'] ?? 0);
        $endingBalance = (float) ($report['ending_balance'] ?? 0);
        $isSurplus = (bool) ($report['is_surplus'] ?? false);
    @endphp

    <main class="mx-auto w-full px-6 py-6 lg:px-10 max-w-5xl flex flex-col justify-start">
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm no-print print:hidden">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="p-1 rounded bg-teal-100 text-teal-800">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                    </span>
                    <h2 class="text-lg font-bold text-slate-900">Laporan Arus Kas (Cash Flow)</h2>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Daftar penerimaan kas, pengeluaran kas, dan kenaikan/penurunan bersih kas &amp; bank</p>
            </div>

            <!-- Form Filter Rentang Tanggal & Cabang -->
            <form method="GET" action="{{ route('reports.cash-flow') }}" class="flex flex-wrap items-center gap-2">
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

                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-sky-600 px-3.5 py-2 text-xs font-bold text-white shadow-xs hover:bg-sky-700 focus:outline-none transition">
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

        <div class="print-container rounded-2xl border border-slate-300 bg-white shadow-lg p-6 sm:p-10 lg:p-12 text-slate-900">
            
            <!-- KOP PERUSAHAAN (MAJU BERSAMA GRUP) -->
            <header class="border-b-2 border-slate-900 pb-5 mb-6">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <p class="font-bold uppercase text-sm tracking-wider text-amber-600">Sistem Keuangan &amp; Akuntansi Terintegrasi</p>
                        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-950 uppercase mt-0.5">MAJU BERSAMA GRUP</h1>
                        <p class="text-xs text-slate-500 mt-1">Multi-Store Agriculture &amp; FMCG Retail Network</p>
                    </div>
                    <div class="sm:text-right">
                        <span class="inline-block rounded-md bg-slate-900 text-white font-mono text-xs px-2.5 py-1 font-bold tracking-wider uppercase">
                            LAPORAN ARUS KAS
                        </span>
                        <div class="mt-2 flex sm:justify-end items-center gap-1.5">
                            <span class="rounded bg-teal-100 text-teal-800 border border-teal-300 text-[10px] font-bold px-2 py-0.5 uppercase tracking-wider">
                                METODE LANGSUNG
                            </span>
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

            <!-- RINGKASAN SALDO AWAL KAS -->
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 flex items-center justify-between mb-6">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-800">Saldo Awal Kas &amp; Bank</span>
                    <p class="text-[11px] text-slate-500">Posisi likuiditas kas sebelum tanggal {{ \Carbon\Carbon::parse($startDate)->translatedFormat('d F Y') }}</p>
                </div>
                <div class="text-right">
                    <span class="font-mono-num text-sm sm:text-base font-extrabold text-slate-900">
                        {{ $formatRupiah($openingBalance) }}
                    </span>
                </div>
            </div>

            <!-- DETAIL ARUS KAS MASUK DAN KELUAR -->
            <div class="space-y-8">

                <!-- 1. PENERIMAAN KAS (CASH INFLOW) -->
                <section>
                    <div class="bg-slate-100/90 px-3 py-1.5 rounded border-l-4 border-emerald-600 mb-2 flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">1. Penerimaan Kas (Kas Masuk)</h3>
                        <span class="text-[10px] font-mono text-emerald-700 font-semibold">{{ count($report['cash_in']['items']) }} Transaksi</span>
                    </div>

                    <table class="w-full text-xs">
                        <thead class="bg-slate-900 text-xs font-semibold uppercase tracking-wider text-white">
                            <tr class="border-b text-left">
                                <th class="py-1.5 font-medium pl-3 w-24">Tanggal</th>
                                <th class="py-1.5 font-medium w-32">Referensi</th>
                                <th class="py-1.5 font-medium">Keterangan / Memo</th>
                                <th class="py-1.5 font-medium text-right pr-3 w-40">Jumlah (IDR)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 [&>tr:nth-child(even)]:bg-slate-50/60">
                            @forelse ($report['cash_in']['items'] as $item)
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-2 pl-3 font-mono text-slate-500">{{ $item['date'] }}</td>
                                    <td class="py-2 font-mono text-slate-700 font-medium">{{ $item['reference'] }}</td>
                                    <td class="py-2 text-slate-800">
                                        {{ $item['description'] }}
                                        <span class="text-[10px] text-slate-400 ml-1">[{{ $item['account'] }}]</span>
                                    </td>
                                    <td class="py-2 pr-3 text-right font-mono-num font-semibold text-emerald-800">
                                        +{{ $formatRupiah($item['amount']) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-3 text-center text-slate-400 italic">Tidak ada transaksi penerimaan kas pada periode ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-slate-300 font-bold bg-slate-50/60">
                                <td colspan="3" class="py-2.5 pl-3 text-slate-900 uppercase">Total Penerimaan Kas</td>
                                <td class="py-2.5 pr-3 text-right font-mono-num text-sm text-emerald-900">
                                    {{ $formatRupiah($totalCashIn) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </section>

                <!-- 2. PENGELUARAN KAS (CASH OUTFLOW) -->
                <section>
                    <div class="bg-slate-100/90 px-3 py-1.5 rounded border-l-4 border-rose-600 mb-2 flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">2. Pengeluaran Kas (Kas Keluar)</h3>
                        <span class="text-[10px] font-mono text-rose-700 font-semibold">{{ count($report['cash_out']['items']) }} Transaksi</span>
                    </div>

                    <table class="w-full text-xs">
                        <thead class="bg-slate-900 text-xs font-semibold uppercase tracking-wider text-white">
                            <tr class="border-b text-left">
                                <th class="py-1.5 font-medium pl-3 w-24">Tanggal</th>
                                <th class="py-1.5 font-medium w-32">Referensi</th>
                                <th class="py-1.5 font-medium">Keterangan / Memo</th>
                                <th class="py-1.5 font-medium text-right pr-3 w-40">Jumlah (IDR)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 [&>tr:nth-child(even)]:bg-slate-50/60">
                            @forelse ($report['cash_out']['items'] as $item)
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-2 pl-3 font-mono text-slate-500">{{ $item['date'] }}</td>
                                    <td class="py-2 font-mono text-slate-700 font-medium">{{ $item['reference'] }}</td>
                                    <td class="py-2 text-slate-800">
                                        {{ $item['description'] }}
                                        <span class="text-[10px] text-slate-400 ml-1">[{{ $item['account'] }}]</span>
                                    </td>
                                    <td class="py-2 pr-3 text-right font-mono-num font-semibold text-rose-800">
                                        -{{ $formatRupiah($item['amount']) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-3 text-center text-slate-400 italic">Tidak ada transaksi pengeluaran kas pada periode ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-slate-300 font-bold bg-slate-50/60">
                                <td colspan="3" class="py-2.5 pl-3 text-slate-900 uppercase">Total Pengeluaran Kas</td>
                                <td class="py-2.5 pr-3 text-right font-mono-num text-sm text-rose-900">
                                    {{ $formatRupiah($totalCashOut) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </section>

                <!-- 3. KENAIKAN / PENURUNAN BERSIH KAS (NET CASH FLOW) -->
                <div class="rounded-xl border-2 border-slate-300 bg-slate-100/90 px-4 py-3.5 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-900">
                            {{ $isSurplus ? 'Kenaikan Bersih Kas & Bank (Net Increase)' : 'Penurunan Bersih Kas & Bank (Net Decrease)' }}
                        </span>
                        <p class="text-[11px] text-slate-500">Total Kas Masuk dikurangi Total Kas Keluar</p>
                    </div>
                    <div class="text-right">
                        <span class="font-mono-num text-base sm:text-lg font-extrabold {{ $isSurplus ? 'text-emerald-900' : 'text-rose-900' }}">
                            {{ $formatRupiah($netCashFlow) }}
                        </span>
                    </div>
                </div>

                <!-- 4. SALDO AKHIR KAS (ENDING CASH BALANCE) -->
                <div class="rounded-xl px-5 py-4 flex items-center justify-between border-2 bg-slate-950 text-white">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-extrabold uppercase tracking-wide">SALDO AKHIR KAS &amp; BANK</span>
                            <span class="rounded bg-slate-800 text-amber-400 border border-slate-700 text-[10px] font-bold px-2 py-0.5 uppercase tracking-wider">
                                Posisi Kas Riil
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">Saldo Awal Kas ditambah Kenaikan/Penurunan Bersih Kas</p>
                    </div>
                    <div class="text-right">
                        <span class="font-mono-num text-lg sm:text-xl font-extrabold text-amber-400">
                            {{ $formatRupiah($endingBalance) }}
                        </span>
                    </div>
                </div>

            </div>

            <!-- LEMBAR PENGESAHAN / TANDA TANGAN -->
            <footer class="mt-12 pt-8 border-t border-slate-200 grid grid-cols-2 gap-8 text-xs text-center text-slate-600">
                <div>
                    <p class="text-slate-400">Dibuat Oleh:</p>
                    <div class="h-16"></div>
                    <p class="font-bold text-slate-900 border-t border-slate-300 mx-auto w-40 pt-1.5">{{ $currentUser->name }}</p>
                    <p class="text-[10px] text-slate-400">Bendahara / Kasir Utama</p>
                </div>
                <div>
                    <p class="text-slate-400">Disetujui Oleh:</p>
                    <div class="h-16"></div>
                    <p class="font-bold text-slate-900 border-t border-slate-300 mx-auto w-40 pt-1.5">( ............................................ )</p>
                    <p class="text-[10px] text-slate-400">Pimpinan / Kepala Toko</p>
                </div>
            </footer>

            <div class="mt-8 text-center text-[10px] text-slate-400 border-t border-slate-100 pt-3">
                Dicetak pada: {{ now()->translatedFormat('d F Y, H:i:s') }} WIB &bull; Sistem ERP Maju Bersama POS-Accounting
            </div>

        </div>
    </main>
</x-app-layout>
