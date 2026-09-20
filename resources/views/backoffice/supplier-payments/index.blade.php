<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pembayaran Supplier | Maju Bersama ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <!-- Header Utama -->
    <header class="border-b border-slate-800 bg-slate-950 text-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
            <div class="flex items-center gap-4">
                <a href="/backoffice" class="block">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-400">Maju Bersama ERP</p>
                    <h1 class="text-xl font-bold tracking-tight">Pengadaan &amp; Keuangan</h1>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <nav class="hidden items-center gap-4 text-sm text-slate-300 md:flex">
                    <a href="/pos" class="hover:text-white">POS Kasir</a>
                    <a href="/inventory" class="hover:text-white">Inventory</a>
                    <a href="{{ route('backoffice.purchase-orders.index') }}" class="hover:text-white">Purchase Order</a>
                    <a href="{{ route('backoffice.supplier-payments.index') }}" class="text-amber-400 font-semibold">Pembayaran Supplier</a>
                    <a href="/reports/accounting/ledger" class="hover:text-white">Akuntansi</a>
                    <a href="/backoffice" class="hover:text-white">Backoffice</a>
                </nav>
                <div class="rounded-full border border-sky-400/30 bg-sky-400/10 px-3 py-1 text-xs font-medium text-sky-200">
                    {{ $currentUser->branch?->name ?? 'Pusat' }} ({{ $currentUser->role }})
                </div>
                <form method="POST" action="/logout" class="hidden sm:block">
                    @csrf
                    <button type="submit" class="text-sm text-slate-300 hover:text-white">Logout</button>
                </form>
            </div>
        </div>
    </header>

    <!-- Sub-Navbar Modul Pembayaran -->
    <div class="border-b border-slate-200 bg-white shadow-xs">
        <div class="mx-auto flex max-w-7xl items-center justify-between overflow-x-auto px-6 py-2.5 lg:px-8">
            <div class="flex items-center gap-2 text-sm font-medium">
                <a href="{{ route('backoffice.supplier-payments.index') }}" class="rounded-lg bg-amber-500 px-3.5 py-2 font-bold text-slate-950 shadow-xs">
                    Riwayat Pembayaran
                </a>
                <a href="{{ route('backoffice.supplier-payments.create') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    + Entri Pembayaran Baru
                </a>
                <a href="{{ route('backoffice.purchase-orders.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Purchase Order (PO)
                </a>
                <a href="/purchases/goods-receipts" class="rounded-lg px-3.5 py-2 text-emerald-700 hover:bg-emerald-50">
                    Penerimaan Barang (GR)
                </a>
                <a href="{{ route('backoffice.suppliers.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Data Supplier
                </a>
            </div>
            <a href="{{ route('backoffice.supplier-payments.create') }}" id="btn-tambah-pembayaran" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3.5 py-2 text-xs font-bold text-amber-400 shadow-xs hover:bg-slate-800 transition-colors">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Entri Pembayaran
            </a>
        </div>
    </div>

    <main class="mx-auto max-w-7xl space-y-6 px-6 py-8 lg:px-8">
        <!-- Flash Message -->
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900 shadow-xs">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <p class="text-sm font-semibold">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        <!-- Judul Halaman -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-amber-600">Keuangan &amp; Hutang Dagang</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Riwayat Pembayaran Supplier</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Monitoring daftar transaksi pelunasan hutang kepada rekanan supplier beserta jurnal ganda akuntansinya.
                </p>
            </div>
            <div>
                <a href="{{ route('backoffice.supplier-payments.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-bold text-slate-950 shadow-sm transition hover:bg-amber-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    + Entri Pembayaran Baru
                </a>
            </div>
        </div>

        <!-- Metric Summary Cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Pembayaran</p>
                <p class="mt-2 text-2xl font-extrabold text-slate-950 font-mono">Rp {{ number_format($totalPayments, 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-400">Akumulasi sesuai filter aktif</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Jumlah Transaksi</p>
                <p class="mt-2 text-2xl font-extrabold text-indigo-600 font-mono">{{ $totalCount }}</p>
                <p class="mt-1 text-xs text-slate-400">Bukti pembayaran tercatat</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Via Transfer Bank</p>
                <p class="mt-2 text-2xl font-extrabold text-sky-600 font-mono">Rp {{ number_format($totalTransfer, 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-400">Metode transfer rekening</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Via Kas Tunai</p>
                <p class="mt-2 text-2xl font-extrabold text-emerald-600 font-mono">Rp {{ number_format($totalCash, 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-400">Metode tunai / kas operasional</p>
            </div>
        </div>

        <!-- Filter & Search Card -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
            <form method="GET" action="{{ route('backoffice.supplier-payments.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5 items-end">
                <div class="lg:col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Pencarian</label>
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Cari No. Referensi atau Nama Supplier..."
                        class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                    >
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Dari Tanggal</label>
                    <input
                        type="date"
                        name="start_date"
                        value="{{ $startDate }}"
                        class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                    >
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Sampai Tanggal</label>
                    <input
                        type="date"
                        name="end_date"
                        value="{{ $endDate }}"
                        class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                    >
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="flex-1 rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white shadow-xs hover:bg-slate-800 transition">
                        Filter
                    </button>
                    @if ($search || $startDate || $endDate || $paymentMethod)
                        <a href="{{ route('backoffice.supplier-payments.index') }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition" title="Reset filter">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </section>

        <!-- Tabel Riwayat Pembayaran -->
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wider text-slate-600">
                        <tr>
                            <th class="py-3.5 px-4 w-12 text-center">#</th>
                            <th class="py-3.5 px-4 w-32">Tanggal</th>
                            <th class="py-3.5 px-4 w-44">No. Referensi</th>
                            <th class="py-3.5 px-4 min-w-[200px]">Supplier</th>
                            <th class="py-3.5 px-4 w-36">Metode</th>
                            <th class="py-3.5 px-4 w-44">Sumber Dana</th>
                            <th class="py-3.5 px-4 text-right tabular-nums w-44">Jumlah (Rp)</th>
                            <th class="py-3.5 px-4 min-w-[180px]">Catatan / Jurnal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($payments as $index => $payment)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-3.5 px-4 text-center text-xs font-bold text-slate-400 font-mono">
                                    {{ $payments->firstItem() + $index }}
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap text-slate-700 font-medium">
                                    {{ $payment->payment_date ? $payment->payment_date->format('d/m/Y') : '-' }}
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap font-mono text-xs font-bold text-slate-900">
                                    {{ $payment->reference_number }}
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-950">{{ $payment->supplier?->name ?? 'Supplier Dihapus' }}</div>
                                    @if ($payment->supplier?->phone)
                                        <div class="text-[11px] text-slate-400">{{ $payment->supplier->phone }}</div>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    @php
                                        $method = strtolower($payment->payment_method);
                                    @endphp
                                    @if ($method === 'transfer')
                                        <span class="inline-flex items-center rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-bold text-sky-800">
                                            Transfer Bank
                                        </span>
                                    @elseif ($method === 'cash')
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800">
                                            Tunai (Kas)
                                        </span>
                                    @elseif ($method === 'giro')
                                        <span class="inline-flex items-center rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-bold text-purple-800">
                                            Giro / Cek
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-800">
                                            {{ ucfirst($payment->payment_method) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap text-xs text-slate-600">
                                    <span class="font-semibold text-slate-800">{{ $payment->chartOfAccount?->name ?? '-' }}</span>
                                    <span class="block text-[10px] text-slate-400 font-mono">{{ $payment->chartOfAccount?->code }}</span>
                                </td>
                                <td class="py-3.5 px-4 text-right tabular-nums whitespace-nowrap font-mono font-extrabold text-slate-950">
                                    Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}
                                </td>
                                <td class="py-3.5 px-4 text-xs text-slate-500">
                                    <div>{{ $payment->notes ?: '-' }}</div>
                                    @if ($payment->journal_header_id)
                                        <span class="inline-flex items-center gap-1 mt-0.5 text-[10px] font-semibold text-emerald-700">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                            Jurnal #{{ $payment->journal_header_id }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-12 text-center">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-50 text-amber-500">
                                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                    </div>
                                    <p class="mt-3 text-sm font-bold text-slate-900">Belum Ada Transaksi Pembayaran</p>
                                    <p class="mt-1 text-xs text-slate-500">Catat pengeluaran pelunasan hutang pembelian pertama Anda ke supplier.</p>
                                    <div class="mt-4">
                                        <a href="{{ route('backoffice.supplier-payments.create') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-amber-500 px-4 py-2 text-xs font-bold text-slate-950 shadow-xs hover:bg-amber-400">
                                            + Catat Pembayaran Baru
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer -->
            @if ($payments->hasPages())
                <div class="border-t border-slate-200 bg-slate-50/50 p-4">
                    {{ $payments->links() }}
                </div>
            @endif
        </section>
    </main>
</body>
</html>
