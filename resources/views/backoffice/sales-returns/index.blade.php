<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Retur Penjualan | Maju Bersama ERP</title>
    <meta name="description" content="Daftar pengembalian barang dari konsumen (Sales Returns) beserta riwayat refund dan viewer transaksi.">
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
                    <h1 class="text-xl font-bold tracking-tight">Penjualan &amp; Kasir</h1>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <nav class="hidden items-center gap-4 text-sm text-slate-300 md:flex">
                    <a href="/pos" class="hover:text-white">POS Kasir</a>
                    <a href="/inventory" class="hover:text-white">Inventory</a>
                    <a href="/backoffice" class="hover:text-white">Backoffice</a>
                </nav>
                @if ($isMaster)
                    <div class="rounded-full border border-amber-400/40 bg-amber-400/10 px-3 py-1 text-xs font-semibold text-amber-300">
                        👑 Master Pusat
                    </div>
                @else
                    <div class="rounded-full border border-sky-400/40 bg-sky-400/10 px-3 py-1 text-xs font-semibold text-sky-300">
                        🏪 {{ $currentUser->branch->name ?? 'Cabang' }}
                    </div>
                @endif
            </div>
        </div>
    </header>

    <!-- Sub-Navbar -->
    <div class="border-b border-slate-200 bg-white shadow-xs">
        <div class="mx-auto flex max-w-7xl items-center justify-between overflow-x-auto px-6 py-2.5 lg:px-8">
            <div class="flex items-center gap-2 text-sm font-medium">
                <a href="{{ route('backoffice.sales-returns.index') }}" class="rounded-lg bg-rose-600 px-3.5 py-2 font-bold text-white shadow-xs">
                    Riwayat Retur Penjualan
                </a>
                <a href="{{ route('backoffice.sales-returns.create') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    + Input Retur Pelanggan
                </a>
                <a href="/pos" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Kasir POS
                </a>
            </div>
        </div>
    </div>

    <!-- Main Container dengan Alpine.js untuk Universal Transaction Viewer -->
    <main x-data="salesReturnIndex()" class="mx-auto max-w-7xl px-6 py-8 lg:px-8 space-y-6">

        <!-- Flash Message -->
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900 shadow-xs flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <p class="text-sm font-bold">{{ session('success') }}</p>
                </div>
                <button type="button" @click="$el.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700 text-xs font-bold">
                    &times;
                </button>
            </div>
        @endif

        <!-- Header Halaman -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-rose-600">Layanan Purna Jual</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Riwayat Retur Penjualan</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Monitoring pengembalian barang dari pelanggan dan pengeluaran dana refund. Klik No. Referensi untuk membuka rincian transaksi &amp; bukti jurnal.
                </p>
            </div>
            <div>
                <a href="{{ route('backoffice.sales-returns.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-rose-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    + Input Retur Pelanggan
                </a>
            </div>
        </div>

        <!-- Metric Summary Cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Pengembalian Dana</p>
                <p class="mt-2 text-2xl font-extrabold text-slate-950 font-mono">Rp {{ number_format($totalReturnsAmount, 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-400">Total nominal refund konsumen</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Jumlah Transaksi Retur</p>
                <p class="mt-2 text-2xl font-extrabold text-rose-600 font-mono">{{ $totalCount }}</p>
                <p class="mt-1 text-xs text-slate-400">Berkas retur terdaftar</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Refund Tunai (Kas Laci)</p>
                <p class="mt-2 text-2xl font-extrabold text-emerald-600 font-mono">Rp {{ number_format($totalCash, 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-400">Memotong saldo laci kasir</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Refund Transfer Bank</p>
                <p class="mt-2 text-2xl font-extrabold text-sky-600 font-mono">Rp {{ number_format($totalTransfer, 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-400">Memotong saldo rekening bank</p>
            </div>
        </div>

        <!-- Filter & Search Card -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
            <form method="GET" action="{{ route('backoffice.sales-returns.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5 items-end">
                <div class="lg:col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Pencarian</label>
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Cari No. Referensi (SR-...), Pelanggan, No. Struk..."
                        class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 outline-none focus:border-rose-500 focus:ring-1 focus:ring-rose-500"
                    >
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Dari Tanggal</label>
                    <input
                        type="date"
                        name="start_date"
                        value="{{ $startDate }}"
                        class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 outline-none focus:border-rose-500 focus:ring-1 focus:ring-rose-500"
                    >
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Sampai Tanggal</label>
                    <input
                        type="date"
                        name="end_date"
                        value="{{ $endDate }}"
                        class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 outline-none focus:border-rose-500 focus:ring-1 focus:ring-rose-500"
                    >
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="flex-1 rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white shadow-xs hover:bg-slate-800 transition">
                        Filter
                    </button>
                    @if ($search || $startDate || $endDate || $refundMethod)
                        <a href="{{ route('backoffice.sales-returns.index') }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition" title="Reset filter">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </section>

        <!-- Tabel Riwayat Retur Penjualan -->
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wider text-slate-600">
                        <tr>
                            <th class="py-3.5 px-4 w-12 text-center">#</th>
                            <th class="py-3.5 px-4 w-32">Tanggal</th>
                            <th class="py-3.5 px-4 w-48">No. Referensi (Klik Rincian)</th>
                            <th class="py-3.5 px-4 min-w-[180px]">Pelanggan</th>
                            <th class="py-3.5 px-4 w-36">Ref. Struk</th>
                            <th class="py-3.5 px-4 w-36">Metode Refund</th>
                            <th class="py-3.5 px-4 text-right tabular-nums w-44">Total Refund</th>
                            <th class="py-3.5 px-4 w-28 text-center">Status</th>
                            <th class="py-3.5 px-4 min-w-[180px]">Alasan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($returns as $index => $ret)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-3.5 px-4 text-center text-xs font-bold text-slate-400 font-mono">
                                    {{ $returns->firstItem() + $index }}
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap text-slate-700 font-medium">
                                    {{ $ret->return_date ? \Carbon\Carbon::parse($ret->return_date)->format('d/m/Y') : '-' }}
                                </td>
                                <!-- Nomor Referensi sebagai Tombol Viewer Transaksi Universal -->
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <button
                                        type="button"
                                        @click="loadTransaction('{{ $ret->reference_number }}')"
                                        class="font-mono text-xs font-bold text-rose-600 hover:text-rose-800 hover:underline inline-flex items-center gap-1.5 group transition cursor-pointer"
                                        title="Buka rincian transaksi retur pelanggan ini"
                                    >
                                        <span>{{ $ret->reference_number }}</span>
                                        <svg class="h-3.5 w-3.5 text-rose-400 group-hover:text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </button>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-950">{{ $ret->customer_name ?: 'Pelanggan Umum' }}</div>
                                    @if ($ret->user)
                                        <div class="text-[11px] text-slate-400">Kasir: {{ $ret->user->name }}</div>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap text-xs font-mono text-slate-600">
                                    @if ($ret->sale)
                                        <button
                                            type="button"
                                            @click="loadTransaction('{{ $ret->sale->receipt_number }}')"
                                            class="text-sky-700 hover:underline hover:text-sky-900 font-semibold"
                                            title="Buka struk transaksi penjualan terkait"
                                        >
                                            {{ $ret->sale->receipt_number }}
                                        </button>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    @if (strtolower($ret->refund_method) === 'cash' || strtolower($ret->refund_method) === 'tunai')
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800">
                                            Kas Tunai
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-bold text-sky-800">
                                            Transfer Bank
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono font-bold text-slate-950 whitespace-nowrap">
                                    Rp {{ number_format($ret->total_amount, 0, ',', '.') }}
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    @if ($ret->status === 'completed')
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800">
                                            Completed
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800">
                                            {{ ucfirst($ret->status) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-xs text-slate-600 max-w-xs truncate" title="{{ $ret->reason }}">
                                    {{ $ret->reason ?? '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-12 text-center text-slate-400">
                                    <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    <p class="mt-2 text-sm font-semibold text-slate-600">Belum ada riwayat retur penjualan</p>
                                    <p class="text-xs text-slate-400">Gunakan tombol "Input Retur Pelanggan" untuk mencatat klaim retur.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if ($returns->hasPages())
                <div class="border-t border-slate-200 bg-slate-50 px-4 py-3">
                    {{ $returns->links() }}
                </div>
            @endif
        </section>

        <!-- UNIVERSAL TRANSACTION VIEWER MODAL CONTAINER -->
        <div x-show="showModal"
             class="fixed inset-0 z-50 overflow-y-auto"
             style="display: none;"
             role="dialog"
             aria-modal="true">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
                 x-show="showModal"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="showModal = false">
            </div>

            <!-- Modal Panel -->
            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-4xl"
                     x-show="showModal"
                     x-transition:enter="ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     @click.stop>
                    
                    <!-- Modal Header -->
                    <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-100 text-rose-700">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </span>
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Rincian Transaksi</h3>
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
                    <div class="p-6 max-h-[80vh] overflow-y-auto">
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
    </main>

    <!-- Script Alpine.js Component -->
    <script>
        function salesReturnIndex() {
            return {
                showModal: false,
                transactionHtml: '',
                isLoading: false,
                async loadTransaction(ref) {
                    if (!ref) return;
                    this.isLoading = true;
                    this.showModal = true;
                    this.transactionHtml = `
                        <div class="flex flex-col items-center justify-center py-12 text-slate-500">
                            <svg class="h-8 w-8 animate-spin text-rose-600 mb-3" fill="none" viewBox="0 0 24 24">
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
                                <p class="font-bold">Terjadi kesalahan jaringan saat memuat transaksi</p>
                            </div>
                        `;
                    } finally {
                        this.isLoading = false;
                    }
                }
            };
        }
    </script>
</body>
</html>
