<x-app-layout :title="'Ubah '.$sale->receipt_number" :breadcrumbs="[
    ['label' => 'Riwayat Penjualan', 'url' => route('reports.sales')],
    ['label' => $sale->receipt_number, 'url' => route('reports.sales.show', $sale)],
    ['label' => 'Ubah'],
]">
    <main class="mx-auto w-full max-w-5xl space-y-6 px-6 py-6 lg:px-8">
        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900 shadow-xs">
                <p class="text-sm font-bold">Perubahan belum disimpan</p>
                <ul class="mt-1 list-inside list-disc text-xs text-rose-800">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-amber-600">Koreksi penjualan</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">Ubah {{ $sale->receipt_number }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Mengurangi kuantitas atau menghapus barang mengembalikan stok ke {{ $stockOrigin }}. Menambah kuantitas memotong stok dari tempat yang sama. Nomor nota dan tanggal transaksi tetap.
                </p>
            </div>
            <a href="{{ route('reports.sales.show', $sale) }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50">
                Batal
            </a>
        </div>

        @if ($lockReason)
            <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
                {{ $lockReason }}
            </div>
        @endif

        <form method="POST" action="{{ route('reports.sales.update', $sale) }}" class="space-y-6"
              x-data="saleEditor(@js($products), @js($initialItems), @js((bool) $lockReason), @js(strtolower((string) old('payment_method', $sale->payment_method ?: 'cash'))))">
            @csrf
            @method('PUT')

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">No. Nota</label>
                        <p class="mt-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 font-mono text-sm font-bold text-slate-800">{{ $sale->receipt_number }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">Tempat stok</label>
                        <p class="mt-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-800">{{ $stockOrigin }}</p>
                    </div>
                    <div>
                        <label for="payment_method" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">Metode Pembayaran</label>
                        <select id="payment_method" name="payment_method" x-model="paymentMethod" required @disabled($lockReason) class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 disabled:bg-slate-100">
                            <option value="cash">Tunai</option>
                            <option value="transfer">Transfer</option>
                            <option value="qris">QRIS</option>
                            <option value="tempo">Tempo (Piutang)</option>
                        </select>
                    </div>
                    <div>
                        <label for="customer_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">Pelanggan</label>
                        <select id="customer_id" name="customer_id" @disabled($lockReason) class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 disabled:bg-slate-100">
                            <option value="">Pelanggan Umum</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" @selected((string) old('customer_id', $sale->customer_id) === (string) $customer->id)>{{ $customer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="paymentMethod === 'tempo'" x-cloak>
                        <label for="due_date" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">Jatuh Tempo</label>
                        <input id="due_date" type="date" name="due_date" value="{{ old('due_date', $sale->due_date?->toDateString()) }}" @disabled($lockReason) class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 disabled:bg-slate-100">
                    </div>
                    <div>
                        <label for="discount_amount" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">Diskon Nota</label>
                        <input id="discount_amount" type="number" name="discount_amount" min="0" step="0.01" value="{{ old('discount_amount', (float) $sale->discount_amount) }}" @readonly($lockReason) class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-right font-mono text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 read-only:bg-slate-100">
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h3 class="text-sm font-bold text-slate-950">Barang</h3>
                    @unless ($lockReason)
                        <button type="button" @click="addItem()" class="rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-sky-700">Tambah Baris</button>
                    @endunless
                </div>
                <div class="overflow-x-auto p-5">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs font-bold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-2 py-2">Produk</th>
                                <th class="px-2 py-2 w-32 text-center">Kuantitas</th>
                                <th class="px-2 py-2 w-40 text-right">Harga Jual</th>
                                <th class="px-2 py-2 w-36 text-right">Diskon Baris</th>
                                <th class="px-2 py-2 w-16"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, index) in items" :key="index">
                                <tr class="border-t border-slate-100">
                                    <td class="px-2 py-3">
                                        <select :name="'items[' + index + '][product_id]'" x-model="item.product_id" @change="fillPrice(index)" required :disabled="locked" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100">
                                            <option value="">Pilih produk</option>
                                            <template x-for="product in products" :key="product.id">
                                                <option :value="String(product.id)" x-text="product.sku + ' — ' + product.name"></option>
                                            </template>
                                        </select>
                                    </td>
                                    <td class="px-2 py-3">
                                        <input type="number" :name="'items[' + index + '][quantity]'" x-model="item.quantity" min="0.001" step="0.001" required :readonly="locked" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-center font-mono text-sm read-only:bg-slate-100">
                                    </td>
                                    <td class="px-2 py-3">
                                        <input type="number" :name="'items[' + index + '][price]'" x-model="item.price" min="0" step="0.01" required :readonly="locked" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-right font-mono text-sm read-only:bg-slate-100">
                                    </td>
                                    <td class="px-2 py-3">
                                        <input type="number" :name="'items[' + index + '][discount_amount]'" x-model="item.discount_amount" min="0" step="0.01" :readonly="locked" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-right font-mono text-sm read-only:bg-slate-100">
                                    </td>
                                    <td class="px-2 py-3 text-center">
                                        <button type="button" x-show="!locked && items.length > 1" @click="items.splice(index, 1)" class="text-xs font-semibold text-rose-700">Hapus</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </section>

            @unless ($lockReason)
                <div class="flex justify-end gap-2">
                    <a href="{{ route('reports.sales.show', $sale) }}" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Batal</a>
                    <button type="submit" class="rounded-xl bg-sky-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-sky-700">Simpan Koreksi</button>
                </div>
            @endunless
        </form>
    </main>

    <script>
        function saleEditor(products, initialItems, locked, paymentMethod) {
            return {
                products,
                locked,
                paymentMethod,
                items: initialItems.length ? initialItems.map((item) => ({
                    product_id: String(item.product_id ?? ''),
                    quantity: item.quantity,
                    price: item.price,
                    discount_amount: item.discount_amount ?? 0,
                })) : [{ product_id: '', quantity: 1, price: 0, discount_amount: 0 }],
                addItem() {
                    this.items.push({ product_id: '', quantity: 1, price: 0, discount_amount: 0 });
                },
                fillPrice(index) {
                    const product = this.products.find((row) => String(row.id) === String(this.items[index].product_id));
                    if (product) {
                        this.items[index].price = product.selling_price;
                    }
                },
            };
        }
    </script>
</x-app-layout>
