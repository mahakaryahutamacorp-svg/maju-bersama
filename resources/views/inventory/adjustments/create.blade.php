<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Penyesuaian Stok (Stock Opname) | Maju Bersama ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <!-- Header Utama -->
    <header class="border-b border-slate-800 bg-slate-950 text-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
            <div class="flex items-center gap-4">
                <a href="/backoffice" class="block">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-400">Maju Bersama ERP</p>
                    <h1 class="text-xl font-bold tracking-tight">Formulir Stock Opname</h1>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <nav class="hidden items-center gap-4 text-sm text-slate-300 md:flex">
                    <a href="/inventory/adjustments" class="hover:text-white">← Kembali ke Riwayat</a>
                    <a href="/inventory" class="hover:text-white">Katalog Stok</a>
                    <a href="/backoffice" class="hover:text-white">Backoffice</a>
                </nav>
                <div class="rounded-full border border-sky-400/30 bg-sky-400/10 px-3 py-1 text-xs font-medium text-sky-200">
                    {{ $currentBranch->name }} ({{ $currentUser->role }})
                </div>
            </div>
        </div>
    </header>

    <!-- Sub-Navbar Breadcrumb -->
    <div class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-2.5 lg:px-8">
            <nav class="flex items-center gap-2 text-sm text-slate-500">
                <a href="/inventory" class="hover:text-slate-900">Inventory</a>
                <span>/</span>
                <a href="{{ route('inventory.adjustments.index') }}" class="hover:text-slate-900">Stock Opname</a>
                <span>/</span>
                <span class="font-semibold text-slate-900">Input Hasil Pemeriksaan Fisik</span>
            </nav>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                    <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Mode Live Audit Real-time
                </span>
            </div>
        </div>
    </div>

    <!-- Konten Utama Alpine.js -->
    <main 
        class="mx-auto max-w-7xl px-6 py-8 lg:px-8" 
        x-data="stockAdjustmentForm({{ Js::from($products) }}, {{ $currentBranch->id }})"
        x-cloak
    >
        <!-- Error Alerts jika ada dari Laravel Validation -->
        @if ($errors->any())
            <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 shadow-sm">
                <div class="flex items-center gap-2 font-semibold">
                    <svg class="h-5 w-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    Terjadi Kesalahan Pengisian Formulir:
                </div>
                <ul class="mt-2 list-inside list-disc pl-2 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('inventory.adjustments.store') }}" @submit="handleSubmit($event)">
            @csrf
            <input type="hidden" name="branch_id" :value="branchId">

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                <!-- Kolom Kiri & Tengah: Input Form & Tabel Item (Col Span 2) -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Kartu Informasi Dokumen -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <svg class="h-5 w-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Informasi Sesi Stock Opname
                        </h2>

                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-medium text-slate-700">Cabang Lokasi Opname</label>
                                @if ($isMaster && $branches->count() > 1)
                                    <select 
                                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                        x-model="branchId"
                                        @change="changeBranch($event.target.value)"
                                    >
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}" {{ $currentBranch->id == $branch->id ? 'selected' : '' }}>
                                                {{ $branch->name }} ({{ $branch->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="text" readonly value="{{ $currentBranch->name }}" class="mt-1 block w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600 font-medium">
                                @endif
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-slate-700">Tanggal Pemeriksaan Fisik</label>
                                <input 
                                    type="date" 
                                    name="date" 
                                    x-model="date" 
                                    required 
                                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                >
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-slate-700">Catatan / Alasan Pemeriksaan (Opsional)</label>
                                <input 
                                    type="text" 
                                    name="notes" 
                                    x-model="notes" 
                                    placeholder="Contoh: Opname rutin akhir bulan, audit gudang makanan, dsb." 
                                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Pencarian Pintar Produk (Smart Search Autocomplete) -->
                    <div class="rounded-2xl border border-indigo-200 bg-indigo-50/40 p-5 shadow-sm">
                        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">Pilih Produk Yang Diperiksa</h3>
                                <p class="text-xs text-slate-500">Cari berdasarkan SKU atau Nama Produk untuk dimasukkan ke tabel audit.</p>
                            </div>
                            <button 
                                type="button" 
                                @click="addAllProducts()" 
                                class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-300 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 shadow-sm transition hover:bg-indigo-50"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                                </svg>
                                Muat Semua Produk (<span x-text="availableProducts.length"></span>)
                            </button>
                        </div>

                        <div class="relative mt-3">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <input 
                                type="text" 
                                x-model="searchQuery" 
                                @keydown.escape="showDropdown = false"
                                @focus="showDropdown = true"
                                placeholder="Ketik nama atau SKU produk (misal: Minyak, Beras, PRD-001)..." 
                                class="block w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-10 pr-4 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                            >

                            <!-- Dropdown List Hasil Pencarian -->
                            <div 
                                x-show="showDropdown && filteredProducts.length > 0" 
                                @click.away="showDropdown = false"
                                class="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-xl"
                            >
                                <template x-for="product in filteredProducts" :key="product.id">
                                    <button 
                                        type="button" 
                                        @click="addProduct(product)" 
                                        class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm transition hover:bg-indigo-50"
                                    >
                                        <div>
                                            <p class="font-semibold text-slate-900" x-text="product.name"></p>
                                            <p class="text-xs text-slate-500">
                                                SKU: <span class="font-mono" x-text="product.sku"></span> | HPP: <span x-text="formatRupiah(product.purchase_price)"></span>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">
                                                Stok Sistem: <span class="ml-1" x-text="product.stock"></span>
                                            </span>
                                            <span class="block text-[11px] font-semibold text-indigo-600 hover:underline mt-0.5">+ Tambahkan</span>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Item Hasil Pemeriksaan Fisik -->
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                                <span>Daftar Item Audit Opname</span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700" x-text="items.length + ' item'"></span>
                            </h3>
                            <button 
                                type="button" 
                                x-show="items.length > 0"
                                @click="items = []" 
                                class="text-xs font-semibold text-rose-600 hover:text-rose-800"
                            >
                                Kosongkan Semua
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600">
                                    <tr>
                                        <th class="px-4 py-3">Produk / SKU</th>
                                        <th class="px-4 py-3 text-center">Stok Sistem (Expected)</th>
                                        <th class="px-4 py-3 text-center">Stok Fisik (Actual)</th>
                                        <th class="px-4 py-3 text-center">Selisih Fisik</th>
                                        <th class="px-4 py-3 text-right">HPP (Unit Cost)</th>
                                        <th class="px-4 py-3 text-right">Nilai Selisih</th>
                                        <th class="px-3 py-3 text-center">Hapus</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <template x-for="(item, index) in items" :key="item.product_id">
                                        <tr 
                                            class="transition" 
                                            :class="{
                                                'bg-rose-50/80 border-l-4 border-rose-500': item.actual_qty - item.expected_qty < 0,
                                                'bg-emerald-50/80 border-l-4 border-emerald-500': item.actual_qty - item.expected_qty > 0,
                                                'bg-white border-l-4 border-transparent': item.actual_qty - item.expected_qty === 0
                                            }"
                                        >
                                            <!-- Hidden Inputs untuk submit form Laravel -->
                                            <input type="hidden" :name="'items[' + index + '][product_id]'" :value="item.product_id">
                                            <input type="hidden" :name="'items[' + index + '][expected_qty]'" :value="item.expected_qty">
                                            <input type="hidden" :name="'items[' + index + '][unit_cost]'" :value="item.unit_cost">

                                            <!-- Info Produk -->
                                            <td class="px-4 py-3">
                                                <p class="font-semibold text-slate-900" x-text="item.name"></p>
                                                <p class="font-mono text-xs text-slate-500" x-text="item.sku"></p>
                                            </td>

                                            <!-- Stok Sistem (Expected) -->
                                            <td class="px-4 py-3 text-center font-semibold text-slate-700">
                                                <span class="inline-block rounded-md bg-slate-100 px-2.5 py-1 text-xs" x-text="item.expected_qty"></span>
                                            </td>

                                            <!-- Input Stok Fisik (Actual) -->
                                            <td class="px-4 py-3 text-center">
                                                <input 
                                                    type="number" 
                                                    min="0" 
                                                    :name="'items[' + index + '][actual_qty]'" 
                                                    x-model.number="item.actual_qty" 
                                                    @input="recalculate()"
                                                    required 
                                                    class="w-24 rounded-lg border border-slate-300 px-2.5 py-1.5 text-center font-bold text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                                >
                                            </td>

                                            <!-- Badge Selisih Kuantitas (Conditional Formatting) -->
                                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                                <template x-if="item.actual_qty - item.expected_qty < 0">
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2.5 py-1 text-xs font-bold text-rose-800">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                                                        </svg>
                                                        <span x-text="(item.actual_qty - item.expected_qty) + ' (Minus)'"></span>
                                                    </span>
                                                </template>
                                                <template x-if="item.actual_qty - item.expected_qty > 0">
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
                                                        </svg>
                                                        <span x-text="'+' + (item.actual_qty - item.expected_qty) + ' (Plus)'"></span>
                                                    </span>
                                                </template>
                                                <template x-if="item.actual_qty - item.expected_qty === 0">
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                                                        ✓ Pas / Klop (0)
                                                    </span>
                                                </template>
                                            </td>

                                            <!-- HPP (Unit Cost) -->
                                            <td class="px-4 py-3 text-right font-mono text-xs text-slate-600" x-text="formatRupiah(item.unit_cost)"></td>

                                            <!-- Subtotal Nilai Selisih -->
                                            <td class="px-4 py-3 text-right whitespace-nowrap font-semibold">
                                                <template x-if="item.actual_qty - item.expected_qty < 0">
                                                    <span class="text-rose-700" x-text="'-' + formatRupiah(Math.abs((item.actual_qty - item.expected_qty) * item.unit_cost))"></span>
                                                </template>
                                                <template x-if="item.actual_qty - item.expected_qty > 0">
                                                    <span class="text-emerald-700" x-text="'+' + formatRupiah((item.actual_qty - item.expected_qty) * item.unit_cost)"></span>
                                                </template>
                                                <template x-if="item.actual_qty - item.expected_qty === 0">
                                                    <span class="text-slate-400">Rp 0</span>
                                                </template>
                                            </td>

                                            <!-- Tombol Hapus Baris -->
                                            <td class="px-3 py-3 text-center">
                                                <button 
                                                    type="button" 
                                                    @click="removeItem(index)" 
                                                    class="rounded p-1 text-slate-400 hover:bg-rose-100 hover:text-rose-700 transition"
                                                    title="Hapus dari audit"
                                                >
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>

                                    <!-- Empty Row Placeholder -->
                                    <template x-if="items.length === 0">
                                        <tr>
                                            <td colspan="7" class="px-6 py-10 text-center text-slate-400">
                                                <svg class="mx-auto h-8 w-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                                </svg>
                                                <p class="mt-2 text-sm font-medium text-slate-600">Belum ada produk yang dimasukkan</p>
                                                <p class="text-xs text-slate-400">Cari produk di atas atau klik "Muat Semua Produk" untuk memulai audit stok fisik.</p>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Kolom Kanan: Live Journal Preview & Ringkasan Eksekusi (Col Span 1) -->
                <div class="space-y-6">
                    <!-- Kartu Ringkasan Dampak Finansial Real-time -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-sm font-bold text-slate-900 flex items-center justify-between">
                            <span>Ringkasan Opname</span>
                            <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-800">Real-time</span>
                        </h3>

                        <div class="mt-4 space-y-3 divide-y divide-slate-100 text-sm">
                            <div class="flex items-center justify-between pt-1">
                                <span class="text-slate-500">Jumlah Produk Diperiksa:</span>
                                <span class="font-bold text-slate-800" x-text="items.length + ' Produk'"></span>
                            </div>

                            <div class="flex items-center justify-between pt-3">
                                <span class="text-slate-500">Item Selisih Minus (Rugi):</span>
                                <span class="font-bold text-rose-600" x-text="countLossItems + ' Item'"></span>
                            </div>

                            <div class="flex items-center justify-between pt-3">
                                <span class="text-slate-500">Item Selisih Plus (Untung):</span>
                                <span class="font-bold text-emerald-600" x-text="countGainItems + ' Item'"></span>
                            </div>

                            <div class="flex items-center justify-between pt-3">
                                <span class="text-slate-500">Total Kerugian Stok (Minus):</span>
                                <span class="font-bold text-rose-700" x-text="formatRupiah(totalLossValue)"></span>
                            </div>

                            <div class="flex items-center justify-between pt-3">
                                <span class="text-slate-500">Total Keuntungan Stok (Plus):</span>
                                <span class="font-bold text-emerald-700" x-text="formatRupiah(totalGainValue)"></span>
                            </div>

                            <div class="flex items-center justify-between pt-3 text-base">
                                <span class="font-bold text-slate-900">Dampak Bersih Nilai:</span>
                                <span 
                                    class="font-black" 
                                    :class="netAdjustmentValue >= 0 ? 'text-emerald-700' : 'text-rose-700'"
                                    x-text="(netAdjustmentValue >= 0 ? '+' : '-') + formatRupiah(Math.abs(netAdjustmentValue))"
                                ></span>
                            </div>
                        </div>
                    </div>

                    <!-- LIVE JOURNAL PREVIEW WIDGET (Sangat Interaktif) -->
                    <div class="rounded-2xl border border-indigo-200 bg-gradient-to-b from-indigo-50/60 to-white p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-bold text-indigo-950 flex items-center gap-2">
                                <svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                                Live Journal Preview
                            </h3>
                            <template x-if="totalLossValue > 0 || totalGainValue > 0">
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800">
                                    ✓ SEIMBANG
                                </span>
                            </template>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            Simulasi pencatatan jurnal akuntansi ganda yang akan otomatis dibukukan saat formulir disimpan.
                        </p>

                        <!-- Tabel Simulasi Jurnal -->
                        <div class="mt-4 rounded-xl border border-slate-200 bg-white p-3 shadow-inner">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="border-b border-slate-100 text-slate-400 text-left">
                                        <th class="pb-1.5">Kode / Akun</th>
                                        <th class="pb-1.5 text-right">Debit</th>
                                        <th class="pb-1.5 text-right">Kredit</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50 font-mono">
                                    <!-- Jika ada selisih minus / loss -->
                                    <template x-if="totalLossValue > 0">
                                        <tr>
                                            <td class="py-2 text-slate-800 font-sans">
                                                <span class="font-bold text-indigo-700">5120</span> Beban Selisih
                                            </td>
                                            <td class="py-2 text-right font-bold text-rose-700" x-text="formatRupiah(totalLossValue)"></td>
                                            <td class="py-2 text-right text-slate-300">0</td>
                                        </tr>
                                    </template>
                                    <template x-if="totalLossValue > 0">
                                        <tr>
                                            <td class="py-2 pl-4 text-slate-600 font-sans">
                                                <span class="font-bold text-indigo-700">1210</span> Persediaan
                                            </td>
                                            <td class="py-2 text-right text-slate-300">0</td>
                                            <td class="py-2 text-right font-bold text-rose-700" x-text="formatRupiah(totalLossValue)"></td>
                                        </tr>
                                    </template>

                                    <!-- Jika ada selisih plus / gain -->
                                    <template x-if="totalGainValue > 0">
                                        <tr>
                                            <td class="py-2 text-slate-800 font-sans">
                                                <span class="font-bold text-indigo-700">1210</span> Persediaan
                                            </td>
                                            <td class="py-2 text-right font-bold text-emerald-700" x-text="formatRupiah(totalGainValue)"></td>
                                            <td class="py-2 text-right text-slate-300">0</td>
                                        </tr>
                                    </template>
                                    <template x-if="totalGainValue > 0">
                                        <tr>
                                            <td class="py-2 pl-4 text-slate-600 font-sans">
                                                <span class="font-bold text-indigo-700">4120</span> Pendapatan Lain
                                            </td>
                                            <td class="py-2 text-right text-slate-300">0</td>
                                            <td class="py-2 text-right font-bold text-emerald-700" x-text="formatRupiah(totalGainValue)"></td>
                                        </tr>
                                    </template>

                                    <!-- Jika belum ada selisih sama sekali -->
                                    <template x-if="totalLossValue === 0 && totalGainValue === 0">
                                        <tr>
                                            <td colspan="3" class="py-4 text-center text-slate-400 font-sans italic">
                                                Tidak ada jurnal (Stok Fisik &amp; Sistem klop / belum ada data selisih)
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot class="border-t border-slate-200 font-mono font-bold" x-show="totalLossValue > 0 || totalGainValue > 0">
                                    <tr>
                                        <td class="pt-2 text-slate-700 font-sans">Total Jurnal:</td>
                                        <td class="pt-2 text-right text-indigo-700" x-text="formatRupiah(totalLossValue + totalGainValue)"></td>
                                        <td class="pt-2 text-right text-indigo-700" x-text="formatRupiah(totalLossValue + totalGainValue)"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Tombol Aksi Submit -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <button 
                            type="submit" 
                            :disabled="items.length === 0 || isSubmitting"
                            class="w-full rounded-xl bg-indigo-600 py-3.5 text-center text-sm font-bold text-white shadow-lg shadow-indigo-600/30 transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <span x-show="!isSubmitting">Simpan &amp; Bukukan Stock Opname</span>
                            <span x-show="isSubmitting" class="flex items-center justify-center gap-2">
                                <svg class="h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Memproses Jurnal &amp; Stok...
                            </span>
                        </button>
                        <p class="mt-2 text-center text-[11px] text-slate-400">
                            Stok di database akan disesuaikan seketika dan jurnal akuntansi otomatis dicatat.
                        </p>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <!-- Script Logika SPA Alpine.js -->
    <script>
        function stockAdjustmentForm(initialProducts, initialBranchId) {
            return {
                availableProducts: initialProducts || [],
                branchId: initialBranchId,
                date: new Date().toISOString().split('T')[0],
                notes: '',
                searchQuery: '',
                showDropdown: false,
                isSubmitting: false,
                items: [],

                // Filter produk untuk autocomplete
                get filteredProducts() {
                    if (!this.searchQuery.trim()) {
                        return this.availableProducts.slice(0, 10);
                    }
                    const q = this.searchQuery.toLowerCase();
                    return this.availableProducts.filter(p => 
                        p.name.toLowerCase().includes(q) || 
                        p.sku.toLowerCase().includes(q)
                    ).slice(0, 15);
                },

                // Tambahkan produk ke tabel
                addProduct(product) {
                    const existingIndex = this.items.findIndex(i => i.product_id === product.id);
                    if (existingIndex !== -1) {
                        alert('Produk ' + product.name + ' sudah ada di tabel!');
                        this.showDropdown = false;
                        this.searchQuery = '';
                        return;
                    }

                    this.items.push({
                        product_id: product.id,
                        name: product.name,
                        sku: product.sku,
                        expected_qty: parseInt(product.stock) || 0,
                        actual_qty: parseInt(product.stock) || 0, // default ke expected
                        unit_cost: parseFloat(product.purchase_price) || 0,
                    });

                    this.showDropdown = false;
                    this.searchQuery = '';
                },

                // Tambahkan semua produk cabang sekaligus
                addAllProducts() {
                    if (this.availableProducts.length === 0) {
                        alert('Tidak ada katalog produk di cabang ini.');
                        return;
                    }

                    const existingIds = new Set(this.items.map(i => i.product_id));
                    let addedCount = 0;

                    this.availableProducts.forEach(p => {
                        if (!existingIds.has(p.id)) {
                            this.items.push({
                                product_id: p.id,
                                name: p.name,
                                sku: p.sku,
                                expected_qty: parseInt(p.stock) || 0,
                                actual_qty: parseInt(p.stock) || 0,
                                unit_cost: parseFloat(p.purchase_price) || 0,
                            });
                            addedCount++;
                        }
                    });

                    this.showDropdown = false;
                },

                // Hapus produk dari baris audit
                removeItem(index) {
                    this.items.splice(index, 1);
                },

                // Hitung ulang real-time
                recalculate() {
                    // Trigger reactivity
                },

                // Ganti cabang (jika user master mengubah dropdown)
                changeBranch(newBranchId) {
                    window.location.href = "{{ route('inventory.adjustments.create') }}?branch_id=" + newBranchId;
                },

                // Perhitungan Ringkasan
                get countLossItems() {
                    return this.items.filter(i => (i.actual_qty - i.expected_qty) < 0).length;
                },

                get countGainItems() {
                    return this.items.filter(i => (i.actual_qty - i.expected_qty) > 0).length;
                },

                get totalLossValue() {
                    return this.items.reduce((acc, i) => {
                        const diff = i.actual_qty - i.expected_qty;
                        if (diff < 0) {
                            return acc + (Math.abs(diff) * (parseFloat(i.unit_cost) || 0));
                        }
                        return acc;
                    }, 0);
                },

                get totalGainValue() {
                    return this.items.reduce((acc, i) => {
                        const diff = i.actual_qty - i.expected_qty;
                        if (diff > 0) {
                            return acc + (diff * (parseFloat(i.unit_cost) || 0));
                        }
                        return acc;
                    }, 0);
                },

                get netAdjustmentValue() {
                    return this.totalGainValue - this.totalLossValue;
                },

                formatRupiah(num) {
                    return 'Rp ' + Math.round(num || 0).toLocaleString('id-ID');
                },

                handleSubmit(event) {
                    if (this.items.length === 0) {
                        event.preventDefault();
                        alert('Minimal satu produk wajib dimasukkan untuk penyesuaian stok.');
                        return;
                    }

                    const confirmed = confirm(
                        'Konfirmasi Simpan Stock Opname:\n' +
                        '- Total Produk: ' + this.items.length + '\n' +
                        '- Kerugian Stok: ' + this.formatRupiah(this.totalLossValue) + '\n' +
                        '- Keuntungan Stok: ' + this.formatRupiah(this.totalGainValue) + '\n\n' +
                        'Apakah Anda yakin ingin memproses dokumen opname dan jurnal akuntansi ini?'
                    );

                    if (!confirmed) {
                        event.preventDefault();
                        return;
                    }

                    this.isSubmitting = true;
                }
            };
        }
    </script>
</body>
</html>
