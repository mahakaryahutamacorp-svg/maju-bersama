<x-app-layout :title="'Nota '.$sale->receipt_number" :breadcrumbs="[
    ['label' => 'Riwayat Penjualan', 'url' => route('reports.sales')],
    ['label' => $sale->receipt_number],
]">
    <main class="mx-auto w-full max-w-5xl space-y-6 px-6 py-6 lg:px-8">
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-300 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900">
                <p class="text-sm font-bold">Nota belum diubah</p>
                <ul class="mt-1 list-inside list-disc text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-amber-600">Koreksi transaksi</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">{{ $sale->receipt_number }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Jika nota ini dihapus atau kuantitasnya dikurangi, stok kembali ke {{ $stockOrigin }}.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('reports.sales') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50">
                    Kembali
                </a>
                <a href="{{ url('/pos/receipt/'.$sale->receipt_number) }}" target="_blank" rel="noopener" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50">
                    Cetak Struk
                </a>
                @unless ($lockReason)
                    <a href="{{ route('reports.sales.edit', $sale) }}" class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-2 text-sm font-semibold text-sky-800 hover:bg-sky-100">
                        Ubah
                    </a>
                    <form method="POST" action="{{ route('reports.sales.destroy', $sale) }}" onsubmit="return confirm('Hapus nota {{ $sale->receipt_number }}? Stok akan dikembalikan ke {{ $stockOrigin }} dan jurnal penjualan dibatalkan.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-800 hover:bg-rose-100">
                            Hapus
                        </button>
                    </form>
                @endunless
            </div>
        </div>

        @if ($lockReason)
            <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
                {{ $lockReason }}
            </div>
        @endif

        <section class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-xs sm:grid-cols-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Tanggal</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $sale->created_at?->isoFormat('D MMMM Y HH:mm') }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Cabang / tempat stok</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $stockOrigin }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Pelanggan</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $sale->customer?->name ?: 'Pelanggan Umum' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Kasir</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $sale->user?->name ?: 'Kasir POS' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Metode</p>
                <p class="mt-1 text-sm font-semibold uppercase text-slate-900">{{ $sale->payment_method }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total</p>
                <p class="mt-1 font-mono text-sm font-bold text-slate-950">Rp {{ number_format((float) $sale->total_amount, 2, ',', '.') }}</p>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
            <div class="border-b border-slate-100 px-5 py-4">
                <h3 class="text-sm font-bold text-slate-950">Barang pada nota</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Produk</th>
                            <th class="px-5 py-3 text-right">Kuantitas</th>
                            <th class="px-5 py-3 text-right">Harga</th>
                            <th class="px-5 py-3 text-right">Diskon</th>
                            <th class="px-5 py-3 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($sale->items as $item)
                            <tr>
                                <td class="px-5 py-3">
                                    <p class="font-semibold text-slate-900">{{ $item->product?->name ?: 'Produk #'.$item->product_id }}</p>
                                    <p class="text-xs text-slate-500">{{ $item->product?->sku }}</p>
                                </td>
                                <td class="px-5 py-3 text-right font-mono">{{ \App\Support\DecimalQuantity::present($item->quantity) }}</td>
                                <td class="px-5 py-3 text-right font-mono">{{ number_format((float) $item->price, 2, ',', '.') }}</td>
                                <td class="px-5 py-3 text-right font-mono">{{ number_format((float) $item->discount_amount, 2, ',', '.') }}</td>
                                <td class="px-5 py-3 text-right font-mono font-semibold">{{ number_format((float) $item->subtotal, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500">Nota ini tidak memiliki barang.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</x-app-layout>
