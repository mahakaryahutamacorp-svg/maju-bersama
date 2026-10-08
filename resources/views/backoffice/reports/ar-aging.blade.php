<x-app-layout title="Laporan Umur Piutang (AR Aging)" :breadcrumbs="[
    ['label' => 'Pusat Laporan', 'url' => route('reports.index')],
    ['label' => 'Umur Piutang'],
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
            $num = (float) $val;
            if ($num == 0) {
                return '-';
            }
            $isNeg = $num < 0;
            $formatted = number_format(abs($num), 0, ',', '.');
            return ($isNeg ? '(Rp ' . $formatted . ')' : 'Rp ' . $formatted);
        };
    @endphp

    <main class="mx-auto w-full px-6 py-6 lg:px-10 max-w-7xl flex flex-col justify-start">
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm no-print print:hidden">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="p-1 rounded bg-amber-100 text-amber-800">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    <h2 class="text-lg font-bold text-slate-900">Laporan Umur Piutang (AR Aging)</h2>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Klasifikasi sisa tagihan piutang pelanggan berdasarkan rentang usia jatuh tempo</p>
            </div>

            <!-- Form Filter Cabang & Tombol Cetak -->
            <form method="GET" action="{{ route('reports.ar-aging') }}" class="flex flex-wrap items-center gap-2">
                @if ($isMaster)
                    <div class="flex items-center gap-1.5 bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs">
                        <label for="branch_id" class="text-slate-500 font-medium">Cabang / Entitas:</label>
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

    <!-- Main Content Paper Container -->

        <div class="print-container rounded-2xl border border-slate-300 bg-white shadow-lg p-6 sm:p-8 lg:p-10 text-slate-900">
            
            <!-- KOP PERUSAHAAN (MAJU BERSAMA GRUP) -->
            <header class="border-b-2 border-slate-900 pb-5 mb-6">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <p class="font-bold uppercase text-sm tracking-wider text-amber-600">Sistem Pengelolaan Piutang Usaha (Account Receivable)</p>
                        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-950 uppercase mt-0.5">MAJU BERSAMA GRUP</h1>
                        <p class="text-xs text-slate-500 mt-1">Multi-Store Agriculture &amp; FMCG Retail Network</p>
                    </div>
                    <div class="sm:text-right">
                        <span class="inline-block rounded-md bg-slate-900 text-white font-mono text-xs px-2.5 py-1 font-bold tracking-wider uppercase">
                            LAPORAN UMUR PIUTANG
                        </span>
                        <p class="text-xs font-semibold text-slate-700 mt-1.5">Accounts Receivable Aging Schedule</p>
                    </div>
                </div>

                <!-- Informasi Entitas / Cabang -->
                <div class="mt-4 pt-3 border-t border-slate-200 grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                    <div>
                        <span class="text-slate-500">Tanggal Analisis:</span>
                        <span class="font-semibold text-slate-800 ml-1">{{ now()->isoFormat('D MMMM Y') }}</span>
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

            <!-- Summary KPI Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
                <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-3.5 col-span-2 sm:col-span-1">
                    <p class="text-[11px] font-semibold uppercase text-slate-500">Total Piutang</p>
                    <p class="text-lg sm:text-xl font-extrabold text-slate-900 font-mono-num mt-1">{{ $formatRupiah($totals['total_amount'] ?? 0) }}</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">{{ $totals['total_customers'] ?? 0 }} Pelanggan / Debitur</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-3.5">
                    <p class="text-[11px] font-semibold uppercase text-emerald-700">0 - 30 Hari</p>
                    <p class="text-lg font-extrabold text-emerald-900 font-mono-num mt-1">{{ $formatRupiah($totals['bucket_0_30'] ?? 0) }}</p>
                    <p class="text-[10px] text-emerald-600 mt-0.5">Lancar / Baru</p>
                </div>
                <div class="rounded-xl border border-sky-200 bg-sky-50/50 p-3.5">
                    <p class="text-[11px] font-semibold uppercase text-sky-700">31 - 60 Hari</p>
                    <p class="text-lg font-extrabold text-sky-900 font-mono-num mt-1">{{ $formatRupiah($totals['bucket_31_60'] ?? 0) }}</p>
                    <p class="text-[10px] text-sky-600 mt-0.5">Perhatian Awal</p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-3.5">
                    <p class="text-[11px] font-semibold uppercase text-amber-700">61 - 90 Hari</p>
                    <p class="text-lg font-extrabold text-amber-900 font-mono-num mt-1">{{ $formatRupiah($totals['bucket_61_90'] ?? 0) }}</p>
                    <p class="text-[10px] text-amber-600 mt-0.5">Menunggak</p>
                </div>
                <div class="rounded-xl border border-rose-200 bg-rose-50/50 p-3.5">
                    <p class="text-[11px] font-semibold uppercase text-rose-700">&gt; 90 Hari</p>
                    <p class="text-lg font-extrabold text-rose-900 font-mono-num mt-1">{{ $formatRupiah($totals['bucket_over_90'] ?? 0) }}</p>
                    <p class="text-[10px] text-rose-600 mt-0.5">Kritis / Macet</p>
                </div>
            </div>

            <!-- Tabel Matriks Umur Piutang -->
            <div class="overflow-x-auto rounded-xl border border-slate-300">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-900 text-xs font-semibold uppercase tracking-wider text-white">
                        <tr>
                            <th scope="col" class="py-3 px-4">Nama Pelanggan</th>
                            <th scope="col" class="py-3 px-4 text-right">Total Piutang</th>
                            <th scope="col" class="py-3 px-4 text-right">0 - 30 Hari</th>
                            <th scope="col" class="py-3 px-4 text-right">31 - 60 Hari</th>
                            <th scope="col" class="py-3 px-4 text-right">61 - 90 Hari</th>
                            <th scope="col" class="py-3 px-4 text-right">&gt; 90 Hari</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white [&>tr:nth-child(even)]:bg-slate-50/60">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-900">{{ $row['customer_name'] }}</div>
                                    @if (!empty($row['customer']?->phone))
                                        <div class="text-[10px] text-slate-400 font-mono">{{ $row['customer']->phone }}</div>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-right font-bold text-slate-900 font-mono-num">
                                    {{ $formatRupiah($row['total_amount']) }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono-num text-slate-700">
                                    {{ $formatRupiah($row['bucket_0_30']) }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono-num text-slate-700">
                                    {{ $formatRupiah($row['bucket_31_60']) }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono-num {{ $row['bucket_61_90'] > 0 ? 'text-amber-700 font-semibold' : 'text-slate-700' }}">
                                    {{ $formatRupiah($row['bucket_61_90']) }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono-num {{ $row['bucket_over_90'] > 0 ? 'text-rose-700 font-bold' : 'text-slate-700' }}">
                                    {{ $formatRupiah($row['bucket_over_90']) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-500 italic bg-slate-50/50">
                                    Tidak ada data piutang pelanggan yang belum lunas pada cabang ini. Seluruh tagihan berada dalam status lunas (PAID).
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-slate-100 border-t-2 border-slate-900 font-bold text-slate-950">
                        <tr>
                            <td class="py-3.5 px-4 uppercase tracking-wider text-xs">Total Keseluruhan</td>
                            <td class="py-3.5 px-4 text-right font-mono-num text-sm text-slate-950">
                                {{ $formatRupiah($totals['total_amount'] ?? 0) }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono-num text-emerald-800">
                                {{ $formatRupiah($totals['bucket_0_30'] ?? 0) }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono-num text-sky-800">
                                {{ $formatRupiah($totals['bucket_31_60'] ?? 0) }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono-num text-amber-800">
                                {{ $formatRupiah($totals['bucket_61_90'] ?? 0) }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono-num text-rose-800">
                                {{ $formatRupiah($totals['bucket_over_90'] ?? 0) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Catatan Kaki & Tanda Tangan (Khusus Cetak) -->
            <div class="mt-10 pt-6 border-t border-slate-200 grid grid-cols-2 text-xs text-slate-600">
                <div>
                    <p class="font-bold text-slate-800">Catatan Kebijakan Kredit:</p>
                    <p class="mt-1 text-[11px] leading-relaxed text-slate-500">
                        * Piutang > 60 hari memerlukan tindak lanjut penagihan intensif.<br>
                        * Piutang > 90 hari akan dibekukan sementara fasilitas pembelian kreditnya hingga pelunasan diterima.
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-slate-500">Dicetak oleh: <span class="font-semibold text-slate-800">{{ $currentUser->name }}</span></p>
                    <p class="text-[11px] text-slate-400 mt-1 font-mono">Waktu Cetak: {{ now()->format('Y-m-d H:i:s') }}</p>
                </div>
            </div>

        </div>
    </main>
</x-app-layout>
