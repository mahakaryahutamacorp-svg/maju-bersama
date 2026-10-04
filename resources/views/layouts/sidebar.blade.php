@php
    $sidebarUser = $currentUser ?? auth()->user();
    $sidebarIsMaster = $isMaster ?? ($sidebarUser ? $sidebarUser->isMaster() : false);
    $hasOB = false;
    if ($sidebarUser && $sidebarUser->branch_id) {
        $hasOB = \App\Services\OpeningBalanceService::hasOpeningBalance($sidebarUser->branch_id);
    }

    // Status Menu Aktif per Divisi
    $isOverviewActive = (isset($active) && $active === 'overview') || request()->is('backoffice') || request()->is('dashboard');
    $isPosActive = request()->is('pos*') || request()->routeIs('pos*');
    $isDashboardOpen = $isOverviewActive || $isPosActive;

    $isInventoryActive = request()->is('inventory') || request()->is('inventory/transfer*');
    $isOpnameActive = request()->is('inventory/adjustments*') || request()->is('backoffice/inventory/adjustments*');
    $isWarehouseActive = request()->is('backoffice/warehouses*');
    $isGoodsReceiptActive = request()->is('purchases/goods-receipts*');
    $isGudangOpen = $isInventoryActive || $isOpnameActive || $isWarehouseActive || $isGoodsReceiptActive;

    $isProductsActive = request()->is('backoffice/products*');
    $isCategoriesActive = request()->is('backoffice/categories*');
    $isSuppliersActive = request()->is('backoffice/suppliers*');
    $isCustomersActive = request()->is('backoffice/customers*');
    $isUsersActive = request()->is('backoffice/users*');
    $isBranchesActive = request()->is('backoffice/branches*');
    $isExpenseCatActive = request()->is('backoffice/expense-categories*');
    $isPreviewActive = request()->is('preview*');
    $isMasterDataOpen = $isProductsActive || $isCategoriesActive || $isSuppliersActive || $isCustomersActive || $isUsersActive || $isBranchesActive || $isExpenseCatActive || $isPreviewActive;

    $isReceivablesActive = request()->is('backoffice/payments/receivables*') || request()->is('backoffice/payments*');
    $isSalesReturnsActive = request()->is('backoffice/sales-returns*');
    $isPenjualanOpen = $isReceivablesActive || $isSalesReturnsActive;

    $isPurchaseOrdersActive = request()->is('backoffice/purchase-orders*');
    $isSupplierPaymentsActive = request()->is('backoffice/supplier-payments*') || request()->is('purchases/payables*');
    $isPurchaseReturnsActive = request()->is('backoffice/purchase-returns*');
    $isPembelianOpen = $isPurchaseOrdersActive || $isSupplierPaymentsActive || $isPurchaseReturnsActive;

    $isJournalActive = request()->is('reports/journal*') || request()->is('reports/accounting/ledger*');
    $isCashTransferActive = request()->is('backoffice/cash-transfers*') || request()->is('backoffice/cash-transactions*');
    $isExpensesActive = request()->is('backoffice/expenses*');
    $isOpeningBalanceActive = request()->is('backoffice/opening-balances*');
    $isFixedAssetsActive = request()->is('backoffice/fixed-assets*');
    $isReportsActive = request()->is('reports*') || request()->is('backoffice/reports*');
    $isKeuanganOpen = $isJournalActive || $isCashTransferActive || $isExpensesActive || $isOpeningBalanceActive || $isFixedAssetsActive || $isReportsActive;
@endphp

