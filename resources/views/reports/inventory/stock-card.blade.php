<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kartu Stok (Stock Card Movement) | Maju Bersama ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; font-size: 11px; }
            .print-border { border-color: #94a3b8 !important; }
        }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <!-- Header Utama -->
    <header class="border-b border-slate-800 bg-slate-950 text-white no-print">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
            <div class="flex items-center gap-4">
                <a href="/backoffice" class="block">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-400">Maju Bersama ERP</p>
                    <h1 class="text-xl font-bold tracking-tight">Laporan Mutasi &amp; Kartu Stok</h1>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <nav class="hidden items-center gap-4 text-sm text-slate-300 md:flex">
                    <a href="/pos" class="hover:text-white">Kasir (POS)</a>
                    <a href="/inventory" class="hover:text-white">Persediaan</a>
                    <a href="/inventory/adjustments" class="hover:text-white">Stok Opname</a>
                    <a href="/purchases/goods-receipts" class="hover:text-white">Penerimaan Barang</a>
                    <a href="/reports/accounting/ledger" class="hover:text-white">Buku Besar</a>
                    <a href="/backoffice" class="hover:text-white">Panel Admin</a>
                </nav>
                <div class="rounded-full border border-sky-400/30 bg-sky-400/10 px-3 py-1 text-xs font-medium text-sky-200">
                    {{ $currentUser->branch?->name ?? 'Pusat' }} ({{ $currentUser->role }})
                </div>
                <form method="POST" action="/logout" class="hidden sm:block">
                    @csrf
                    <button type="submit" class="text-sm text-slate-300 hover:text-white">Keluar</button>
                </form>
            </div>
        </div>
    </header>

    <!-- Sub-Navbar Modul Laporan -->
    <div class="border-b border-slate-200 bg-white no-print">
        <div class="mx-auto flex max-w-7xl items-center justify-between overflow-x-auto px-6 py-2.5 lg:px-8">
            <div class="flex items-center gap-2 text-sm font-medium">
                <a href="{{ route('reports.inventory.stock-card') }}" class="rounded-lg bg-indigo-600 px-3.5 py-2 font-semibold text-white shadow-sm">
                    Kartu Stok Produk
                </a>
                <a href="/inventory" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Katalog Persediaan
                </a>
                <a href="/inventory/adjustments" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Stock Opname
                </a>
                <a href="/inventory/transfer" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Transfer Stok
                </a>
                <a href="/reports/accounting/ledger" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Buku Besar
                </a>
            </div>
            @if ($stockCard)
                <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                    <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                    </svg>
                    Cetak Kartu Stok
                </button>
            @endif
        </div>
    </div>

    <!-- Konten Utama -->
    <main class="mx-auto max-w-7xl px-6 py-8 lg:px-8">
        <!-- Form Filter & Pencarian Produk Interaktif (Alpine.js) -->
        <div 
            class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm no-print"
            x-data="stockCardFilter({{ Js::from($products) }}, '{{ $selectedProductId }}')"
        >
            <form method="GET" action="{{ route('reports.inventory.stock-card') }}" class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Dropdown Cabang (jika master) -->
                    @if ($isMaster && $branches->count() > 1)
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Cabang</label>
                            <select 
                                name="branch_id" 
                                onchange="this.form.submit()" 
                                class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                            >
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" {{ $selectedBranchId == $branch->id ? 'selected' : '' }}>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">
                    @endif

                    <!-- Pencarian & Pemilihan Produk (Dinamis dengan Search Autocomplete) -->
                    <div class="relative sm:col-span-2 lg:col-span-1" @click.away="showDropdown = false">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Pilih Produk (Wajib)</label>
                        <input type="hidden" name="product_id" :value="selectedId">
                        
                        <div class="relative mt-1">
                            <input 
                                type="text" 
                                x-model="searchQuery" 
                                @focus="showDropdown = true"
                                placeholder="Ketik nama atau SKU..."
                                class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 pr-8 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                            >
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </div>

                        <!-- Dropdown Produk -->
                        <div 
                            x-show="showDropdown && filteredProducts.length > 0" 
                            x-cloak
                            class="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-xl"
                        >
                            <template x-for="p in filteredProducts" :key="p.id">
                                <button 
                                    type="button" 
                                    @click="selectProduct(p)" 
                                    class="flex w-full items-center justify-between px-3 py-2 text-left text-xs transition hover:bg-indigo-50"
                                >
                                    <div>
                                        <p class="font-bold text-slate-900" x-text="p.name"></p>
                                        <p class="font-mono text-slate-500" x-text="'SKU: ' + p.sku"></p>
                                    </div>
                                    <span class="rounded bg-slate-100 px-2 py-0.5 font-semibold text-slate-700" x-text="'Stok: ' + p.stock"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Tanggal Mulai -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Tanggal Mulai</label>
                        <input 
                            type="date" 
                            name="start_date" 
                            value="{{ $startDate }}" 
                            class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>

                    <!-- Tanggal Selesai -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Tanggal Selesai</label>
                        <input 
                            type="date" 
                            name="end_date" 
                            value="{{ $endDate }}" 
                            class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>
                </div>

                <div class="flex items-center justify-between border-t border-slate-100 pt-3">
                    <span class="text-xs text-slate-500">
                        Pilih produk untuk melacak seluruh pergerakan mutasi fisik dan saldo berjalan secara akurat.
                    </span>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('reports.inventory.stock-card') }}" class="rounded-lg border border-slate-300 bg-slate-100 px-3.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-200">
                            Reset
                        </a>
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500">
                            Tampilkan Kartu Stok
                        </button>
                    </div>
                </div>
            </form>
        </div>

        @if ($stockCard)
            <!-- Dokumen Kartu Stok (Tampilan Layar & Cetak) -->
            <div class="space-y-6">
                <!-- Header Dokumen Kartu Stok -->
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-col justify-between gap-4 border-b border-slate-100 pb-5 md:flex-row md:items-center">
                        <div>
                            <span class="inline-flex items-center rounded-md bg-indigo-100 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-indigo-800">
                                Kartu Stok Persediaan (Stock Card)
                            </span>
                            <h2 class="mt-2 text-2xl font-black text-slate-900">{{ $stockCard['product']->name }}</h2>
                            <p class="text-sm font-mono text-slate-500">SKU: <strong class="text-slate-800">{{ $stockCard['product']->sku }}</strong></p>
                        </div>
                        <div class="text-left md:text-right">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Cabang / Gudang</p>
                            <p class="text-base font-bold text-slate-800">{{ $stockCard['branch']->name }}</p>
                            <p class="text-xs text-slate-500">
                                Periode: 
                                <strong>
                                    {{ $stockCard['start_date'] ? \Carbon\Carbon::parse($stockCard['start_date'])->format('d/m/Y') : 'Awal' }} 
                                    s/d 
                                    {{ $stockCard['end_date'] ? \Carbon\Carbon::parse($stockCard['end_date'])->format('d/m/Y') : 'Sekarang' }}
                                </strong>
                            </p>
                        </div>
                    </div>

                    <!-- Kartu Ringkasan Metrik Mutasi -->
                    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-5">
                        <!-- Saldo Awal -->
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-center">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Saldo Awal</p>
                            <p class="mt-1 text-xl font-bold text-slate-800">{{ number_format($stockCard['beginning_balance']) }}</p>
                            <p class="text-[10px] text-slate-400">Unit sebelum periode</p>
                        </div>

                        <!-- Total Masuk -->
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-3.5 text-center">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700">Total Masuk (+)</p>
                            <p class="mt-1 text-xl font-bold text-emerald-800">+{{ number_format($stockCard['total_in']) }}</p>
                            <p class="text-[10px] text-emerald-600">Penerimaan &amp; Transfer Masuk</p>
                        </div>

                        <!-- Total Keluar -->
                        <div class="rounded-xl border border-rose-200 bg-rose-50/60 p-3.5 text-center">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-rose-700">Total Keluar (-)</p>
                            <p class="mt-1 text-xl font-bold text-rose-800">-{{ number_format($stockCard['total_out']) }}</p>
                            <p class="text-[10px] text-rose-600">Penjualan &amp; Transfer Keluar</p>
                        </div>

                        <!-- Saldo Akhir Periode -->
                        <div class="rounded-xl border border-indigo-200 bg-indigo-50/60 p-3.5 text-center">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-indigo-700">Saldo Akhir Periode</p>
                            <p class="mt-1 text-xl font-black text-indigo-900">{{ number_format($stockCard['ending_balance']) }}</p>
                            <p class="text-[10px] text-indigo-600">Unit per akhir periode</p>
                        </div>

                        <!-- Stok Sistem Terkini -->
                        <div class="col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-center sm:col-span-1">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Stok On-Hand Saat Ini</p>
                            <p class="mt-1 text-xl font-bold text-slate-900">{{ number_format($stockCard['current_stock']) }}</p>
                            <p class="text-[10px] text-slate-500">Database Live</p>
                        </div>
                    </div>
                </div>

                <!-- Tabel Riwayat Mutasi Kronologis -->
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600">
                                <tr>
                                    <th class="px-5 py-3.5">Tanggal</th>
                                    <th class="px-5 py-3.5">No. Referensi</th>
                                    <th class="px-5 py-3.5">Tipe Transaksi</th>
                                    <th class="px-5 py-3.5">Keterangan / Rekanan</th>
                                    <th class="px-5 py-3.5 text-right text-emerald-700">Masuk (In)</th>
                                    <th class="px-5 py-3.5 text-right text-rose-700">Keluar (Out)</th>
                                    <th class="px-5 py-3.5 text-right font-bold text-slate-900">Saldo Berjalan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-800">
                                <!-- Baris Saldo Awal -->
                                <tr class="bg-slate-50/80 font-medium text-slate-600 italic">
                                    <td class="whitespace-nowrap px-5 py-3 font-sans">
                                        {{ $stockCard['start_date'] ? \Carbon\Carbon::parse($stockCard['start_date'])->format('d/m/Y') : '-' }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 font-mono">-</td>
                                    <td class="whitespace-nowrap px-5 py-3">
                                        <span class="inline-flex items-center rounded-md bg-slate-200 px-2 py-0.5 text-xs font-bold uppercase tracking-wider text-slate-700 font-sans">
                                            SALDO AWAL
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-xs">Saldo persediaan barang sebelum periode yang dipilih</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right font-mono text-slate-400">-</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right font-mono text-slate-400">-</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right font-mono font-bold text-slate-900 not-italic">
                                        {{ number_format($stockCard['beginning_balance']) }}
                                    </td>
                                </tr>

                                <!-- Baris Mutasi Periode -->
                                @forelse ($stockCard['movements'] as $mov)
                                    <tr class="transition hover:bg-slate-50/70">
                                        <td class="whitespace-nowrap px-5 py-3.5 font-sans font-medium text-slate-900">
                                            {{ \Carbon\Carbon::parse($mov->date)->format('d/m/Y') }}
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3.5 font-mono text-xs font-semibold text-indigo-700">
                                            {{ $mov->reference_number }}
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3.5">
                                            @if (str_contains($mov->type, 'Penerimaan'))
                                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                                    {{ $mov->type }}
                                                </span>
                                            @elseif (str_contains($mov->type, 'Penjualan'))
                                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                                                    {{ $mov->type }}
                                                </span>
                                            @elseif (str_contains($mov->type, 'Transfer'))
                                                <span class="inline-flex items-center rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-semibold text-sky-800">
                                                    {{ $mov->type }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-semibold text-purple-800">
                                                    {{ $mov->type }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3.5 text-xs text-slate-600">
                                            {{ $mov->notes }}
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3.5 text-right font-mono font-bold text-emerald-600">
                                            {{ $mov->qty_in > 0 ? '+' . number_format($mov->qty_in) : '-' }}
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3.5 text-right font-mono font-bold text-rose-600">
                                            {{ $mov->qty_out > 0 ? '-' . number_format($mov->qty_out) : '-' }}
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3.5 text-right font-mono font-black text-slate-900 bg-slate-50/50">
                                            {{ number_format($mov->running_balance) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-5 py-8 text-center text-slate-400 italic">
                                            Tidak ada transaksi mutasi stok pada rentang tanggal ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="bg-slate-100 font-bold text-slate-900 border-t-2 border-slate-300">
                                <tr>
                                    <td colspan="4" class="px-5 py-3 text-right uppercase tracking-wider text-xs">
                                        Total Mutasi Periode &amp; Saldo Akhir:
                                    </td>
                                    <td class="px-5 py-3 text-right font-mono text-emerald-700">
                                        +{{ number_format($stockCard['total_in']) }}
                                    </td>
                                    <td class="px-5 py-3 text-right font-mono text-rose-700">
                                        -{{ number_format($stockCard['total_out']) }}
                                    </td>
                                    <td class="px-5 py-3 text-right font-mono text-base font-black text-indigo-900 bg-indigo-50">
                                        {{ number_format($stockCard['ending_balance']) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Lembar Tanda Tangan Cetak Fisik -->
                <div class="mt-8 border-t border-dashed border-slate-300 pt-6 hidden print:block">
                    <div class="grid grid-cols-3 gap-6 text-center text-xs">
                        <div>
                            <p class="font-semibold text-slate-600">Petugas Gudang / Logistik</p>
                            <div class="mt-14 border-b border-slate-400 mx-auto w-36"></div>
                            <p class="mt-1 text-slate-500">( Nama &amp; Paraf )</p>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-600">Staff Akuntansi Persediaan</p>
                            <div class="mt-14 border-b border-slate-400 mx-auto w-36"></div>
                            <p class="mt-1 text-slate-500">( Nama &amp; Paraf )</p>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-600">Pimpinan / Kepala Cabang</p>
                            <div class="mt-14 border-b border-slate-400 mx-auto w-36"></div>
                            <p class="mt-1 text-slate-500">( Tanda Tangan &amp; Cap )</p>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <!-- Empty State Jika Pengguna Belum Memilih Produk -->
            <div class="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-sm">
                <svg class="mx-auto h-16 w-16 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 class="mt-4 text-lg font-bold text-slate-900">Silakan Pilih Produk Terlebih Dahulu</h3>
                <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">
                    Gunakan formulir pencarian di atas untuk memilih produk dari katalog cabang Anda. Sistem akan secara kronologis merekonstruksi mutasi keluar/masuk dan saldo berjalan stok.
                </p>
            </div>
        @endif
    </main>

    <!-- Script Autocomplete Pencarian Produk Alpine.js -->
    <script>
        function stockCardFilter(productsList, initialSelectedId) {
            const initialProduct = (productsList || []).find(p => p.id == initialSelectedId);
            return {
                products: productsList || [],
                selectedId: initialSelectedId || '',
                searchQuery: initialProduct ? initialProduct.name + ' (' + initialProduct.sku + ')' : '',
                showDropdown: false,

                get filteredProducts() {
                    if (!this.searchQuery.trim()) {
                        return this.products.slice(0, 15);
                    }
                    const q = this.searchQuery.toLowerCase();
                    return this.products.filter(p => 
                        p.name.toLowerCase().includes(q) || 
                        p.sku.toLowerCase().includes(q)
                    ).slice(0, 20);
                },

                selectProduct(p) {
                    this.selectedId = p.id;
                    this.searchQuery = p.name + ' (' + p.sku + ')';
                    this.showDropdown = false;
                }
            };
        }
    </script>
</body>
</html>
