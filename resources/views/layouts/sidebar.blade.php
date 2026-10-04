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
         class="flex-1 overflow-y-auto px-3 py-3 space-y-2 lg:block custom-scrollbar">

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
                <div class="flex items-center gap-1.5 shrink-0 ml-2">
                    <span class="text-[9px] bg-sky-500/20 text-sky-300 font-mono px-1.5 py-0.5 rounded">3</span>
                    <svg :class="open ? 'rotate-180 text-amber-400' : 'text-slate-500'" class="h-3.5 w-3.5 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </button>
            <div x-show="open" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-1"
                 class="space-y-1 px-2.5 pt-1.5 pb-2.5 border-t border-slate-800/60 bg-slate-950/40">
                <button type="button" 
                        @click="typeof select === 'function' ? select('overview') : window.location.href = '/backoffice'" 
                        :class="typeof active !== 'undefined' && active === 'overview' ? 'bg-sky-600/30 text-white border-sky-500/40 font-semibold' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white border-transparent'" 
                        class="w-full rounded-lg px-2.5 py-1.5 text-left text-xs font-medium flex items-center justify-between border transition">
                    <span class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full {{ $isOverviewActive ? 'bg-sky-400 ring-2 ring-sky-400/30' : 'bg-slate-600' }}"></span>
                        <span>Overview</span>
                    </span>
                    <span class="text-[9px] bg-slate-800 px-1.5 py-0.5 rounded text-slate-400 font-mono">Panel</span>
                </button>
                <a href="/pos" 
                   class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-medium border transition {{ $isPosActive ? 'bg-sky-600/30 text-white border-sky-500/40 font-semibold' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white border-transparent' }}">
                    <span class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full {{ $isPosActive ? 'bg-emerald-400 ring-2 ring-emerald-400/30' : 'bg-slate-600' }}"></span>
                        <span>Point of Sale (POS)</span>
                    </span>
                    <span class="text-[9px] bg-emerald-400/20 text-emerald-300 px-1.5 py-0.5 rounded font-mono">POS</span>
                </a>
                <a href="/pos" 
                   class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-medium border transition {{ $isPosActive ? 'bg-sky-600/30 text-white border-sky-500/40 font-semibold' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white border-transparent' }}">
                    <span class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full {{ $isPosActive ? 'bg-emerald-400 ring-2 ring-emerald-400/30' : 'bg-slate-600' }}"></span>
                        <span>Kasir POS</span>
                    </span>
                    <span class="text-[9px] bg-emerald-400/20 text-emerald-300 px-1.5 py-0.5 rounded font-mono">Kasir</span>
                </a>
            </div>
        </div>

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
                <div class="flex items-center gap-1.5 shrink-0 ml-2">
                    <span class="text-[9px] bg-teal-500/20 text-teal-300 font-mono px-1.5 py-0.5 rounded">4</span>
                    <svg :class="open ? 'rotate-180 text-amber-400' : 'text-slate-500'" class="h-3.5 w-3.5 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </button>
            <div x-show="open" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-1"
                 class="space-y-1 px-2.5 pt-1.5 pb-2.5 border-t border-slate-800/60 bg-slate-950/40">
                <a href="/inventory" 
                   class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-medium border transition {{ $isInventoryActive ? 'bg-sky-600/30 text-white border-sky-500/40 font-semibold' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white border-transparent' }}">
                    <span class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full {{ $isInventoryActive ? 'bg-teal-400 ring-2 ring-teal-400/30' : 'bg-slate-600' }}"></span>
                        <span>Inventory (Stok)</span>
                    </span>
                    <span class="text-[9px] bg-slate-800 px-1.5 py-0.5 rounded text-slate-400 font-mono">Stok</span>
                </a>
                <a href="{{ route('inventory.adjustments.index') }}" 
                   class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-medium border transition {{ $isOpnameActive ? 'bg-sky-600/30 text-white border-sky-500/40 font-semibold' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white border-transparent' }}">
                    <span class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full {{ $isOpnameActive ? 'bg-teal-400 ring-2 ring-teal-400/30' : 'bg-slate-600' }}"></span>
                        <span>Stock Opname</span>
                    </span>
                    <span class="text-[9px] bg-teal-400/20 text-teal-300 px-1.5 py-0.5 rounded font-mono">Opname</span>
                </a>
                <a href="/backoffice/warehouses" 
                   class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-medium border transition {{ $isWarehouseActive ? 'bg-sky-600/30 text-white border-sky-500/40 font-semibold' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white border-transparent' }}">
                    <span class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full {{ $isWarehouseActive ? 'bg-teal-400 ring-2 ring-teal-400/30' : 'bg-slate-600' }}"></span>
                        <span>Multi Gudang</span>
                    </span>
                    <span class="text-[9px] bg-teal-400/20 text-teal-300 px-1.5 py-0.5 rounded font-mono">CRUD</span>
                </a>
                <a href="/purchases/goods-receipts" 
                   class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-medium border transition {{ $isGoodsReceiptActive ? 'bg-sky-600/30 text-white border-sky-500/40 font-semibold' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white border-transparent' }}">
                    <span class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full {{ $isGoodsReceiptActive ? 'bg-emerald-400 ring-2 ring-emerald-400/30' : 'bg-slate-600' }}"></span>
                        <span>Penerimaan Barang (GR)</span>
                    </span>
                    <span class="text-[9px] bg-emerald-400/20 text-emerald-300 px-1.5 py-0.5 rounded font-mono">GR</span>
                </a>
            </div>
        </div>

        <!-- DIVISI 3: 📂 Master Data (Format Grid 2 Kolom) -->
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
                <div class="flex items-center gap-1.5 shrink-0 ml-2">
                    <span class="text-[9px] bg-amber-500/20 text-amber-300 font-mono px-1.5 py-0.5 rounded">Grid 2-Kolom</span>
                    <svg :class="open ? 'rotate-180 text-amber-400' : 'text-slate-500'" class="h-3.5 w-3.5 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </button>
            <div x-show="open" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-1"
                 class="p-2 border-t border-slate-800/60 bg-slate-950/40">
                <!-- Sub-menu dalam format grid 2 kolom hemat ruang vertikal -->
                <div class="grid grid-cols-2 gap-1.5">
                    <!-- Row 1: Katalog Produk | Kategori Produk -->
                    <a href="/backoffice/products" 
                       class="group flex flex-col justify-center rounded-lg border px-2 py-1.5 transition {{ $isProductsActive ? 'border-sky-500/50 bg-sky-600/30 text-white font-semibold shadow-xs' : 'border-slate-800/80 bg-slate-900/60 text-slate-300 hover:border-slate-700 hover:bg-slate-800/90 hover:text-white' }}">
                        <div class="flex items-center justify-between gap-1">
                            <span class="truncate text-[11px] font-medium leading-tight">Katalog Produk</span>
                            <span class="text-[8px] rounded bg-slate-800 px-1 py-0.2 text-slate-400 font-mono">CRUD</span>
                        </div>
                    </a>
                    <a href="/backoffice/categories" 
                       class="group flex flex-col justify-center rounded-lg border px-2 py-1.5 transition {{ $isCategoriesActive ? 'border-sky-500/50 bg-sky-600/30 text-white font-semibold shadow-xs' : 'border-slate-800/80 bg-slate-900/60 text-slate-300 hover:border-slate-700 hover:bg-slate-800/90 hover:text-white' }}">
                        <div class="flex items-center justify-between gap-1">
                            <span class="truncate text-[11px] font-medium leading-tight">Kategori Produk</span>
                            <span class="text-[8px] rounded bg-slate-800 px-1 py-0.2 text-slate-400 font-mono">CRUD</span>
                        </div>
                    </a>

                    <!-- Row 2: Data Supplier | Data Pelanggan -->
                    <a href="{{ route('backoffice.suppliers.index') }}" 
                       class="group flex flex-col justify-center rounded-lg border px-2 py-1.5 transition {{ $isSuppliersActive ? 'border-indigo-500/50 bg-indigo-600/30 text-white font-semibold shadow-xs' : 'border-slate-800/80 bg-slate-900/60 text-slate-300 hover:border-slate-700 hover:bg-slate-800/90 hover:text-white' }}">
                        <div class="flex items-center justify-between gap-1">
                            <span class="truncate text-[11px] font-medium leading-tight">Data Supplier</span>
                            <span class="text-[8px] rounded bg-indigo-400/20 text-indigo-300 px-1 py-0.2 font-mono">Vendor</span>
                        </div>
                    </a>
                    <a href="{{ route('backoffice.customers.index') }}" 
                       class="group flex flex-col justify-center rounded-lg border px-2 py-1.5 transition {{ $isCustomersActive ? 'border-emerald-500/50 bg-emerald-600/30 text-white font-semibold shadow-xs' : 'border-slate-800/80 bg-slate-900/60 text-slate-300 hover:border-slate-700 hover:bg-slate-800/90 hover:text-white' }}">
                        <div class="flex items-center justify-between gap-1">
                            <span class="truncate text-[11px] font-medium leading-tight">Data Pelanggan</span>
                            <span class="text-[8px] rounded bg-emerald-400/20 text-emerald-300 px-1 py-0.2 font-mono">Client</span>
                        </div>
                    </a>

                    <!-- Row 3: Staf & Kasir | Manajemen Cabang -->
                    <a href="/backoffice/users" 
                       class="group flex flex-col justify-center rounded-lg border px-2 py-1.5 transition {{ $isUsersActive ? 'border-sky-500/50 bg-sky-600/30 text-white font-semibold shadow-xs' : 'border-slate-800/80 bg-slate-900/60 text-slate-300 hover:border-slate-700 hover:bg-slate-800/90 hover:text-white' }}">
                        <div class="flex items-center justify-between gap-1">
                            <span class="truncate text-[11px] font-medium leading-tight">Staf &amp; Kasir</span>
                            <span class="text-[8px] rounded bg-slate-800 px-1 py-0.2 text-slate-400 font-mono">Akun</span>
                        </div>
                    </a>
                    @if ($sidebarIsMaster)
                        <a href="/backoffice/branches" 
                           class="group flex flex-col justify-center rounded-lg border px-2 py-1.5 transition {{ $isBranchesActive ? 'border-amber-500/50 bg-amber-600/30 text-white font-semibold shadow-xs' : 'border-slate-800/80 bg-slate-900/60 text-amber-300 hover:border-slate-700 hover:bg-slate-800/90 hover:text-white' }}">
                            <div class="flex items-center justify-between gap-1">
                                <span class="truncate text-[11px] font-medium leading-tight">Manajemen Cabang</span>
                                <span class="text-[8px] rounded bg-amber-400/20 text-amber-300 px-1 py-0.2 font-mono">Master</span>
                            </div>
                        </a>
                    @else
                        <div class="flex flex-col justify-center rounded-lg border border-slate-800/40 bg-slate-950/30 px-2 py-1.5 opacity-40 cursor-not-allowed">
                            <div class="flex items-center justify-between gap-1">
                                <span class="truncate text-[11px] text-slate-500">Cabang</span>
                                <span class="text-[8px] text-slate-600 font-mono">Locked</span>
                            </div>
                        </div>
                    @endif

                    <!-- Row 4: Kategori Biaya | Preview Data Sistem -->
                    <a href="{{ route('backoffice.expense-categories.index') }}" 
                       class="group flex flex-col justify-center rounded-lg border px-2 py-1.5 transition {{ $isExpenseCatActive ? 'border-rose-500/50 bg-rose-600/30 text-white font-semibold shadow-xs' : 'border-slate-800/80 bg-slate-900/60 text-slate-300 hover:border-slate-700 hover:bg-slate-800/90 hover:text-white' }}">
                        <div class="flex items-center justify-between gap-1">
                            <span class="truncate text-[11px] font-medium leading-tight">Kategori Biaya</span>
                            <span class="text-[8px] rounded bg-rose-400/20 text-rose-300 px-1 py-0.2 font-mono">Beban</span>
                        </div>
                    </a>
                    @if ($sidebarIsMaster)
                        <a href="{{ route('preview') }}" 
                           class="group flex flex-col justify-center rounded-lg border px-2 py-1.5 transition {{ $isPreviewActive ? 'border-amber-500/50 bg-amber-600/30 text-white font-semibold shadow-xs' : 'border-slate-800/80 bg-slate-900/60 text-amber-300 hover:border-slate-700 hover:bg-slate-800/90 hover:text-white' }}">
                            <div class="flex items-center justify-between gap-1">
                                <span class="truncate text-[11px] font-medium leading-tight">Preview Sistem</span>
                                <span class="text-[8px] rounded bg-amber-400/20 text-amber-300 px-1 py-0.2 font-mono">Master</span>
                            </div>
                        </a>
                    @else
                        <div class="flex flex-col justify-center rounded-lg border border-slate-800/40 bg-slate-950/30 px-2 py-1.5 opacity-40 cursor-not-allowed">
                            <div class="flex items-center justify-between gap-1">
                                <span class="truncate text-[11px] text-slate-500">Preview</span>
                                <span class="text-[8px] text-slate-600 font-mono">Locked</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

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
                <div class="flex items-center gap-1.5 shrink-0 ml-2">
                    <span class="text-[9px] bg-emerald-500/20 text-emerald-300 font-mono px-1.5 py-0.5 rounded">2</span>
                    <svg :class="open ? 'rotate-180 text-amber-400' : 'text-slate-500'" class="h-3.5 w-3.5 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </button>
            <div x-show="open" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-1"
                 class="space-y-1 px-2.5 pt-1.5 pb-2.5 border-t border-slate-800/60 bg-slate-950/40">
                <a href="{{ route('backoffice.payments.receivables.create') }}" 
                   class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-medium border transition {{ $isReceivablesActive ? 'bg-sky-600/30 text-white border-sky-500/40 font-semibold' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white border-transparent' }}">
                    <span class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full {{ $isReceivablesActive ? 'bg-emerald-400 ring-2 ring-emerald-400/30' : 'bg-slate-600' }}"></span>
                        <span>Pembayaran Piutang (AR)</span>
                    </span>
                    <span class="text-[9px] bg-emerald-400/20 text-emerald-300 px-1.5 py-0.5 rounded font-mono">AR</span>
                </a>
                <a href="{{ route('backoffice.sales-returns.index') }}" 
                   class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-medium border transition {{ $isSalesReturnsActive ? 'bg-sky-600/30 text-white border-sky-500/40 font-semibold' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white border-transparent' }}">
                    <span class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full {{ $isSalesReturnsActive ? 'bg-rose-400 ring-2 ring-rose-400/30' : 'bg-slate-600' }}"></span>
                        <span>Retur Penjualan</span>
                    </span>
                    <span class="text-[9px] bg-rose-400/20 text-rose-300 px-1.5 py-0.5 rounded font-mono">Retur</span>
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
                <div class="flex items-center gap-1.5 shrink-0 ml-2">
                    <span class="text-[9px] bg-indigo-500/20 text-indigo-300 font-mono px-1.5 py-0.5 rounded">3</span>
                    <svg :class="open ? 'rotate-180 text-amber-400' : 'text-slate-500'" class="h-3.5 w-3.5 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </button>
            <div x-show="open" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-1"
                 class="space-y-1 px-2.5 pt-1.5 pb-2.5 border-t border-slate-800/60 bg-slate-950/40">
                <a href="{{ route('backoffice.purchase-orders.index') }}" 
                   class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-medium border transition {{ $isPurchaseOrdersActive ? 'bg-sky-600/30 text-white border-sky-500/40 font-semibold' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white border-transparent' }}">
                    <span class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full {{ $isPurchaseOrdersActive ? 'bg-amber-400 ring-2 ring-amber-400/30' : 'bg-slate-600' }}"></span>
                        <span>Pembelian / PO</span>
                    </span>
                    <span class="text-[9px] bg-amber-400/20 text-amber-300 px-1.5 py-0.5 rounded font-mono">PO</span>
                </a>
                <a href="{{ route('backoffice.supplier-payments.index') }}" 
                   class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-medium border transition {{ $isSupplierPaymentsActive ? 'bg-sky-600/30 text-white border-sky-500/40 font-semibold' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white border-transparent' }}">
                    <span class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full {{ $isSupplierPaymentsActive ? 'bg-indigo-400 ring-2 ring-indigo-400/30' : 'bg-slate-600' }}"></span>
                        <span>Pembayaran Hutang (AP)</span>
                    </span>
                    <span class="text-[9px] bg-indigo-400/20 text-indigo-300 px-1.5 py-0.5 rounded font-mono">Bayar</span>
                </a>
                <a href="{{ route('backoffice.purchase-returns.index') }}" 
                   class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-medium border transition {{ $isPurchaseReturnsActive ? 'bg-sky-600/30 text-white border-sky-500/40 font-semibold' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white border-transparent' }}">
                    <span class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full {{ $isPurchaseReturnsActive ? 'bg-rose-400 ring-2 ring-rose-400/30' : 'bg-slate-600' }}"></span>
                        <span>Retur Pembelian</span>
                    </span>
                    <span class="text-[9px] bg-rose-400/20 text-rose-300 px-1.5 py-0.5 rounded font-mono">Retur</span>
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
                <div class="flex items-center gap-1.5 shrink-0 ml-2">
                    <span class="text-[9px] bg-amber-500/20 text-amber-300 font-mono px-1.5 py-0.5 rounded">4+</span>
                    <svg :class="open ? 'rotate-180 text-amber-400' : 'text-slate-500'" class="h-3.5 w-3.5 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </button>
            <div x-show="open" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-1"
                 class="space-y-1 px-2.5 pt-1.5 pb-2.5 border-t border-slate-800/60 bg-slate-950/40">
                <a href="/reports/journal" 
                   class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-medium border transition {{ $isJournalActive ? 'bg-sky-600/30 text-white border-sky-500/40 font-semibold' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white border-transparent' }}">
                    <span class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full {{ $isJournalActive ? 'bg-amber-400 ring-2 ring-amber-400/30' : 'bg-slate-600' }}"></span>
                        <span>Journal Ledger</span>
                    </span>
                    <span class="text-[9px] bg-slate-800 px-1.5 py-0.5 rounded text-slate-400 font-mono">Ledger</span>
                </a>
                <a href="{{ route('backoffice.cash-transfers.index') }}" 
                   class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-medium border transition {{ $isCashTransferActive ? 'bg-sky-600/30 text-white border-sky-500/40 font-semibold' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white border-transparent' }}">
                    <span class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full {{ $isCashTransferActive ? 'bg-amber-400 ring-2 ring-amber-400/30' : 'bg-slate-600' }}"></span>
                        <span>Mutasi Kas &amp; Bank</span>
                    </span>
                    <span class="text-[9px] bg-amber-400/20 text-amber-300 px-1.5 py-0.5 rounded font-mono">Mutasi</span>
                </a>
                <a href="{{ route('backoffice.expenses.index') }}" 
                   class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-medium border transition {{ $isExpensesActive ? 'bg-sky-600/30 text-white border-sky-500/40 font-semibold' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white border-transparent' }}">
                    <span class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full {{ $isExpensesActive ? 'bg-rose-400 ring-2 ring-rose-400/30' : 'bg-slate-600' }}"></span>
                        <span>Biaya Operasional</span>
                    </span>
                    <span class="text-[9px] bg-rose-400/20 text-rose-300 px-1.5 py-0.5 rounded font-mono">Kas Keluar</span>
                </a>
                <a href="{{ route('backoffice.opening-balances.create') }}" 
                   class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-medium border transition {{ $isOpeningBalanceActive ? 'bg-amber-500/20 border-amber-400/30 text-amber-300 font-semibold' : ($hasOB ? 'text-slate-400 hover:bg-slate-800/80 hover:text-white border-transparent opacity-80' : 'text-amber-300 bg-amber-500/10 border-amber-400/20 hover:bg-slate-800 hover:text-white') }}">
                    <span class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full {{ $hasOB ? 'bg-emerald-400' : 'bg-amber-400 animate-pulse' }}"></span>
                        <span>Input Saldo Awal</span>
                    </span>
                    <span class="text-[9px] px-1.5 py-0.5 rounded font-mono {{ $hasOB ? 'bg-emerald-400/20 text-emerald-300' : 'bg-amber-400/20 text-amber-300 font-bold' }}">
                        {{ $hasOB ? 'Tercatat' : 'Setup' }}
                    </span>
                </a>
                <a href="{{ route('backoffice.fixed-assets.index') }}" 
                   class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-medium border transition {{ $isFixedAssetsActive ? 'bg-sky-600/30 text-white border-sky-500/40 font-semibold' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white border-transparent' }}">
                    <span class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full {{ $isFixedAssetsActive ? 'bg-purple-400 ring-2 ring-purple-400/30' : 'bg-slate-600' }}"></span>
                        <span>Harta Tetap</span>
                    </span>
                    <span class="text-[9px] bg-purple-400/20 text-purple-300 px-1.5 py-0.5 rounded font-mono">Aset</span>
                </a>
            </div>
        </div>

        <!-- Pusat Laporan (Quick Access Card) -->
        <div class="pt-2">
            <a href="{{ route('reports.index') }}" 
               class="group flex items-center justify-between rounded-xl border border-amber-400/25 bg-amber-400/10 px-3 py-2.5 text-xs font-semibold text-amber-300 hover:bg-amber-400/20 hover:text-white transition shadow-xs">
                <span class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-amber-400 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>Pusat Laporan</span>
                </span>
                <span class="rounded bg-amber-400/20 px-1.5 py-0.5 text-[9px] font-bold text-amber-300">Report Center</span>
            </a>
        </div>
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
