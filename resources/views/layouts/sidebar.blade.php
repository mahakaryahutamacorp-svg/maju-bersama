@php
    $sidebarUser = $currentUser ?? auth()->user();
    $sidebarIsMaster = $isMaster ?? ($sidebarUser ? $sidebarUser->isMaster() : false);

    $isOverviewActive = (isset($active) && $active === 'overview') || request()->is('backoffice') || request()->is('dashboard');
    $isPosActive = request()->is('pos*');

    $isSalesHistoryActive = request()->is('reports/sales*') || request()->is('backoffice/reports/sales*');
    $isArAgingActive = request()->is('reports/ar-aging*') || request()->is('backoffice/reports/ar-aging*');
    $isReceivablesActive = request()->is('backoffice/payments*');
    $isSalesReturnsActive = request()->is('backoffice/sales-returns*');
    $isCustomersActive = request()->is('backoffice/customers*');
    $isJualanOpen = $isSalesHistoryActive || $isArAgingActive || $isReceivablesActive || $isSalesReturnsActive || $isCustomersActive;

    $isGoodsReceiptActive = request()->is('purchases/goods-receipts*');
    $isPurchaseOrdersActive = request()->is('backoffice/purchase-orders*');
    $isSuppliersActive = request()->is('backoffice/suppliers*');
    $isSupplierPaymentsActive = request()->is('backoffice/supplier-payments*') || request()->is('purchases/payables*');
    $isPurchaseReturnsActive = request()->is('backoffice/purchase-returns*');
    $isBelanjaOpen = $isGoodsReceiptActive || $isPurchaseOrdersActive || $isSuppliersActive || $isSupplierPaymentsActive || $isPurchaseReturnsActive;

    $isInventoryActive = request()->is('inventory') || request()->is('inventory/transfer*');
    $isOpnameActive = request()->is('inventory/adjustments*');
    $isWarehouseActive = request()->is('backoffice/warehouses*');
    $isStokOpen = $isInventoryActive || $isOpnameActive || $isWarehouseActive;

    $isFinanceActive = request()->is('backoffice/finance*');
    $financeTab = $isFinanceActive ? request('tab') : null;
    $isJournalActive = request()->is('reports/journal*') || request()->is('reports/accounting/ledger*') || $financeTab === 'jurnal';
    $isCashActive = request()->is('backoffice/cash-transfers*') || request()->is('backoffice/cash-transactions*') || $financeTab === 'kas_bank';
    $isExpensesActive = request()->is('backoffice/expenses*') || $financeTab === 'pengeluaran';
    $isFinanceSummaryActive = $isFinanceActive && ! in_array($financeTab, ['jurnal', 'kas_bank', 'pengeluaran'], true);
    $isFixedAssetsActive = request()->is('backoffice/fixed-assets*');
    $isOpeningBalanceActive = request()->is('backoffice/opening-balances*');
    $isUangOpen = $isFinanceActive || $isJournalActive || $isCashActive || $isExpensesActive || $isFixedAssetsActive || $isOpeningBalanceActive;

    $isProductsActive = request()->is('backoffice/products*');
    $isCategoriesActive = request()->is('backoffice/categories*');
    $isUsersActive = request()->is('backoffice/users*');
    $isBranchesActive = request()->is('backoffice/branches*');
    $isExpenseCatActive = request()->is('backoffice/expense-categories*');
    $isPreviewActive = request()->is('preview*');
    $isPengaturanOpen = $isProductsActive || $isCategoriesActive || $isUsersActive || $isBranchesActive || $isExpenseCatActive || $isPreviewActive;

    $isReportsActive = request()->is('reports') || request()->is('backoffice/reports');

    $topLinkClass = fn (bool $active) => 'flex items-center gap-2.5 rounded-xl border px-3 py-2 text-xs font-semibold transition '
        .($active ? 'border-sky-400/30 bg-sky-400/10 text-white' : 'border-slate-800/80 bg-slate-900/40 text-slate-300 hover:bg-slate-800/50 hover:text-white');
@endphp

