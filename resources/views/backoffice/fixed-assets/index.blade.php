<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Harta Tetap &amp; Depresiasi | Maju Bersama ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        .font-mono-num { font-family: 'JetBrains Mono', monospace; font-variant-numeric: tabular-nums; }
    </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased flex flex-col">
    @php
        $formatRupiah = function ($val) {
            $isNeg = $val < 0;
            $formatted = number_format(abs($val), 2, ',', '.');
            return ($isNeg ? '(Rp ' . $formatted . ')' : 'Rp ' . $formatted);
        };
    @endphp

    <!-- Header Utama -->
    <header class="border-b border-slate-800 bg-slate-950 text-white shrink-0">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3.5 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                <a href="/backoffice" class="block group">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-amber-400 group-hover:text-amber-300 transition">Maju Bersama ERP</p>
                    <div class="flex items-center gap-2">
                        <h1 class="text-base font-bold tracking-tight">Harta Tetap &amp; Aktiva</h1>
                        <span class="rounded bg-purple-500/20 px-2 py-0.5 text-[10px] font-mono font-semibold text-purple-300">Depresiasi Garis Lurus</span>
                    </div>
                </a>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('reports.fixed-assets') }}" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800 hover:text-white transition">
                    Laporan Harta Tetap &rarr;
                </a>
                <a href="/backoffice" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800 hover:text-white transition">
                    Dashboard Backoffice
                </a>
                <div class="rounded-full border border-sky-400/30 bg-sky-400/10 px-3 py-1 text-xs font-medium text-sky-200 hidden sm:inline-flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    <span>{{ $currentUser->branch?->name ?? 'Semua Cabang' }}</span>
                </div>
            </div>
        </div>
    </header>

    <!-- Sub-Navbar Modul Aset -->
    <div class="border-b border-slate-200 bg-white shadow-xs shrink-0">
        <div class="mx-auto flex max-w-7xl items-center justify-between overflow-x-auto px-4 py-2.5 sm:px-6 lg:px-8">
            <div class="flex items-center gap-2 text-sm font-medium">
                <a href="{{ route('backoffice.fixed-assets.index') }}" class="rounded-lg bg-purple-600 px-3.5 py-2 font-bold text-white shadow-xs">
                    Daftar Harta Tetap
                </a>
                <a href="{{ route('backoffice.fixed-assets.create') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    + Tambah Aset Tetap
                </a>
                <a href="{{ route('reports.fixed-assets') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Laporan Register Aset
                </a>
                <a href="/reports/accounting/ledger" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Buku Besar Akuntansi
                </a>
            </div>
            <a href="{{ route('backoffice.fixed-assets.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3.5 py-2 text-xs font-bold text-amber-400 shadow-xs hover:bg-slate-800 transition">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Tambah Aset Baru
            </a>
        </div>
    </div>

    <!-- Main Container -->
    <main class="flex-1 mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8 space-y-6">
        <!-- Flash Messages -->
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900 shadow-xs flex items-center gap-3">
                <svg class="h-5 w-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span class="text-sm font-semibold">{{ session('success') }}</span>
            </div>
        @endif

        @if (session('info'))
            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sky-900 shadow-xs flex items-center gap-3">
                <svg class="h-5 w-5 text-sky-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-sm font-semibold">{{ session('info') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-rose-900 shadow-xs">
                <p class="text-sm font-bold">Terjadi kesalahan input:</p>
                <ul class="mt-1 list-disc list-inside text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Summary KPI Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Nilai Perolehan</p>
                    <div class="rounded-xl bg-sky-500/10 p-2 text-sky-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </div>
                </div>
                <p class="mt-3 text-2xl font-black font-mono-num text-slate-900">{{ $formatRupiah($totalAcquisition) }}</p>
                <p class="mt-1 text-xs text-slate-400">Total harga beli historis seluruh aset</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wider text-rose-600">Akumulasi Penyusutan</p>
                    <div class="rounded-xl bg-rose-500/10 p-2 text-rose-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/></svg>
                    </div>
                </div>
                <p class="mt-3 text-2xl font-black font-mono-num text-rose-600">{{ $formatRupiah($totalDepreciation) }}</p>
                <p class="mt-1 text-xs text-rose-500/80">Total depresiasi garis lurus terbukukan</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wider text-purple-700">Total Nilai Buku</p>
                    <div class="rounded-xl bg-purple-500/10 p-2 text-purple-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                </div>
                <p class="mt-3 text-2xl font-black font-mono-num text-purple-900">{{ $formatRupiah($totalBookValue) }}</p>
                <p class="mt-1 text-xs text-purple-600/80">Net Book Value (Perolehan - Akumulasi)</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Status Aset</p>
                    <div class="rounded-xl bg-emerald-500/10 p-2 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <p class="mt-3 text-2xl font-black font-mono-num text-emerald-600">{{ $activeAssetCount }} <span class="text-sm font-normal text-slate-500">Aktif</span></p>
                <p class="mt-1 text-xs text-slate-400">Dari total {{ $assets->count() }} unit terdaftar</p>
            </div>
        </div>

        <!-- Panel Kontrol Penyusutan & Filter Cabang -->
        <div class="rounded-2xl border border-purple-200 bg-gradient-to-r from-purple-50 to-indigo-50 p-6 shadow-sm">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div class="max-w-xl">
                    <div class="flex items-center gap-2">
                        <span class="p-1.5 rounded-lg bg-purple-600 text-white">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </span>
                        <h2 class="text-base font-bold text-purple-950">Mesin Penyusutan Garis Lurus Otomatis</h2>
                    </div>
                    <p class="text-xs text-purple-800 mt-1 leading-relaxed">
                        Jalankan proses penyusutan periodik bulanan. Sistem akan menghitung <code>(Nilai Perolehan - Nilai Sisa) / Umur</code>, mencegah duplikasi pada periode yang sama, serta otomatis membuat <strong>Jurnal Akuntansi Seimbang</strong> (Debit Beban / Kredit Akumulasi).
                    </p>
                </div>

                <!-- Form Eksekusi Penyusutan Bulanan -->
                <form method="POST" action="{{ route('backoffice.fixed-assets.run-depreciation') }}" class="flex flex-wrap items-center gap-3 bg-white p-3.5 rounded-xl border border-purple-200 shadow-xs">
                    @csrf
                    <div>
                        <label for="year_month" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Periode Bulan</label>
                        <input type="month" id="year_month" name="year_month" value="{{ $currentYearMonth }}" required class="rounded-lg border border-slate-300 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-900 focus:border-purple-500 focus:bg-white focus:outline-none">
                    </div>

                    @if ($isMaster)
                        <div>
                            <label for="depreciation_branch_id" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Cabang</label>
                            <select id="depreciation_branch_id" name="branch_id" class="rounded-lg border border-slate-300 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-900 focus:border-purple-500 focus:bg-white focus:outline-none">
                                <option value="">Semua Cabang (Global)</option>
                                @foreach ($branches as $b)
                                    <option value="{{ $b->id }}" {{ (string)$selectedBranchId === (string)$b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="pt-4">
                        <button type="submit" id="btn-run-depreciation" onclick="return confirm('Apakah Anda yakin ingin menjalankan penyusutan untuk periode yang dipilih?')" class="inline-flex items-center gap-2 rounded-lg bg-purple-700 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-purple-800 focus:outline-none transition">
                            <svg class="w-4 h-4 text-purple-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Jalankan Penyusutan Bulan Ini</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabel Daftar Harta Tetap -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
            <div class="border-b border-slate-200 bg-slate-50/70 px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h3 class="font-bold text-slate-900 text-sm">Katalog Aset &amp; Status Buku Besar</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Daftar aktiva tetap yang terdaftar dan nilai depresiasi berjalan</p>
                </div>
                @if ($isMaster)
                    <form method="GET" action="{{ route('backoffice.fixed-assets.index') }}" class="flex items-center gap-2">
                        <label for="filter_branch" class="text-xs font-semibold text-slate-500">Filter Cabang:</label>
                        <select id="filter_branch" name="branch_id" onchange="this.form.submit()" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-800 focus:outline-none">
                            <option value="">Semua Cabang</option>
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}" {{ (string)$selectedBranchId === (string)$b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-900 text-white uppercase text-[10px] tracking-wider">
                            <th class="py-3.5 px-4">Nama Aset &amp; Cabang</th>
                            <th class="py-3.5 px-4">Tgl Beli &amp; Umur</th>
                            <th class="py-3.5 px-4 text-right">Nilai Perolehan</th>
                            <th class="py-3.5 px-4 text-right">Penyusutan/Bulan</th>
                            <th class="py-3.5 px-4 text-right">Akumulasi Penyusutan</th>
                            <th class="py-3.5 px-4 text-right">Nilai Buku</th>
                            <th class="py-3.5 px-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($assets as $asset)
                            @php
                                $acq = (float) $asset->purchase_price;
                                $dep = (float) ($asset->depreciations_sum_amount ?? 0);
                                $book = $acq - $dep;
                            @endphp
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-3 px-4">
                                    <span class="block font-bold text-slate-900 text-sm">{{ $asset->name }}</span>
                                    <div class="flex items-center gap-1.5 mt-0.5 text-[11px] text-slate-500">
                                        <span class="rounded bg-slate-100 px-1.5 py-0.5 font-medium text-slate-600">{{ $asset->branch?->name ?? 'Pusat' }}</span>
                                        <span>&bull;</span>
                                        <span>Residu: {{ $formatRupiah((float) $asset->salvage_value) }}</span>
                                    </div>
                                    <div class="mt-1 text-[10px] text-slate-400">
                                        Akun: {{ $asset->assetAccount?->name }} / {{ $asset->expenseAccount?->name }}
                                    </div>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <span class="font-medium text-slate-800">{{ $asset->purchase_date?->format('d/m/Y') }}</span>
                                    <span class="block text-[11px] text-slate-500 mt-0.5">{{ $asset->useful_life_months }} Bulan ({{ round($asset->useful_life_months / 12, 1) }} Thn)</span>
                                </td>
                                <td class="py-3 px-4 text-right font-mono-num font-semibold text-slate-900 whitespace-nowrap">
                                    {{ $formatRupiah($acq) }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono-num text-slate-600 whitespace-nowrap">
                                    {{ $formatRupiah((float) $asset->monthly_depreciation) }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono-num font-semibold text-rose-700 whitespace-nowrap">
                                    {{ $formatRupiah($dep) }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono-num font-bold text-purple-950 whitespace-nowrap">
                                    {{ $formatRupiah($book) }}
                                </td>
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    @if ($asset->status === 'ACTIVE')
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700">
                                            ACTIVE
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 border border-slate-200 px-2.5 py-0.5 text-[10px] font-bold text-slate-600">
                                            DISPOSED
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                        <p class="text-sm font-semibold text-slate-600">Belum ada aset tetap yang tercatat di sistem.</p>
                                        <p class="text-xs text-slate-400">Klik tombol di bawah untuk mendaftarkan aset tetap pertama.</p>
                                        <a href="{{ route('backoffice.fixed-assets.create') }}" class="mt-2 inline-flex items-center gap-1.5 rounded-xl bg-purple-700 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-purple-800 transition">
                                            + Tambah Aset Tetap Baru
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-100 font-bold border-t-2 border-slate-900 text-slate-900">
                            <td colspan="2" class="py-3 px-4 uppercase tracking-wider">
                                Total ({{ $assets->count() }} Aset)
                            </td>
                            <td class="py-3 px-4 text-right font-mono-num text-sm text-slate-950">
                                {{ $formatRupiah($totalAcquisition) }}
                            </td>
                            <td class="py-3 px-4 text-right text-slate-400">-</td>
                            <td class="py-3 px-4 text-right font-mono-num text-sm text-rose-700">
                                {{ $formatRupiah($totalDepreciation) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono-num text-sm text-purple-900">
                                {{ $formatRupiah($totalBookValue) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
