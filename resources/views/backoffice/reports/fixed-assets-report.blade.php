<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Aktiva Tetap &amp; Inventaris | Maju Bersama ERP</title>
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

        $totalAcquisition = $assets->sum('acquisition_value') ?? 0;
        $totalDepreciation = $assets->sum('accumulated_depreciation') ?? 0;
        $totalBookValue = $totalAcquisition - $totalDepreciation;
    @endphp

    <!-- Header Bar Utama (Aplikasi) -->
    <header class="border-b border-slate-800 bg-slate-950 text-white shrink-0 no-print">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3.5 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                <a href="/backoffice" class="block group">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-amber-400 group-hover:text-amber-300 transition">Maju Bersama ERP</p>
                    <div class="flex items-center gap-2">
                        <h1 class="text-base font-bold tracking-tight">Pusat Laporan</h1>
                        <span class="rounded bg-slate-800 px-1.5 py-0.5 text-[10px] font-mono text-slate-300">Fixed Assets</span>
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
                    <span class="p-1 rounded bg-purple-100 text-purple-800">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </span>
                    <h2 class="text-lg font-bold text-slate-900">Daftar Aktiva Tetap &amp; Inventaris</h2>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Katalog aset perusahaan, nilai perolehan historis, akumulasi penyusutan, dan nilai buku</p>
            </div>

            <!-- Form Filter Cabang & Tombol Cetak -->
            <form method="GET" action="{{ route('reports.fixed-assets') }}" class="flex flex-wrap items-center gap-2">
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
    <main class="flex-1 mx-auto w-full max-w-5xl p-4 sm:p-6 lg:p-8 flex flex-col justify-start">
        <div class="print-container rounded-2xl border border-slate-300 bg-white shadow-lg p-6 sm:p-8 lg:p-10 text-slate-900">
            
            <!-- KOP PERUSAHAAN (MAJU BERSAMA GRUP) -->
            <header class="border-b-2 border-slate-900 pb-5 mb-6">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold tracking-[0.2em] text-amber-600 uppercase">Sistem Pengelolaan Harta Tetap &amp; Aktiva</p>
                        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-950 uppercase mt-0.5">MAJU BERSAMA GRUP</h1>
                        <p class="text-xs text-slate-500 mt-1">Multi-Store Agriculture &amp; FMCG Retail Network</p>
                    </div>
                    <div class="sm:text-right">
                        <span class="inline-block rounded-md bg-slate-900 text-white font-mono text-xs px-2.5 py-1 font-bold tracking-wider uppercase">
                            DAFTAR HARTA TETAP
                        </span>
                        <p class="text-xs font-semibold text-slate-700 mt-1.5">Fixed Asset Register</p>
                    </div>
                </div>

                <!-- Informasi Entitas / Cabang -->
                <div class="mt-4 pt-3 border-t border-slate-200 grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                    <div>
                        <span class="text-slate-500">Tanggal Laporan:</span>
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

            <!-- Active Status Banner -->
            <div class="mb-6 rounded-xl border border-purple-200 bg-purple-50/70 p-4 text-xs text-purple-900 flex items-start justify-between gap-3 no-print">
                <div class="flex items-start gap-3">
                    <div class="p-1 rounded bg-purple-200 text-purple-800 shrink-0 mt-0.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-sm text-purple-950">Modul Harta Tetap &amp; Depresiasi Terproteksi</h3>
                        <p class="mt-0.5 text-purple-700 leading-relaxed">
                            Data aktiva tetap, akumulasi penyusutan metode garis lurus, dan nilai buku terhubung otomatis secara real-time dengan modul akuntansi dan jurnal umum.
                        </p>
                    </div>
                </div>
                <a href="{{ route('backoffice.fixed-assets.index') }}" class="shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-purple-700 px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-purple-800 transition">
                    Kelola Aset Tetap &rarr;
                </a>
            </div>

            <!-- Summary KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
                <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-3.5">
                    <p class="text-[11px] font-semibold uppercase text-slate-500">Total Nilai Perolehan</p>
                    <p class="text-xl font-extrabold text-slate-900 font-mono-num mt-1">{{ $formatRupiah($totalAcquisition) }}</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">Harga Beli Historis</p>
                </div>

                <div class="rounded-xl border border-rose-200 bg-rose-50/50 p-3.5">
                    <p class="text-[11px] font-semibold uppercase text-rose-800">Akumulasi Penyusutan</p>
                    <p class="text-xl font-extrabold text-rose-700 font-mono-num mt-1">{{ $formatRupiah($totalDepreciation) }}</p>
                    <p class="text-[10px] text-rose-600 mt-0.5">Total Depresiasi</p>
                </div>

                <div class="rounded-xl border border-purple-200 bg-purple-50/50 p-3.5">
                    <p class="text-[11px] font-semibold uppercase text-purple-800">Total Nilai Buku</p>
                    <p class="text-xl font-extrabold text-purple-700 font-mono-num mt-1">{{ $formatRupiah($totalBookValue) }}</p>
                    <p class="text-[10px] text-purple-600 mt-0.5">Net Book Value</p>
                </div>
            </div>

            <!-- Tabel Daftar Harta Tetap -->
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-900 text-white uppercase text-[10px] tracking-wider">
                            <th class="py-3 px-3">Nama Aset</th>
                            <th class="py-3 px-3 text-right">Nilai Perolehan</th>
                            <th class="py-3 px-3 text-right">Akumulasi Penyusutan</th>
                            <th class="py-3 px-3 text-right">Nilai Buku</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($assets as $asset)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="py-2.5 px-3 font-medium text-slate-900">
                                    {{ $asset->name ?? '-' }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono-num font-semibold text-slate-800 whitespace-nowrap">
                                    {{ $formatRupiah((float) ($asset->acquisition_value ?? 0)) }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono-num text-rose-700 whitespace-nowrap">
                                    {{ $formatRupiah((float) ($asset->accumulated_depreciation ?? 0)) }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono-num font-bold text-purple-900 whitespace-nowrap">
                                    {{ $formatRupiah((float) (($asset->acquisition_value ?? 0) - ($asset->accumulated_depreciation ?? 0))) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-10 text-center text-slate-400 italic">
                                    <div class="flex flex-col items-center justify-center gap-1.5">
                                        <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                        </svg>
                                        <span>Belum ada aset tetap yang tercatat pada entitas ini.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-100 font-bold border-t-2 border-slate-900 text-slate-900">
                            <td class="py-3 px-3 uppercase tracking-wider">
                                Total Aktiva Tetap
                            </td>
                            <td class="py-3 px-3 text-right font-mono-num text-sm text-slate-900">
                                {{ $formatRupiah($totalAcquisition) }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono-num text-sm text-rose-700">
                                {{ $formatRupiah($totalDepreciation) }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono-num text-sm text-purple-900">
                                {{ $formatRupiah($totalBookValue) }}
                            </td>
                        </tr>
                    </tfoot>
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
