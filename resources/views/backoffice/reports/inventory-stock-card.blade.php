<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kartu Stok &amp; Mutasi Barang | Maju Bersama ERP</title>
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
        $product = $report['product'] ?? null;
        $branch = $report['branch'] ?? null;
        $products = $report['products'] ?? collect([]);
        $movements = $report['movements'] ?? collect([]);
        $beginningBalance = (int) ($report['beginning_balance'] ?? 0);
        $totalIn = (int) ($report['total_in'] ?? 0);
        $totalOut = (int) ($report['total_out'] ?? 0);
        $endingBalance = (int) ($report['ending_balance'] ?? 0);
        $currentStock = (int) ($report['current_stock'] ?? 0);
    @endphp

    <!-- Header Bar Utama (Aplikasi) -->
    <header class="border-b border-slate-800 bg-slate-950 text-white shrink-0 no-print">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3.5 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                <a href="/backoffice" class="block group">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-amber-400 group-hover:text-amber-300 transition">Maju Bersama ERP</p>
                    <div class="flex items-center gap-2">
                        <h1 class="text-base font-bold tracking-tight">Pusat Laporan</h1>
                        <span class="rounded bg-slate-800 px-1.5 py-0.5 text-[10px] font-mono text-slate-300">Stock Card</span>
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </span>
                    <h2 class="text-lg font-bold text-slate-900">Kartu Stok &amp; Mutasi Barang</h2>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Riwayat pergerakan stok masuk, keluar, transfer cabang, dan saldo akhir secara kronologis</p>
            </div>

            <!-- Form Filter: Tanggal, Cabang, dan DROPDOWN PRODUK -->
            <form method="GET" action="{{ route('reports.stock-card') }}" class="flex flex-wrap items-center gap-2">
                <!-- Dropdown Produk Filter -->
                <div class="flex items-center gap-1.5 bg-slate-50 border border-amber-300 rounded-lg px-2.5 py-1.5 text-xs shadow-xs">
                    <label for="product_id" class="text-slate-700 font-bold">Produk:</label>
                    <select id="product_id" name="product_id" class="bg-transparent text-slate-900 font-semibold focus:outline-none max-w-[200px] truncate">
                        @if ($products->isEmpty())
                            <option value="">(Belum Ada Produk)</option>
                        @else
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}" {{ (string)$selectedProductId === (string)$p->id ? 'selected' : '' }}>
                                    {{ $p->sku ? '[' . $p->sku . '] ' : '' }}{{ $p->name }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>

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

    <!-- Main Content Paper Container -->
    <main class="flex-1 mx-auto w-full max-w-6xl p-4 sm:p-6 lg:p-8 flex flex-col justify-start">
        <div class="print-container rounded-2xl border border-slate-300 bg-white shadow-lg p-6 sm:p-8 lg:p-10 text-slate-900">
            
            <!-- KOP PERUSAHAAN (MAJU BERSAMA GRUP) -->
            <header class="border-b-2 border-slate-900 pb-5 mb-6">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold tracking-[0.2em] text-amber-600 uppercase">Sistem Inventaris &amp; Logistik Terintegrasi</p>
                        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-950 uppercase mt-0.5">MAJU BERSAMA GRUP</h1>
                        <p class="text-xs text-slate-500 mt-1">Multi-Store Agriculture &amp; FMCG Retail Network</p>
                    </div>
                    <div class="sm:text-right">
                        <span class="inline-block rounded-md bg-slate-900 text-white font-mono text-xs px-2.5 py-1 font-bold tracking-wider uppercase">
                            KARTU STOK BARANG
                        </span>
                        <p class="text-xs font-semibold text-slate-700 mt-1.5">Buku Mutasi Keluar/Masuk Fisik</p>
                    </div>
                </div>

                <!-- Informasi Barang Terpilih & Cabang -->
                <div class="mt-4 pt-3 border-t border-slate-200 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-slate-500">Nama Barang:</span>
                            <span class="font-bold text-slate-900 text-sm">{{ $product->name ?? 'Belum Memilih Barang' }}</span>
                        </div>
                        <div class="flex items-center gap-4 mt-1 text-[11px] text-slate-500">
                            <span>SKU: <strong class="text-slate-800 font-mono">{{ $product->sku ?? '-' }}</strong></span>
                            <span>Kategori: <strong class="text-slate-800">{{ $product->category->name ?? 'Umum' }}</strong></span>
                            <span>Satuan: <strong class="text-slate-800">{{ $product->unit ?? 'PCS' }}</strong></span>
                        </div>
                    </div>
                    <div class="sm:text-right">
                        <div>
                            <span class="text-slate-500">Cabang / Gudang:</span>
                            <span class="font-semibold text-slate-800 ml-1">{{ $branch->name ?? 'Pusat' }}</span>
                        </div>
                        <div class="mt-1 text-[11px] text-slate-500">
                            <span>Periode: <strong class="text-slate-800">{{ \Carbon\Carbon::parse($startDate)->isoFormat('D MMMM Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->isoFormat('D MMMM Y') }}</strong></span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- KPI Stock Movement Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
                <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-3.5">
                    <p class="text-[11px] font-semibold uppercase text-slate-500">Saldo Awal</p>
                    <p class="text-xl font-extrabold text-slate-800 font-mono-num mt-1">{{ number_format($beginningBalance, 0, ',', '.') }}</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">Per {{ \Carbon\Carbon::parse($startDate)->isoFormat('D MMM Y') }}</p>
                </div>

                <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-3.5">
                    <p class="text-[11px] font-semibold uppercase text-emerald-800">Total Masuk (In)</p>
                    <p class="text-xl font-extrabold text-emerald-700 font-mono-num mt-1">+{{ number_format($totalIn, 0, ',', '.') }}</p>
                    <p class="text-[10px] text-emerald-600 mt-0.5">Beli, Retur &amp; Transfer Masuk</p>
                </div>

                <div class="rounded-xl border border-rose-200 bg-rose-50/50 p-3.5">
                    <p class="text-[11px] font-semibold uppercase text-rose-800">Total Keluar (Out)</p>
                    <p class="text-xl font-extrabold text-rose-700 font-mono-num mt-1">-{{ number_format($totalOut, 0, ',', '.') }}</p>
                    <p class="text-[10px] text-rose-600 mt-0.5">Penjualan &amp; Transfer Keluar</p>
                </div>

                <div class="rounded-xl border border-sky-300 bg-sky-50/60 p-3.5">
                    <p class="text-[11px] font-semibold uppercase text-sky-800">Sisa Saldo Akhir</p>
                    <p class="text-xl font-extrabold text-sky-900 font-mono-num mt-1">{{ number_format($endingBalance, 0, ',', '.') }}</p>
                    <p class="text-[10px] text-sky-600 mt-0.5">Fisik On-Hand: {{ number_format($currentStock, 0, ',', '.') }}</p>
                </div>
            </div>

            <!-- Tabel Kronologi Kartu Stok -->
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-900 text-white uppercase text-[10px] tracking-wider">
                            <th class="py-3 px-3">Tanggal</th>
                            <th class="py-3 px-3">Dokumen (No. Ref)</th>
                            <th class="py-3 px-3">Keterangan</th>
                            <th class="py-3 px-3 text-right text-emerald-300">Masuk</th>
                            <th class="py-3 px-3 text-right text-rose-300">Keluar</th>
                            <th class="py-3 px-3 text-right text-amber-300">Sisa Saldo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <!-- Baris Saldo Awal -->
                        <tr class="bg-slate-50 font-semibold text-slate-800">
                            <td class="py-2.5 px-3 font-mono-num whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($startDate)->isoFormat('DD/MM/YYYY') }}
                            </td>
                            <td class="py-2.5 px-3 font-mono text-slate-500 whitespace-nowrap">
                                -
                            </td>
                            <td class="py-2.5 px-3 text-slate-700 italic">
                                SALDO AWAL PERIODE
                            </td>
                            <td class="py-2.5 px-3 text-right font-mono-num text-slate-400">-</td>
                            <td class="py-2.5 px-3 text-right font-mono-num text-slate-400">-</td>
                            <td class="py-2.5 px-3 text-right font-mono-num font-bold text-slate-900 whitespace-nowrap">
                                {{ number_format($beginningBalance, 0, ',', '.') }}
                            </td>
                        </tr>

                        <!-- Daftar Pergerakan Kronologis -->
                        @forelse ($movements as $mov)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="py-2.5 px-3 font-mono-num text-slate-700 whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($mov->date)->isoFormat('DD/MM/YYYY') }}
                                </td>
                                <td class="py-2.5 px-3 font-mono font-semibold text-slate-900 whitespace-nowrap">
                                    {{ $mov->reference_number }}
                                </td>
                                <td class="py-2.5 px-3 text-slate-800">
                                    <div class="font-medium">{{ $mov->notes ?? '-' }}</div>
                                    <div class="text-[10px] text-slate-400 uppercase font-mono">{{ $mov->type ?? 'MUTASI' }}</div>
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono-num text-emerald-700 font-semibold whitespace-nowrap">
                                    {{ $mov->qty_in > 0 ? '+' . number_format($mov->qty_in, 0, ',', '.') : '-' }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono-num text-rose-700 font-semibold whitespace-nowrap">
                                    {{ $mov->qty_out > 0 ? '-' . number_format($mov->qty_out, 0, ',', '.') : '-' }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono-num font-bold text-slate-900 whitespace-nowrap">
                                    {{ number_format($mov->running_balance, 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400 italic">
                                    Tidak ada transaksi mutasi fisik untuk barang ini pada rentang tanggal yang dipilih.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-100 font-bold border-t-2 border-slate-900 text-slate-900">
                            <td colspan="3" class="py-3 px-3 uppercase text-right tracking-wider">
                                Total Pergerakan &amp; Saldo Akhir
                            </td>
                            <td class="py-3 px-3 text-right font-mono-num text-emerald-800">
                                +{{ number_format($totalIn, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono-num text-rose-800">
                                -{{ number_format($totalOut, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono-num text-sm text-sky-900">
                                {{ number_format($endingBalance, 0, ',', '.') }}
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
