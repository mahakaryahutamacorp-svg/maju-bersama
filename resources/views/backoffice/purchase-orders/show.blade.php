<x-app-layout :title="'PO '.$purchaseOrder->reference_number" :breadcrumbs="[
    ['label' => 'Belanja Barang'],
    ['label' => 'Pesan Barang', 'url' => route('backoffice.purchase-orders.index')],
    ['label' => $purchaseOrder->reference_number],
]">
    <x-slot:head>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; }
            .print-shadow-none { box-shadow: none !important; border: 1px solid #cbd5e1 !important; }
        }
    </style>
    </x-slot:head>

    <main class="mx-auto w-full max-w-5xl space-y-6 px-6 py-6 lg:px-10">
        <!-- Flash Message -->
        @if (session('success'))
            <div x-data="{ show: true }" x-show="show" class="no-print flex items-center justify-between rounded-2xl border border-emerald-300 bg-emerald-50 p-4 text-emerald-900 shadow-sm transition">
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

        <!-- Judul & Aksi -->
        <div class="no-print flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Langkah 1: Pesan Barang (PO)</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Detail Pesanan</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Kalau barangnya sudah datang, catat di
                    <a href="/purchases/goods-receipts/create" class="font-semibold text-sky-700 hover:underline">Langkah 2: Terima Barang</a>
                    lalu pilih nomor PO ini.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('backoffice.purchase-orders.index') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50">
                    &larr; Kembali
                </a>
                <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50">
                    <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Cetak / Simpan PDF
                </button>
                <a href="{{ route('backoffice.purchase-orders.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-sky-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-sky-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Buat PO Baru
                </a>
            </div>
        </div>

        <!-- Slip Dokumen Purchase Order -->
        <div class="print-shadow-none space-y-8 rounded-2xl border border-slate-200 bg-white p-8 shadow-sm sm:p-10">

            <!-- Header Dokumen Slip -->
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-6 border-b border-slate-200 pb-8">
                <div>
                    <span class="text-xs font-extrabold uppercase tracking-[0.25em] text-amber-600">Purchase Order</span>
                    <h2 class="mt-1 font-mono text-3xl font-black text-slate-950">{{ $purchaseOrder->reference_number }}</h2>
                    <p class="mt-2 text-xs text-slate-500">
                        Cabang: <span class="font-bold text-slate-800">{{ $purchaseOrder->branch->name ?? '-' }}</span> ({{ $purchaseOrder->branch->code ?? '-' }})
                    </p>
                </div>
                <div class="sm:text-right">
                    @if ($purchaseOrder->status === 'completed')
                        <span class="inline-flex items-center rounded-full px-3.5 py-1 text-xs font-extrabold uppercase tracking-wider border border-emerald-300 bg-emerald-50 text-emerald-700">
                            Status: {{ $purchaseOrder->status }}
                        </span>
                    @elseif ($purchaseOrder->status === 'partial')
                        <span class="inline-flex items-center rounded-full px-3.5 py-1 text-xs font-extrabold uppercase tracking-wider border border-sky-300 bg-sky-50 text-sky-700">
                            Status: {{ $purchaseOrder->status }}
                        </span>
                    @elseif ($purchaseOrder->status === 'pending')
                        <span class="inline-flex items-center rounded-full px-3.5 py-1 text-xs font-extrabold uppercase tracking-wider border border-amber-300 bg-amber-50 text-amber-700">
                            Status: {{ $purchaseOrder->status }}
                        </span>
                    @elseif ($purchaseOrder->status === 'cancelled')
                        <span class="inline-flex items-center rounded-full px-3.5 py-1 text-xs font-extrabold uppercase tracking-wider border border-rose-300 bg-rose-50 text-rose-700">
                            Status: {{ $purchaseOrder->status }}
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-full px-3.5 py-1 text-xs font-extrabold uppercase tracking-wider border border-slate-300 bg-slate-100 text-slate-700">
                            Status: {{ $purchaseOrder->status }}
                        </span>
                    @endif
                    <p class="mt-3 text-xs text-slate-500">Tanggal Order:</p>
                    <p class="font-mono text-sm font-bold text-slate-800">{{ $purchaseOrder->order_date ? $purchaseOrder->order_date->format('d F Y') : '-' }}</p>
                    @if ($purchaseOrder->expected_date)
                        <p class="mt-1 text-xs text-slate-400">Estimasi Datang: {{ $purchaseOrder->expected_date->format('d/m/Y') }}</p>
                    @endif
                </div>
            </div>

            <!-- Identitas Supplier & Pengiriman -->
            <div class="grid gap-6 sm:grid-cols-2 rounded-2xl bg-slate-50 p-6 border border-slate-200">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Kepada Rekanan (Supplier):</h3>
                    <p class="text-base font-bold text-slate-900">{{ $purchaseOrder->supplier->name ?? '-' }}</p>
                    @if ($purchaseOrder->supplier?->contact_person)
                        <p class="text-xs text-slate-600 mt-1">PIC: {{ $purchaseOrder->supplier->contact_person }}</p>
                    @endif
                    @if ($purchaseOrder->supplier?->phone)
                        <p class="text-xs font-mono text-slate-600 mt-0.5">Telp: {{ $purchaseOrder->supplier->phone }}</p>
                    @endif
                    @if ($purchaseOrder->supplier?->address)
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">{{ $purchaseOrder->supplier->address }}</p>
                    @endif
                </div>
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Instruksi &amp; Catatan:</h3>
                    <p class="text-xs text-slate-700 leading-relaxed italic bg-white p-3 rounded-xl border border-slate-200">
                        {{ $purchaseOrder->notes ?: 'Tidak ada catatan khusus.' }}
                    </p>
                </div>
            </div>

            <!-- Tabel Rincian Barang Pesanan -->
            <div>
                <h3 class="text-sm font-bold text-slate-900 mb-3 uppercase tracking-wider">Rincian Barang Dipesan</h3>
                <div class="overflow-x-auto rounded-2xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-900 text-xs font-semibold uppercase tracking-wider text-white">
                            <tr>
                                <th class="px-4 py-3 text-center w-12">No</th>
                                <th class="px-4 py-3 text-left">SKU</th>
                                <th class="px-4 py-3 text-left">Nama Produk</th>
                                <th class="px-4 py-3 text-right">Qty Pesan</th>
                                <th class="px-4 py-3 text-right">Qty Diterima</th>
                                <th class="px-4 py-3 text-right">Harga Satuan (Rp)</th>
                                <th class="px-4 py-3 text-right">Subtotal (Rp)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 [&>tr:nth-child(even)]:bg-slate-50/60">
                            @foreach ($purchaseOrder->items as $index => $item)
                                <tr>
                                    <td class="px-4 py-3 text-center text-xs font-bold text-slate-400">{{ $index + 1 }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $item->product->sku ?? '-' }}</td>
                                    <td class="px-4 py-3 font-semibold text-slate-900">{{ $item->product->name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-slate-800">{{ number_format($item->quantity) }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-xs {{ $item->received_quantity >= $item->quantity ? 'text-emerald-700 font-bold' : 'text-slate-500' }}">
                                        {{ number_format($item->received_quantity) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-slate-700">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-slate-300 bg-slate-50 font-bold">
                            <tr>
                                <td colspan="6" class="px-5 py-4 text-right uppercase tracking-wider text-slate-700 text-xs">
                                    Grand Total Nilai PO:
                                </td>
                                <td class="px-5 py-4 text-right font-mono text-xl font-black text-sky-900">
                                    Rp {{ number_format($purchaseOrder->total_amount, 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Riwayat Penerimaan Barang (Goods Receipt) Terkait -->
            @if ($purchaseOrder->goodsReceipts->isNotEmpty())
                <div class="pt-4 border-t border-slate-100">
                    <h3 class="text-sm font-bold text-slate-900 mb-3 uppercase tracking-wider flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                        Riwayat Penerimaan Barang (Goods Receipt Terkait)
                    </h3>
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-xs">
                            <thead class="bg-slate-900 font-semibold uppercase tracking-wider text-white">
                                <tr>
                                    <th class="px-4 py-2.5 text-left">No. Goods Receipt</th>
                                    <th class="px-4 py-2.5 text-left">Tanggal Penerimaan</th>
                                    <th class="px-4 py-2.5 text-left">Tipe Pembayaran</th>
                                    <th class="px-4 py-2.5 text-right">Nilai Diterima (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 [&>tr:nth-child(even)]:bg-slate-50/60">
                                @foreach ($purchaseOrder->goodsReceipts as $gr)
                                    <tr>
                                        <td class="px-4 py-2.5 font-mono font-bold text-sky-700">
                                            <a href="/purchases/goods-receipts/{{ $gr->id }}" class="hover:underline">
                                                {{ $gr->reference_number }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-2.5 text-slate-600">{{ $gr->date ? $gr->date->format('d/m/Y') : '-' }}</td>
                                        <td class="px-4 py-2.5 uppercase font-semibold text-slate-700">{{ $gr->payment_type }}</td>
                                        <td class="px-4 py-2.5 text-right font-mono font-bold text-slate-900">Rp {{ number_format($gr->total_amount, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- Tanda Tangan / Persetujuan (Cocok untuk Cetak) -->
            <div class="grid grid-cols-2 gap-8 pt-8 border-t border-slate-200 text-center text-xs">
                <div>
                    <p class="text-slate-500">Dibuat / Dipesan Oleh:</p>
                    <div class="h-16"></div>
                    <p class="font-bold border-t border-slate-300 pt-1 w-48 mx-auto">( Bagian Pengadaan / Staff )</p>
                </div>
                <div>
                    <p class="text-slate-500">Disetujui Oleh:</p>
                    <div class="h-16"></div>
                    <p class="font-bold border-t border-slate-300 pt-1 w-48 mx-auto">( Manajer Cabang / Pimpinan )</p>
                </div>
            </div>
        </div>
    </main>
</x-app-layout>
