<div class="space-y-6">
    <!-- Header Modal Info -->
    <div class="border-b border-slate-200 pb-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-sky-50 px-2.5 py-0.5 text-xs font-semibold text-sky-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-sky-500"></span>
                    Transfer Stok Antar Gudang / Cabang
                </span>
                <h3 class="mt-1 font-mono text-xl font-bold tracking-tight text-slate-900">{{ $stockTransfer->reference_number }}</h3>
            </div>
            <div class="flex items-center gap-2.5">
                <a href="{{ route('stock-transfers.print', $stockTransfer->reference_number) }}" 
                   target="_blank"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 hover:text-slate-900 transition-colors">
                    🖨️ Cetak Surat Jalan
                </a>
                <div class="text-right">
                    <span class="inline-flex rounded-md px-2.5 py-1 text-xs font-bold uppercase tracking-wider {{ $stockTransfer->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-sky-100 text-sky-800' }}">
                        {{ $stockTransfer->status ?? 'Completed' }}
                    </span>
                    <p class="mt-1 text-xs text-slate-500">{{ $stockTransfer->transfer_date?->format('d M Y') ?? $stockTransfer->created_at?->format('d M Y') }}</p>
                </div>
            </div>
        </div>

        <!-- Detail Metadata Grid -->
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 rounded-xl bg-slate-50 p-3.5 text-xs">
            <div>
                <span class="text-slate-500 block">Dari Gudang / Cabang</span>
                <span class="font-semibold text-slate-900">{{ $stockTransfer->fromBranch?->name ?? $stockTransfer->sourceBranch?->name ?? '-' }}</span>
            </div>
            <div>
                <span class="text-slate-500 block">Tujuan Gudang / Cabang</span>
                <span class="font-semibold text-slate-900">{{ $stockTransfer->toBranch?->name ?? $stockTransfer->destinationBranch?->name ?? '-' }}</span>
            </div>
            <div>
                <span class="text-slate-500 block">Petugas / Operator</span>
                <span class="font-semibold text-slate-900">{{ $stockTransfer->user?->name ?? $stockTransfer->creator?->name ?? '-' }}</span>
            </div>
            <div>
                <span class="text-slate-500 block">Total Macam Produk</span>
                <span class="font-bold text-sky-700 font-mono">{{ $stockTransfer->items->count() }} Produk</span>
            </div>
        </div>

        @if($stockTransfer->notes)
            <div class="mt-3 rounded-lg border border-slate-200 bg-white p-3 text-xs text-slate-600">
                <span class="font-semibold text-slate-700">Catatan Transfer:</span> {{ $stockTransfer->notes }}
            </div>
        @endif
    </div>

    <!-- Items Table -->
    <div>
        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Daftar Produk yang Ditransfer</h4>
        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5">No</th>
                        <th class="px-4 py-2.5">Produk yang Ditransfer</th>
                        <th class="px-4 py-2.5 text-right">Qty</th>
                        <th class="px-4 py-2.5">Gudang / Cabang Asal</th>
                        <th class="px-4 py-2.5">Gudang / Cabang Tujuan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @php $totalQty = 0; @endphp
                    @forelse ($stockTransfer->items as $index => $item)
                        @php 
                            $totalQty += $item->quantity; 
                            $product = $item->product ?? $item->sourceProduct ?? $item->destinationProduct;
                        @endphp
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-4 py-3 text-xs text-slate-400">{{ $index + 1 }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $product?->name ?? 'Produk' }}</div>
                                @if($product?->sku)
                                    <div class="font-mono text-xs text-slate-400">SKU: {{ $product->sku }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-sky-700">{{ number_format($item->quantity) }}</td>
                            <td class="px-4 py-3 text-xs text-slate-700">
                                <span class="inline-flex items-center gap-1">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                                    {{ $stockTransfer->fromBranch?->name ?? $stockTransfer->sourceBranch?->name ?? '-' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-700">
                                <span class="inline-flex items-center gap-1">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    {{ $stockTransfer->toBranch?->name ?? $stockTransfer->destinationBranch?->name ?? '-' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-slate-400">Tidak ada item transfer.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-slate-50/80 font-semibold text-slate-900 border-t border-slate-200">
                    <tr>
                        <td colspan="2" class="px-4 py-3 text-right text-xs uppercase tracking-wider text-slate-500">Total Unit Ditransfer</td>
                        <td class="px-4 py-3 text-right font-mono text-base text-sky-700">{{ number_format($totalQty) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Footer Modal Actions -->
    <div class="flex items-center justify-between border-t border-slate-200 pt-4">
        <p class="text-xs text-slate-500">
            Cetak dokumen fisik Delivery Note untuk dibawa oleh kurir logistik saat pengiriman barang.
        </p>
        <a href="{{ route('stock-transfers.print', $stockTransfer->reference_number) }}" 
           target="_blank"
           class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 hover:text-slate-900 transition-colors">
            🖨️ Cetak Surat Jalan
        </a>
    </div>
</div>
