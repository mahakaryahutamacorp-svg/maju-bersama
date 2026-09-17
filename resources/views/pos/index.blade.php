<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="pos-token" content="{{ $previewToken }}">
    <title>Penjualan Kasir | Maju Bersama ERP</title>
    <style>
        [x-cloak] { display: none !important; }
    </style>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <div 
        x-data="posSystem({{ Js::from($products) }}, {{ Js::from($categories) }})" 
        class="min-h-screen flex flex-col"
        @keydown.window.escape="handleEscapeKey()"
    >
        <!-- Header Utama Kasir -->
        <header class="border-b border-slate-800 bg-slate-950 text-white shadow-md">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-3.5 lg:px-8">
                <div class="flex items-center gap-3">
                    <a href="/backoffice" class="block">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-amber-400">Maju Bersama ERP</p>
                        <h1 class="text-lg font-bold tracking-tight flex items-center gap-2">
                            <span>Penjualan Kasir Multi-Store</span>
                            <span class="rounded bg-sky-500/20 px-2 py-0.5 text-xs text-sky-300 font-mono">{{ $currentUser->branch?->name ?? 'Cabang Pusat' }}</span>
                        </h1>
                    </a>
                </div>

                <div class="flex items-center gap-4">
                    <nav class="hidden items-center gap-3 text-xs text-slate-300 sm:flex font-medium">
                        <a href="/inventory" class="hover:text-white px-2 py-1 rounded hover:bg-slate-800">Data Barang</a>
                        <a href="/inventory/adjustments" class="hover:text-white px-2 py-1 rounded hover:bg-slate-800">Opname</a>
                        <a href="/reports/inventory/stock-card" class="hover:text-white px-2 py-1 rounded hover:bg-slate-800">Kartu Stok</a>
                        <a href="/backoffice" class="hover:text-white px-2 py-1 rounded hover:bg-slate-800">Backoffice</a>
                    </nav>

                    <div class="flex items-center gap-2 border-l border-slate-800 pl-4">
                        <span class="text-xs text-slate-400">Kasir: <strong class="text-white">{{ $currentUser->name }}</strong></span>
                        <form method="POST" action="/logout" class="inline">
                            @csrf
                            <button type="submit" class="rounded-lg bg-rose-600/20 px-2.5 py-1 text-xs font-semibold text-rose-300 hover:bg-rose-600/40 transition">
                                Keluar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Layout: Katalog Data Barang & Rincian Transaksi -->
        <main class="mx-auto grid max-w-7xl flex-1 w-full gap-6 px-6 py-6 lg:grid-cols-[minmax(0,1fr)_390px] lg:px-8">
            <!-- Kolom Kiri: Barcode Search, Kelompok Barang Chips & Grid Data Barang -->
            <section class="flex flex-col gap-4">
                <!-- 1. Kolom Input Scan Barcode & Pencarian Cerdas -->
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                            <svg class="h-5 w-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                            </svg>
                        </div>
                        <input 
                            type="text" 
                            x-ref="barcodeInput"
                            x-model="searchQuery" 
                            @keydown.enter.prevent="handleScanOrSearch()"
                            placeholder="Cari nama atau scan barcode... (Tekan Enter untuk cepat)" 
                            class="block w-full rounded-xl border border-slate-300 bg-slate-50/50 py-3 pl-11 pr-24 text-sm font-medium shadow-inner focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                            autofocus
                        >
                        <div class="absolute inset-y-0 right-0 flex items-center pr-2">
                            <span class="rounded bg-slate-200 px-2 py-0.5 text-[10px] font-mono font-semibold text-slate-600">Enter = Tambah</span>
                        </div>
                    </div>

                    <!-- Notifikasi Flash Sukses Scan Barcode -->
                    <div 
                        x-show="scanNotification" 
                        x-transition 
                        x-text="scanNotification" 
                        class="mt-2 rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 flex items-center gap-1.5"
                    ></div>
                </div>

                <!-- 2. Tab / Chips Filter Kelompok Barang -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1">
                    <button 
                        type="button" 
                        @click="selectedCategory = null" 
                        class="whitespace-nowrap rounded-xl px-4 py-2 text-xs font-semibold shadow-sm transition"
                        :class="selectedCategory === null ? 'bg-indigo-600 text-white shadow-indigo-200' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200'"
                    >
                        Semua Kelompok Barang (<span x-text="products.length"></span>)
                    </button>
                    <template x-for="cat in categories" :key="cat.id">
                        <button 
                            type="button" 
                            @click="selectedCategory = cat.id" 
                            class="whitespace-nowrap rounded-xl px-4 py-2 text-xs font-semibold shadow-sm transition"
                            :class="selectedCategory === cat.id ? 'bg-indigo-600 text-white shadow-indigo-200' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200'"
                        >
                            <span x-text="cat.name"></span>
                        </button>
                    </template>
                </div>

                <!-- 3. Grid Data Barang -->
                <div class="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-3">
                    <template x-for="product in filteredProducts" :key="product.id">
                        <button 
                            type="button" 
                            @click="addToCart(product)" 
                            :disabled="product.stock < 1"
                            class="group flex min-h-[140px] flex-col justify-between rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-400 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <div>
                                <div class="flex items-start justify-between gap-2">
                                    <span class="font-mono text-[11px] font-semibold text-indigo-700" x-text="product.sku"></span>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600" x-text="product.category?.name || 'Umum'"></span>
                                </div>
                                <h3 class="mt-2 font-bold leading-5 text-slate-900 line-clamp-2" x-text="product.name"></h3>
                            </div>

                            <div class="mt-3 flex items-end justify-between gap-2 border-t border-slate-100 pt-2.5">
                                <div>
                                    <p class="text-base font-black text-slate-900" x-text="formatRupiah(product.selling_price)"></p>
                                    <p 
                                        class="text-[11px] font-semibold"
                                        :class="product.stock > 5 ? 'text-slate-500' : (product.stock > 0 ? 'text-amber-600 font-bold' : 'text-rose-600 font-bold')"
                                        x-text="product.stock > 0 ? ('Stok: ' + product.stock) : 'Stok Habis'"
                                    ></p>
                                </div>
                                <span class="rounded-lg bg-slate-900 px-2.5 py-1.5 text-xs font-semibold text-white transition group-hover:bg-indigo-600">
                                    + Tambah
                                </span>
                            </div>
                        </button>
                    </template>
                </div>

                <!-- Empty state jika tidak ada produk yang cocok -->
                <div x-show="filteredProducts.length === 0" class="rounded-2xl border border-slate-200 bg-white p-10 text-center">
                    <p class="font-bold text-slate-700">Data Barang Tidak Ditemukan</p>
                    <p class="mt-1 text-xs text-slate-500">Coba ubah kata kunci pencarian atau pilih kelompok barang lain.</p>
                </div>
            </section>

            <!-- Kolom Kanan: Rincian Transaksi (Cart Aside) -->
            <aside class="flex flex-col h-fit rounded-2xl border border-slate-200 bg-white shadow-sm lg:sticky lg:top-6">
                <!-- Header Rincian Transaksi -->
                <div class="border-b border-slate-200 px-5 py-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Rincian Transaksi</h2>
                        <p class="text-xs text-slate-500" x-text="totalItems + ' item barang'"></p>
                    </div>
                    <button 
                        type="button" 
                        x-show="cart.length > 0" 
                        @click="clearCart()" 
                        class="text-xs font-semibold text-rose-600 hover:underline"
                    >
                        Kosongkan
                    </button>
                </div>

                <!-- Daftar Item di Rincian Transaksi -->
                <div class="max-h-[min(52vh,480px)] overflow-y-auto px-5 divide-y divide-slate-100">
                    <template x-if="cart.length === 0">
                        <div class="py-12 text-center text-slate-400">
                            <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            <p class="mt-2 text-sm font-semibold text-slate-600">Rincian Transaksi Masih Kosong</p>
                            <p class="text-xs text-slate-400">Cari nama atau scan barcode untuk menambah barang.</p>
                        </div>
                    </template>

                    <template x-for="item in cart" :key="item.product.id">
                        <div class="py-3.5 flex gap-3 items-center">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-bold text-slate-900" x-text="item.product.name"></p>
                                <p class="text-[11px] text-slate-500 font-mono" x-text="formatRupiah(item.product.selling_price) + ' /pcs'"></p>

                                <div class="mt-2 flex items-center gap-1.5">
                                    <button 
                                        type="button" 
                                        @click="changeQuantity(item.product.id, -1)" 
                                        class="h-6 w-6 rounded-md border border-slate-300 bg-slate-50 text-slate-700 hover:bg-slate-200 font-bold text-xs"
                                    >-</button>
                                    <span class="w-7 text-center text-xs font-bold font-mono" x-text="item.quantity"></span>
                                    <button 
                                        type="button" 
                                        @click="changeQuantity(item.product.id, 1)" 
                                        class="h-6 w-6 rounded-md border border-slate-300 bg-slate-50 text-slate-700 hover:bg-slate-200 font-bold text-xs"
                                    >+</button>
                                </div>
                            </div>

                            <div class="text-right">
                                <p class="text-xs font-black text-slate-900 font-mono" x-text="formatRupiah(item.product.selling_price * item.quantity)"></p>
                                <button 
                                    type="button" 
                                    @click="removeFromCart(item.product.id)" 
                                    class="mt-2 text-[11px] font-semibold text-rose-600 hover:underline"
                                >
                                    Hapus
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Ringkasan & Tombol Pembayaran -->
                <div class="border-t border-slate-200 bg-slate-50/70 p-5 rounded-b-2xl">
                    <div class="flex items-baseline justify-between">
                        <span class="text-sm font-semibold text-slate-600">Total Akhir:</span>
                        <span class="text-2xl font-black text-indigo-900" x-text="formatRupiah(grandTotal)"></span>
                    </div>

                    <button 
                        type="button" 
                        @click="openPaymentModal()" 
                        :disabled="cart.length === 0"
                        class="mt-4 w-full rounded-xl bg-indigo-600 py-3.5 text-center text-sm font-bold text-white shadow-lg shadow-indigo-600/30 transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none"
                    >
                        Pembayaran (F9) →
                    </button>
                </div>
            </aside>
        </main>

        <!-- ======================================================== -->
        <!-- MODAL 1: PEMBAYARAN & KALKULASI KEMBALIAN (CHECKOUT)    -->
        <!-- ======================================================== -->
        <div 
            x-show="paymentModalOpen" 
            x-cloak 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
        >
            <div 
                class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl"
                @click.away="paymentModalOpen = false"
            >
                <!-- Header Modal Pembayaran -->
                <div class="bg-indigo-600 px-6 py-4 text-white flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-indigo-200">Penjualan Kasir</p>
                        <h2 class="text-lg font-bold">Pembayaran</h2>
                    </div>
                    <button type="button" @click="paymentModalOpen = false" class="text-white hover:text-indigo-200 text-xl font-bold">&times;</button>
                </div>

                <div class="p-6 space-y-5">
                    <!-- Total Akhir Display -->
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 flex items-center justify-between">
                        <span class="text-sm font-bold text-slate-700">Total Akhir:</span>
                        <span class="text-2xl font-black text-indigo-700 font-mono" x-text="formatRupiah(grandTotal)"></span>
                    </div>

                    <!-- Metode Pembayaran -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Metode Pembayaran</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button 
                                type="button" 
                                @click="paymentMethod = 'cash'" 
                                class="rounded-lg border py-2 text-xs font-bold transition"
                                :class="paymentMethod === 'cash' ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                            >
                                💵 Tunai
                            </button>
                            <button 
                                type="button" 
                                @click="paymentMethod = 'qris'; cashTendered = grandTotal" 
                                class="rounded-lg border py-2 text-xs font-bold transition"
                                :class="paymentMethod === 'qris' ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                            >
                                📱 QRIS
                            </button>
                            <button 
                                type="button" 
                                @click="paymentMethod = 'transfer'; cashTendered = grandTotal" 
                                class="rounded-lg border py-2 text-xs font-bold transition"
                                :class="paymentMethod === 'transfer' ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                            >
                                💳 Transfer
                            </button>
                        </div>
                    </div>

                    <!-- Input Tunai / Bayar -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Tunai / Bayar (Rp)</label>
                        <input 
                            type="number" 
                            x-ref="cashInput"
                            x-model.number="cashTendered" 
                            placeholder="0"
                            class="block w-full rounded-xl border border-slate-300 p-3 text-xl font-bold font-mono text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                        >

                        <!-- Tombol Nominal Cepat -->
                        <div class="mt-2.5 flex flex-wrap gap-2">
                            <button 
                                type="button" 
                                @click="setCash(grandTotal)" 
                                class="rounded-lg border border-slate-300 bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-800 hover:bg-slate-200"
                            >
                                Uang Pas
                            </button>
                            <button 
                                type="button" 
                                @click="setCash(10000)" 
                                x-show="grandTotal <= 10000"
                                class="rounded-lg border border-slate-300 bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-800 hover:bg-slate-200"
                            >
                                10.000
                            </button>
                            <button 
                                type="button" 
                                @click="setCash(20000)" 
                                x-show="grandTotal <= 20000"
                                class="rounded-lg border border-slate-300 bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-800 hover:bg-slate-200"
                            >
                                20.000
                            </button>
                            <button 
                                type="button" 
                                @click="setCash(50000)" 
                                x-show="grandTotal <= 50000"
                                class="rounded-lg border border-slate-300 bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-800 hover:bg-slate-200"
                            >
                                50.000
                            </button>
                            <button 
                                type="button" 
                                @click="setCash(100000)" 
                                class="rounded-lg border border-slate-300 bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-800 hover:bg-slate-200"
                            >
                                100.000
                            </button>
                            <button 
                                type="button" 
                                @click="setCash(nextRoundUpNominal(grandTotal))" 
                                x-show="nextRoundUpNominal(grandTotal) > grandTotal"
                                class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700 hover:bg-indigo-100"
                            >
                                <span x-text="formatRupiah(nextRoundUpNominal(grandTotal))"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Kalkulasi Kembalian -->
                    <div 
                        class="rounded-xl p-4 transition"
                        :class="{
                            'bg-emerald-50 border border-emerald-200': changeDue >= 0,
                            'bg-rose-50 border border-rose-200': changeDue < 0
                        }"
                    >
                        <div class="flex items-center justify-between">
                            <span 
                                class="text-xs font-bold uppercase tracking-wider"
                                :class="changeDue >= 0 ? 'text-emerald-800' : 'text-rose-800'"
                            >
                                <span x-show="changeDue >= 0">Kembalian:</span>
                                <span x-show="changeDue < 0">Kekurangan Bayar:</span>
                            </span>
                            <span 
                                class="text-xl font-black font-mono"
                                :class="changeDue >= 0 ? 'text-emerald-700' : 'text-rose-700'"
                                x-text="formatRupiah(Math.abs(changeDue))"
                            ></span>
                        </div>
                    </div>

                    <!-- Error Alert -->
                    <div x-show="checkoutError" class="rounded-lg bg-rose-50 p-3 text-xs font-semibold text-rose-700" x-text="checkoutError"></div>
                </div>

                <!-- Footer Modal -->
                <div class="border-t border-slate-100 bg-slate-50 px-6 py-4 flex items-center justify-end gap-3">
                    <button 
                        type="button" 
                        @click="paymentModalOpen = false" 
                        class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-100"
                    >
                        Batal
                    </button>
                    <button 
                        type="button" 
                        @click="processCheckout()" 
                        :disabled="loading || (paymentMethod === 'cash' && changeDue < 0)"
                        class="rounded-xl bg-indigo-600 px-6 py-2.5 text-xs font-bold text-white shadow-md transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-300"
                    >
                        <span x-show="!loading">Selesaikan & Cetak Faktur →</span>
                        <span x-show="loading">Memproses...</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- MODAL 2: SUKSES TRANSAKSI & PILIHAN CETAK STRUK THERMAL -->
        <!-- ======================================================== -->
        <div 
            x-show="receiptOpen" 
            x-cloak 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm"
        >
            <div class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="bg-emerald-600 px-6 py-4 text-white text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-white/20">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h2 class="mt-2 text-lg font-bold">Transaksi Berhasil!</h2>
                    <p class="text-xs text-emerald-100 font-mono" x-text="'No. Faktur: ' + lastReceiptNumber"></p>
                </div>

                <div class="p-6 space-y-4 text-sm">
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-3.5 space-y-2">
                        <div class="flex justify-between text-slate-600">
                            <span>Total Akhir:</span>
                            <span class="font-bold text-slate-900" x-text="formatRupiah(lastSaleTotal)"></span>
                        </div>
                        <div class="flex justify-between text-slate-600">
                            <span>Tunai / Bayar:</span>
                            <span class="font-mono text-slate-800" x-text="formatRupiah(lastCashTendered)"></span>
                        </div>
                        <div class="flex justify-between text-emerald-700 font-bold border-t border-slate-200 pt-1.5">
                            <span>Kembalian:</span>
                            <span class="font-mono text-base" x-text="formatRupiah(lastChangeDue)"></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-2">
                        <!-- Tombol Cetak Faktur -->
                        <button 
                            type="button" 
                            @click="printThermalReceipt()" 
                            class="flex items-center justify-center gap-1.5 rounded-xl border border-indigo-600 bg-indigo-50 py-3 text-xs font-bold text-indigo-700 shadow-sm transition hover:bg-indigo-100"
                        >
                            🖨️ Cetak Faktur
                        </button>

                        <!-- Tombol Transaksi Baru -->
                        <button 
                            type="button" 
                            @click="resetAfterSale()" 
                            class="rounded-xl bg-indigo-600 py-3 text-xs font-bold text-white shadow-md transition hover:bg-indigo-500"
                        >
                            Transaksi Baru (Esc)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Script Alpine.js Penjualan Kasir -->
    <script>
        function posSystem(initialProducts, initialCategories) {
            return {
                products: initialProducts || [],
                categories: initialCategories || [],
                selectedCategory: null,
                searchQuery: '',
                scanNotification: '',
                cart: [],
                loading: false,

                // State Modal Pembayaran
                paymentModalOpen: false,
                paymentMethod: 'cash',
                cashTendered: 0,
                checkoutError: '',

                // State Sukses Transaksi
                receiptOpen: false,
                lastReceiptNumber: '',
                lastSale: null,
                lastSaleTotal: 0,
                lastCashTendered: 0,
                lastChangeDue: 0,

                // Filter Katalog Produk berdasarkan Kategori dan Search Query
                get filteredProducts() {
                    let list = this.products;

                    if (this.selectedCategory !== null) {
                        list = list.filter(p => p.category_id === this.selectedCategory);
                    }

                    if (this.searchQuery.trim()) {
                        const q = this.searchQuery.toLowerCase();
                        list = list.filter(p => 
                            p.name.toLowerCase().includes(q) || 
                            p.sku.toLowerCase().includes(q)
                        );
                    }

                    return list;
                },

                get totalItems() {
                    return this.cart.reduce((sum, item) => sum + item.quantity, 0);
                },

                get grandTotal() {
                    return this.cart.reduce((sum, item) => sum + (Number(item.product.selling_price) * item.quantity), 0);
                },

                get changeDue() {
                    return (this.cashTendered || 0) - this.grandTotal;
                },

                // Scan Barcode / Quick Add via Enter
                handleScanOrSearch() {
                    if (!this.searchQuery.trim()) return;

                    const q = this.searchQuery.trim().toLowerCase();

                    // Cek exact match SKU
                    const exactSku = this.products.find(p => p.sku.toLowerCase() === q);
                    if (exactSku) {
                        this.addToCart(exactSku);
                        this.notifyScan('✓ ' + exactSku.name + ' ditambahkan ke transaksi');
                        this.searchQuery = '';
                        return;
                    }

                    // Cek jika hanya ada 1 produk yang matching
                    const matches = this.filteredProducts;
                    if (matches.length === 1) {
                        this.addToCart(matches[0]);
                        this.notifyScan('✓ ' + matches[0].name + ' ditambahkan ke transaksi');
                        this.searchQuery = '';
                        return;
                    }
                },

                notifyScan(text) {
                    this.scanNotification = text;
                    setTimeout(() => {
                        this.scanNotification = '';
                    }, 2500);
                },

                addToCart(product) {
                    if (product.stock < 1) return;

                    const existing = this.cart.find(item => item.product.id === product.id);
                    if (existing) {
                        if (existing.quantity < product.stock) {
                            existing.quantity++;
                        } else {
                            alert('Jumlah melebihi stok barang yang tersedia (' + product.stock + ').');
                        }
                        return;
                    }

                    this.cart.push({ product, quantity: 1 });
                },

                changeQuantity(productId, amount) {
                    const item = this.cart.find(i => i.product.id === productId);
                    if (!item) return;

                    const product = this.products.find(p => p.id === productId);
                    const newQty = item.quantity + amount;

                    if (newQty <= 0) {
                        this.removeFromCart(productId);
                        return;
                    }

                    if (newQty > product.stock) {
                        alert('Jumlah melebihi stok yang tersedia (' + product.stock + ').');
                        return;
                    }

                    item.quantity = newQty;
                },

                removeFromCart(productId) {
                    this.cart = this.cart.filter(i => i.product.id !== productId);
                },

                clearCart() {
                    if (confirm('Apakah Anda yakin ingin mengosongkan rincian transaksi?')) {
                        this.cart = [];
                    }
                },

                // Buka Modal Pembayaran
                openPaymentModal() {
                    if (this.cart.length === 0) return;
                    this.paymentMethod = 'cash';
                    this.cashTendered = this.grandTotal; // default uang pas
                    this.checkoutError = '';
                    this.paymentModalOpen = true;

                    this.$nextTick(() => {
                        if (this.$refs.cashInput) {
                            this.$refs.cashInput.select();
                        }
                    });
                },

                setCash(amount) {
                    this.cashTendered = amount;
                },

                nextRoundUpNominal(total) {
                    if (total <= 0) return 0;
                    if (total <= 20000) return 20000;
                    if (total <= 50000) return 50000;
                    if (total <= 100000) return 100000;
                    return Math.ceil(total / 50000) * 50000;
                },

                // Proses Checkout ke API
                async processCheckout() {
                    if (this.paymentMethod === 'cash' && this.changeDue < 0) {
                        alert('Uang pembayaran tunai masih kurang!');
                        return;
                    }

                    this.loading = true;
                    this.checkoutError = '';

                    try {
                        const response = await fetch('/api/checkout', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'Authorization': 'Bearer ' + document.querySelector('meta[name="pos-token"]').content,
                            },
                            body: JSON.stringify({
                                payment_method: this.paymentMethod,
                                items: this.cart.map(i => ({ product_id: i.product.id, quantity: i.quantity })),
                            }),
                        });

                        const payload = await response.json();
                        if (!response.ok) {
                            throw new Error(payload.message || 'Gagal memproses transaksi checkout.');
                        }

                        // Update local stocks
                        this.products = this.products.map(p => {
                            const soldItem = (payload.sale?.items || []).find(si => si.product_id === p.id);
                            return soldItem ? { ...p, stock: p.stock - soldItem.quantity } : p;
                        });

                        // Set info struk
                        this.lastReceiptNumber = payload.receipt_number;
                        this.lastSale = payload.sale;
                        this.lastSaleTotal = this.grandTotal;
                        this.lastCashTendered = this.cashTendered;
                        this.lastChangeDue = Math.max(0, this.changeDue);

                        // Tutup modal bayar & buka modal sukses
                        this.paymentModalOpen = false;
                        this.receiptOpen = true;

                    } catch (err) {
                        this.checkoutError = err.message;
                    } finally {
                        this.loading = false;
                    }
                },

                // Cetak Struk Thermal Pop-up Window
                printThermalReceipt() {
                    const url = '/pos/receipt/' + this.lastReceiptNumber + '?cash=' + this.lastCashTendered + '&change=' + this.lastChangeDue;
                    window.open(url, 'ThermalReceipt', 'width=350,height=600,menubar=no,toolbar=no,location=no');
                },

                // Reset untuk transaksi baru
                resetAfterSale() {
                    this.receiptOpen = false;
                    this.cart = [];
                    this.lastReceiptNumber = '';
                    this.lastSale = null;
                    this.searchQuery = '';
                    this.$nextTick(() => {
                        if (this.$refs.barcodeInput) {
                            this.$refs.barcodeInput.focus();
                        }
                    });
                },

                handleEscapeKey() {
                    if (this.receiptOpen) {
                        this.resetAfterSale();
                    } else if (this.paymentModalOpen) {
                        this.paymentModalOpen = false;
                    }
                },

                formatRupiah(num) {
                    return 'Rp ' + Math.round(num || 0).toLocaleString('id-ID');
                }
            };
        }
    </script>
</body>
</html>
