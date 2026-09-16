<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penerimaan Barang (Goods Receipt) | Maju Bersama ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <!-- Header Utama -->
    <header class="border-b border-slate-800 bg-slate-950 text-white no-print">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
            <div class="flex items-center gap-4">
                <a href="/backoffice" class="block">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-400">Maju Bersama ERP</p>
                    <h1 class="text-xl font-bold tracking-tight">Pengadaan &amp; Persediaan</h1>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <nav class="hidden items-center gap-4 text-sm text-slate-300 md:flex">
                    <a href="/pos" class="hover:text-white">POS</a>
                    <a href="/inventory" class="hover:text-white">Inventory</a>
                    <a href="/inventory/transfer" class="hover:text-white">Transfer</a>
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

    <!-- Sub-Navbar Modul Pengadaan -->
    <div class="border-b border-slate-200 bg-white no-print">
        <div class="mx-auto flex max-w-7xl items-center justify-between overflow-x-auto px-6 py-2.5 lg:px-8">
            <div class="flex items-center gap-2 text-sm font-medium">
                <a href="/purchases/goods-receipts" class="rounded-lg bg-sky-600 px-3.5 py-2 font-semibold text-white shadow-sm">
                    Riwayat Penerimaan
                </a>
                <a href="/purchases/goods-receipts/create" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    + Penerimaan Baru
                </a>
                <a href="/inventory" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Katalog Stok
                </a>
                <a href="/inventory/transfer" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Transfer Antar Cabang
                </a>
            </div>
            <a href="/purchases/goods-receipts/create" class="flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Input Barang Masuk
            </a>
        </div>
    </div>

    <main class="mx-auto max-w-7xl space-y-6 px-6 py-8 lg:px-8">
        <!-- Flash Message -->
        @if (session('success'))
            <div x-data="{ show: true }" x-show="show" class="flex items-center justify-between rounded-2xl border border-emerald-300 bg-emerald-50 p-4 text-emerald-900 shadow-sm transition">
                <div class="flex items-center gap-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-600 text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-bold">Operasi Berhasil</p>
                        <p class="text-xs text-emerald-800">{{ session('success') }}</p>
                    </div>
                </div>
                <button type="button" @click="show = false" class="text-emerald-700 hover:text-emerald-900">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endif

        <!-- Judul & Breadcrumb -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Modul Pembelian &amp; Gudang</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Penerimaan Barang (Goods Receipt)</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Penerimaan persediaan masuk ke Gudang Pusat dengan pembaruan stok otomatis dan pencatatan jurnal akuntansi.
                </p>
            </div>
            <div>
                <a href="/purchases/goods-receipts/create" class="inline-flex items-center gap-2 rounded-xl bg-sky-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-sky-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Catat Penerimaan Baru
                </a>
            </div>
        </div>

        <!-- Kartu Metrik Ringkas -->
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Transaksi</p>
                <p class="mt-2 text-2xl font-bold font-mono text-slate-950">{{ $totalReceipts }} Nota</p>
                <p class="mt-1 text-xs text-slate-500">Transaksi penerimaan terdata</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Nilai Pembelian</p>
                <p class="mt-2 text-2xl font-bold font-mono text-sky-900">Rp {{ number_format($totalAmount, 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">Akumulasi nilai persediaan masuk</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Pembayaran Tunai (Kas)</p>
                <p class="mt-2 text-2xl font-bold font-mono text-emerald-800">Rp {{ number_format($totalCash, 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">Pengeluaran kas tunai langsung</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Pembayaran Tempo (Hutang)</p>
                <p class="mt-2 text-2xl font-bold font-mono text-amber-800">Rp {{ number_format($totalCredit, 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">Kewajiban hutang dagang supplier</p>
            </div>
        </section>

        <!-- Filter & Search Section -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm no-print">
            <form method="GET" action="/purchases/goods-receipts" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Cari Referensi / Supplier</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="No. Nota atau Nama Supplier..." class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Metode Pembayaran</label>
                    <select name="payment_type" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                        <option value="">Semua Metode</option>
                        <option value="cash" {{ $paymentType === 'cash' ? 'selected' : '' }}>Tunai (Kas)</option>
                        <option value="credit" {{ $paymentType === 'credit' ? 'selected' : '' }}>Tempo (Hutang Dagang)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Dari Tanggal</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Sampai Tanggal</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="w-full rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                        Filter
                    </button>
                    <a href="/purchases/goods-receipts" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <!-- Tabel Riwayat Penerimaan -->
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-900 text-xs font-semibold uppercase tracking-wider text-white">
                        <tr>
                            <th class="px-5 py-3.5">Tanggal</th>
                            <th class="px-5 py-3.5">No. Referensi</th>
                            <th class="px-5 py-3.5">Supplier</th>
                            <th class="px-5 py-3.5 text-right">Total Nilai</th>
                            <th class="px-5 py-3.5 text-center">Pembayaran</th>
                            <th class="px-5 py-3.5 text-center">Jurnal Otomatis</th>
                            <th class="px-5 py-3.5 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($receipts as $receipt)
                            <tr class="transition hover:bg-slate-50">
                                <!-- Tanggal -->
                                <td class="whitespace-nowrap px-5 py-4 font-mono text-xs text-slate-700">
                                    {{ \Carbon\Carbon::parse($receipt->date)->format('d/m/Y') }}
                                </td>

                                <!-- No. Referensi -->
                                <td class="whitespace-nowrap px-5 py-4 font-mono text-xs font-bold text-sky-700">
                                    <a href="/purchases/goods-receipts/{{ $receipt->id }}" class="hover:underline">
                                        {{ $receipt->reference_number }}
                                    </a>
                                </td>

                                <!-- Supplier -->
                                <td class="px-5 py-4 text-slate-800 font-medium">
                                    {{ $receipt->supplier_name ?: '—' }}
                                    @if ($receipt->notes)
                                        <p class="text-[11px] text-slate-400 truncate max-w-xs">{{ $receipt->notes }}</p>
                                    @endif
                                </td>

                                <!-- Total Nominal -->
                                <td class="whitespace-nowrap px-5 py-4 text-right font-mono text-sm font-bold text-slate-950">
                                    Rp {{ number_format($receipt->total_amount, 0, ',', '.') }}
                                </td>

                                <!-- Metode Pembayaran -->
                                <td class="whitespace-nowrap px-5 py-4 text-center">
                                    @if ($receipt->payment_type === 'cash')
                                        <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-800">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            Tunai (Kas)
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-800">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                            Tempo (Hutang)
                                        </span>
                                    @endif
                                </td>

                                <!-- Jurnal Otomatis -->
                                <td class="whitespace-nowrap px-5 py-4 text-center">
                                    @if ($receipt->journalHeader)
                                        <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-1 font-mono text-[11px] font-bold text-slate-700" title="Jurnal telah terbentuk">
                                            <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            Tercatat
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>

                                <!-- Tombol Aksi -->
                                <td class="whitespace-nowrap px-5 py-4 text-center">
                                    <a href="/purchases/goods-receipts/{{ $receipt->id }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 hover:text-sky-600">
                                        <svg class="h-3.5 w-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        Detail Slip
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-slate-500">
                                    <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                    <h3 class="mt-4 text-base font-semibold text-slate-900">Belum Ada Riwayat Penerimaan Barang</h3>
                                    <p class="mt-1 text-sm text-slate-500">Catat penerimaan barang baru dari supplier untuk menambah stok Gudang Pusat.</p>
                                    <div class="mt-6">
                                        <a href="/purchases/goods-receipts/create" class="inline-flex items-center gap-2 rounded-xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-700">
                                            + Catat Penerimaan Baru
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if ($receipts->hasPages())
                <div class="border-t border-slate-200 bg-white px-5 py-3">
                    {{ $receipts->links() }}
                </div>
            @endif
        </section>
    </main>
</body>
</html>
