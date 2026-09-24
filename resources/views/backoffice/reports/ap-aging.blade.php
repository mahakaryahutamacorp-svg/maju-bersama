<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Umur Hutang (AP Aging) | Maju Bersama ERP</title>
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
            $num = (float) $val;
            if ($num == 0) {
                return '-';
            }
            $isNeg = $num < 0;
            $formatted = number_format(abs($num), 0, ',', '.');
            return ($isNeg ? '(Rp ' . $formatted . ')' : 'Rp ' . $formatted);
        };
    @endphp

    <!-- Header Bar Utama (Aplikasi) -->
    <header class="border-b border-slate-800 bg-slate-950 text-white shrink-0 no-print">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3.5 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                <a href="/backoffice" class="block group">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-amber-400 group-hover:text-amber-300 transition">Maju Bersama ERP</p>
                    <div class="flex items-center gap-2">
                        <h1 class="text-base font-bold tracking-tight">Pusat Laporan</h1>
                        <span class="rounded bg-slate-800 px-1.5 py-0.5 text-[10px] font-mono text-slate-300">AP Aging</span>
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </span>
                    <h2 class="text-lg font-bold text-slate-900">Laporan Umur Hutang (AP Aging)</h2>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Klasifikasi sisa kewajiban pembayaran kepada supplier berdasarkan jatuh tempo</p>
            </div>

            <!-- Form Filter Cabang & Tombol Cetak -->
            <form method="GET" action="{{ route('reports.ap-aging') }}" class="flex flex-wrap items-center gap-2">
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
    <main class="flex-1 mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8 flex flex-col justify-start">
        <div class="print-container rounded-2xl border border-slate-300 bg-white shadow-lg p-6 sm:p-8 lg:p-10 text-slate-900">
            
            <!-- KOP PERUSAHAAN (MAJU BERSAMA GRUP) -->
            <header class="border-b-2 border-slate-900 pb-5 mb-6">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold tracking-[0.2em] text-amber-600 uppercase">Sistem Pengelolaan Hutang Dagang (Account Payable)</p>
                        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-950 uppercase mt-0.5">MAJU BERSAMA GRUP</h1>
                        <p class="text-xs text-slate-500 mt-1">Multi-Store Agriculture &amp; FMCG Retail Network</p>
                    </div>
                    <div class="sm:text-right">
                        <span class="inline-block rounded-md bg-slate-900 text-white font-mono text-xs px-2.5 py-1 font-bold tracking-wider uppercase">
                            LAPORAN UMUR HUTANG
                        </span>
                        <p class="text-xs font-semibold text-slate-700 mt-1.5">Accounts Payable Aging Schedule</p>
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
                    <p class="text-[11px] font-semibold uppercase text-slate-500">Total Hutang</p>
                    <p class="text-lg sm:text-xl font-extrabold text-slate-900 font-mono-num mt-1">{{ $formatRupiah($totals['total_amount'] ?? 0) }}</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">{{ $totals['total_suppliers'] ?? 0 }} Supplier / Kreditur</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-3.5">
                    <p class="text-[11px] font-semibold uppercase text-emerald-700">0 - 30 Hari</p>
                    <p class="text-lg font-extrabold text-emerald-900 font-mono-num mt-1">{{ $formatRupiah($totals['bucket_0_30'] ?? 0) }}</p>
                    <p class="text-[10px] text-emerald-600 mt-0.5">Tempo Aman</p>
                </div>
                <div class="rounded-xl border border-sky-200 bg-sky-50/50 p-3.5">
                    <p class="text-[11px] font-semibold uppercase text-sky-700">31 - 60 Hari</p>
                    <p class="text-lg font-extrabold text-sky-900 font-mono-num mt-1">{{ $formatRupiah($totals['bucket_31_60'] ?? 0) }}</p>
                    <p class="text-[10px] text-sky-600 mt-0.5">Mendekati Jatuh Tempo</p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-3.5">
                    <p class="text-[11px] font-semibold uppercase text-amber-700">61 - 90 Hari</p>
                    <p class="text-lg font-extrabold text-amber-900 font-mono-num mt-1">{{ $formatRupiah($totals['bucket_61_90'] ?? 0) }}</p>
                    <p class="text-[10px] text-amber-600 mt-0.5">Jatuh Tempo</p>
                </div>
                <div class="rounded-xl border border-rose-200 bg-rose-50/50 p-3.5">
                    <p class="text-[11px] font-semibold uppercase text-rose-700">&gt; 90 Hari</p>
                    <p class="text-lg font-extrabold text-rose-900 font-mono-num mt-1">{{ $formatRupiah($totals['bucket_over_90'] ?? 0) }}</p>
                    <p class="text-[10px] text-rose-600 mt-0.5">Menunggak Lama</p>
                </div>
            </div>

            <!-- Tabel Matriks Umur Hutang -->
            <div class="overflow-x-auto rounded-xl border border-slate-300">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-900 text-white uppercase text-[11px] tracking-wider font-semibold">
                        <tr>
                            <th scope="col" class="py-3 px-4">Nama Supplier</th>
                            <th scope="col" class="py-3 px-4 text-right">Total Hutang</th>
                            <th scope="col" class="py-3 px-4 text-right">0 - 30 Hari</th>
                            <th scope="col" class="py-3 px-4 text-right">31 - 60 Hari</th>
                            <th scope="col" class="py-3 px-4 text-right">61 - 90 Hari</th>
                            <th scope="col" class="py-3 px-4 text-right">&gt; 90 Hari</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-900">{{ $row['supplier_name'] }}</div>
                                    @if (!empty($row['supplier']?->phone))
                                        <div class="text-[10px] text-slate-400 font-mono">{{ $row['supplier']->phone }}</div>
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
                                    Tidak ada data kewajiban hutang supplier yang belum lunas pada cabang ini. Seluruh pesanan pembelian (PO) berada dalam status lunas (PAID).
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
                    <p class="font-bold text-slate-800">Prosedur Manajemen Kas &amp; Pembayaran:</p>
                    <p class="mt-1 text-[11px] leading-relaxed text-slate-500">
                        * Prioritaskan jadwal pembayaran supplier pada bucket 61-90 hari guna menjaga hubungan kemitraan dan pasokan barang.<br>
                        * Alokasikan dana kas/bank secara terjadwal melalui modul Pembayaran Supplier (AP Payment).
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-slate-500">Dicetak oleh: <span class="font-semibold text-slate-800">{{ $currentUser->name }}</span></p>
                    <p class="text-[11px] text-slate-400 mt-1 font-mono">Waktu Cetak: {{ now()->format('Y-m-d H:i:s') }}</p>
                </div>
            </div>

        </div>
    </main>
</body>
</html>
