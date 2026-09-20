<div class="space-y-6">
    <!-- Header Modal Info -->
    <div class="border-b border-slate-200 pb-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-teal-50 px-2.5 py-0.5 text-xs font-semibold text-teal-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-teal-500"></span>
                    Penerimaan Barang (Goods Receipt)
                </span>
                <h3 class="mt-1 font-mono text-xl font-bold tracking-tight text-slate-900">{{ $goodsReceipt->reference_number }}</h3>
            </div>
            <div class="text-right">
                <span class="inline-flex rounded-md bg-teal-100 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-teal-800">
                    {{ $goodsReceipt->payment_type ?? 'Credit' }}
                </span>
                <p class="mt-1 text-xs text-slate-500">{{ $goodsReceipt->date?->format('d M Y') ?? $goodsReceipt->created_at?->format('d M Y') }}</p>
            </div>
        </div>

        <!-- Detail Metadata Grid -->
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 rounded-xl bg-slate-50 p-3.5 text-xs">
            <div>
                <span class="text-slate-500 block">Cabang</span>
                <span class="font-semibold text-slate-900">{{ $goodsReceipt->branch?->name ?? '-' }}</span>
            </div>
            <div>
                <span class="text-slate-500 block">Supplier</span>
                <span class="font-semibold text-slate-900">{{ $goodsReceipt->supplier_name ?? $goodsReceipt->purchaseOrder?->supplier?->name ?? '-' }}</span>
            </div>
            <div>
                <span class="text-slate-500 block">Ref Purchase Order</span>
                <span class="font-mono font-semibold text-slate-900">{{ $goodsReceipt->purchaseOrder?->po_number ?? '-' }}</span>
            </div>
            <div>
                <span class="text-slate-500 block">Total Nilai Tagihan</span>
                <span class="font-bold text-teal-700 font-mono">Rp {{ number_format($goodsReceipt->total_amount, 0, ',', '.') }}</span>
            </div>
        </div>

        @if($goodsReceipt->notes)
            <div class="mt-3 rounded-lg border border-slate-200 bg-white p-3 text-xs text-slate-600">
                <span class="font-semibold text-slate-700">Catatan Penerimaan:</span> {{ $goodsReceipt->notes }}
            </div>
        @endif
    </div>

    <!-- Items Table -->
    <div>
        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Daftar Barang Masuk</h4>
        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5">No</th>
                        <th class="px-4 py-2.5">Nama Produk</th>
                        <th class="px-4 py-2.5 text-right">Qty Masuk</th>
                        <th class="px-4 py-2.5 text-right">Harga Beli</th>
                        <th class="px-4 py-2.5 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($goodsReceipt->items as $index => $item)
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-4 py-3 text-xs text-slate-400">{{ $index + 1 }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $item->product?->name ?? 'Produk' }}</div>
                                @if($item->product?->sku)
                                    <div class="font-mono text-xs text-slate-400">SKU: {{ $item->product->sku }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-semibold text-slate-700">{{ number_format($item->quantity_received) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-700">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-slate-400">Tidak ada rincian item.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
