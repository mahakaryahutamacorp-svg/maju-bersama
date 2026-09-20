<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Jalan #{{ $stockTransfer->reference_number }} | MAJU BERSAMA GRUP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @page {
            size: 9in 5in;
            margin: 0.1in 0.2in;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #ffffff !important;
                color: #000000 !important;
                padding: 0 !important;
                margin: 0 !important;
                font-family: 'Courier New', Courier, monospace !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .print-sheet {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
                margin: 0 !important;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-white text-black font-mono antialiased p-2 sm:p-4">

    <!-- Control Bar (Hanya tampil di layar monitor, tidak tercetak di kertas) -->
    <div class="no-print mx-auto mb-3 max-w-[9in] flex items-center justify-between border border-black bg-white px-4 py-2">
        <div class="flex items-center gap-2 text-xs">
            <span class="inline-block h-2.5 w-2.5 bg-black"></span>
            <span class="font-bold text-black uppercase">Continuous Form 9 x 5 Inci &bull; Mode Dot Matrix (Monochrome)</span>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" onclick="window.print()" class="border border-black bg-black px-3 py-1 text-xs font-bold text-white hover:bg-neutral-800 transition">
                🖨️ Cetak Surat Jalan
            </button>
            <button type="button" onclick="window.close()" class="border border-black bg-white px-3 py-1 text-xs font-bold text-black hover:bg-neutral-100 transition">
                ✕ Tutup
            </button>
        </div>
    </div>

    <!-- Lembar Surat Jalan Continuous Form 9x5 Inci (Tanpa Warna Abu/Bayangan) -->
    <div class="print-sheet mx-auto max-w-[9in] bg-white border border-black p-3 text-black">
        
        <!-- KOP SURAT / IDENTITAS PERUSAHAAN -->
        <div class="border-b-2 border-black pb-1 mb-1.5">
            <div class="flex items-center justify-between">
                <div class="text-left">
                    <h1 class="text-base sm:text-lg font-black uppercase tracking-wider text-black leading-tight">
                        MAJU BERSAMA GRUP
                    </h1>
                    <p class="text-[11px] font-semibold text-black leading-tight">
                        Jl. Nusa Indah Ujung BK 9, OKU Timur, Belitang, Sumatera Selatan
                    </p>
                    <p class="text-[11px] text-black leading-tight">
                        Telp/WA: 085198548662
                    </p>
                </div>
                <div class="text-right">
                    <div class="border-2 border-black px-2.5 py-0.5 inline-block text-center">
                        <span class="text-xs sm:text-sm font-black uppercase tracking-wider text-black block">
                            SURAT JALAN / DELIVERY NOTE
                        </span>
                    </div>
                    <p class="text-xs font-bold text-black mt-0.5">
                        NO: {{ $stockTransfer->reference_number }}
                    </p>
                </div>
            </div>
        </div>

        <!-- HEADER DOKUMEN / INFORMASI PENGIRIMAN -->
        <div class="mb-1.5 border border-black px-2 py-1 text-[11px]">
            <div class="grid grid-cols-2 gap-x-4 gap-y-0.5">
                <!-- Kolom Kiri: Meta Transaksi -->
                <div class="space-y-0.5">
                    <div class="flex">
                        <span class="w-28 font-bold uppercase text-black">No. Referensi</span>
                        <span class="font-bold text-black">: {{ $stockTransfer->reference_number }}</span>
                    </div>
                    <div class="flex">
                        <span class="w-28 font-bold uppercase text-black">Tgl Transfer</span>
                        <span class="text-black">: {{ $stockTransfer->transfer_date?->format('d/m/Y') ?? $stockTransfer->created_at?->format('d/m/Y') }}</span>
                    </div>
                    <div class="flex">
                        <span class="w-28 font-bold uppercase text-black">Operator</span>
                        <span class="text-black font-semibold">: {{ $stockTransfer->user?->name ?? $stockTransfer->creator?->name ?? 'Admin Gudang' }}</span>
                    </div>
                </div>

                <!-- Kolom Kanan: Rute Pengiriman Antar Gudang -->
                <div class="space-y-0.5 border-l border-black pl-3">
                    <div class="flex">
                        <span class="w-28 font-bold uppercase text-black">Gudang Asal</span>
                        <span class="font-bold text-black">: {{ $stockTransfer->fromBranch?->name ?? $stockTransfer->sourceBranch?->name ?? '-' }}</span>
                    </div>
                    <div class="flex">
                        <span class="w-28 font-bold uppercase text-black">Gudang Tujuan</span>
                        <span class="font-bold text-black">: {{ $stockTransfer->toBranch?->name ?? $stockTransfer->destinationBranch?->name ?? '-' }}</span>
                    </div>
                    @if($stockTransfer->notes)
                        <div class="flex">
                            <span class="w-28 font-bold uppercase text-black">Catatan</span>
                            <span class="text-black italic truncate">: {{ $stockTransfer->notes }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- TABEL BARANG (MURNI LOGISTIK TANPA HARGA MODAL / HPP) --}}
        <div class="mb-1.5">
            <table class="w-full border-collapse border border-black text-[11px]">
                <thead>
                    <tr class="border-b-2 border-black">
                        <th class="border border-black px-1.5 py-0.5 text-center font-bold uppercase w-8">No</th>
                        <th class="border border-black px-2 py-0.5 text-left font-bold uppercase">Nama Barang / Deskripsi</th>
                        <th class="border border-black px-2 py-0.5 text-left font-bold uppercase w-36">SKU / Kode</th>
                        <th class="border border-black px-2 py-0.5 text-right font-bold uppercase w-24">Qty Fisik</th>
                    </tr>
                </thead>
                <tbody>
                    @php $totalQuantity = 0; @endphp
                    @forelse($stockTransfer->items as $index => $item)
                        @php
                            $product = $item->product ?? $item->sourceProduct ?? $item->destinationProduct;
                            $qty = $item->quantity ?? 0;
                            $totalQuantity += $qty;
                        @endphp
                        <tr class="border-b border-black">
                            <td class="border border-black px-1.5 py-0.5 text-center">{{ $index + 1 }}</td>
                            <td class="border border-black px-2 py-0.5">
                                <span class="font-bold text-black">{{ $product?->name ?? 'Item #' . $item->id }}</span>
                            </td>
                            <td class="border border-black px-2 py-0.5 font-bold">
                                {{ $product?->sku ?? '-' }}
                            </td>
                            <td class="border border-black px-2 py-0.5 text-right font-bold">
                                {{ number_format($qty) }} {{ $product?->unit ?? 'Unit' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="border border-black px-2 py-2 text-center italic text-black">
                                Tidak ada item barang dalam dokumen transfer stok ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-black font-bold">
                        <td colspan="2" class="border border-black px-2 py-0.5 text-left uppercase text-black">
                            Total: {{ $stockTransfer->items->count() }} Jenis Produk
                        </td>
                        <td class="border border-black px-2 py-0.5 text-right uppercase text-black">
                            Total Kuantitas:
                        </td>
                        <td class="border border-black px-2 py-0.5 text-right font-bold text-black">
                            {{ number_format($totalQuantity) }} Unit
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- FOOTER TANDA TANGAN (3 KOLOM SEJAJAR - COMPACT UNTUK 5 INCI) -->
        <div class="grid grid-cols-3 gap-2 text-center text-[10px]">
            <!-- Kolom 1: Gudang Asal -->
            <div class="border border-black p-1 flex flex-col justify-between h-20">
                <p class="font-bold uppercase text-black">Dikeluarkan Oleh (Gudang Asal)</p>
                <div>
                    <p class="font-bold text-black border-b border-black inline-block min-w-[120px] pb-0.5">
                        {{ $stockTransfer->user?->name ?? $stockTransfer->creator?->name ?? '..................' }}
                    </p>
                    <p class="text-[9px] text-black">
                        Tgl: {{ $stockTransfer->transfer_date?->format('d/m/Y') ?? '..../..../20..' }}
                    </p>
                </div>
            </div>

            <!-- Kolom 2: Kurir -->
            <div class="border border-black p-1 flex flex-col justify-between h-20">
                <p class="font-bold uppercase text-black">Dibawa Oleh (Kurir)</p>
                <div>
                    <p class="font-bold text-black border-b border-black inline-block min-w-[120px] pb-0.5">
                        ..................
                    </p>
                    <p class="text-[9px] text-black">
                        Tgl: ..../..../20..
                    </p>
                </div>
            </div>

            <!-- Kolom 3: Cabang Tujuan -->
            <div class="border border-black p-1 flex flex-col justify-between h-20">
                <p class="font-bold uppercase text-black">Diterima Oleh (Cabang Tujuan)</p>
                <div>
                    <p class="font-bold text-black border-b border-black inline-block min-w-[120px] pb-0.5">
                        ..................
                    </p>
                    <p class="text-[9px] text-black">
                        Tgl: ..../..../20..
                    </p>
                </div>
            </div>
        </div>

        <!-- IDENTITAS APLIKASI (FOOTER PALING BAWAH) -->
        <div class="mt-1 flex items-center justify-between text-[9px] text-black">
            <span>* Dokumen fisik resmi kurir logistik antar cabang</span>
            <span class="font-semibold text-right">maju bersama abi - 2029 | supported by rocellgadget</span>
        </div>

    </div>

    <!-- Script Cetak Otomatis -->
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                window.print();
            }, 500);
        });
    </script>
</body>
</html>