<aside class="flex shrink-0 flex-col print:hidden border-b border-slate-800 bg-slate-950 text-white lg:sticky lg:top-0 lg:h-screen lg:w-72 lg:border-b-0 lg:border-r">
    <div class="flex items-center justify-between border-b border-slate-800/80 px-5 py-4 lg:py-5">
        <a href="/backoffice" class="group block">
            <span class="flex items-center gap-2">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg border border-amber-400/30 bg-amber-400/20 text-xs font-bold text-amber-400">MB</span>
                <span>
                    <span class="block text-[10px] font-bold uppercase tracking-[0.24em] text-amber-400 transition group-hover:text-amber-300">ERP System</span>
                    <span class="block text-lg font-bold leading-tight tracking-tight text-white">Maju Bersama</span>
                </span>
            </span>
        </a>
        <button type="button" @click="mobileMenu = !mobileMenu"
                class="rounded-lg border border-slate-700 bg-slate-900 px-2.5 py-1.5 text-xs font-medium text-slate-300 hover:text-white lg:hidden">
            <span x-text="mobileMenu ? 'Tutup' : 'Menu'">Menu</span>
        </button>
    </div>

    <div class="border-b border-slate-800/50 bg-slate-900/30 px-4 py-3">
        <div class="rounded-xl border border-sky-400/20 bg-sky-400/10 px-3.5 py-2.5">
            <div class="flex items-center justify-between">
                <p class="text-[10px] font-bold uppercase tracking-wider text-sky-200">
                    {{ $sidebarIsMaster ? 'Akses Pusat' : 'Cabang Aktif' }}
                </p>
                <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-emerald-400"></span>
            </div>
            <p class="mt-0.5 truncate text-xs font-semibold text-white">
                {{ $sidebarIsMaster ? 'Semua cabang' : ($sidebarUser->branch->name ?? 'Cabang Utama') }}
            </p>
            <p class="mt-0.5 truncate text-[10px] text-slate-400">
                {{ $sidebarUser->name ?? 'User' }} &middot; <span class="capitalize">{{ str_replace('_', ' ', $sidebarUser->role ?? 'staf') }}</span>
            </p>
        </div>
    </div>

    <nav :class="{ 'hidden': ! mobileMenu }" class="custom-scrollbar flex-1 space-y-1.5 overflow-y-auto px-3 py-3 lg:block" aria-label="Menu utama">
        <div class="grid grid-cols-2 gap-1.5">
            <button type="button"
                    @click="typeof select === 'function' ? select('overview') : window.location.href = '/backoffice'"
                    class="{{ $topLinkClass($isOverviewActive) }}">
                <svg class="h-4 w-4 shrink-0 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                Beranda
            </button>
            <a href="/pos" class="{{ $topLinkClass($isPosActive) }}">
                <svg class="h-4 w-4 shrink-0 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                Kasir (POS)
            </a>
        </div>

        @can('manage-branch-operations')
            <x-sidebar.group label="Jualan" hint="Riwayat, piutang, retur pelanggan" tone="emerald" :open="$isJualanOpen">
                <x-slot:icon>
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                </x-slot:icon>
                <x-sidebar.link :href="route('reports.sales')" :active="$isSalesHistoryActive">Riwayat Penjualan</x-sidebar.link>
                <x-sidebar.link :href="route('reports.ar-aging')" :active="$isArAgingActive">Piutang Pelanggan</x-sidebar.link>
                <x-sidebar.link :href="route('backoffice.payments.receivables.create')" :active="$isReceivablesActive">Terima Bayaran Piutang</x-sidebar.link>
                <x-sidebar.link :href="route('backoffice.sales-returns.index')" :active="$isSalesReturnsActive">Retur dari Pelanggan</x-sidebar.link>
                @can('manage-catalog')
                    <x-sidebar.link :href="route('backoffice.customers.index')" :active="$isCustomersActive">Data Pelanggan</x-sidebar.link>
                @endcan
            </x-sidebar.group>
        @endcan

        @canany(['access-inventory', 'access-enterprise'])
            <x-sidebar.group label="Belanja Barang" hint="Pesan, terima, bayar pemasok" tone="indigo" :open="$isBelanjaOpen">
                <x-slot:icon>
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1" /></svg>
                </x-slot:icon>
                @can('access-enterprise')
                    <x-sidebar.link :href="route('backoffice.purchase-orders.index')" :active="$isPurchaseOrdersActive">1. Pesan Barang (PO)</x-sidebar.link>
                @endcan
                @can('access-inventory')
                    <x-sidebar.link :href="route('purchases.goods-receipts.index')" :active="$isGoodsReceiptActive">2. Terima Barang</x-sidebar.link>
                @endcan
                @can('manage-system')
                    <x-sidebar.link :href="route('backoffice.suppliers.index')" :active="$isSuppliersActive">3. Buku Pemasok &amp; Hutang</x-sidebar.link>
                @endcan
                @can('access-enterprise')
                    <x-sidebar.link :href="route('backoffice.purchase-returns.index')" :active="$isPurchaseReturnsActive">Retur ke Pemasok</x-sidebar.link>
                    <x-sidebar.link :href="route('backoffice.supplier-payments.index')" :active="$isSupplierPaymentsActive">Riwayat Bayar Hutang</x-sidebar.link>
                @endcan
            </x-sidebar.group>
        @endcanany

        @can('access-inventory')
            <x-sidebar.group label="Stok Gudang" hint="Cek stok, hitung ulang, gudang" tone="teal" :open="$isStokOpen">
                <x-slot:icon>
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                </x-slot:icon>
                <x-sidebar.link href="/inventory" :active="$isInventoryActive">Stok Barang</x-sidebar.link>
                <x-sidebar.link :href="route('inventory.adjustments.index')" :active="$isOpnameActive">Hitung Ulang Stok (Opname)</x-sidebar.link>
                <x-sidebar.link :href="route('backoffice.warehouses.index')" :active="$isWarehouseActive">Daftar Gudang</x-sidebar.link>
            </x-sidebar.group>
        @endcan

        @can('manage-branch-operations')
            <x-sidebar.group label="Uang & Akuntansi" hint="Kas, biaya, jurnal" tone="amber" :open="$isUangOpen">
                <x-slot:icon>
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </x-slot:icon>
                <x-sidebar.link :href="route('backoffice.finance.dashboard')" :active="$isFinanceSummaryActive">Ringkasan Keuangan</x-sidebar.link>
                <x-sidebar.link :href="route('backoffice.finance.dashboard', ['tab' => 'kas_bank'])" :active="$isCashActive">Kas &amp; Bank</x-sidebar.link>
                <x-sidebar.link :href="route('backoffice.finance.dashboard', ['tab' => 'pengeluaran'])" :active="$isExpensesActive">Biaya Operasional</x-sidebar.link>
                @can('view-accounting')
                    <x-sidebar.link :href="route('backoffice.finance.dashboard', ['tab' => 'jurnal'])" :active="$isJournalActive">Jurnal Umum</x-sidebar.link>
                @endcan
                <x-sidebar.link :href="route('backoffice.fixed-assets.index')" :active="$isFixedAssetsActive">Harta Tetap</x-sidebar.link>
                @can('manage-system')
                    <x-sidebar.link :href="route('backoffice.opening-balances.create')" :active="$isOpeningBalanceActive">Saldo Awal (Setup)</x-sidebar.link>
                @endcan
            </x-sidebar.group>
        @endcan

        @can('access-inventory')
            <x-sidebar.group label="Pengaturan" hint="Produk, staf, cabang" tone="slate" :open="$isPengaturanOpen">
                <x-slot:icon>
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                </x-slot:icon>
                @can('manage-catalog')
                    <x-sidebar.link :href="route('backoffice.products.index')" :active="$isProductsActive">Katalog Produk</x-sidebar.link>
                @endcan
                @can('manage-system')
                    <x-sidebar.link :href="route('backoffice.categories.index')" :active="$isCategoriesActive">Kategori Produk</x-sidebar.link>
                    <x-sidebar.link :href="route('backoffice.users.index')" :active="$isUsersActive">Staf &amp; Kasir</x-sidebar.link>
                    <x-sidebar.link :href="route('backoffice.branches.index')" :active="$isBranchesActive">Manajemen Cabang</x-sidebar.link>
                    <x-sidebar.link :href="route('backoffice.expense-categories.index')" :active="$isExpenseCatActive">Kategori Biaya</x-sidebar.link>
                    <x-sidebar.link :href="route('preview')" :active="$isPreviewActive">Preview Data Sistem</x-sidebar.link>
                @endcan
            </x-sidebar.group>
        @endcan

        @can('manage-branch-operations')
            <div class="pt-2">
                <a href="{{ route('reports.index') }}"
                   class="group flex items-center justify-between rounded-xl border px-3 py-2 text-xs font-semibold transition {{ $isReportsActive ? 'border-amber-400/50 bg-amber-400/20 text-white' : 'border-amber-400/25 bg-amber-400/10 text-amber-300 hover:bg-amber-400/20 hover:text-white' }}">
                    <span class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-amber-400 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        Pusat Laporan
                    </span>
                    <span class="rounded bg-amber-400/20 px-1.5 py-0.5 text-[9px] font-bold text-amber-300">Laporan</span>
                </a>
            </div>
        @endcan

        <form method="POST" action="/logout" class="pt-2">
            @csrf
            <button type="submit" class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-xs font-semibold text-slate-400 transition hover:bg-slate-800/50 hover:text-white">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                Keluar
            </button>
        </form>
    </nav>
</aside>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.2); border-radius: 9999px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(148, 163, 184, 0.4); }
</style>
