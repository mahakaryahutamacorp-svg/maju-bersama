<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pusat Laporan (Report Center) | Maju Bersama ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        [x-cloak] { display: none !important; }
        .font-mono-code { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased flex flex-col">
    <!-- Header Utama (Sesuai Standar Maju Bersama ERP) -->
    <header class="border-b border-slate-800 bg-slate-950 text-white shrink-0">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
            <div class="flex items-center gap-4">
                <a href="/backoffice" class="block group">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-400 group-hover:text-amber-300 transition">Maju Bersama ERP</p>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-bold tracking-tight">Pusat Laporan</h1>
                        <span class="rounded-md bg-amber-400/20 px-2 py-0.5 text-[10px] font-bold text-amber-300 border border-amber-400/30">Report Center v1</span>
                    </div>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <nav class="hidden items-center gap-4 text-sm text-slate-300 md:flex">
                    <a href="/pos" class="hover:text-white transition">POS Kasir</a>
                    <a href="/inventory" class="hover:text-white transition">Inventory</a>
                    <a href="/backoffice" class="hover:text-white transition">Panel Backoffice</a>
                </nav>
                <div class="rounded-full border border-sky-400/30 bg-sky-400/10 px-3.5 py-1 text-xs font-medium text-sky-200 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>{{ $currentUser->branch?->name ?? 'Semua Cabang' }} ({{ $currentUser->role ?? 'User' }})</span>
                </div>
                <form method="POST" action="/logout" class="hidden sm:block">
                    @csrf
                    <button type="submit" class="text-sm text-slate-300 hover:text-white transition">Keluar</button>
                </form>
            </div>
        </div>
    </header>

    <!-- Sub-Navbar Breadcrumb & Status Bar -->
    <div class="border-b border-slate-200 bg-white shadow-xs shrink-0">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-3 lg:px-8">
            <div class="flex items-center gap-2 text-sm text-slate-600">
                <a href="/backoffice" class="hover:text-slate-900 transition flex items-center gap-1">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span>Backoffice</span>
                </a>
                <span class="text-slate-300">/</span>
                <span class="font-semibold text-slate-900">Pusat Laporan</span>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-slate-400 hidden sm:inline">Struktur Navigasi Tab Software Akuntansi Klasik</span>
                <a href="/backoffice" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition">
                    ← Kembali ke Backoffice
                </a>
            </div>
        </div>
    </div>

    <!-- Main Workspace Container (Dua Kolom: Tab Kiri & Daftar Laporan Kanan) -->
    <main class="flex-1 mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8 flex flex-col justify-start">
        <div class="mb-5 flex flex-col gap-1">
            <h2 class="text-2xl font-bold tracking-tight text-slate-900 flex items-center gap-2.5">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500 text-slate-950 font-bold shadow-xs">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </span>
                Katalog Laporan Terpadu
            </h2>
            <p class="text-sm text-slate-500">Pilih kategori tab pada bilah samping untuk mengakses modul laporan keuangan, penjualan, pembelian, logistik, dan aset.</p>
        </div>

        <!-- Alpine.js Tab Controller Shell -->
        <div x-data="{ activeTab: 'keuangan', searchQuery: '' }" class="rounded-2xl border border-slate-300 bg-white shadow-md overflow-hidden flex flex-col md:flex-row flex-1 min-h-[580px]">
            
            <!-- KOLOM KIRI (Kategori Tab Vertikal - Desain Desktop Klasik Menonjol) -->
            <aside class="w-full md:w-64 lg:w-72 xl:w-80 shrink-0 bg-slate-900 border-b md:border-b-0 md:border-r border-slate-800 flex flex-col text-slate-200">
                <!-- Header Kolom Kiri -->
                <div class="px-5 py-4 border-b border-slate-800/80 bg-slate-950/60 flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-amber-400">Modul Laporan</p>
                        <p class="text-xs text-slate-400">Pilih grup akuntansi</p>
                    </div>
                    <span class="rounded bg-slate-800 px-2 py-0.5 text-[10px] font-mono font-semibold text-slate-300">5 Grup</span>
                </div>

                <!-- Daftar Tombol Tab Vertikal -->
                <nav class="p-3 space-y-1.5 flex-1">
                    <!-- Tab 1: Laporan Keuangan -->
                    <button type="button"
                            @click="activeTab = 'keuangan'"
                            :class="activeTab === 'keuangan' 
                                ? 'bg-amber-500 text-slate-950 font-bold shadow-sm translate-x-1' 
                                : 'text-slate-300 hover:bg-slate-800/90 hover:text-white'"
                            class="w-full text-left rounded-xl px-4 py-3 text-sm flex items-center justify-between transition-all duration-150 group">
                        <div class="flex items-center gap-3">
                            <span :class="activeTab === 'keuangan' ? 'text-slate-950' : 'text-amber-400 group-hover:scale-110'" class="transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                            <div>
                                <p class="leading-tight">Laporan Keuangan</p>
                                <p :class="activeTab === 'keuangan' ? 'text-slate-800' : 'text-slate-400'" class="text-[11px] font-normal mt-0.5">Laba rugi, neraca, arus kas</p>
                            </div>
                        </div>
                        <span :class="activeTab === 'keuangan' ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-slate-400'" class="text-[10px] font-semibold rounded px-1.5 py-0.5">4</span>
                    </button>

                    <!-- Tab 2: Penjualan & Piutang -->
                    <button type="button"
                            @click="activeTab = 'penjualan'"
                            :class="activeTab === 'penjualan' 
                                ? 'bg-amber-500 text-slate-950 font-bold shadow-sm translate-x-1' 
                                : 'text-slate-300 hover:bg-slate-800/90 hover:text-white'"
                            class="w-full text-left rounded-xl px-4 py-3 text-sm flex items-center justify-between transition-all duration-150 group">
                        <div class="flex items-center gap-3">
                            <span :class="activeTab === 'penjualan' ? 'text-slate-950' : 'text-sky-400 group-hover:scale-110'" class="transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </span>
                            <div>
                                <p class="leading-tight">Penjualan &amp; Piutang</p>
                                <p :class="activeTab === 'penjualan' ? 'text-slate-800' : 'text-slate-400'" class="text-[11px] font-normal mt-0.5">Faktur, piutang, retur</p>
                            </div>
                        </div>
                        <span :class="activeTab === 'penjualan' ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-slate-400'" class="text-[10px] font-semibold rounded px-1.5 py-0.5">3</span>
                    </button>

                    <!-- Tab 3: Pembelian & Hutang -->
                    <button type="button"
                            @click="activeTab = 'pembelian'"
                            :class="activeTab === 'pembelian' 
                                ? 'bg-amber-500 text-slate-950 font-bold shadow-sm translate-x-1' 
                                : 'text-slate-300 hover:bg-slate-800/90 hover:text-white'"
                            class="w-full text-left rounded-xl px-4 py-3 text-sm flex items-center justify-between transition-all duration-150 group">
                        <div class="flex items-center gap-3">
                            <span :class="activeTab === 'pembelian' ? 'text-slate-950' : 'text-indigo-400 group-hover:scale-110'" class="transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </span>
                            <div>
                                <p class="leading-tight">Pembelian &amp; Hutang</p>
                                <p :class="activeTab === 'pembelian' ? 'text-slate-800' : 'text-slate-400'" class="text-[11px] font-normal mt-0.5">PO, hutang supplier, bayar</p>
                            </div>
                        </div>
                        <span :class="activeTab === 'pembelian' ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-slate-400'" class="text-[10px] font-semibold rounded px-1.5 py-0.5">3</span>
                    </button>

                    <!-- Tab 4: Persediaan / Barang -->
                    <button type="button"
                            @click="activeTab = 'persediaan'"
                            :class="activeTab === 'persediaan' 
                                ? 'bg-amber-500 text-slate-950 font-bold shadow-sm translate-x-1' 
                                : 'text-slate-300 hover:bg-slate-800/90 hover:text-white'"
                            class="w-full text-left rounded-xl px-4 py-3 text-sm flex items-center justify-between transition-all duration-150 group">
                        <div class="flex items-center gap-3">
                            <span :class="activeTab === 'persediaan' ? 'text-slate-950' : 'text-emerald-400 group-hover:scale-110'" class="transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                            </span>
                            <div>
                                <p class="leading-tight">Persediaan / Barang</p>
                                <p :class="activeTab === 'persediaan' ? 'text-slate-800' : 'text-slate-400'" class="text-[11px] font-normal mt-0.5">Kartu stok, multi-gudang</p>
                            </div>
                        </div>
                        <span :class="activeTab === 'persediaan' ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-slate-400'" class="text-[10px] font-semibold rounded px-1.5 py-0.5">2</span>
                    </button>

                    <!-- Tab 5: Harta Tetap -->
                    <button type="button"
                            @click="activeTab = 'harta_tetap'"
                            :class="activeTab === 'harta_tetap' 
                                ? 'bg-amber-500 text-slate-950 font-bold shadow-sm translate-x-1' 
                                : 'text-slate-300 hover:bg-slate-800/90 hover:text-white'"
                            class="w-full text-left rounded-xl px-4 py-3 text-sm flex items-center justify-between transition-all duration-150 group">
                        <div class="flex items-center gap-3">
                            <span :class="activeTab === 'harta_tetap' ? 'text-slate-950' : 'text-purple-400 group-hover:scale-110'" class="transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </span>
                            <div>
                                <p class="leading-tight">Harta Tetap</p>
                                <p :class="activeTab === 'harta_tetap' ? 'text-slate-800' : 'text-slate-400'" class="text-[11px] font-normal mt-0.5">Aktiva tetap &amp; depresiasi</p>
                            </div>
                        </div>
                        <span :class="activeTab === 'harta_tetap' ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-slate-400'" class="text-[10px] font-semibold rounded px-1.5 py-0.5">3</span>
                    </button>
                </nav>

                <!-- Footer Kolom Kiri -->
                <div class="p-4 border-t border-slate-800/80 bg-slate-950/40 text-[11px] text-slate-400 flex items-center justify-between">
                    <span>Maju Bersama Architecture</span>
                    <span class="font-mono text-amber-400/80">ERP 2026</span>
                </div>
            </aside>

            <!-- KOLOM KANAN (Daftar Tautan Laporan Sesuai Tab Aktif) -->
            <section class="flex-1 bg-slate-50/60 p-5 sm:p-7 lg:p-8 flex flex-col justify-between overflow-y-auto">
                <div>
                    <!-- ============================================== -->
                    <!-- TAB 1: LAPORAN KEUANGAN -->
                    <!-- ============================================== -->
                    <div x-show="activeTab === 'keuangan'"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-cloak>
                        <!-- Banner Kategori -->
                        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-200 pb-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="p-1.5 rounded-lg bg-amber-100 text-amber-700">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </span>
                                    <h3 class="text-lg font-bold text-slate-900">Laporan Keuangan &amp; Akuntansi</h3>
                                </div>
                                <p class="text-xs text-slate-500 mt-1">Laporan finansial formal sesuai standar pembukuan double-entry Indonesia.</p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 border border-emerald-200 w-fit">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                4 Laporan Tersedia
                            </span>
                        </div>

                        <!-- Daftar Laporan Keuangan -->
                        <div class="grid gap-3">
                            <!-- 1. Laba Rugi Standar -->
                            <a href="{{ route('reports.income-statement') }}" class="group block rounded-xl border border-slate-200 bg-white p-4 shadow-xs transition-all duration-150 hover:border-amber-400 hover:shadow-md hover:bg-amber-50/20">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-start gap-3.5">
                                        <div class="rounded-lg bg-emerald-100 text-emerald-800 p-2.5 mt-0.5 group-hover:scale-105 transition-transform">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-900 group-hover:text-amber-800 transition-colors">Laba Rugi Standar</h4>
                                            <p class="text-xs text-slate-500 mt-0.5">Menyajikan pendapatan operasional, harga pokok penjualan (HPP), laba kotor, beban operasional, dan laba bersih.</p>
                                            <div class="mt-2 flex items-center gap-2">
                                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">Income Statement</span>
                                                <span class="rounded bg-emerald-50 px-2 py-0.5 text-[10px] font-medium text-emerald-700">Periodik</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center text-slate-400 group-hover:text-amber-600 group-hover:translate-x-1 transition-all pl-4">
                                        <span class="text-xs font-semibold mr-1.5 hidden sm:inline">Buka Laporan</span>
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </div>
                            </a>

                            <!-- 2. Neraca Saldo -->
                            <a href="{{ route('reports.trial-balance') }}" class="group block rounded-xl border border-slate-200 bg-white p-4 shadow-xs transition-all duration-150 hover:border-amber-400 hover:shadow-md hover:bg-amber-50/20">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-start gap-3.5">
                                        <div class="rounded-lg bg-sky-100 text-sky-800 p-2.5 mt-0.5 group-hover:scale-105 transition-transform">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-900 group-hover:text-amber-800 transition-colors">Neraca Saldo</h4>
                                            <p class="text-xs text-slate-500 mt-0.5">Daftar seluruh akun buku besar beserta saldo debit dan kredit untuk verifikasi keseimbangan (Trial Balance).</p>
                                            <div class="mt-2 flex items-center gap-2">
                                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">Trial Balance</span>
                                                <span class="rounded bg-sky-50 px-2 py-0.5 text-[10px] font-medium text-sky-700">Audit Check</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center text-slate-400 group-hover:text-amber-600 group-hover:translate-x-1 transition-all pl-4">
                                        <span class="text-xs font-semibold mr-1.5 hidden sm:inline">Buka Laporan</span>
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </div>
                            </a>

                            <!-- 3. Neraca Standar -->
                            <a href="{{ route('reports.balance-sheet') }}" class="group block rounded-xl border border-slate-200 bg-white p-4 shadow-xs transition-all duration-150 hover:border-amber-400 hover:shadow-md hover:bg-amber-50/20">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-start gap-3.5">
                                        <div class="rounded-lg bg-indigo-100 text-indigo-800 p-2.5 mt-0.5 group-hover:scale-105 transition-transform">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-900 group-hover:text-amber-800 transition-colors">Neraca Standar</h4>
                                            <p class="text-xs text-slate-500 mt-0.5">Posisi keuangan komprehensif yang menampilkan total Aktiva (Aset), Kewajiban (Liabilitas), dan Modal (Ekuitas).</p>
                                            <div class="mt-2 flex items-center gap-2">
                                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">Balance Sheet</span>
                                                <span class="rounded bg-indigo-50 px-2 py-0.5 text-[10px] font-medium text-indigo-700">Financial Position</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center text-slate-400 group-hover:text-amber-600 group-hover:translate-x-1 transition-all pl-4">
                                        <span class="text-xs font-semibold mr-1.5 hidden sm:inline">Buka Laporan</span>
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </div>
                            </a>

                            <!-- 4. Arus Kas -->
                            <a href="{{ route('reports.cash-flow') }}" class="group block rounded-xl border border-slate-200 bg-white p-4 shadow-xs transition-all duration-150 hover:border-amber-400 hover:shadow-md hover:bg-amber-50/20">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-start gap-3.5">
                                        <div class="rounded-lg bg-teal-100 text-teal-800 p-2.5 mt-0.5 group-hover:scale-105 transition-transform">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-900 group-hover:text-amber-800 transition-colors">Arus Kas</h4>
                                            <p class="text-xs text-slate-500 mt-0.5">Analisis pergerakan kas masuk dan kas keluar dari aktivitas operasional, investasi, dan pendanaan.</p>
                                            <div class="mt-2 flex items-center gap-2">
                                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">Cash Flow</span>
                                                <span class="rounded bg-teal-50 px-2 py-0.5 text-[10px] font-medium text-teal-700">Likuiditas</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center text-slate-400 group-hover:text-amber-600 group-hover:translate-x-1 transition-all pl-4">
                                        <span class="text-xs font-semibold mr-1.5 hidden sm:inline">Buka Laporan</span>
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- TAB 2: PENJUALAN & PIUTANG -->
                    <!-- ============================================== -->
                    <div x-show="activeTab === 'penjualan'"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-cloak>
                        <!-- Banner Kategori -->
                        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-200 pb-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="p-1.5 rounded-lg bg-sky-100 text-sky-700">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                    </span>
                                    <h3 class="text-lg font-bold text-slate-900">Penjualan &amp; Piutang Pelanggan</h3>
                                </div>
                                <p class="text-xs text-slate-500 mt-1">Laporan transaksi kasir POS, faktur penjualan, dan buku umur piutang usaha.</p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700 border border-sky-200 w-fit">
                                <span class="h-1.5 w-1.5 rounded-full bg-sky-500"></span>
                                3 Laporan Tersedia
                            </span>
                        </div>

                        <!-- Daftar Laporan Penjualan -->
                        <div class="grid gap-3">
                            <a href="{{ route('reports.sales') }}" class="group block rounded-xl border border-slate-200 bg-white p-4 shadow-xs transition-all duration-150 hover:border-amber-400 hover:shadow-md hover:bg-amber-50/20">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-start gap-3.5">
                                        <div class="rounded-lg bg-sky-100 text-sky-800 p-2.5 mt-0.5 group-hover:scale-105 transition-transform">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-900 group-hover:text-amber-800 transition-colors">Laporan Penjualan Ringkas &amp; Rinci</h4>
                                            <p class="text-xs text-slate-500 mt-0.5">Rekapitulasi omzet penjualan harian/bulanan per kasir, metode pembayaran, dan rincian produk terjual.</p>
                                            <div class="mt-2 flex items-center gap-2">
                                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">Sales Summary</span>
                                                <span class="rounded bg-sky-50 px-2 py-0.5 text-[10px] font-medium text-sky-700">POS &amp; Backoffice</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center text-slate-400 group-hover:text-amber-600 group-hover:translate-x-1 transition-all pl-4">
                                        <span class="text-xs font-semibold mr-1.5 hidden sm:inline">Buka Laporan</span>
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </div>
                            </a>

                            <a href="{{ route('reports.ar-aging') }}" class="group block rounded-xl border border-slate-200 bg-white p-4 shadow-xs transition-all duration-150 hover:border-amber-400 hover:shadow-md hover:bg-amber-50/20">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-start gap-3.5">
                                        <div class="rounded-lg bg-amber-100 text-amber-800 p-2.5 mt-0.5 group-hover:scale-105 transition-transform">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-900 group-hover:text-amber-800 transition-colors">Daftar Saldo &amp; Buku Umur Piutang (Aging AR)</h4>
                                            <p class="text-xs text-slate-500 mt-0.5">Daftar tagihan piutang pelanggan dengan pengelompokan jatuh tempo (0-30, 31-60, 61-90, >90 hari).</p>
                                            <div class="mt-2 flex items-center gap-2">
                                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">Aging AR</span>
                                                <span class="rounded bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-700">Piutang Dagang</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center text-slate-400 group-hover:text-amber-600 group-hover:translate-x-1 transition-all pl-4">
                                        <span class="text-xs font-semibold mr-1.5 hidden sm:inline">Buka Laporan</span>
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </div>
                            </a>

                            <a href="#" class="group block rounded-xl border border-slate-200 bg-white p-4 shadow-xs transition-all duration-150 hover:border-amber-400 hover:shadow-md hover:bg-amber-50/20">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-start gap-3.5">
                                        <div class="rounded-lg bg-rose-100 text-rose-800 p-2.5 mt-0.5 group-hover:scale-105 transition-transform">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-900 group-hover:text-amber-800 transition-colors">Laporan Retur Penjualan</h4>
                                            <p class="text-xs text-slate-500 mt-0.5">Daftar pengembalian barang dari konsumen, dampak pengembalian dana (refund), dan jurnal koreksi stok.</p>
                                            <div class="mt-2 flex items-center gap-2">
                                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">Sales Return</span>
                                                <span class="rounded bg-rose-50 px-2 py-0.5 text-[10px] font-medium text-rose-700">Retur Pelanggan</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center text-slate-400 group-hover:text-amber-600 group-hover:translate-x-1 transition-all pl-4">
                                        <span class="text-xs font-semibold mr-1.5 hidden sm:inline">Buka Laporan</span>
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- TAB 3: PEMBELIAN & HUTANG -->
                    <!-- ============================================== -->
                    <div x-show="activeTab === 'pembelian'"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-cloak>
                        <!-- Banner Kategori -->
                        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-200 pb-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="p-1.5 rounded-lg bg-indigo-100 text-indigo-700">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </span>
                                    <h3 class="text-lg font-bold text-slate-900">Pembelian &amp; Hutang Supplier</h3>
                                </div>
                                <p class="text-xs text-slate-500 mt-1">Laporan pengadaan, pesanan pembelian (PO), buku hutang dagang, dan pembayaran.</p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 border border-indigo-200 w-fit">
                                <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                                3 Laporan Tersedia
                            </span>
                        </div>

                        <!-- Daftar Laporan Pembelian -->
                        <div class="grid gap-3">
                            <a href="{{ route('reports.purchases') }}" class="group block rounded-xl border border-slate-200 bg-white p-4 shadow-xs transition-all duration-150 hover:border-amber-400 hover:shadow-md hover:bg-amber-50/20">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-start gap-3.5">
                                        <div class="rounded-lg bg-indigo-100 text-indigo-800 p-2.5 mt-0.5 group-hover:scale-105 transition-transform">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-900 group-hover:text-amber-800 transition-colors">Laporan Ringkasan Pembelian &amp; PO</h4>
                                            <p class="text-xs text-slate-500 mt-0.5">Daftar order pembelian per supplier, status penerimaan barang (Goods Receipt), dan nilai transaksi.</p>
                                            <div class="mt-2 flex items-center gap-2">
                                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">Purchase Order</span>
                                                <span class="rounded bg-indigo-50 px-2 py-0.5 text-[10px] font-medium text-indigo-700">Pengadaan</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center text-slate-400 group-hover:text-amber-600 group-hover:translate-x-1 transition-all pl-4">
                                        <span class="text-xs font-semibold mr-1.5 hidden sm:inline">Buka Laporan</span>
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </div>
                            </a>

                            <a href="{{ route('reports.ap-aging') }}" class="group block rounded-xl border border-slate-200 bg-white p-4 shadow-xs transition-all duration-150 hover:border-amber-400 hover:shadow-md hover:bg-amber-50/20">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-start gap-3.5">
                                        <div class="rounded-lg bg-amber-100 text-amber-800 p-2.5 mt-0.5 group-hover:scale-105 transition-transform">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-900 group-hover:text-amber-800 transition-colors">Daftar Saldo &amp; Buku Umur Hutang (Aging AP)</h4>
                                            <p class="text-xs text-slate-500 mt-0.5">Klasifikasi sisa hutang kepada para supplier berdasarkan rentang waktu jatuh tempo pembayaran.</p>
                                            <div class="mt-2 flex items-center gap-2">
                                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">Aging AP</span>
                                                <span class="rounded bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-700">Hutang Dagang</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center text-slate-400 group-hover:text-amber-600 group-hover:translate-x-1 transition-all pl-4">
                                        <span class="text-xs font-semibold mr-1.5 hidden sm:inline">Buka Laporan</span>
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </div>
                            </a>

                            <a href="#" class="group block rounded-xl border border-slate-200 bg-white p-4 shadow-xs transition-all duration-150 hover:border-amber-400 hover:shadow-md hover:bg-amber-50/20">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-start gap-3.5">
                                        <div class="rounded-lg bg-teal-100 text-teal-800 p-2.5 mt-0.5 group-hover:scale-105 transition-transform">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-900 group-hover:text-amber-800 transition-colors">Riwayat Pembayaran Hutang Supplier</h4>
                                            <p class="text-xs text-slate-500 mt-0.5">Daftar disbursement pembayaran kas/bank ke supplier lengkap dengan alokasi nomor invoice &amp; bukti pelunasan.</p>
                                            <div class="mt-2 flex items-center gap-2">
                                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">Disbursement</span>
                                                <span class="rounded bg-teal-50 px-2 py-0.5 text-[10px] font-medium text-teal-700">Kas Keluar</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center text-slate-400 group-hover:text-amber-600 group-hover:translate-x-1 transition-all pl-4">
                                        <span class="text-xs font-semibold mr-1.5 hidden sm:inline">Buka Laporan</span>
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- TAB 4: PERSEDIAAN / BARANG -->
                    <!-- ============================================== -->
                    <div x-show="activeTab === 'persediaan'"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-cloak>
                        <!-- Banner Kategori -->
                        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-200 pb-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="p-1.5 rounded-lg bg-emerald-100 text-emerald-700">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                    </span>
                                    <h3 class="text-lg font-bold text-slate-900">Persediaan &amp; Logistik Barang</h3>
                                </div>
                                <p class="text-xs text-slate-500 mt-1">Kartu stok mutasi, pergerakan barang antar gudang, dan rekonsiliasi opname.</p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 border border-emerald-200 w-fit">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                2 Laporan Tersedia
                            </span>
                        </div>

                        <!-- Daftar Laporan Persediaan -->
                        <div class="grid gap-3">
                            <!-- 1. Kartu Stok / Mutasi Barang -->
                            <a href="{{ route('reports.stock-card') }}" class="group block rounded-xl border border-slate-200 bg-white p-4 shadow-xs transition-all duration-150 hover:border-amber-400 hover:shadow-md hover:bg-amber-50/20">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-start gap-3.5">
                                        <div class="rounded-lg bg-emerald-100 text-emerald-800 p-2.5 mt-0.5 group-hover:scale-105 transition-transform">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-900 group-hover:text-amber-800 transition-colors">Kartu Stok / Mutasi Barang</h4>
                                            <p class="text-xs text-slate-500 mt-0.5">Riwayat lengkap mutasi barang masuk, keluar, transfer cabang, dan saldo akhir secara kronologis.</p>
                                            <div class="mt-2 flex items-center gap-2">
                                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">Stock Card</span>
                                                <span class="rounded bg-emerald-50 px-2 py-0.5 text-[10px] font-medium text-emerald-700">Audit Mutasi</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center text-slate-400 group-hover:text-amber-600 group-hover:translate-x-1 transition-all pl-4">
                                        <span class="text-xs font-semibold mr-1.5 hidden sm:inline">Buka Laporan</span>
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </div>
                            </a>

                            <!-- 2. Daftar Barang per Gudang -->
                            <a href="#" class="group block rounded-xl border border-slate-200 bg-white p-4 shadow-xs transition-all duration-150 hover:border-amber-400 hover:shadow-md hover:bg-amber-50/20">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-start gap-3.5">
                                        <div class="rounded-lg bg-teal-100 text-teal-800 p-2.5 mt-0.5 group-hover:scale-105 transition-transform">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-900 group-hover:text-amber-800 transition-colors">Daftar Barang per Gudang</h4>
                                            <p class="text-xs text-slate-500 mt-0.5">Pemetaan kuantitas stok fisik dan valuasi persediaan yang tersebar di seluruh gudang dan cabang.</p>
                                            <div class="mt-2 flex items-center gap-2">
                                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">Warehouse Inventory</span>
                                                <span class="rounded bg-teal-50 px-2 py-0.5 text-[10px] font-medium text-teal-700">Multi-Gudang</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center text-slate-400 group-hover:text-amber-600 group-hover:translate-x-1 transition-all pl-4">
                                        <span class="text-xs font-semibold mr-1.5 hidden sm:inline">Buka Laporan</span>
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- TAB 5: HARTA TETAP -->
                    <!-- ============================================== -->
                    <div x-show="activeTab === 'harta_tetap'"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-cloak>
                        <!-- Banner Kategori -->
                        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-200 pb-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="p-1.5 rounded-lg bg-purple-100 text-purple-700">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                    </span>
                                    <h3 class="text-lg font-bold text-slate-900">Harta Tetap &amp; Aktiva Perusahaan</h3>
                                </div>
                                <p class="text-xs text-slate-500 mt-1">Pengelolaan aset berwujud, umur ekonomis, penyusutan periodik, dan nilai sisa buku.</p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700 border border-purple-200 w-fit">
                                <span class="h-1.5 w-1.5 rounded-full bg-purple-500"></span>
                                3 Laporan Tersedia
                            </span>
                        </div>

                        <!-- Daftar Laporan Harta Tetap -->
                        <div class="grid gap-3">
                            <a href="{{ route('reports.fixed-assets') }}" class="group block rounded-xl border border-slate-200 bg-white p-4 shadow-xs transition-all duration-150 hover:border-amber-400 hover:shadow-md hover:bg-amber-50/20">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-start gap-3.5">
                                        <div class="rounded-lg bg-purple-100 text-purple-800 p-2.5 mt-0.5 group-hover:scale-105 transition-transform">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-900 group-hover:text-amber-800 transition-colors">Daftar Aktiva Tetap &amp; Inventaris</h4>
                                            <p class="text-xs text-slate-500 mt-0.5">Katalog seluruh aset tetap seperti kendaraan operasional, peralatan toko, dan bangunan per cabang.</p>
                                            <div class="mt-2 flex items-center gap-2">
                                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">Fixed Assets</span>
                                                <span class="rounded bg-purple-50 px-2 py-0.5 text-[10px] font-medium text-purple-700">Inventaris</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center text-slate-400 group-hover:text-amber-600 group-hover:translate-x-1 transition-all pl-4">
                                        <span class="text-xs font-semibold mr-1.5 hidden sm:inline">Buka Laporan</span>
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </div>
                            </a>

                            <a href="#" class="group block rounded-xl border border-slate-200 bg-white p-4 shadow-xs transition-all duration-150 hover:border-amber-400 hover:shadow-md hover:bg-amber-50/20">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-start gap-3.5">
                                        <div class="rounded-lg bg-pink-100 text-pink-800 p-2.5 mt-0.5 group-hover:scale-105 transition-transform">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-900 group-hover:text-amber-800 transition-colors">Laporan Penyusutan &amp; Depresiasi Aset</h4>
                                            <p class="text-xs text-slate-500 mt-0.5">Jadwal amortisasi dan beban penyusutan periodik menggunakan metode garis lurus (Straight-line method).</p>
                                            <div class="mt-2 flex items-center gap-2">
                                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">Depreciation</span>
                                                <span class="rounded bg-pink-50 px-2 py-0.5 text-[10px] font-medium text-pink-700">Garis Lurus</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center text-slate-400 group-hover:text-amber-600 group-hover:translate-x-1 transition-all pl-4">
                                        <span class="text-xs font-semibold mr-1.5 hidden sm:inline">Buka Laporan</span>
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </div>
                            </a>

                            <a href="#" class="group block rounded-xl border border-slate-200 bg-white p-4 shadow-xs transition-all duration-150 hover:border-amber-400 hover:shadow-md hover:bg-amber-50/20">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-start gap-3.5">
                                        <div class="rounded-lg bg-amber-100 text-amber-800 p-2.5 mt-0.5 group-hover:scale-105 transition-transform">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-900 group-hover:text-amber-800 transition-colors">Buku Rekapitulasi Nilai Buku Aset</h4>
                                            <p class="text-xs text-slate-500 mt-0.5">Ringkasan harga perolehan historis, akumulasi penyusutan terkumpul, dan sisa nilai buku (Book value).</p>
                                            <div class="mt-2 flex items-center gap-2">
                                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">Book Value</span>
                                                <span class="rounded bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-700">Audit Aset</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center text-slate-400 group-hover:text-amber-600 group-hover:translate-x-1 transition-all pl-4">
                                        <span class="text-xs font-semibold mr-1.5 hidden sm:inline">Buka Laporan</span>
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Footer Status & Tips Kolom Kanan -->
                <div class="mt-8 pt-4 border-t border-slate-200/80 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                        <span>Tahap 1: Cangkang Antarmuka Navigasi Tab Selesai</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="font-mono text-slate-400">Pusat Laporan &bull; Maju Bersama POS-Accounting</span>
                    </div>
                </div>
            </section>

        </div>
    </main>
</body>
</html>
