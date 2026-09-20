<div class="space-y-6">
    <!-- Header Modal Info -->
    <div class="border-b border-slate-200 pb-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                    Penyesuaian Stok / Stock Opname
                </span>
                <h3 class="mt-1 font-mono text-xl font-bold tracking-tight text-slate-900">{{ $stockAdjustment->reference_number }}</h3>
            </div>
            <div class="text-right">
                <span class="inline-flex rounded-md bg-amber-100 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-amber-800">
                    Stock Adjustment
                </span>
                <p class="mt-1 text-xs text-slate-500">{{ $stockAdjustment->date?->format('d M Y') ?? $stockAdjustment->created_at?->format('d M Y') }}</p>
            </div>
        </div>

        <!-- Detail Metadata Grid -->
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 rounded-xl bg-slate-50 p-3.5 text-xs">
            <div>
                <span class="text-slate-500 block">Cabang</span>
                <span class="font-semibold text-slate-900">{{ $stockAdjustment->branch?->name ?? '-' }}</span>
            </div>
            <div>
                <span class="text-slate-500 block">Tanggal Opname</span>
                <span class="font-semibold text-slate-900">{{ $stockAdjustment->date?->format('d M Y') ?? '-' }}</span>
            </div>
            <div>
                <span class="text-slate-500 block">Total Selisih Kurang</span>
                <span class="font-bold text-rose-600 font-mono">Rp {{ number_format($stockAdjustment->total_loss_value ?? 0, 0, ',', '.') }}</span>
            </div>
            <div>
                <span class="text-slate-500 block">Total Selisih Lebih</span>
                <span class="font-bold text-emerald-600 font-mono">Rp {{ number_format($stockAdjustment->total_gain_value ?? 0, 0, ',', '.') }}</span>
            </div>
        </div>

        @if($stockAdjustment->notes)
            <div class="mt-3 rounded-lg border border-slate-200 bg-white p-3 text-xs text-slate-600">
                <span class="font-semibold text-slate-700">Alasan Penyesuaian:</span> {{ $stockAdjustment->notes }}
            </div>
        @endif
    </div>

    <!-- Items Table -->
    <div>
        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Item Penyesuaian Fisik</h4>
        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5">No</th>
                        <th class="px-4 py-2.5">Produk</th>
                        <th class="px-4 py-2.5 text-right">Stok Buku</th>
                        <th class="px-4 py-2.5 text-right">Stok Fisik</th>
                        <th class="px-4 py-2.5 text-right">Selisih</th>
                        <th class="px-4 py-2.5 text-right">Nilai Selisih</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($stockAdjustment->items as $index => $item)
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-4 py-3 text-xs text-slate-400">{{ $index + 1 }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $item->product?->name ?? 'Produk' }}</div>
                                @if($item->product?->sku)
                                    <div class="font-mono text-xs text-slate-400">SKU: {{ $item->product->sku }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-slate-600">{{ number_format($item->book_quantity ?? 0) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-600">{{ number_format($item->physical_quantity ?? 0) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-semibold {{ ($item->difference_quantity ?? 0) < 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                                {{ ($item->difference_quantity ?? 0) > 0 ? '+' : '' }}{{ number_format($item->difference_quantity ?? 0) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">
                                Rp {{ number_format($item->adjustment_value ?? 0, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-400">Tidak ada rincian item.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
