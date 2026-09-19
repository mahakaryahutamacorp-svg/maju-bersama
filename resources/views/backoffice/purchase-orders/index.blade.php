<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order (PO) | Maju Bersama ERP</title>
    <meta name="description" content="Daftar dokumen pesanan pembelian ke supplier.">
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
                    <h1 class="text-xl font-bold tracking-tight">Pengadaan &amp; Pembelian</h1>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <nav class="hidden items-center gap-4 text-sm text-slate-300 md:flex">
                    <a href="/pos" class="hover:text-white">POS Kasir</a>
                    <a href="/inventory" class="hover:text-white">Inventory</a>
                    <a href="{{ route('backoffice.suppliers.index') }}" class="hover:text-white">Supplier</a>
                    <a href="/backoffice" class="hover:text-white">Backoffice</a>
                </nav>
                @if ($isMaster)
                    <div class="rounded-full border border-amber-400/40 bg-amber-400/10 px-3 py-1 text-xs font-semibold text-amber-300">
                        👑 Akses Master Pusat
                    </div>
                @else
                    <div class="rounded-full border border-sky-400/40 bg-sky-400/10 px-3 py-1 text-xs font-semibold text-sky-300">
                        🏪 {{ $currentUser->branch->name }}
                    </div>
                @endif
                <form method="POST" action="/logout" class="hidden sm:block">
                    @csrf
                    <button type="submit" class="text-sm text-slate-300 hover:text-white">Logout</button>
                </form>
            </div>
        </div>
    </header>

    <!-- Sub-Navbar -->
    <div class="border-b border-slate-200 bg-white shadow-xs">
        <div class="mx-auto flex max-w-7xl items-center justify-between overflow-x-auto px-6 py-2.5 lg:px-8">
            <div class="flex items-center gap-2 text-sm font-medium">
                <a href="{{ route('backoffice.purchase-orders.index') }}" class="rounded-lg bg-amber-500 px-3.5 py-2 font-bold text-slate-950 shadow-xs">
                    Riwayat Purchase Order
                </a>
                <a href="{{ route('backoffice.purchase-orders.create') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    + Buat PO Baru
                </a>
                <a href="{{ route('backoffice.suppliers.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Data Supplier
                </a>
                <a href="/purchases/goods-receipts" class="rounded-lg px-3.5 py-2 text-emerald-700 hover:bg-emerald-50">
                    Penerimaan Barang (GR)
                </a>
            </div>
            <a href="{{ route('backoffice.purchase-orders.create') }}" id="btn-tambah-po" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3.5 py-2 text-xs font-bold text-amber-400 shadow-xs hover:bg-slate-800 transition-colors">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Buat Purchase Order
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <main class="mx-auto max-w-7xl px-6 py-8 lg:px-8">
        <!-- Flash Messages -->
        @if (session('success'))
            <div class="mb-6 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 shadow-xs">
                <svg class="h-5 w-5 shrink-0 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                <span class="text-sm font-medium">{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 flex items-center gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800 shadow-xs">
                <svg class="h-5 w-5 shrink-0 text-rose-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                <span class="text-sm font-medium">{{ session('error') }}</span>
            </div>
        @endif

        <!-- Metric Cards -->
        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Purchase Order</p>
                <p class="mt-2 text-3xl font-black text-slate-900">{{ $metrics['total'] }}</p>
                <p class="mt-1 text-xs text-slate-400">Seluruh dokumen terbit</p>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50/50 p-5 shadow-xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-amber-700">PO Pending</p>
                <p class="mt-2 text-3xl font-black text-amber-900">{{ $metrics['pending'] }}</p>
                <p class="mt-1 text-xs text-amber-700">Menunggu pengiriman supplier</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-5 shadow-xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">PO Selesai (Completed)</p>
                <p class="mt-2 text-3xl font-black text-emerald-900">{{ $metrics['completed'] }}</p>
                <p class="mt-1 text-xs text-emerald-700">Barang diterima penuh</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Nilai Pemesanan</p>
                <p class="mt-2 text-2xl font-black text-slate-900 font-mono">Rp {{ number_format($metrics['amount'], 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-400">Akumulasi nilai pesanan</p>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <form method="GET" action="{{ route('backoffice.purchase-orders.index') }}" class="grid gap-3 sm:grid-cols-2 md:grid-cols-5">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Cari PO / Supplier</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="No PO / Nama Supplier..." class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Status Dokumen</label>
                    <select name="status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                        <option value="">Semua Status</option>
                        <option value="draft" {{ $status === 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="partial" {{ $status === 'partial' ? 'selected' : '' }}>Partial</option>
                        <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Tanggal Mulai</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Tanggal Akhir</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="w-full rounded-lg bg-amber-500 px-4 py-2 text-sm font-bold text-slate-950 hover:bg-amber-400 transition-colors">
                        Filter
                    </button>
                    @if ($search || $status || $startDate || $endDate || $filterBranch)
                        <a href="{{ route('backoffice.purchase-orders.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Tabel Purchase Orders -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-3.5 text-left">No. Referensi PO</th>
                            <th class="px-5 py-3.5 text-left">Supplier</th>
                            <th class="px-5 py-3.5 text-left">Tgl Order</th>
                            <th class="px-5 py-3.5 text-left">Estimasi</th>
                            @if ($isMaster)
                                <th class="px-5 py-3.5 text-left">Cabang</th>
                            @endif
                            <th class="px-5 py-3.5 text-center">Status</th>
                            <th class="px-5 py-3.5 text-right">Total Nilai</th>
                            <th class="px-5 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($purchaseOrders as $po)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-5 py-4 font-mono font-bold text-indigo-900">
                                    <a href="{{ route('backoffice.purchase-orders.show', $po->id) }}" class="hover:underline">
                                        {{ $po->reference_number }}
                                    </a>
                                </td>
                                <td class="px-5 py-4 font-semibold text-slate-900">
                                    {{ $po->supplier->name ?? '-' }}
                                </td>
                                <td class="px-5 py-4 text-slate-600">
                                    {{ $po->order_date ? $po->order_date->format('d/m/Y') : '-' }}
                                </td>
                                <td class="px-5 py-4 text-slate-500">
                                    {{ $po->expected_date ? $po->expected_date->format('d/m/Y') : '-' }}
                                </td>
                                @if ($isMaster)
                                    <td class="px-5 py-4 text-xs font-medium text-slate-600">
                                        <span class="rounded bg-slate-100 px-2 py-1">{{ $po->branch->name ?? '-' }}</span>
                                    </td>
                                @endif
                                <td class="px-5 py-4 text-center">
                                    @php
                                        $badgeClasses = match($po->status) {
                                            'completed' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            'partial'   => 'bg-blue-50 text-blue-700 border-blue-200',
                                            'pending'   => 'bg-amber-50 text-amber-700 border-amber-200',
                                            'cancelled' => 'bg-rose-50 text-rose-700 border-rose-200',
                                            default     => 'bg-slate-100 text-slate-700 border-slate-200',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold border {{ $badgeClasses }}">
                                        {{ ucfirst($po->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right font-mono font-bold text-slate-900">
                                    Rp {{ number_format($po->total_amount, 0, ',', '.') }}
                                </td>
                                <td class="px-5 py-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('backoffice.purchase-orders.show', $po->id) }}" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                            Detail
                                        </a>
                                        @if ($po->goodsReceipts->isEmpty())
                                            <form method="POST" action="{{ route('backoffice.purchase-orders.destroy', $po) }}" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan/menghapus PO {{ $po->reference_number }}?');" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 hover:border-rose-300">
                                                    Hapus
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isMaster ? 8 : 7 }}" class="px-6 py-12 text-center text-slate-400">
                                    <svg class="mx-auto h-10 w-10 text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Belum ada dokumen Purchase Order yang diterbitkan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($purchaseOrders->hasPages())
                <div class="border-t border-slate-200 px-6 py-4">
                    {{ $purchaseOrders->links() }}
                </div>
            @endif
        </div>
    </main>
</body>
</html>