<aside class="border-b border-slate-800 bg-slate-950 text-white lg:h-screen lg:sticky lg:top-0 lg:w-72 lg:border-b-0 lg:border-r shrink-0 flex flex-col">
    <!-- Brand Header -->
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-800/80 lg:py-5">
        <a href="/backoffice" class="block group">
            <div class="flex items-center gap-2">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-400/20 border border-amber-400/30 text-amber-400 font-bold text-xs">
                    MB
                </span>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.24em] text-amber-400 group-hover:text-amber-300 transition">ERP System</p>
                    <p class="text-lg font-bold tracking-tight text-white leading-tight">Maju Bersama</p>
                </div>
            </div>
        </a>
        <button type="button" @click="mobileMenu = !mobileMenu" class="rounded-lg border border-slate-700 bg-slate-900 px-2.5 py-1.5 text-xs font-medium text-slate-300 hover:text-white lg:hidden">
            <span x-text="mobileMenu ? 'Tutup' : 'Menu'">Menu</span>
        </button>
    </div>

    <!-- Active User & Branch Info -->
    <div class="px-4 py-3 border-b border-slate-800/50 bg-slate-900/30">
        <div class="rounded-xl border border-sky-400/20 bg-sky-400/10 px-3.5 py-2.5 shadow-xs">
            <div class="flex items-center justify-between">
                <p class="text-[10px] font-bold uppercase tracking-wider text-sky-200">
                    {{ $sidebarIsMaster ? 'Master access' : 'Active branch' }}
                </p>
                <span class="inline-block h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
            </div>
            <p class="mt-0.5 text-xs font-semibold text-white truncate">
                {{ $sidebarIsMaster ? 'All branches' : ($sidebarUser->branch->name ?? 'Cabang Utama') }}
            </p>
            <p class="mt-0.5 text-[10px] text-slate-400 truncate">
                {{ $sidebarUser->name ?? 'User' }} &middot; <span class="capitalize">{{ $sidebarUser->role ?? 'Staf' }}</span>
            </p>
        </div>
    </div>

    <!-- Navigation Accordion -->
    <nav x-show="mobileMenu" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="flex-1 overflow-y-auto px-3 py-3 space-y-1.5 lg:block custom-scrollbar">

        <!-- DIVISI 1: 🏠 Dashboard & Kasir -->
        <div x-data="{ open: {{ $isDashboardOpen ? 'true' : 'false' }} }" class="rounded-xl border border-slate-800/80 bg-slate-900/40 overflow-hidden">
            <button type="button" 
                    @click="open = !open" 
                    class="flex w-full items-center justify-between px-3 py-2 text-left text-xs font-semibold transition {{ $isDashboardOpen ? 'text-white bg-slate-800/80' : 'text-slate-300 hover:text-white hover:bg-slate-800/50' }}">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-sky-500/10 border border-sky-400/20 text-sky-400">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                    </span>
                    <span class="truncate">Dashboard &amp; Kasir</span>
                </div>
                <svg :class="open ? 'rotate-180 text-amber-400' : 'text-slate-500'" class="h-3.5 w-3.5 transition-transform duration-200 shrink-0 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="open" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-1"
                 class="space-y-0.5 px-1 py-1 border-t border-slate-800/60 bg-slate-950/40">
                <button type="button" 
                        @click="typeof select === 'function' ? select('overview') : window.location.href = '/backoffice'" 
                        class="block w-full text-left rounded-md pl-8 pr-3 py-1.5 text-sm transition {{ $isOverviewActive ? 'text-sky-400 bg-white/5 font-semibold' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    Overview
                </button>
                <a href="/pos" 
                   class="block rounded-md pl-8 pr-3 py-1.5 text-sm transition {{ $isPosActive ? 'text-sky-400 bg-white/5 font-semibold' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    Point of Sale (POS)
                </a>
                <a href="/pos" 
                   class="block rounded-md pl-8 pr-3 py-1.5 text-sm transition {{ $isPosActive ? 'text-sky-400 bg-white/5 font-semibold' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    Kasir POS
                </a>
            </div>
        </div>

        {{-- RBAC: Divisi 2 & 3 hanya untuk master + branch_admin. Kasir tidak menerima markup ini sama sekali. --}}
        @can('access-inventory')
        <!-- DIVISI 2: 📦 Gudang & Inventaris -->
        <div x-data="{ open: {{ $isGudangOpen ? 'true' : 'false' }} }" class="rounded-xl border border-slate-800/80 bg-slate-900/40 overflow-hidden">
            <button type="button" 
                    @click="open = !open" 
                    class="flex w-full items-center justify-between px-3 py-2 text-left text-xs font-semibold transition {{ $isGudangOpen ? 'text-white bg-slate-800/80' : 'text-slate-300 hover:text-white hover:bg-slate-800/50' }}">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-teal-500/10 border border-teal-400/20 text-teal-400">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </span>
                    <span class="truncate">Gudang &amp; Inventaris</span>
                </div>
                <svg :class="open ? 'rotate-180 text-amber-400' : 'text-slate-500'" class="h-3.5 w-3.5 transition-transform duration-200 shrink-0 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="open" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-1"
                 class="space-y-0.5 px-1 py-1 border-t border-slate-800/60 bg-slate-950/40">
                <a href="/inventory" 
                   class="block rounded-md pl-8 pr-3 py-1.5 text-sm transition {{ $isInventoryActive ? 'text-sky-400 bg-white/5 font-semibold' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    Inventory (Stok)
                </a>
                <a href="{{ route('inventory.adjustments.index') }}" 
                   class="block rounded-md pl-8 pr-3 py-1.5 text-sm transition {{ $isOpnameActive ? 'text-sky-400 bg-white/5 font-semibold' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    Stock Opname
                </a>
                <a href="/backoffice/warehouses" 
                   class="block rounded-md pl-8 pr-3 py-1.5 text-sm transition {{ $isWarehouseActive ? 'text-sky-400 bg-white/5 font-semibold' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    Multi Gudang
                </a>
                <a href="/purchases/goods-receipts" 
                   class="block rounded-md pl-8 pr-3 py-1.5 text-sm transition {{ $isGoodsReceiptActive ? 'text-sky-400 bg-white/5 font-semibold' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    Penerimaan Barang (GR)
                </a>
            </div>
        </div>

        <!-- DIVISI 3: 📂 Master Data (1-Kolom Vertikal Rapat) -->
        <div x-data="{ open: {{ $isMasterDataOpen ? 'true' : 'false' }} }" class="rounded-xl border border-slate-800/80 bg-slate-900/40 overflow-hidden">
            <button type="button" 
                    @click="open = !open" 
                    class="flex w-full items-center justify-between px-3 py-2 text-left text-xs font-semibold transition {{ $isMasterDataOpen ? 'text-white bg-slate-800/80' : 'text-slate-300 hover:text-white hover:bg-slate-800/50' }}">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 border border-amber-400/20 text-amber-400">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                    </span>
                    <span class="truncate">Master Data</span>
                </div>
                <svg :class="open ? 'rotate-180 text-amber-400' : 'text-slate-500'" class="h-3.5 w-3.5 transition-transform duration-200 shrink-0 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="open" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-1"
                 class="space-y-0.5 px-1 py-1 border-t border-slate-800/60 bg-slate-950/40">
                {{-- Sebagian Master Data: master + branch_admin --}}
                @can('manage-catalog')
                    <a href="/backoffice/products" 
                       class="block rounded-md pl-8 pr-3 py-1.5 text-sm transition {{ $isProductsActive ? 'text-sky-400 bg-white/5 font-semibold' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                        Katalog Produk
                    </a>
                @endcan
                @can('manage-system')
                    <a href="/backoffice/categories" 
                       class="block rounded-md pl-8 pr-3 py-1.5 text-sm transition {{ $isCategoriesActive ? 'text-sky-400 bg-white/5 font-semibold' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                        Kategori Produk
                    </a>
                    <a href="{{ route('backoffice.suppliers.index') }}" 
                       class="block rounded-md pl-8 pr-3 py-1.5 text-sm transition {{ $isSuppliersActive ? 'text-sky-400 bg-white/5 font-semibold' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                        Data Supplier
                    </a>
                @endcan
                @can('manage-catalog')
                    <a href="{{ route('backoffice.customers.index') }}" 
                       class="block rounded-md pl-8 pr-3 py-1.5 text-sm transition {{ $isCustomersActive ? 'text-sky-400 bg-white/5 font-semibold' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                        Data Pelanggan
                    </a>
                @endcan
                {{-- Pengaturan master: HANYA master --}}
                @can('manage-system')
                    <a href="/backoffice/users" 
                       class="block rounded-md pl-8 pr-3 py-1.5 text-sm transition {{ $isUsersActive ? 'text-sky-400 bg-white/5 font-semibold' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                        Staf &amp; Kasir
                    </a>
                    <a href="/backoffice/branches" 
                       class="block rounded-md pl-8 pr-3 py-1.5 text-sm transition {{ $isBranchesActive ? 'text-sky-400 bg-white/5 font-semibold' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                        Manajemen Cabang
                    </a>
                    <a href="{{ route('backoffice.expense-categories.index') }}" 
                       class="block rounded-md pl-8 pr-3 py-1.5 text-sm transition {{ $isExpenseCatActive ? 'text-sky-400 bg-white/5 font-semibold' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                        Kategori Biaya
                    </a>
                    <a href="{{ route('preview') }}" 
                       class="block rounded-md pl-8 pr-3 py-1.5 text-sm transition {{ $isPreviewActive ? 'text-sky-400 bg-white/5 font-semibold' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                        Preview Data Sistem
                    </a>
                @endcan
            </div>
        </div>
        @endcan

        {{-- RBAC: Divisi 4, 5, 6 & Pusat Laporan = modul Enterprise, HANYA role master. --}}
        @can('access-enterprise')

        <!-- DIVISI 4: 🛒 Penjualan & Piutang (AR) -->
        <div x-data="{ open: {{ $isPenjualanOpen ? 'true' : 'false' }} }" class="rounded-xl border border-slate-800/80 bg-slate-900/40 overflow-hidden">
            <button type="button" 
                    @click="open = !open" 
                    class="flex w-full items-center justify-between px-3 py-2 text-left text-xs font-semibold transition {{ $isPenjualanOpen ? 'text-white bg-slate-800/80' : 'text-slate-300 hover:text-white hover:bg-slate-800/50' }}">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 border border-emerald-400/20 text-emerald-400">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </span>
                    <span class="truncate">Penjualan &amp; Piutang (AR)</span>
                </div>
                <svg :class="open ? 'rotate-180 text-amber-400' : 'text-slate-500'" class="h-3.5 w-3.5 transition-transform duration-200 shrink-0 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="open" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-1"
                 class="space-y-0.5 px-1 py-1 border-t border-slate-800/60 bg-slate-950/40">
                <a href="{{ route('backoffice.payments.receivables.create') }}" 
                   class="block rounded-md pl-8 pr-3 py-1.5 text-sm transition {{ $isReceivablesActive ? 'text-sky-400 bg-white/5 font-semibold' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    Pembayaran Piutang (AR)
                </a>
                <a href="{{ route('backoffice.sales-returns.index') }}" 
                   class="block rounded-md pl-8 pr-3 py-1.5 text-sm transition {{ $isSalesReturnsActive ? 'text-sky-400 bg-white/5 font-semibold' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    Retur Penjualan
                </a>
            </div>
        </div>

        <!-- DIVISI 5: 🚛 Pembelian & Hutang (AP) -->
        <div x-data="{ open: {{ $isPembelianOpen ? 'true' : 'false' }} }" class="rounded-xl border border-slate-800/80 bg-slate-900/40 overflow-hidden">
            <button type="button" 
                    @click="open = !open" 
                    class="flex w-full items-center justify-between px-3 py-2 text-left text-xs font-semibold transition {{ $isPembelianOpen ? 'text-white bg-slate-800/80' : 'text-slate-300 hover:text-white hover:bg-slate-800/50' }}">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-indigo-500/10 border border-indigo-400/20 text-indigo-400">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1" />
                        </svg>
                    </span>
                    <span class="truncate">Pembelian &amp; Hutang (AP)</span>
                </div>
                <svg :class="open ? 'rotate-180 text-amber-400' : 'text-slate-500'" class="h-3.5 w-3.5 transition-transform duration-200 shrink-0 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="open" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-1"
                 class="space-y-0.5 px-1 py-1 border-t border-slate-800/60 bg-slate-950/40">
                <a href="{{ route('backoffice.purchase-orders.index') }}" 
                   class="block rounded-md pl-8 pr-3 py-1.5 text-sm transition {{ $isPurchaseOrdersActive ? 'text-sky-400 bg-white/5 font-semibold' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    Pembelian / PO
                </a>
                <a href="{{ route('backoffice.supplier-payments.index') }}" 
                   class="block rounded-md pl-8 pr-3 py-1.5 text-sm transition {{ $isSupplierPaymentsActive ? 'text-sky-400 bg-white/5 font-semibold' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    Pembayaran Hutang (AP)
                </a>
                <a href="{{ route('backoffice.purchase-returns.index') }}" 
                   class="block rounded-md pl-8 pr-3 py-1.5 text-sm transition {{ $isPurchaseReturnsActive ? 'text-sky-400 bg-white/5 font-semibold' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    Retur Pembelian
                </a>
            </div>
        </div>

        <!-- DIVISI 6: 💰 Keuangan & Akuntansi -->
        <div x-data="{ open: {{ $isKeuanganOpen ? 'true' : 'false' }} }" class="rounded-xl border border-slate-800/80 bg-slate-900/40 overflow-hidden">
            <button type="button" 
                    @click="open = !open" 
                    class="flex w-full items-center justify-between px-3 py-2 text-left text-xs font-semibold transition {{ $isKeuanganOpen ? 'text-white bg-slate-800/80' : 'text-slate-300 hover:text-white hover:bg-slate-800/50' }}">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 border border-amber-400/20 text-amber-300">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    <span class="truncate">Keuangan &amp; Akuntansi</span>
                </div>
                <svg :class="open ? 'rotate-180 text-amber-400' : 'text-slate-500'" class="h-3.5 w-3.5 transition-transform duration-200 shrink-0 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="open" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-1"
                 class="space-y-0.5 px-1 py-1 border-t border-slate-800/60 bg-slate-950/40">
                <a href="/reports/journal" 
                   class="block rounded-md pl-8 pr-3 py-1.5 text-sm transition {{ $isJournalActive ? 'text-sky-400 bg-white/5 font-semibold' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    Journal Ledger
                </a>
                <a href="{{ route('backoffice.cash-transfers.index') }}" 
                   class="block rounded-md pl-8 pr-3 py-1.5 text-sm transition {{ $isCashTransferActive ? 'text-sky-400 bg-white/5 font-semibold' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    Mutasi Kas &amp; Bank
                </a>
                <a href="{{ route('backoffice.expenses.index') }}" 
                   class="block rounded-md pl-8 pr-3 py-1.5 text-sm transition {{ $isExpensesActive ? 'text-sky-400 bg-white/5 font-semibold' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    Biaya Operasional (Kas Keluar)
                </a>
                <a href="{{ route('backoffice.opening-balances.create') }}" 
                   class="block rounded-md pl-8 pr-3 py-1.5 text-sm transition {{ $isOpeningBalanceActive ? 'text-sky-400 bg-white/5 font-semibold' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    Input Saldo Awal (Setup)
                </a>
                <a href="{{ route('backoffice.fixed-assets.index') }}" 
                   class="block rounded-md pl-8 pr-3 py-1.5 text-sm transition {{ $isFixedAssetsActive ? 'text-sky-400 bg-white/5 font-semibold' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    Harta Tetap
                </a>
            </div>
        </div>

        <!-- Pusat Laporan (Quick Access Button) -->
        <div class="pt-2">
            <a href="{{ route('reports.index') }}" 
               class="group flex items-center justify-between rounded-xl border border-amber-400/25 bg-amber-400/10 px-3 py-2 text-xs font-semibold text-amber-300 hover:bg-amber-400/20 hover:text-white transition shadow-xs">
                <span class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-amber-400 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>Pusat Laporan</span>
                </span>
                <span class="rounded bg-amber-400/20 px-1.5 py-0.5 text-[9px] font-bold text-amber-300">Report Center</span>
            </a>
        </div>
        @endcan
    </nav>
</aside>

<style>
    /* Styling scrollbar halus untuk area sidebar navigasi */
    .custom-scrollbar::-webkit-scrollbar {
        width: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(148, 163, 184, 0.2);
        border-radius: 9999px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: rgba(148, 163, 184, 0.4);
    }
</style>
