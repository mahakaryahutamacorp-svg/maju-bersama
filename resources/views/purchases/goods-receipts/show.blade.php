<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slip Penerimaan Barang #{{ $receipt->reference_number }} | Maju Bersama ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; padding: 0 !important; }
            .print-card { border: 1px solid #cbd5e1 !important; box-shadow: none !important; }
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

    <!-- Sub-Navbar -->
    <div class="border-b border-slate-200 bg-white no-print">
        <div class="mx-auto flex max-w-5xl items-center justify-between overflow-x-auto px-6 py-2.5 lg:px-8">
            <div class="flex items-center gap-2 text-sm font-medium">
                <a href="/purchases/goods-receipts" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    &larr; Riwayat Penerimaan
                </a>
                <a href="/purchases/goods-receipts/create" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    + Input Baru
                </a>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-1.5 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50">
                    <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Cetak Slip Bukti
                </button>
            </div>
        </div>
    </div>

    <main class="mx-auto max-w-5xl space-y-6 px-6 py-8 lg:px-8">
        <!-- Flash Alert -->
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-300 bg-emerald-50 p-4 text-emerald-900 shadow-sm no-print">
                <div class="flex items-center gap-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-600 text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-bold">Penerimaan Barang Selesai</p>
                        <p class="text-xs text-emerald-800">{{ session('success') }}</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Lembar Slip Bukti Penerimaan Barang (Invoice Style) -->
        <article class="print-card overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <!-- Header Nota -->
            <div class="border-b border-slate-200 bg-slate-950 p-8 text-white">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.24em] text-amber-400">Maju Bersama Multistore ERP</p>
                        <h2 class="mt-1 text-2xl font-black tracking-tight sm:text-3xl">BUKTI PENERIMAAN BARANG</h2>
                        <p class="text-xs text-slate-400 mt-1">GUDANG PUSAT (GOODS RECEIPT SLIP)</p>
                    </div>
                    <div class="text-left sm:text-right font-mono">
                        <div class="inline-block rounded-xl border border-slate-800 bg-slate-900 px-4 py-2">
                            <p class="text-[10px] uppercase tracking-wider text-slate-400 font-sans">No. Referensi</p>
                            <p class="text-lg font-bold text-amber-400">{{ $receipt->reference_number }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-8 space-y-8">
                <!-- Grid Informasi Dokumen -->
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4 rounded-2xl border border-slate-200 bg-slate-50/70 p-5">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Tanggal Penerimaan</p>
                        <p class="mt-1 font-mono text-sm font-bold text-slate-900">
                            {{ \Carbon\Carbon::parse($receipt->date)->format('d F Y') }}
                        </p>
                    </div>

                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Lokasi / Gudang</p>
                        <p class="mt-1 text-sm font-bold text-slate-900">
                            {{ $receipt->branch->name ?? 'Gudang Pusat' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Pemasok / Supplier</p>
                        <p class="mt-1 text-sm font-bold text-slate-900">
                            {{ $receipt->supplier_name ?: 'Pemasok Umum' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Metode Pembayaran</p>
                        <div class="mt-1">
                            @if ($receipt->payment_type === 'cash')
                                <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-800">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    Tunai (Kas)
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-800">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                    Tempo (Hutang Dagang)
                                </span>
                            @endif
                        </div>
                    </div>

                    @if ($receipt->notes)
                        <div class="sm:col-span-2 lg:col-span-4 border-t border-slate-200 pt-3">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Catatan / Keterangan</p>
                            <p class="mt-0.5 text-xs text-slate-700">{{ $receipt->notes }}</p>
                        </div>
                    @endif
                </div>

                <!-- Tabel Rincian Barang Masuk -->
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900 border-b border-slate-200 pb-2">
                        Rincian Barang yang Diterima
                    </h3>

                    <div class="overflow-x-auto mt-2">
                        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                            <thead class="bg-slate-100 text-xs font-bold uppercase tracking-wider text-slate-700">
                                <tr>
                                    <th class="py-3 px-4 w-12 text-center">#</th>
                                    <th class="py-3 px-4 w-32">Kode SKU</th>
                                    <th class="py-3 px-4">Nama Produk</th>
                                    <th class="py-3 px-4 text-center w-28">Kuantitas</th>
                                    <th class="py-3 px-4 text-right w-40">Harga Satuan</th>
                                    <th class="py-3 px-4 text-right w-44">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($receipt->items as $index => $item)
                                    <tr>
                                        <td class="py-3.5 px-4 text-center font-mono text-xs text-slate-400">{{ $index + 1 }}</td>
                                        <td class="py-3.5 px-4 font-mono text-xs font-bold text-slate-700">
                                            {{ $item->product->sku ?? '-' }}
                                        </td>
                                        <td class="py-3.5 px-4 font-medium text-slate-900">
                                            {{ $item->product->name ?? '-' }}
                                        </td>
                                        <td class="py-3.5 px-4 text-center font-mono text-xs font-bold text-slate-950">
                                            {{ number_format($item->quantity, 0, ',', '.') }} unit
                                        </td>
                                        <td class="py-3.5 px-4 text-right font-mono text-xs text-slate-700">
                                            Rp {{ number_format($item->unit_price, 0, ',', '.') }}
                                        </td>
                                        <td class="py-3.5 px-4 text-right font-mono text-sm font-bold text-slate-950">
                                            Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t-2 border-slate-300 bg-slate-50 text-sm font-bold">
                                <tr>
                                    <td colspan="3" class="py-3.5 px-4 text-right uppercase text-slate-600">Total Kuantitas Masuk:</td>
                                    <td class="py-3.5 px-4 text-center font-mono text-slate-950">
                                        {{ number_format($receipt->items->sum('quantity'), 0, ',', '.') }} unit
                                    </td>
                                    <td class="py-3.5 px-4 text-right uppercase text-slate-900">GRAND TOTAL:</td>
                                    <td class="py-3.5 px-4 text-right font-mono text-base font-extrabold text-sky-950 bg-slate-100">
                                        Rp {{ number_format($receipt->total_amount, 0, ',', '.') }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Bagian Dampak Jurnal Akuntansi Terbentuk -->
                @if ($receipt->journalHeader)
                    <div class="rounded-2xl border border-sky-200 bg-gradient-to-r from-sky-50/50 to-white p-6 shadow-xs">
                        <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center border-b border-sky-200/60 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="rounded bg-sky-600 px-2 py-0.5 text-[10px] font-extrabold text-white uppercase">Jurnal Terposting</span>
                                <h4 class="text-sm font-bold text-slate-900">
                                    Jurnal Pembukuan Berpasangan: <span class="font-mono text-sky-800">{{ $receipt->journalHeader->reference_number }}</span>
                                </h4>
                            </div>
                            <span class="text-xs font-mono text-slate-500">
                                Tanggal: {{ \Carbon\Carbon::parse($receipt->journalHeader->transaction_date)->format('d/m/Y') }}
                            </span>
                        </div>

                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                                <thead>
                                    <tr class="font-bold text-slate-600 uppercase tracking-wider">
                                        <th class="py-2 px-3">Kode &amp; Nama Akun</th>
                                        <th class="py-2 px-3">Keterangan / Memo</th>
                                        <th class="py-2 px-3 text-right">Debit (Rp)</th>
                                        <th class="py-2 px-3 text-right">Kredit (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 font-mono">
                                    @foreach ($receipt->journalHeader->journalLines as $line)
                                        <tr>
                                            <td class="py-2 px-3 font-medium text-slate-900">
                                                <span class="font-bold text-slate-700">[{{ $line->chartOfAccount->code ?? '-' }}]</span>
                                                {{ $line->chartOfAccount->name ?? '-' }}
                                            </td>
                                            <td class="py-2 px-3 text-slate-500 font-sans text-[11px]">
                                                {{ $line->memo ?: $receipt->journalHeader->description }}
                                            </td>
                                            <td class="py-2 px-3 text-right {{ $line->debit > 0 ? 'font-bold text-emerald-800' : 'text-slate-300' }}">
                                                {{ $line->debit > 0 ? 'Rp ' . number_format($line->debit, 0, ',', '.') : '—' }}
                                            </td>
                                            <td class="py-2 px-3 text-right {{ $line->credit > 0 ? 'font-bold text-sky-800' : 'text-slate-300' }}">
                                                {{ $line->credit > 0 ? 'Rp ' . number_format($line->credit, 0, ',', '.') : '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="border-t border-slate-300 font-bold bg-white/60">
                                    <tr>
                                        <td colspan="2" class="py-2 px-3 text-right font-sans uppercase text-slate-600 text-[11px]">Total Keseimbangan Jurnal:</td>
                                        <td class="py-2 px-3 text-right text-emerald-950 font-bold">
                                            Rp {{ number_format($receipt->journalHeader->journalLines->sum('debit'), 0, ',', '.') }}
                                        </td>
                                        <td class="py-2 px-3 text-right text-sky-950 font-bold">
                                            Rp {{ number_format($receipt->journalHeader->journalLines->sum('credit'), 0, ',', '.') }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                @endif

                <!-- Blok Tanda Tangan Cetak Fisik -->
                <div class="pt-6 border-t border-slate-200">
                    <div class="grid grid-cols-3 gap-6 text-center text-xs">
                        <div>
                            <p class="text-slate-500">Diserahkan Oleh (Pemasok / Driver),</p>
                            <div class="h-20"></div>
                            <p class="font-bold text-slate-900 border-t border-slate-300 pt-1.5">
                                {{ $receipt->supplier_name ?: '....................................' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-slate-500">Diterima Oleh (Gudang Pusat),</p>
                            <div class="h-20"></div>
                            <p class="font-bold text-slate-900 border-t border-slate-300 pt-1.5">
                                {{ $currentUser->name }}
                            </p>
                        </div>
                        <div>
                            <p class="text-slate-500">Diverifikasi Oleh (Keuangan / Akuntansi),</p>
                            <div class="h-20"></div>
                            <p class="font-bold text-slate-900 border-t border-slate-300 pt-1.5">
                                Supervisor / Manajer
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </article>
    </main>
</body>
</html>
