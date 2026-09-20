<div class="space-y-6">
    <!-- Header Modal Info -->
    <div class="border-b border-slate-200 pb-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                    Retur Penjualan (Sales Return)
                </span>
                <h3 class="mt-1 font-mono text-xl font-bold tracking-tight text-slate-900">{{ $salesReturn->reference_number }}</h3>
            </div>
            <div class="text-right">
                <span class="inline-flex rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-emerald-800">
                    {{ $salesReturn->status ?? 'Completed' }}
                </span>
                <p class="mt-1 text-xs text-slate-500">
                    {{ $salesReturn->return_date ? \Carbon\Carbon::parse($salesReturn->return_date)->format('d M Y') : $salesReturn->created_at?->format('d M Y') }}
                </p>
            </div>
        </div>

        <!-- Detail Metadata Grid -->
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 rounded-xl bg-slate-50 p-3.5 text-xs">
            <div>
                <span class="text-slate-500 block">Cabang</span>
                <span class="font-semibold text-slate-900">{{ $salesReturn->branch?->name ?? '-' }}</span>
            </div>
            <div>
                <span class="text-slate-500 block">Pelanggan</span>
                <span class="font-semibold text-slate-900">{{ $salesReturn->customer_name ?: 'Pelanggan Umum' }}</span>
            </div>
            <div>
                <span class="text-slate-500 block">Ref. No. Struk</span>
                <span class="font-mono font-semibold text-slate-900">{{ $salesReturn->sale?->receipt_number ?? '-' }}</span>
            </div>
            <div>
                <span class="text-slate-500 block">Total Refund Konsumen</span>
                <span class="font-bold text-rose-700 font-mono">Rp {{ number_format($salesReturn->total_amount, 0, ',', '.') }}</span>
            </div>
        </div>

        <!-- Baris Kedua Metadata -->
        <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3 rounded-xl bg-slate-50/70 p-3 text-xs">
            <div>
                <span class="text-slate-500 block">Metode Refund</span>
                <span class="font-bold text-slate-900">
                    @if (strtolower($salesReturn->refund_method) === 'cash' || strtolower($salesReturn->refund_method) === 'tunai')
                        <span class="text-emerald-700">Tunai (Laci Kasir)</span>
                    @else
                        <span class="text-sky-700">Transfer Bank</span>
                    @endif
                </span>
            </div>
            <div>
                <span class="text-slate-500 block">Akun Pemotong</span>
                <span class="font-semibold text-slate-900">{{ $salesReturn->chartOfAccount?->name ?? '-' }}</span>
            </div>
            <div>
                <span class="text-slate-500 block">Kasir Pemroses</span>
                <span class="font-semibold text-slate-900">{{ $salesReturn->user?->name ?? '-' }}</span>
            </div>
        </div>

        @if ($salesReturn->reason)
            <div class="mt-3 rounded-lg border border-slate-200 bg-white p-3 text-xs text-slate-600">
                <span class="font-semibold text-slate-700">Alasan / Catatan Retur:</span> {{ $salesReturn->reason }}
            </div>
        @endif
    </div>

    <!-- Items Table -->
    <div>
        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Daftar Barang yang Diretur Pelanggan</h4>
        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5 w-12 text-center">No</th>
                        <th class="px-4 py-2.5">Nama Produk</th>
                        <th class="px-4 py-2.5 text-right w-28">Qty Retur</th>
                        <th class="px-4 py-2.5 text-right w-36">Harga Refund</th>
                        <th class="px-4 py-2.5 text-right w-40">Subtotal Refund</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($salesReturn->items as $index => $item)
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-4 py-3 text-xs text-center text-slate-400 font-mono">{{ $index + 1 }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $item->product?->name ?? 'Produk' }}</div>
                                @if ($item->product?->sku)
                                    <div class="font-mono text-xs text-slate-400">SKU: {{ $item->product->sku }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-semibold text-emerald-700">+{{ number_format($item->quantity) }} (Masuk)</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-700">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-slate-400">Tidak ada rincian item.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-slate-50 border-t border-slate-200 font-mono">
                    <tr>
                        <td colspan="4" class="px-4 py-3 text-right font-bold text-xs uppercase text-slate-600">Total Pengembalian Dana:</td>
                        <td class="px-4 py-3 text-right font-black text-rose-700">Rp {{ number_format($salesReturn->total_amount, 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Jurnal Akuntansi 4 Baris Berpasangan -->
    @if ($salesReturn->journalHeader)
        <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4 space-y-3">
            <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Jurnal Akuntansi Otomatis (4 Baris Entri)</span>
                <span class="font-mono text-xs text-slate-500">{{ $salesReturn->journalHeader->reference_number }}</span>
            </div>
            <p class="text-xs text-slate-600 italic">"{{ $salesReturn->journalHeader->description }}"</p>
            <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                    <thead class="bg-slate-50 text-slate-500 font-semibold uppercase">
                        <tr>
                            <th class="px-3 py-2">Kode Akun</th>
                            <th class="px-3 py-2">Nama Akun</th>
                            <th class="px-3 py-2 text-right">Debit (Rp)</th>
                            <th class="px-3 py-2 text-right">Kredit (Rp)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-mono">
                        @foreach ($salesReturn->journalHeader->journalLines as $line)
                            <tr>
                                <td class="px-3 py-2 text-slate-700 font-bold">{{ $line->chartOfAccount?->code ?? '-' }}</td>
                                <td class="px-3 py-2 font-sans font-medium text-slate-900">{{ $line->chartOfAccount?->name ?? '-' }}</td>
                                <td class="px-3 py-2 text-right {{ $line->debit > 0 ? 'text-emerald-700 font-bold' : 'text-slate-400' }}">
                                    {{ $line->debit > 0 ? number_format($line->debit, 0, ',', '.') : '-' }}
                                </td>
                                <td class="px-3 py-2 text-right {{ $line->credit > 0 ? 'text-amber-700 font-bold' : 'text-slate-400' }}">
                                    {{ $line->credit > 0 ? number_format($line->credit, 0, ',', '.') : '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50 border-t border-slate-200 font-mono font-bold text-slate-900">
                        <tr>
                            <td colspan="2" class="px-3 py-2 text-right text-slate-600">Total Seimbang:</td>
                            <td class="px-3 py-2 text-right text-emerald-700">
                                Rp {{ number_format($salesReturn->journalHeader->journalLines->sum('debit'), 0, ',', '.') }}
                            </td>
                            <td class="px-3 py-2 text-right text-amber-700">
                                Rp {{ number_format($salesReturn->journalHeader->journalLines->sum('credit'), 0, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif
</div>
