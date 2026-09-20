<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backoffice | Maju Bersama ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body x-data="backoffice()" class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <div class="min-h-screen lg:flex">
        <aside class="border-b border-slate-800 bg-slate-950 text-white lg:min-h-screen lg:w-72 lg:border-b-0 lg:border-r">
            <div class="flex items-center justify-between px-6 py-5 lg:block">
                <a href="/backoffice" class="block">
                    <p class="text-xs font-semibold uppercase tracking-[0.26em] text-amber-400">Maju Bersama ERP</p>
                    <p class="mt-1 text-xl font-bold tracking-tight">Backoffice</p>
                </a>
                <button type="button" @click="mobileMenu = !mobileMenu" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 lg:hidden">Menu</button>
            </div>

            <div class="px-4 pb-5 lg:pb-6">
                <div class="rounded-xl border border-sky-400/20 bg-sky-400/10 px-4 py-3">
                    <p class="text-xs uppercase tracking-wider text-sky-200">{{ $isMaster ? 'Master access' : 'Active branch' }}</p>
                    <p class="mt-1 font-semibold text-white">{{ $isMaster ? 'All branches' : $currentUser->branch->name }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ $currentUser->name }} · {{ $currentUser->role }}</p>
                </div>
            </div>

            <nav x-show="mobileMenu" x-transition class="space-y-1 px-4 pb-6 lg:block">
                <p class="px-3 pb-2 pt-1 text-xs font-semibold uppercase tracking-wider text-slate-500">Workspace</p>
                <button @click="select('overview')" :class="active === 'overview' ? 'bg-sky-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white'" class="w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium">Overview</button>
                <a href="/pos" class="block rounded-lg px-3 py-2.5 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white">Point of Sale</a>
                <a href="/inventory" class="block rounded-lg px-3 py-2.5 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white">Inventory</a>
                <a href="/reports/journal" class="block rounded-lg px-3 py-2.5 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white">Journal Ledger</a>

                <p class="px-3 pb-2 pt-5 text-xs font-semibold uppercase tracking-wider text-amber-400/90">Master Data</p>
                <a href="/backoffice/products" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white flex items-center justify-between">
                    <span>Katalog Produk</span>
                    <span class="text-[10px] bg-slate-800 px-1.5 py-0.5 rounded text-slate-400">CRUD</span>
                </a>
                <a href="/backoffice/categories" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white flex items-center justify-between">
                    <span>Kategori Produk</span>
                    <span class="text-[10px] bg-slate-800 px-1.5 py-0.5 rounded text-slate-400">CRUD</span>
                </a>
                <a href="{{ route('backoffice.suppliers.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white flex items-center justify-between">
                    <span>Data Supplier</span>
                    <span class="text-[10px] bg-indigo-400/20 text-indigo-300 px-1.5 py-0.5 rounded">Supplier</span>
                </a>
                <a href="/backoffice/users" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white flex items-center justify-between">
                    <span>Staf &amp; Kasir</span>
                    <span class="text-[10px] bg-slate-800 px-1.5 py-0.5 rounded text-slate-400">Akun</span>
                </a>
                @if ($isMaster)
                    <a href="/backoffice/branches" class="block rounded-lg px-3 py-2 text-sm font-medium text-amber-300 hover:bg-slate-800 hover:text-white flex items-center justify-between">
                        <span>Manajemen Cabang</span>
                        <span class="text-[10px] bg-amber-400/20 text-amber-300 px-1.5 py-0.5 rounded">Master</span>
                    </a>
                @endif
                <a href="/backoffice/warehouses" class="block rounded-lg px-3 py-2 text-sm font-medium text-teal-300 hover:bg-slate-800 hover:text-white flex items-center justify-between">
                    <span>Multi Gudang</span>
                    <span class="text-[10px] bg-teal-400/20 text-teal-300 px-1.5 py-0.5 rounded">CRUD</span>
                </a>
                <a href="{{ route('backoffice.expense-categories.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white flex items-center justify-between">
                    <span>Kategori Biaya</span>
                    <span class="text-[10px] bg-rose-400/20 text-rose-300 px-1.5 py-0.5 rounded">Beban</span>
                </a>

                <p class="px-3 pb-2 pt-5 text-xs font-semibold uppercase tracking-wider text-amber-400/90">Pengadaan &amp; PO</p>
                <a href="{{ route('backoffice.purchase-orders.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white flex items-center justify-between">
                    <span>Pembelian / PO</span>
                    <span class="text-[10px] bg-amber-400/20 text-amber-300 px-1.5 py-0.5 rounded">PO</span>
                </a>
                <a href="/purchases/goods-receipts" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white flex items-center justify-between">
                    <span>Penerimaan Barang</span>
                    <span class="text-[10px] bg-emerald-400/20 text-emerald-300 px-1.5 py-0.5 rounded">GR</span>
                </a>
                <a href="{{ route('backoffice.supplier-payments.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white flex items-center justify-between">
                    <span>Pembayaran Supplier</span>
                    <span class="text-[10px] bg-indigo-400/20 text-indigo-300 px-1.5 py-0.5 rounded">Bayar</span>
                </a>

                <p class="px-3 pb-2 pt-5 text-xs font-semibold uppercase tracking-wider text-amber-400/90">Keuangan &amp; Akuntansi</p>
                <a href="{{ route('backoffice.cash-transfers.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white flex items-center justify-between">
                    <span>Mutasi Kas &amp; Bank</span>
                    <span class="text-[10px] bg-amber-400/20 text-amber-300 px-1.5 py-0.5 rounded">Mutasi</span>
                </a>
                @php
                    $hasOB = \App\Services\OpeningBalanceService::hasOpeningBalance($currentUser->branch_id);
                @endphp
                @if (! $hasOB)
                    <a href="{{ route('backoffice.opening-balances.create') }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-amber-300 hover:bg-slate-800 hover:text-white flex items-center justify-between bg-amber-500/10 border border-amber-400/20">
                        <span>Input Saldo Awal</span>
                        <span class="text-[10px] bg-amber-400/20 text-amber-300 px-1.5 py-0.5 rounded font-bold">Setup</span>
                    </a>
                @else
                    <a href="{{ route('backoffice.opening-balances.create') }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white flex items-center justify-between opacity-70">
                        <span>Input Saldo Awal</span>
                        <span class="text-[10px] bg-emerald-400/20 text-emerald-300 px-1.5 py-0.5 rounded">Tercatat</span>
                    </a>
                @endif
                <a href="{{ route('backoffice.expenses.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white flex items-center justify-between">
                    <span>Biaya Operasional</span>
                    <span class="text-[10px] bg-rose-400/20 text-rose-300 px-1.5 py-0.5 rounded">Kas Keluar</span>
                </a>
                <a href="/reports/accounting/ledger" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white flex items-center justify-between">
                    <span>Buku Besar Akuntansi</span>
                    <span class="text-[10px] bg-sky-400/20 text-sky-300 px-1.5 py-0.5 rounded">Ledger</span>
                </a>
                <a href="/reports/accounting/income-statement" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white flex items-center justify-between">
                    <span>Laporan Laba Rugi</span>
                    <span class="text-[10px] bg-emerald-400/20 text-emerald-300 px-1.5 py-0.5 rounded">L/R</span>
                </a>

                <p class="px-3 pb-2 pt-5 text-xs font-semibold uppercase tracking-wider text-slate-500">Administration</p>
                <template x-for="module in modules" :key="module.id">
                    <button @click="select(module.id)" :class="active === module.id ? 'bg-slate-800 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white'" class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-sm font-medium">
                        <span x-text="module.name"></span>
                        <span x-show="module.status === 'planned'" class="text-[10px] uppercase tracking-wider text-slate-500">Soon</span>
                    </button>
                </template>
            </nav>
        </aside>

        <main class="min-w-0 flex-1">
            <header class="border-b border-slate-200 bg-white">
                <div class="flex flex-col justify-between gap-4 px-6 py-5 sm:flex-row sm:items-center lg:px-10">
                    <div>
                        <p class="text-sm font-medium text-amber-600">Operational control center</p>
                        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950" x-text="pageTitle"></h1>
                    </div>
                    <div class="flex items-center gap-4">
                        <a href="/" class="text-sm text-slate-500 hover:text-slate-900">Public preview</a>
                        <form method="POST" action="/logout">
                            @csrf
                            <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Logout</button>
                        </form>
                    </div>
                </div>
            </header>

            <div class="mx-auto max-w-7xl space-y-8 px-6 py-8 lg:px-10">
                <section x-show="active === 'overview'" x-transition>
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <template x-for="stat in stats" :key="stat.label">
                            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <p class="text-sm text-slate-500" x-text="stat.label"></p>
                                <p class="mt-3 text-3xl font-bold tracking-tight text-slate-950" x-text="stat.value"></p>
                                <p class="mt-2 text-xs text-slate-400" x-text="stat.caption"></p>
                            </div>
                        </template>
                    </div>

                    @if ($isMaster)
                        <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                                <div>
                                    <p class="text-sm font-medium text-amber-600">Master monitoring</p>
                                    <h2 class="mt-1 text-xl font-bold text-slate-950">Inventory seluruh branch</h2>
                                </div>
                                <span class="text-sm text-slate-500">{{ $branchSummaries->count() }} branches terpantau</span>
                            </div>
                            <div class="mt-5 overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Branch</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Parent</th>
                                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Products</th>
                                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Stock</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach ($branchSummaries as $branch)
                                            <tr class="hover:bg-slate-50">
                                                <td class="px-4 py-3 text-sm font-semibold text-slate-900">{{ $branch->name }}<span class="ml-2 font-mono text-xs text-slate-400">{{ $branch->code }}</span></td>
                                                <td class="px-4 py-3 text-sm text-slate-500">{{ $branch->parent?->name ?? 'Root pusat' }}</td>
                                                <td class="px-4 py-3 text-right text-sm text-slate-700">{{ $branch->products_count }}</td>
                                                <td class="px-4 py-3 text-right text-sm font-semibold text-emerald-700">{{ number_format($branch->products_sum_stock ?? 0) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    @endif

                    <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-end justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-amber-600">{{ $isMaster ? 'Cross-branch activity' : 'Aktivitas transaksi' }}</p>
                                <h2 class="mt-1 text-xl font-bold text-slate-950">{{ $isMaster ? 'Transaksi terbaru seluruh branch' : 'Transaksi terbaru branch ini' }}</h2>
                            </div>
                            <a href="/reports/journal" class="text-sm font-semibold text-sky-700 hover:text-sky-800">Buka ledger</a>
                        </div>
                            <div class="mt-5 overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Reference</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Branch</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Description</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">User</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @forelse ($recentJournals as $journal)
                                            <tr class="hover:bg-slate-50">
                                                <td class="px-4 py-3 font-mono text-xs font-semibold">
                                                    <button type="button" 
                                                            @click="loadTransaction('{{ $journal->reference_number }}')" 
                                                            class="group inline-flex items-center gap-1.5 font-semibold text-sky-600 hover:text-sky-800 hover:underline focus:outline-none transition text-left" 
                                                            title="Klik untuk melihat detail transaksi">
                                                        <span>{{ $journal->reference_number }}</span>
                                                        <svg class="h-3.5 w-3.5 text-sky-400 group-hover:text-sky-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                        </svg>
                                                    </button>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-slate-700">{{ $journal->branch?->name }}</td>
                                                <td class="px-4 py-3 text-sm text-slate-700">{{ $journal->description }}</td>
                                                <td class="px-4 py-3 text-sm text-slate-500">{{ $journal->user?->name }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">Belum ada transaksi antar branch.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </section>

                    <div class="mt-8 grid gap-6 xl:grid-cols-[1.4fr_0.6fr]">
                        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-medium text-amber-600">Daily workflow</p>
                                    <h2 class="mt-1 text-xl font-bold text-slate-950">Quick actions</h2>
                                </div>
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Ready</span>
                            </div>
                            <div class="mt-6 grid gap-3 sm:grid-cols-3">
                                <a href="/pos" class="rounded-xl border border-slate-200 p-4 transition hover:border-sky-300 hover:bg-sky-50">
                                    <p class="font-semibold text-slate-900">New sale</p>
                                    <p class="mt-1 text-xs text-slate-500">Open POS checkout</p>
                                </a>
                                <a href="/inventory" class="rounded-xl border border-slate-200 p-4 transition hover:border-sky-300 hover:bg-sky-50">
                                    <p class="font-semibold text-slate-900">Check stock</p>
                                    <p class="mt-1 text-xs text-slate-500">Review branch inventory</p>
                                </a>
                                <a href="/reports/journal" class="rounded-xl border border-slate-200 p-4 transition hover:border-sky-300 hover:bg-sky-50">
                                    <p class="font-semibold text-slate-900">Review ledger</p>
                                    <p class="mt-1 text-xs text-slate-500">Verify double-entry journals</p>
                                </a>
                            </div>
                        </section>
                        <section class="rounded-2xl bg-slate-900 p-6 text-white shadow-sm">
                            <p class="text-sm font-medium text-sky-300">Branch access</p>
                            <h2 class="mt-2 text-xl font-bold">{{ $currentUser->branch->name }}</h2>
                            <p class="mt-3 text-sm leading-6 text-slate-300">Semua transaksi operasional dan laporan pada workspace ini mengikuti branch yang sedang aktif.</p>
                        </section>
                    </div>
                </section>

                <section x-show="active !== 'overview'" x-transition class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                        <div>
                            <p class="text-sm font-medium text-amber-600" x-text="selectedModule.group"></p>
                            <h2 class="mt-1 text-2xl font-bold text-slate-950" x-text="selectedModule.name"></h2>
                            <p class="mt-3 max-w-2xl text-slate-500" x-text="selectedModule.description"></p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-bold" :class="selectedModule.status === 'ready' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'" x-text="selectedModule.status === 'ready' ? 'Active module' : 'Planned module'"></span>
                    </div>
                    <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <template x-for="action in selectedModule.actions" :key="action">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4">
                                <p class="text-sm font-semibold text-slate-800" x-text="action"></p>
                                <p class="mt-1 text-xs text-slate-500">Workflow siap dikembangkan dengan kontrol branch aktif.</p>
                            </div>
                        </template>
                    </div>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a x-show="selectedModule.id === 'inventory'" href="/inventory" class="rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-sky-700">Open inventory</a>
                        <a x-show="selectedModule.id === 'sales'" href="/pos" class="rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-sky-700">Open POS</a>
                        <a x-show="selectedModule.id === 'reports'" href="/reports/journal" class="rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-sky-700">Open ledger</a>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <!-- Universal Transaction Viewer Modal -->
    <div x-show="showModal" 
         x-cloak 
         class="fixed inset-0 z-50 overflow-y-auto" 
         aria-labelledby="modal-title" 
         role="dialog" 
         aria-modal="true"
         style="display: none;">
        <!-- Backdrop -->
        <div x-show="showModal"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" 
             @click="showModal = false"></div>

        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <!-- Modal Panel -->
            <div x-show="showModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 @keydown.escape.window="showModal = false"
                 class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-3xl border border-slate-200">
                
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-100 text-sky-700 shadow-xs">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </span>
                        <div>
                            <h3 class="text-base font-bold text-slate-900" id="modal-title">Rincian Transaksi</h3>
                            <p class="text-xs text-slate-500">Universal Transaction Viewer</p>
                        </div>
                    </div>
                    <button type="button" 
                            @click="showModal = false" 
                            class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition">
                        <span class="sr-only">Tutup</span>
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Body Content injected via HTML partial -->
                <div class="p-6">
                    <div x-html="transactionHtml"></div>
                </div>

                <!-- Modal Footer -->
                <div class="border-t border-slate-100 bg-slate-50 px-6 py-3.5 flex justify-end">
                    <button type="button" 
                            @click="showModal = false" 
                            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 focus:outline-none transition">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function backoffice() {
            const modules = [
                { id: 'purchases', group: 'Purchases', name: 'Pembelian', status: 'planned', description: 'Kelola hutang usaha, pembayaran hutang, dan retur pembelian dari satu alur kerja.', actions: ['Hutang usaha', 'Pembayaran hutang usaha', 'Retur pembelian'] },
                { id: 'sales', group: 'Sales', name: 'Penjualan', status: 'ready', description: 'Jalankan penjualan dari POS dan siapkan alur piutang serta retur penjualan.', actions: ['Piutang usaha', 'Pembayaran piutang usaha', 'Retur penjualan'] },
                { id: 'cash', group: 'Treasury', name: 'Kas dan bank', status: 'planned', description: 'Pantau arus kas masuk, kas keluar, kas besar, kas kecil, dan kas bank.', actions: ['Kas masuk', 'Kas keluar', 'Kas besar', 'Kas kecil', 'Kas bank'] },
                { id: 'assets', group: 'Assets', name: 'Harta tetap', status: 'planned', description: 'Catat total aset, umur manfaat, dan akumulasi penyusutan secara terkontrol.', actions: ['Daftar aset tetap', 'Penyusutan periodik', 'Akumulasi penyusutan'] },
                { id: 'inventory', group: 'Inventory', name: 'Persediaan', status: 'ready', description: 'Kelola stok branch dan siapkan klasifikasi produk berdasarkan kategori serta status pajak.', actions: ['Pupuk', 'Insektisida', 'Pestisida', 'Fungisida', 'Pakan', 'Penyesuaian persediaan'] },
                { id: 'warehouses', group: 'Inventory', name: 'Multi gudang', status: 'planned', description: 'Pindahkan persediaan antar gudang dengan jejak transfer yang jelas.', actions: ['Daftar gudang', 'Transfer persediaan antar gudang', 'Riwayat transfer'] },
                { id: 'pricing', group: 'Commercial', name: 'Multi price', status: 'planned', description: 'Sediakan harga berbeda untuk retailer dan petani dalam satu katalog produk.', actions: ['Harga retailer', 'Harga petani', 'Riwayat perubahan harga'] },
                { id: 'reports', group: 'Reports', name: 'Laporan', status: 'ready', description: 'Akses laporan persediaan, laba rugi, neraca, hutang, piutang, dan kas keluar.', actions: ['Persediaan per gudang', 'Laba rugi', 'Neraca', 'Hutang', 'Piutang', 'Kas keluar'] },
            ];

            return {
                modules,
                active: 'overview',
                mobileMenu: true,
                showModal: false,
                transactionHtml: '',
                isLoading: false,
                async loadTransaction(ref) {
                    if (!ref) return;
                    this.isLoading = true;
                    this.showModal = true;
                    this.transactionHtml = `
                        <div class="flex flex-col items-center justify-center py-12 text-slate-500">
                            <svg class="h-8 w-8 animate-spin text-sky-600 mb-3" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <p class="text-sm font-medium">Memuat rincian transaksi...</p>
                        </div>
                    `;
                    try {
                        const res = await fetch('/backoffice/transactions/' + encodeURIComponent(ref) + '/details');
                        if (res.ok) {
                            this.transactionHtml = await res.text();
                        } else {
                            this.transactionHtml = `
                                <div class="p-6 text-center text-rose-600">
                                    <p class="font-bold">Gagal memuat rincian transaksi</p>
                                    <p class="text-xs mt-1 text-slate-500">Kode respons server: ${res.status}</p>
                                </div>
                            `;
                        }
                    } catch (err) {
                        this.transactionHtml = `
                            <div class="p-6 text-center text-rose-600">
                                <p class="font-bold">Terjadi kesalahan jaringan</p>
                                <p class="text-xs mt-1 text-slate-500">Tidak dapat terhubung ke server saat memuat rincian transaksi.</p>
                            </div>
                        `;
                    } finally {
                        this.isLoading = false;
                    }
                },
                stats: [
                    { label: '{{ $isMaster ? 'Branch terpantau' : 'Produk aktif' }}', value: '{{ $isMaster ? $stats['branches'] : $stats['products'] }}', caption: '{{ $isMaster ? 'Pusat dan cabang' : 'Pada branch aktif' }}' },
                    { label: 'Total stok', value: '{{ number_format($stats['stock']) }}', caption: '{{ $isMaster ? 'Seluruh branch' : 'Unit tersedia' }}' },
                    { label: 'Jurnal', value: '{{ $stats['journals'] }}', caption: '{{ $isMaster ? 'Lintas branch' : 'Transaksi tercatat' }}' },
                    { label: 'Penjualan POS', value: '{{ $stats['sales'] }}', caption: 'Transaksi otomatis' },
                ],
                get selectedModule() {
                    return this.modules.find((module) => module.id === this.active) || this.modules[0];
                },
                get pageTitle() {
                    return this.active === 'overview' ? 'Overview' : this.selectedModule.name;
                },
                select(module) {
                    this.active = module;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },
            };
        }
    </script>
</body>
</html>