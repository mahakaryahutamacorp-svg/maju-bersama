<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order {{ $purchaseOrder->reference_number }} | Maju Bersama ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @media print {
            header, .no-print { display: none !important; }
            body { background: white !important; color: black !important; }
            .print-shadow-none { box-shadow: none !important; border: 1px solid #cbd5e1 !important; }
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
                    <h1 class="text-xl font-bold tracking-tight">Pengadaan &amp; Pembelian</h1>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <nav class="hidden items-center gap-4 text-sm text-slate-300 md:flex">
                    <a href="{{ route('backoffice.purchase-orders.index') }}" class="hover:text-white">Daftar PO</a>
                    <a href="{{ route('backoffice.suppliers.index') }}" class="hover:text-white">Supplier</a>
                    <a href="/backoffice" class="hover:text-white">Backoffice</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Sub-Navbar Navigasi -->
    <div class="border-b border-slate-200 bg-white shadow-xs no-print">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-2.5 lg:px-8">
            <a href="{{ route('backoffice.purchase-orders.index') }}" class="rounded-lg px-3.5 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                &larr; Kembali ke Riwayat PO
            </a>
            <div class="flex items-center gap-3">
                <button onclick="window.print()" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50">
                    <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Cetak / Simpan PDF
                </button>
                <a href="{{ route('backoffice.purchase-orders.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 px-3.5 py-2 text-xs font-bold text-slate-950 shadow-xs hover:bg-amber-400">
                    + Buat PO Baru
                </a>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <main class="mx-auto max-w-5xl px-6 py-8 lg:px-8">

        <!-- Flash Messages -->
        @if (session('success'))
            <div class="mb-6 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 shadow-xs no-print">
                <svg class="h-5 w-5 shrink-0 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                <span class="text-sm font-medium">{{ session('success') }}</span>
            </div>
        @endif

        <!-- Slip Dokumen Purchase Order -->
        <div class="rounded-3xl border border-slate-200 bg-white p-8 shadow-xs print-shadow-none sm:p-10 space-y-8">

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
                    @php
                        $badgeClasses = match($purchaseOrder->status) {
                            'completed' => 'bg-emerald-50 text-emerald-700 border-emerald-300',
                            'partial'   => 'bg-blue-50 text-blue-700 border-blue-300',
                            'pending'   => 'bg-amber-50 text-amber-700 border-amber-300',
                            'cancelled' => 'bg-rose-50 text-rose-700 border-rose-300',
                            default     => 'bg-slate-100 text-slate-700 border-slate-300',
                        };
                    @endphp
                    <span class="inline-flex items-center rounded-full px-3.5 py-1 text-xs font-extrabold uppercase tracking-wider border {{ $badgeClasses }}">
                        Status: {{ $purchaseOrder->status }}
                    </span>
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
                        <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wider text-slate-600">
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
                        <tbody class="divide-y divide-slate-100">
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
                                <td class="px-5 py-4 text-right font-mono text-xl font-black text-amber-700">
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
                            <thead class="bg-slate-50 text-slate-500 font-semibold uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-2.5 text-left">No. Goods Receipt</th>
                                    <th class="px-4 py-2.5 text-left">Tanggal Penerimaan</th>
                                    <th class="px-4 py-2.5 text-left">Tipe Pembayaran</th>
                                    <th class="px-4 py-2.5 text-right">Nilai Diterima (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($purchaseOrder->goodsReceipts as $gr)
                                    <tr>
                                        <td class="px-4 py-2.5 font-mono font-bold text-indigo-700">
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
</body>
</html>
