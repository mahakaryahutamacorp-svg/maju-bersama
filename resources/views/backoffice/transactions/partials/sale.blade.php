<div class="space-y-6">
    <!-- Header Modal Info -->
    <div class="border-b border-slate-200 pb-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    Penjualan POS
                </span>
                <h3 class="mt-1 font-mono text-xl font-bold tracking-tight text-slate-900">{{ $sale->receipt_number }}</h3>
            </div>
            <div class="text-right">
                <span class="inline-flex rounded-md px-2.5 py-1 text-xs font-bold uppercase tracking-wider {{ $sale->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                    {{ $sale->status ?? 'Completed' }}
                </span>
                <p class="mt-1 text-xs text-slate-500">{{ $sale->created_at?->format('d M Y, H:i') }}</p>
            </div>
        </div>

        <!-- Detail Metadata Grid -->
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 rounded-xl bg-slate-50 p-3.5 text-xs">
            <div>
                <span class="text-slate-500 block">Cabang</span>
                <span class="font-semibold text-slate-900">{{ $sale->branch?->name ?? '-' }}</span>
            </div>
            <div>
                <span class="text-slate-500 block">Kasir / Operator</span>
                <span class="font-semibold text-slate-900">{{ $sale->user?->name ?? '-' }}</span>
            </div>
            <div>
                <span class="text-slate-500 block">Metode Pembayaran</span>
                <span class="font-semibold uppercase text-slate-900">{{ $sale->payment_method ?? 'CASH' }}</span>
            </div>
            <div>
                <span class="text-slate-500 block">Total Transaksi</span>
                <span class="font-bold text-emerald-600 font-mono">Rp {{ number_format($sale->total_amount, 0, ',', '.') }}</span>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <div>
        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Rincian Barang yang Dijual</h4>
        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5">No</th>
                        <th class="px-4 py-2.5">Nama Produk</th>
                        <th class="px-4 py-2.5 text-right">Qty</th>
                        <th class="px-4 py-2.5 text-right">Harga Satuan</th>
                        <th class="px-4 py-2.5 text-right">Diskon</th>
                        <th class="px-4 py-2.5 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @php $totalQty = 0; @endphp
                    @forelse ($sale->items as $index => $item)
                        @php $totalQty += $item->quantity; @endphp
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-4 py-3 text-xs text-slate-400">{{ $index + 1 }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $item->product?->name ?? 'Produk dihapus' }}</div>
                                @if($item->product?->sku)
                                    <div class="font-mono text-xs text-slate-400">SKU: {{ $item->product->sku }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-semibold text-slate-700">{{ number_format($item->quantity) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-700">Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-500">Rp 0</td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-400">Tidak ada item dalam penjualan ini.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-slate-50/80 font-semibold text-slate-900 border-t border-slate-200">
                    <tr>
                        <td colspan="2" class="px-4 py-3 text-right text-xs uppercase tracking-wider text-slate-500">Total Keseluruhan</td>
                        <td class="px-4 py-3 text-right font-mono text-emerald-700">{{ number_format($totalQty) }}</td>
                        <td colspan="2"></td>
                        <td class="px-4 py-3 text-right font-mono text-base text-emerald-600">Rp {{ number_format($sale->total_amount, 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
