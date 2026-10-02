<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Hutang Distributor (Accounts Payable) | Maju Bersama</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono-code { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body x-data="payablesApp()" class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <!-- Top Navigation Header -->
    <header class="sticky top-0 z-30 border-b border-slate-800 bg-slate-950 text-white shadow-md">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500 font-extrabold text-slate-950 shadow-sm">
                    MB
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-amber-400">Maju Bersama ERP</p>
                        <span class="rounded bg-amber-500/20 px-1.5 py-0.2 text-[10px] font-semibold text-amber-300">Pusat</span>
                    </div>
                    <h1 class="text-lg font-bold tracking-tight text-white sm:text-xl">Hutang Distributor (Accounts Payable)</h1>
                </div>
            </div>

            <div class="flex items-center gap-3 sm:gap-6">
                <nav class="hidden items-center gap-4 text-sm font-medium text-slate-300 lg:flex">
                    <a href="/pos" class="hover:text-white transition">Kasir (POS)</a>
                    <a href="/inventory" class="hover:text-white transition">Persediaan</a>
                    <a href="/inventory/transfer" class="hover:text-white transition">Transfer Stok</a>
                    <a href="/purchases/goods-receipts" class="hover:text-white transition">Penerimaan Barang</a>
                    <a href="/purchases/payables" class="font-bold text-amber-400 border-b-2 border-amber-400 pb-0.5">Hutang Distributor</a>
                    <a href="/reports/accounting/ledger" class="hover:text-white transition">Buku Besar</a>
                    <a href="/backoffice" class="hover:text-white transition">Panel Admin</a>
                </nav>

                <div class="flex items-center gap-3">
                    <div class="hidden text-right sm:block">
                        <p class="text-xs font-semibold text-white">{{ auth()->user()->name ?? 'Admin Pusat' }}</p>
                        <p class="text-[10px] text-slate-400">{{ auth()->user()->branch->name ?? 'Kantor Pusat' }}</p>
                    </div>
                    <form method="POST" action="/logout">
                        @csrf
                        <button type="submit" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-rose-900/40 hover:text-rose-300 hover:border-rose-500/40 transition">
                            Keluar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 space-y-8">
        <!-- Breadcrumb & Header Title -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-1">
                    <a href="/dashboard" class="hover:text-slate-800">Dashboard</a>
                    <span>&rsaquo;</span>
                    <a href="/purchases/goods-receipts" class="hover:text-slate-800">Pembelian</a>
                    <span>&rsaquo;</span>
                    <span class="text-slate-800 font-semibold">Hutang Distributor (AP)</span>
                </nav>
                <h2 class="text-2xl font-extrabold tracking-tight text-slate-950 sm:text-3xl">Pusat Kontrol Hutang Dagang</h2>
                <p class="mt-1 text-sm text-slate-600">Pantau jatuh tempo faktur, eksekusi pembayaran cicilan fleksibel, dan integrasi otomatis ke buku jurnal akuntansi.</p>
            </div>

            <div class="flex items-center gap-3">
                <button @click="openCreatePurchaseModal()" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-slate-800 transition active:scale-95">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                    </svg>
                    + Buat Faktur Pembelian
                </button>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 shadow-xs flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        <!-- 3 SUMMARY STATS CARDS -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <!-- Card 1: Total Hutang Aktif -->
            <div class="relative overflow-hidden rounded-2xl border border-rose-100 bg-white p-6 shadow-xs hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-rose-600">Total Hutang Aktif</span>
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-4">
                    <h3 class="text-2xl font-black tracking-tight text-slate-900 font-mono-code">Rp {{ number_format($totalActiveDebt, 0, ',', '.') }}</h3>
                    <p class="mt-1 text-xs text-slate-500">Akumulasi sisa tagihan status Belum Lunas &amp; Sebagian</p>
                </div>
                <div class="absolute inset-x-0 bottom-0 h-1 bg-gradient-to-r from-rose-500 to-rose-400"></div>
            </div>

            <!-- Card 2: Jatuh Tempo Minggu Ini -->
            <div class="relative overflow-hidden rounded-2xl border border-amber-100 bg-white p-6 shadow-xs hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-amber-600">Jatuh Tempo Minggu Ini</span>
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-4">
                    <h3 class="text-2xl font-black tracking-tight text-slate-900 font-mono-code">Rp {{ number_format($totalDueThisWeek, 0, ',', '.') }}</h3>
                    <p class="mt-1 text-xs text-slate-500">Perlu diprioritaskan dalam 7 hari ke depan</p>
                </div>
                <div class="absolute inset-x-0 bottom-0 h-1 bg-gradient-to-r from-amber-500 to-amber-400"></div>
            </div>

            <!-- Card 3: Total Lunas Bulan Ini -->
            <div class="relative overflow-hidden rounded-2xl border border-emerald-100 bg-white p-6 shadow-xs hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-600">Total Lunas Bulan Ini</span>
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-4">
                    <h3 class="text-2xl font-black tracking-tight text-slate-900 font-mono-code">Rp {{ number_format($totalPaidThisMonth, 0, ',', '.') }}</h3>
                    <p class="mt-1 text-xs text-slate-500">Hutang distributor yang telah berhasil diselesaikan</p>
                </div>
                <div class="absolute inset-x-0 bottom-0 h-1 bg-gradient-to-r from-emerald-500 to-emerald-400"></div>
            </div>
        </div>

        <!-- SEARCH & FILTER BAR -->
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <form method="GET" action="{{ route('purchases.payables') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="relative flex-1">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari no faktur atau nama distributor..." class="w-full rounded-xl border border-slate-300 bg-slate-50/50 py-2 pl-9 pr-4 text-sm font-medium text-slate-800 placeholder-slate-400 outline-none focus:border-amber-500 focus:bg-white focus:ring-2 focus:ring-amber-200 transition">
                    </div>

                    <div class="w-full sm:w-48">
                        <select name="status" onchange="this.form.submit()" class="w-full rounded-xl border border-slate-300 bg-slate-50/50 py-2 px-3 text-sm font-medium text-slate-800 outline-none focus:border-amber-500 focus:bg-white focus:ring-2 focus:ring-amber-200 transition">
                            <option value="">Semua Status</option>
                            <option value="unpaid" {{ request('status') === 'unpaid' ? 'selected' : '' }}>Belum Lunas (Unpaid)</option>
                            <option value="partial" {{ request('status') === 'partial' ? 'selected' : '' }}>Sebagian (Partial)</option>
                            <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Lunas (Paid)</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900 transition">
                        Filter
                    </button>
                    @if (request('search') || request('status'))
                        <a href="{{ route('purchases.payables') }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 transition">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- MAIN DATA TABLE -->
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th scope="col" class="px-6 py-4">No. Faktur</th>
                            <th scope="col" class="px-6 py-4">Distributor / Supplier</th>
                            <th scope="col" class="px-6 py-4">Jatuh Tempo</th>
                            <th scope="col" class="px-6 py-4 text-right">Total Tagihan</th>
                            <th scope="col" class="px-6 py-4 text-right">Sisa Hutang</th>
                            <th scope="col" class="px-6 py-4 text-center">Status</th>
                            <th scope="col" class="px-6 py-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white text-sm">
                        @forelse ($purchases as $item)
                            @php
                                $isOverdue = $item->due_date && $item->due_date->isPast() && $item->status !== 'paid';
                                $isDueSoon = $item->due_date && !$item->due_date->isPast() && $item->due_date->diffInDays(now()) <= 3 && $item->status !== 'paid';
                            @endphp
                            <tr class="transition-colors hover:bg-slate-50/75">
                                <td class="whitespace-nowrap px-6 py-4 font-mono-code font-bold text-slate-900">
                                    <span class="text-sky-700">{{ $item->invoice_number }}</span>
                                    @if ($item->notes)
                                        <p class="text-[11px] font-normal text-slate-400 truncate max-w-xs">{{ $item->notes }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="font-semibold text-slate-900">{{ $item->distributor->name ?? 'Distributor Umum' }}</div>
                                    <div class="text-xs text-slate-400">{{ $item->branch->name ?? 'Pusat' }}</div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium">
                                    @if ($item->due_date)
                                        <div class="flex items-center gap-1.5">
                                            @if ($isOverdue)
                                                <span class="inline-flex h-2 w-2 rounded-full bg-rose-600 animate-pulse"></span>
                                                <span class="font-bold text-rose-600">{{ $item->due_date->format('d/m/Y') }} (Lewat)</span>
                                            @elseif ($isDueSoon)
                                                <span class="inline-flex h-2 w-2 rounded-full bg-amber-500"></span>
                                                <span class="font-semibold text-amber-700">{{ $item->due_date->format('d/m/Y') }} (Segera)</span>
                                            @else
                                                <span class="text-slate-600">{{ $item->due_date->format('d/m/Y') }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right font-mono-code font-semibold text-slate-700">
                                    Rp {{ number_format((float) $item->total_amount, 0, ',', '.') }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right font-mono-code font-bold {{ $item->remaining_debt > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                                    Rp {{ number_format((float) $item->remaining_debt, 0, ',', '.') }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-center">
                                    @if ($item->status === 'paid')
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                            LUNAS
                                        </span>
                                    @elseif ($item->status === 'partial')
                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700 ring-1 ring-inset ring-amber-600/20">
                                            SEBAGIAN
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-rose-50 px-3 py-1 text-xs font-bold text-rose-700 ring-1 ring-inset ring-rose-600/20">
                                            BELUM LUNAS
                                        </span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-center">
                                    <div class="inline-flex items-center gap-1.5">
                                        @if ($item->status !== 'paid')
                                            <button @click="openPaymentModal({{ json_encode($item) }})" class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white shadow-xs hover:bg-emerald-700 transition active:scale-95">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                                </svg>
                                                Bayar Cicilan
                                            </button>
                                        @endif
                                        <button @click="openHistoryModal({{ json_encode($item) }})" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition">
                                            <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            Riwayat
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-slate-500">
                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 mb-3">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <p class="font-semibold text-slate-700">Belum ada faktur hutang distributor</p>
                                    <p class="text-xs text-slate-400 mt-1">Buat faktur pembelian baru untuk mulai mencatat hutang dagang.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($purchases->hasPages())
                <div class="border-t border-slate-200 bg-slate-50 px-6 py-4">
                    {{ $purchases->links() }}
                </div>
            @endif
        </section>

        <!-- MODAL 1: BAYAR CICILAN -->
        <div x-show="showPaymentModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="showPaymentModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity" @click="showPaymentModal = false"></div>
                <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                <div x-show="showPaymentModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="relative inline-block transform overflow-hidden rounded-2xl bg-white text-left align-bottom shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:align-middle">
                    <form @submit.prevent="submitPayment()">
                        <div class="border-b border-slate-100 bg-slate-50/75 px-6 py-4 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 font-bold">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-slate-900">Form Pembayaran Cicilan</h3>
                                    <p class="text-xs text-slate-500 font-mono-code" x-text="'Faktur: ' + (activePurchase ? activePurchase.invoice_number : '')"></p>
                                </div>
                            </div>
                            <button type="button" @click="showPaymentModal = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Card Info Sisa Hutang -->
                        <div class="p-6 space-y-5">
                            <div class="rounded-xl border border-rose-100 bg-rose-50/50 p-4">
                                <div class="flex items-center justify-between text-xs text-slate-600 mb-1">
                                    <span>Total Tagihan:</span>
                                    <span class="font-bold text-slate-900 font-mono-code" x-text="formatRupiah(activePurchase ? activePurchase.total_amount : 0)"></span>
                                </div>
                                <div class="flex items-center justify-between text-xs text-slate-600 mb-1">
                                    <span>Sudah Dibayar:</span>
                                    <span class="font-bold text-emerald-700 font-mono-code" x-text="formatRupiah(activePurchase ? activePurchase.paid_amount : 0)"></span>
                                </div>
                                <div class="border-t border-rose-200/60 pt-2 mt-2 flex items-center justify-between text-sm">
                                    <span class="font-bold text-rose-800">Sisa Hutang:</span>
                                    <span class="font-black text-rose-700 font-mono-code text-base" x-text="formatRupiah(activePurchase ? activePurchase.remaining_debt : 0)"></span>
                                </div>
                            </div>

                            <!-- Form Inputs -->
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Tanggal Pembayaran</label>
                                    <input type="date" x-model="paymentForm.payment_date" required class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200">
                                </div>

                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Nominal Pembayaran (Rp)</label>
                                        <button type="button" @click="setFullPayment()" class="text-xs font-bold text-emerald-600 hover:text-emerald-700 underline">Lunasi Semua</button>
                                    </div>
                                    <div class="relative">
                                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm font-bold text-slate-400">Rp</span>
                                        <input type="number" step="0.01" min="0.01" :max="activePurchase ? activePurchase.remaining_debt : 999999999" x-model="paymentForm.amount" required placeholder="0" class="w-full rounded-xl border border-slate-300 py-2 pl-10 pr-4 text-base font-bold font-mono-code text-slate-900 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200">
                                    </div>
                                    <p class="text-[11px] text-slate-400 mt-1" x-text="'Maksimal bayar: ' + formatRupiah(activePurchase ? activePurchase.remaining_debt : 0)"></p>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Metode Pembayaran (Akun Kas/Bank)</label>
                                    <select x-model="paymentForm.payment_method" required class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-semibold text-slate-900 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200">
                                        <option value="cash">Kas Tunai (Akun 1110)</option>
                                        <option value="bank">Transfer Bank (Akun 1120)</option>
                                        <option value="transfer">Giro / Kliring</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">No. Referensi / Bukti Transfer</label>
                                    <input type="text" x-model="paymentForm.reference_number" placeholder="Contoh: TRF-BCA-88291 / Bilyet Giro" class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Catatan</label>
                                    <textarea x-model="paymentForm.notes" rows="2" placeholder="Catatan opsional pembayaran termin..." class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-slate-100 bg-slate-50 px-6 py-4 flex items-center justify-end gap-2">
                            <button type="button" @click="showPaymentModal = false" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 transition">
                                Batal
                            </button>
                            <button type="submit" :disabled="loading" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2 text-sm font-bold text-white shadow-sm hover:bg-emerald-700 transition disabled:opacity-50">
                                <span x-show="!loading">Konfirmasi Pembayaran</span>
                                <span x-show="loading" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL 2: RIWAYAT PEMBAYARAN -->
        <div x-show="showHistoryModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="showHistoryModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity" @click="showHistoryModal = false"></div>
                <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                <div x-show="showHistoryModal" class="relative inline-block transform overflow-hidden rounded-2xl bg-white text-left align-bottom shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-2xl sm:align-middle">
                    <div class="border-b border-slate-100 bg-slate-50/75 px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-100 text-sky-700 font-bold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Riwayat Pembayaran Cicilan</h3>
                                <p class="text-xs text-slate-500 font-mono-code" x-text="'No Faktur: ' + (activePurchase ? activePurchase.invoice_number : '')"></p>
                            </div>
                        </div>
                        <button type="button" @click="showHistoryModal = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="p-6 space-y-4">
                        <template x-if="activePurchase && activePurchase.payments && activePurchase.payments.length > 0">
                            <div class="overflow-x-auto rounded-xl border border-slate-200">
                                <table class="min-w-full divide-y divide-slate-200 text-xs">
                                    <thead class="bg-slate-50 font-bold uppercase text-slate-500">
                                        <tr>
                                            <th class="px-4 py-3 text-left">No. Referensi</th>
                                            <th class="px-4 py-3 text-left">Tanggal</th>
                                            <th class="px-4 py-3 text-left">Metode</th>
                                            <th class="px-4 py-3 text-right">Nominal</th>
                                            <th class="px-4 py-3 text-left">Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                                        <template x-for="p in activePurchase.payments" :key="p.id">
                                            <tr class="hover:bg-slate-50">
                                                <td class="px-4 py-3 font-mono-code font-bold text-sky-700" x-text="p.reference_number || ('PAY-' + p.id)"></td>
                                                <td class="px-4 py-3 text-slate-600" x-text="p.payment_date"></td>
                                                <td class="px-4 py-3 uppercase text-slate-700">
                                                    <span class="rounded bg-slate-100 px-2 py-0.5 font-bold" x-text="p.payment_method"></span>
                                                </td>
                                                <td class="px-4 py-3 text-right font-mono-code font-bold text-emerald-700" x-text="formatRupiah(p.amount)"></td>
                                                <td class="px-4 py-3 text-slate-500" x-text="p.notes || '-'"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <template x-if="!activePurchase || !activePurchase.payments || activePurchase.payments.length === 0">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-8 text-center">
                                <svg class="mx-auto h-8 w-8 text-slate-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p class="text-sm font-semibold text-slate-700">Belum ada pembayaran yang dicatat</p>
                                <p class="text-xs text-slate-400 mt-0.5">Klik tombol "Bayar Cicilan" untuk melakukan pembayaran pertama.</p>
                            </div>
                        </template>
                    </div>

                    <div class="border-t border-slate-100 bg-slate-50 px-6 py-3 flex justify-end">
                        <button type="button" @click="showHistoryModal = false" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 transition">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL 3: BUAT FAKTUR HUTANG DISTRIBUTOR BARU -->
        <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="showCreateModal" class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity" @click="showCreateModal = false"></div>
                <span class="hidden sm:inline-block sm:h-screen sm:align-middle">&#8203;</span>

                <div x-show="showCreateModal" class="relative inline-block transform overflow-hidden rounded-2xl bg-white text-left align-bottom shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:align-middle">
                    <form @submit.prevent="submitCreatePurchase()">
                        <div class="border-b border-slate-100 bg-slate-50/75 px-6 py-4 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-900 text-white font-bold">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-slate-900">Buat Faktur Pembelian (Hutang AP)</h3>
                                    <p class="text-xs text-slate-500">Mencatat hutang dagang baru ke distributor</p>
                                </div>
                            </div>
                            <button type="button" @click="showCreateModal = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="p-6 space-y-4">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">No. Faktur / Invoice</label>
                                <input type="text" x-model="createForm.invoice_number" required placeholder="Contoh: INV-DIST-2026-001" class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-mono-code text-slate-900 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-200">
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Distributor / Supplier</label>
                                <select x-model="createForm.distributor_id" class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-medium text-slate-900 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-200">
                                    <option value="">-- Pilih Distributor (Opsional) --</option>
                                    @foreach ($distributors as $dist)
                                        <option value="{{ $dist->id }}">{{ $dist->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Total Tagihan (Rp)</label>
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm font-bold text-slate-400">Rp</span>
                                    <input type="number" step="0.01" min="0.01" x-model="createForm.total_amount" required placeholder="0" class="w-full rounded-xl border border-slate-300 py-2 pl-10 pr-4 text-base font-bold font-mono-code text-slate-900 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-200">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Tanggal Jatuh Tempo</label>
                                <input type="date" x-model="createForm.due_date" class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-200">
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Catatan / Keterangan Barang</label>
                                <textarea x-model="createForm.notes" rows="2" placeholder="Keterangan pasokan barang dari distributor..." class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-200"></textarea>
                            </div>
                        </div>

                        <div class="border-t border-slate-100 bg-slate-50 px-6 py-4 flex items-center justify-end gap-2">
                            <button type="button" @click="showCreateModal = false" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 transition">
                                Batal
                            </button>
                            <button type="submit" :disabled="loading" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-2 text-sm font-bold text-white shadow-sm hover:bg-slate-800 transition disabled:opacity-50">
                                <span x-show="!loading">Simpan Faktur</span>
                                <span x-show="loading" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        function payablesApp() {
            return {
                showPaymentModal: false,
                showHistoryModal: false,
                showCreateModal: false,
                activePurchase: null,
                loading: false,
                paymentForm: {
                    payment_date: new Date().toISOString().split('T')[0],
                    amount: '',
                    payment_method: 'cash',
                    reference_number: '',
                    notes: ''
                },
                createForm: {
                    invoice_number: 'INV-DIST-' + Math.floor(100000 + Math.random() * 900000),
                    distributor_id: '',
                    total_amount: '',
                    due_date: new Date(Date.now() + 30*24*60*60*1000).toISOString().split('T')[0],
                    notes: ''
                },

                openPaymentModal(item) {
                    this.activePurchase = item;
                    this.paymentForm.amount = item.remaining_debt;
                    this.paymentForm.reference_number = 'PAY-' + item.invoice_number.replace('INV-', '') + '-' + Math.floor(10 + Math.random() * 90);
                    this.paymentForm.notes = 'Cicilan hutang faktur ' + item.invoice_number;
                    this.showPaymentModal = true;
                },

                openHistoryModal(item) {
                    this.activePurchase = item;
                    this.showHistoryModal = true;
                },

                openCreatePurchaseModal() {
                    this.createForm.invoice_number = 'INV-DIST-' + Math.floor(100000 + Math.random() * 900000);
                    this.showCreateModal = true;
                },

                setFullPayment() {
                    if (this.activePurchase) {
                        this.paymentForm.amount = this.activePurchase.remaining_debt;
                    }
                },

                formatRupiah(val) {
                    const num = parseFloat(val) || 0;
                    return 'Rp ' + num.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
                },

                async submitPayment() {
                    if (!this.activePurchase) return;
                    this.loading = true;

                    try {
                        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
                        const res = await fetch(`/api/purchases/${this.activePurchase.id}/payments`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': tokenMeta ? tokenMeta.getAttribute('content') : ''
                            },
                            body: JSON.stringify(this.paymentForm)
                        });

                        const data = await res.json();
                        if (res.ok) {
                            alert('Pembayaran cicilan berhasil disimpan & jurnal akuntansi otomatis dicatat!');
                            window.location.reload();
                        } else {
                            alert('Gagal: ' + (data.message || 'Terjadi kesalahan saat memproses pembayaran.'));
                        }
                    } catch (e) {
                        console.error(e);
                        alert('Terjadi kesalahan jaringan.');
                    } finally {
                        this.loading = false;
                    }
                },

                async submitCreatePurchase() {
                    this.loading = true;
                    try {
                        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
                        const res = await fetch('/api/purchases', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': tokenMeta ? tokenMeta.getAttribute('content') : ''
                            },
                            body: JSON.stringify(this.createForm)
                        });

                        const data = await res.json();
                        if (res.ok) {
                            alert('Faktur pembelian distributor berhasil dibuat!');
                            window.location.reload();
                        } else {
                            alert('Gagal: ' + (data.message || 'Terjadi kesalahan validasi.'));
                        }
                    } catch (e) {
                        console.error(e);
                        alert('Terjadi kesalahan jaringan.');
                    } finally {
                        this.loading = false;
                    }
                }
            }
        }
    </script>
</body>
</html>
