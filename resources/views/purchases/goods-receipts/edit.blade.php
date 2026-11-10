<x-app-layout :title="'Ubah '.$receipt->reference_number" :breadcrumbs="[
    ['label' => 'Belanja Barang'],
    ['label' => 'Terima Barang', 'url' => route('purchases.goods-receipts.index')],
    ['label' => 'Ubah '.$receipt->reference_number],
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
                <p class="text-xs font-semibold uppercase tracking-wider text-amber-600">Koreksi penerimaan</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">Ubah {{ $receipt->reference_number }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Mengubah tunai menjadi tempo memindahkan jurnal dari Kas ke Hutang Dagang, dan hutangnya masuk ke Buku Pemasok. Stok ikut berubah hanya jika kuantitas barang diubah.
                </p>
            </div>
            <a href="{{ route('purchases.goods-receipts.show', $receipt->id) }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50">
                Batal
            </a>
        </div>

        @if ($lockReason)
            <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
                {{ $lockReason }} Tanggal dan catatan masih bisa diubah.
            </div>
        @endif

        @if ($receipt->purchaseOrder)
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                Nota ini berasal dari pesanan {{ $receipt->purchaseOrder->reference_number }}
                ({{ $receipt->purchaseOrder->supplier?->name ?? $receipt->supplier_name }}).
                Pembayarannya tetap tempo, mengikuti pesanan.
            </div>
        @endif

        <form method="POST" action="{{ route('purchases.goods-receipts.update', $receipt->id) }}" class="space-y-6"
              x-data="receiptEditor(@js($products), @js($initialItems), @js((bool) $lockReason), @js($receipt->purchase_order_id ? 'credit' : old('payment_type', $receipt->payment_type)))">
            @csrf
            @method('PUT')

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">No. Referensi</label>
                        <p class="mt-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 font-mono text-sm font-bold text-slate-800">{{ $receipt->reference_number }}</p>
                    </div>
                    <div>
                        <label for="date" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">Tanggal Dokumen</label>
                        <input id="date" type="date" name="date" required value="{{ old('date', $receipt->date->toDateString()) }}" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                    </div>

                    <div>
                        <label for="payment_type" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">Metode Pembayaran</label>
                        @if ($receipt->purchase_order_id || $lockReason)
                            <input type="hidden" name="payment_type" value="{{ $receipt->purchase_order_id ? 'credit' : $receipt->payment_type }}">
                            <p class="mt-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-700">
                                {{ ($receipt->purchase_order_id ? 'credit' : $receipt->payment_type) === 'credit' ? 'Tempo (Hutang)' : 'Tunai (Kas)' }}
                            </p>
                        @else
                            <select id="payment_type" name="payment_type" x-model="paymentType" required class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                                <option value="cash">Tunai (Kas)</option>
                                <option value="credit">Tempo (Hutang Dagang)</option>
                            </select>
                            <p class="mt-1 text-[11px] text-slate-500" x-text="paymentType === 'credit' ? 'Jurnal kredit pindah ke Hutang Dagang (2110).' : 'Jurnal kredit tetap di Kas (1110).'"></p>
                        @endif
                    </div>

                    <div>
                        <label for="supplier_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                            Pemasok
                            <span class="font-normal normal-case text-slate-400" x-show="paymentType === 'credit'">(wajib untuk tempo)</span>
                        </label>
                        @if ($receipt->purchase_order_id)
                            <input type="hidden" name="supplier_id" value="{{ $receipt->supplier_id }}">
                            <p class="mt-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-700">{{ $receipt->supplier_name ?: '—' }}</p>
                        @elseif ($lockReason)
                            <input type="hidden" name="supplier_id" value="{{ $receipt->supplier_id }}">
                            <input type="hidden" name="supplier_name" value="{{ $receipt->supplier_name }}">
                            <p class="mt-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-700">{{ $receipt->supplier_name ?: '—' }}</p>
                        @else
                            <select id="supplier_id" name="supplier_id" x-model="supplierId" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                                <option value="">— Beli putus / tidak terdaftar —</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="supplier_name" x-show="paymentType === 'cash' && supplierId === ''" value="{{ old('supplier_name', $receipt->supplier_id ? '' : $receipt->supplier_name) }}" maxlength="255" placeholder="Nama pemasok jika tidak terdaftar" class="mt-2 w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                        @endif
                    </div>

                    <div class="sm:col-span-2">
                        <label for="notes" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">Catatan</label>
                        <input id="notes" type="text" name="notes" value="{{ old('notes', $receipt->notes) }}" maxlength="1000" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h3 class="text-sm font-bold text-slate-950">Barang yang diterima</h3>
                    @unless ($lockReason || $receipt->purchase_order_id)
                        <button type="button" @click="addItem()" class="rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-sky-700">Tambah Baris</button>
                    @endunless
                </div>
                <div class="overflow-x-auto p-5">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs font-bold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-2 py-2">Produk</th>
                                <th class="px-2 py-2 w-32 text-center">Kuantitas</th>
                                <th class="px-2 py-2 w-40 text-right">Harga Satuan</th>
                                <th class="px-2 py-2 w-16"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, index) in items" :key="index">
                                <tr class="border-t border-slate-100">
                                    <td class="px-2 py-3">
                                        <template x-if="locked || fromPurchaseOrder">
                                            <input type="hidden" :name="'items[' + index + '][product_id]'" :value="item.product_id">
                                        </template>
                                        <select :name="(locked || fromPurchaseOrder) ? null : ('items[' + index + '][product_id]')" x-model="item.product_id" @change="fillPrice(index)" :required="!locked && !fromPurchaseOrder" :disabled="locked || fromPurchaseOrder" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100">
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
                                        <input type="number" :name="'items[' + index + '][unit_price]'" x-model="item.unit_price" min="0" step="0.01" required :readonly="locked || fromPurchaseOrder" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-right font-mono text-sm read-only:bg-slate-100">
                                    </td>
                                    <td class="px-2 py-3 text-center">
                                        <button type="button" x-show="!locked && !fromPurchaseOrder && items.length > 1" @click="items.splice(index, 1)" class="text-xs font-semibold text-rose-700">Hapus</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="flex justify-end gap-2">
                <a href="{{ route('purchases.goods-receipts.show', $receipt->id) }}" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Batal</a>
                <button type="submit" class="rounded-xl bg-sky-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-sky-700">Simpan Koreksi</button>
            </div>
        </form>
    </main>

    <script>
        function receiptEditor(products, initialItems, locked, paymentType) {
            return {
                products,
                locked,
                fromPurchaseOrder: @js((bool) $receipt->purchase_order_id),
                paymentType,
                supplierId: @js((string) old('supplier_id', $receipt->supplier_id ?? '')),
                items: initialItems.length ? initialItems.map((item) => ({
                    product_id: String(item.product_id ?? ''),
                    quantity: item.quantity,
                    unit_price: item.unit_price,
                })) : [{ product_id: '', quantity: 1, unit_price: 0 }],
                addItem() {
                    this.items.push({ product_id: '', quantity: 1, unit_price: 0 });
                },
                fillPrice(index) {
                    const product = this.products.find((row) => String(row.id) === String(this.items[index].product_id));
                    if (product && !this.fromPurchaseOrder) {
                        this.items[index].unit_price = product.purchase_price;
                    }
                },
            };
        }
    </script>
</x-app-layout>
