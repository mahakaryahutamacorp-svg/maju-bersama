<x-app-layout title="Buat Purchase Order" :breadcrumbs="[
    ['label' => 'Belanja Barang'],
    ['label' => 'Pesan Barang', 'url' => route('backoffice.purchase-orders.index')],
    ['label' => 'Buat PO Baru'],
]">
    <main class="mx-auto w-full max-w-7xl space-y-6 px-6 py-6 lg:px-10">
        @unless ($isMaster)
            <p class="text-xs font-semibold text-slate-500">
                Cabang pemesan: <span class="font-bold text-slate-900">{{ $currentUser->branch->name ?? '-' }}</span>
            </p>
        @endunless

        <!-- Error Banner -->
        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900 shadow-xs">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <div>
                        <p class="text-sm font-bold">Terjadi kesalahan input:</p>
                        <ul class="mt-1 list-inside list-disc space-y-0.5 text-xs text-rose-800">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <!-- Judul Form -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-amber-600">Langkah 1: Pesan Barang (PO)</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Penerbitan Purchase Order (PO)</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Surat pesanan resmi ke pemasok. PO tidak menambah stok dan belum mencatat hutang sampai barangnya diterima.
                </p>
            </div>
            <div>
                <a href="{{ route('backoffice.purchase-orders.index') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50">
                    Batal
                </a>
            </div>
        </div>

        <!-- Form Alpine.js -->
        <div x-data="poForm(@js($products))" class="space-y-6">
            <form method="POST" action="{{ route('backoffice.purchase-orders.store') }}" id="poFormElement" class="space-y-6">
                @csrf

                <!-- Bagian 1: Informasi Pemesanan -->
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="flex items-center gap-2 border-b border-slate-100 pb-3 text-base font-bold text-slate-900">
                        <span class="flex h-6 w-6 items-center justify-center rounded-md bg-slate-900 text-xs font-bold text-white">1</span>
                        Informasi Utama Pemesanan
                    </h3>

                    <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="lg:col-span-2">
                            <label for="supplier_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-600">
                                Pemasok / Supplier <span class="text-rose-500">*</span>
                            </label>
                            @if ($suppliers->isEmpty())
                                <div class="mt-1.5 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                                    Belum ada pemasok aktif. Silakan <a href="{{ route('backoffice.suppliers.create') }}" class="font-bold text-amber-900 underline">tambah pemasok baru</a> terlebih dahulu.
                                </div>
                            @else
                                <select id="supplier_id" name="supplier_id" required class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                                    <option value="">-- Pilih Pemasok --</option>
                                    @foreach ($suppliers as $sup)
                                        <option value="{{ $sup->id }}" @selected(old('supplier_id') == $sup->id)>
                                            {{ $sup->name }} {{ $sup->phone ? '('.$sup->phone.')' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                        </div>

                        <div>
                            <label for="order_date" class="block text-xs font-semibold uppercase tracking-wider text-slate-600">
                                Tanggal Order <span class="text-rose-500">*</span>
                            </label>
                            <input type="date" id="order_date" name="order_date" value="{{ old('order_date', $today) }}" required class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                        </div>

                        <div>
                            <label for="expected_date" class="block text-xs font-semibold uppercase tracking-wider text-slate-600">
                                Estimasi Kedatangan
                            </label>
                            <input type="date" id="expected_date" name="expected_date" value="{{ old('expected_date') }}" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                        </div>

                        @if ($isMaster)
                            <div class="lg:col-span-2">
                                <label for="branch_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-600">
                                    Cabang Pemesan
                                </label>
                                <select id="branch_id" name="branch_id" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected(old('branch_id', $currentUser->branch_id) == $branch->id)>
                                            {{ $branch->name }} ({{ $branch->code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="{{ $isMaster ? 'lg:col-span-2' : 'lg:col-span-4' }}">
                            <label for="notes" class="block text-xs font-semibold uppercase tracking-wider text-slate-600">
                                Catatan / Instruksi Pengiriman
                            </label>
                            <input type="text" id="notes" name="notes" value="{{ old('notes') }}" placeholder="Contoh: Kirim via ekspedisi langganan, kemas rapi..." class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                        </div>
                    </div>
                </section>

                <!-- Bagian 2: Rincian Barang -->
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col justify-between gap-3 border-b border-slate-100 p-6 pb-4 sm:flex-row sm:items-center">
                        <h3 class="flex items-center gap-2 text-base font-bold text-slate-900">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-slate-900 text-xs font-bold text-white">2</span>
                            Rincian Barang yang Dipesan
                        </h3>
                        <button type="button" @click="addItem()" id="btn-tambah-baris" class="inline-flex items-center gap-1.5 rounded-lg bg-sky-600 px-3.5 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-sky-700">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Tambah Baris
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                            <thead class="bg-slate-900 text-xs font-semibold uppercase tracking-wider text-white">
                                <tr>
                                    <th class="w-12 px-4 py-3.5 text-center">No</th>
                                    <th class="min-w-[240px] px-4 py-3.5">Produk</th>
                                    <th class="w-24 px-4 py-3.5 text-right">Stok Saat Ini</th>
                                    <th class="w-32 px-4 py-3.5 text-right">Kuantitas</th>
                                    <th class="w-44 px-4 py-3.5 text-right">Harga Beli Satuan (Rp)</th>
                                    <th class="w-48 px-4 py-3.5 text-right">Subtotal (Rp)</th>
                                    <th class="w-16 px-4 py-3.5 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="(item, index) in items" :key="index">
                                    <tr class="transition hover:bg-slate-50">
                                        <td class="px-4 py-3.5 text-center text-xs font-bold text-slate-400" x-text="index + 1"></td>

                                        <td class="px-4 py-3.5">
                                            <select
                                                :name="'items[' + index + '][product_id]'"
                                                x-model="item.product_id"
                                                @change="onProductSelect(index)"
                                                required
                                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                            >
                                                <option value="">-- Pilih Produk --</option>
                                                <template x-for="prod in products" :key="prod.id">
                                                    <option :value="prod.id" x-text="prod.name + ' (' + prod.sku + ')'"></option>
                                                </template>
                                            </select>
                                        </td>

                                        <td class="px-4 py-3.5 text-right font-mono text-xs text-slate-500">
                                            <span x-text="getSelectedProductStock(item.product_id)"></span>
                                        </td>

                                        <td class="px-4 py-3.5 text-right">
                                            <input
                                                type="number"
                                                :name="'items[' + index + '][quantity]'"
                                                x-model.number="item.quantity"
                                                @input="calculateSubtotal(index)"
                                                min="1"
                                                required
                                                placeholder="1"
                                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-right font-mono text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                            >
                                        </td>

                                        <td class="px-4 py-3.5 text-right">
                                            <input
                                                type="number"
                                                step="any"
                                                :name="'items[' + index + '][unit_price]'"
                                                x-model.number="item.unit_price"
                                                @input="calculateSubtotal(index)"
                                                min="0"
                                                required
                                                placeholder="0"
                                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-right font-mono text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                            >
                                        </td>

                                        <td class="px-4 py-3.5 text-right font-mono font-bold text-slate-950">
                                            <span x-text="formatRupiah(item.subtotal)"></span>
                                        </td>

                                        <td class="px-4 py-3.5 text-center">
                                            <button
                                                type="button"
                                                @click="removeItem(index)"
                                                :disabled="items.length <= 1"
                                                :class="items.length <= 1 ? 'cursor-not-allowed text-slate-400 opacity-30' : 'text-rose-500 hover:bg-rose-50 hover:text-rose-700'"
                                                class="rounded-lg p-1.5 transition-colors"
                                                title="Hapus baris ini"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                                <tr>
                                    <td colspan="5" class="px-4 py-4 text-right text-xs font-bold uppercase tracking-wider text-slate-700">
                                        Total Keseluruhan PO:
                                    </td>
                                    <td class="px-4 py-4 text-right font-mono text-xl font-black text-sky-900">
                                        <span x-text="formatRupiah(grandTotal)"></span>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                <!-- Info Alur -->
                <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 shadow-xs">
                    <div class="flex items-start gap-3">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sky-600 font-bold text-white">i</div>
                        <div class="text-xs leading-relaxed text-sky-900">
                            <p class="font-bold">Apa yang terjadi setelah PO diterbitkan?</p>
                            <p class="mt-0.5">
                                PO tersimpan dengan status <strong>Pending</strong>. Stok dan hutang ke pemasok baru tercatat otomatis di
                                <strong>Langkah 2: Terima Barang</strong>, saat Anda mencatat barang datang dan memilih nomor PO ini.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Tombol Aksi -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="{{ route('backoffice.purchase-orders.index') }}" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50">
                        Batal
                    </a>
                    <button type="submit" id="btn-submit-po" class="inline-flex items-center gap-2 rounded-xl bg-sky-600 px-8 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-sky-700 focus:ring-2 focus:ring-sky-500 focus:ring-offset-2">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Terbitkan Purchase Order
                    </button>
                </div>
            </form>
        </div>
    </main>

    <x-slot:scripts>
    <script>
        function poForm(productsList) {
            return {
                products: productsList || [],
                items: [
                    { product_id: '', quantity: 1, unit_price: 0, subtotal: 0 }
                ],
                init() {
                    this.recalculateAll();
                },
                addItem() {
                    this.items.push({
                        product_id: '',
                        quantity: 1,
                        unit_price: 0,
                        subtotal: 0
                    });
                },
                removeItem(index) {
                    if (this.items.length > 1) {
                        this.items.splice(index, 1);
                    }
                },
                onProductSelect(index) {
                    const selectedId = this.items[index].product_id;
                    const product = this.products.find(p => p.id == selectedId);
                    if (product) {
                        this.items[index].unit_price = parseFloat(product.purchase_price) || 0;
                    }
                    this.calculateSubtotal(index);
                },
                calculateSubtotal(index) {
                    const item = this.items[index];
                    const qty = Math.max(0, parseInt(item.quantity) || 0);
                    const price = Math.max(0, parseFloat(item.unit_price) || 0);
                    item.subtotal = qty * price;
                },
                recalculateAll() {
                    this.items.forEach((_, i) => this.calculateSubtotal(i));
                },
                get grandTotal() {
                    return this.items.reduce((sum, item) => sum + (parseFloat(item.subtotal) || 0), 0);
                },
                formatRupiah(amount) {
                    return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount || 0);
                },
                getSelectedProductStock(productId) {
                    if (!productId) return '-';
                    const product = this.products.find(p => p.id == productId);
                    return product ? (product.stock ?? 0) : '-';
                }
            };
        }
    </script>
    </x-slot:scripts>
</x-app-layout>
