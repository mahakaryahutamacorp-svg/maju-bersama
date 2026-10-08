<x-app-layout title="Pesan Barang (PO)" :breadcrumbs="[['label' => 'Belanja Barang'], ['label' => 'Pesan Barang']]">
    <main class="mx-auto w-full max-w-7xl space-y-6 px-6 py-6 lg:px-10">
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

        @if (session('error'))
            <div x-data="{ show: true }" x-show="show" class="flex items-center justify-between rounded-2xl border border-rose-300 bg-rose-50 p-4 text-rose-900 shadow-sm transition">
                <div class="flex items-center gap-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-rose-600 text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-bold">Operasi Gagal</p>
                        <p class="text-xs text-rose-800">{{ session('error') }}</p>
                    </div>
                </div>
                <button type="button" @click="show = false" class="text-rose-700 hover:text-rose-900">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endif

        <!-- Judul Halaman -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Langkah 1: Pesan Barang (PO)</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Pesan Barang</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Buat surat pesanan (PO) ke pemasok. PO belum menambah stok dan belum mencatat hutang. Keduanya baru tercatat saat barangnya datang di
                    <a href="/purchases/goods-receipts" class="font-semibold text-sky-700 hover:underline">Langkah 2: Terima Barang</a>.
                </p>
            </div>
            <div>
                <a href="{{ route('backoffice.purchase-orders.create') }}" id="btn-tambah-po" class="inline-flex items-center gap-2 rounded-xl bg-sky-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-sky-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Buat Purchase Order
                </a>
            </div>
        </div>

        <!-- Kartu Metrik Ringkas -->
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Pesanan</p>
                <p class="mt-2 text-2xl font-bold font-mono text-slate-950">{{ $metrics['total'] }} PO</p>
                <p class="mt-1 text-xs text-slate-500">Seluruh dokumen terbit</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Menunggu Barang</p>
                <p class="mt-2 text-2xl font-bold font-mono text-amber-800">{{ $metrics['pending'] }} PO</p>
                <p class="mt-1 text-xs text-slate-500">Belum dikirim pemasok</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Selesai Diterima</p>
                <p class="mt-2 text-2xl font-bold font-mono text-emerald-800">{{ $metrics['completed'] }} PO</p>
                <p class="mt-1 text-xs text-slate-500">Barang sudah datang penuh</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Nilai Pesanan</p>
                <p class="mt-2 text-2xl font-bold font-mono text-sky-900">Rp {{ number_format($metrics['amount'], 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">Akumulasi nilai PO</p>
            </div>
        </section>

        <!-- Filter & Pencarian -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm print:hidden">
            <form method="GET" action="{{ route('backoffice.purchase-orders.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Cari No. PO / Pemasok</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="No. PO atau Nama Pemasok..." class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Status Pesanan</label>
                    <select name="status" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                        <option value="">Semua Status</option>
                        <option value="draft" @selected($status === 'draft')>Draft</option>
                        <option value="pending" @selected($status === 'pending')>Pending (Menunggu Barang)</option>
                        <option value="partial" @selected($status === 'partial')>Partial (Datang Sebagian)</option>
                        <option value="completed" @selected($status === 'completed')>Completed (Selesai)</option>
                        <option value="cancelled" @selected($status === 'cancelled')>Cancelled (Batal)</option>
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
                    @if ($search || $status || $startDate || $endDate || $filterBranch)
                        <a href="{{ route('backoffice.purchase-orders.index') }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </section>

        <!-- Tabel Daftar Pesanan -->
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-900 text-xs font-semibold uppercase tracking-wider text-white">
                        <tr>
                            <th class="px-5 py-3.5">Tgl Order</th>
                            <th class="px-5 py-3.5">No. PO</th>
                            <th class="px-5 py-3.5">Pemasok</th>
                            <th class="px-5 py-3.5">Estimasi Datang</th>
                            @if ($isMaster)
                                <th class="px-5 py-3.5">Cabang</th>
                            @endif
                            <th class="px-5 py-3.5 text-center">Status</th>
                            <th class="px-5 py-3.5 text-right">Total Nilai</th>
                            <th class="px-5 py-3.5 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 [&>tr:nth-child(even)]:bg-slate-50/60">
                        @forelse ($purchaseOrders as $po)
                            @php
                                $statusBadge = match ($po->status) {
                                    'completed' => ['border-emerald-200 bg-emerald-50 text-emerald-800', 'bg-emerald-500', 'Completed'],
                                    'partial' => ['border-sky-200 bg-sky-50 text-sky-800', 'bg-sky-500', 'Partial'],
                                    'pending' => ['border-amber-200 bg-amber-50 text-amber-800', 'bg-amber-500', 'Pending'],
                                    'cancelled' => ['border-rose-200 bg-rose-50 text-rose-800', 'bg-rose-500', 'Cancelled'],
                                    default => ['border-slate-200 bg-slate-100 text-slate-700', 'bg-slate-400', ucfirst((string) $po->status)],
                                };
                            @endphp
                            <tr class="transition hover:bg-slate-50">
                                <td class="whitespace-nowrap px-5 py-4 font-mono text-xs text-slate-700">
                                    {{ $po->order_date ? $po->order_date->format('d/m/Y') : '-' }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 font-mono text-xs font-bold text-sky-700">
                                    <a href="{{ route('backoffice.purchase-orders.show', $po->id) }}" class="hover:underline">
                                        {{ $po->reference_number }}
                                    </a>
                                </td>
                                <td class="px-5 py-4 font-medium text-slate-800">
                                    {{ $po->supplier->name ?? '-' }}
                                    @if ($po->notes)
                                        <p class="max-w-xs truncate text-[11px] text-slate-400">{{ $po->notes }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 font-mono text-xs text-slate-500">
                                    {{ $po->expected_date ? $po->expected_date->format('d/m/Y') : '-' }}
                                </td>
                                @if ($isMaster)
                                    <td class="whitespace-nowrap px-5 py-4 text-xs font-medium text-slate-600">
                                        <span class="rounded-md bg-slate-100 px-2 py-1">{{ $po->branch->name ?? '-' }}</span>
                                    </td>
                                @endif
                                <td class="whitespace-nowrap px-5 py-4 text-center">
                                    <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-bold {{ $statusBadge[0] }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $statusBadge[1] }}"></span>
                                        {{ $statusBadge[2] }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right font-mono text-sm font-bold text-slate-950">
                                    Rp {{ number_format($po->total_amount, 0, ',', '.') }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <a href="{{ route('backoffice.purchase-orders.show', $po->id) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 hover:text-sky-600">
                                            <svg class="h-3.5 w-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            Detail
                                        </a>
                                        @if ($po->goodsReceipts->isEmpty())
                                            <form method="POST" action="{{ route('backoffice.purchase-orders.destroy', $po) }}" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan/menghapus PO {{ $po->reference_number }}?');" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-600 shadow-xs hover:border-rose-300 hover:bg-rose-50">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    Hapus
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isMaster ? 8 : 7 }}" class="px-6 py-12 text-center text-slate-500">
                                    <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <h3 class="mt-4 text-base font-semibold text-slate-900">Belum Ada Pesanan Barang</h3>
                                    <p class="mt-1 text-sm text-slate-500">Buat PO pertama untuk memesan barang ke pemasok.</p>
                                    <div class="mt-6">
                                        <a href="{{ route('backoffice.purchase-orders.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-700">
                                            + Buat Purchase Order
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($purchaseOrders->hasPages())
                <div class="border-t border-slate-200 bg-white px-5 py-3">
                    {{ $purchaseOrders->links() }}
                </div>
            @endif
        </section>
    </main>
</x-app-layout>
