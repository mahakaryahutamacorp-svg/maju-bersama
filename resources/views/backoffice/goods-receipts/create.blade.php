<x-app-layout title="Catat Barang Datang" :breadcrumbs="[
    ['label' => 'Belanja Barang'],
    ['label' => 'Terima Barang', 'url' => route('purchases.goods-receipts.index')],
    ['label' => 'Catat Barang Datang'],
]">
    <main class="mx-auto w-full max-w-7xl space-y-6 px-6 py-6 lg:px-10">
        <p class="text-xs font-semibold text-slate-500">
            Lokasi barang masuk: <span class="font-bold text-slate-900">{{ $centralBranch->name ?? 'Gudang Pusat' }}</span>
        </p>
        <!-- Error Banner -->
        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900 shadow-xs">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <div>
                        <p class="text-sm font-bold">Terjadi kesalahan input:</p>
                        <ul class="mt-1 list-inside list-disc text-xs text-rose-800 space-y-0.5">
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
                <p class="text-xs font-semibold uppercase tracking-wider text-amber-600">Belanja Barang · Langkah 2</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Catat Barang Datang</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Masukkan rincian barang yang diterima dari supplier. Anda dapat menarik data dari Purchase Order (PO) atau input mandiri.
                </p>
            </div>
            <div>
                <a href="/purchases/goods-receipts" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50">
                    Batal
                </a>
            </div>
        </div>

        <!-- Form Alpine.js -->
        <div x-data="goodsReceiptForm(@js($activePOs), @js($products))" class="space-y-6">
            <form method="POST" action="/purchases/goods-receipts" id="receiptForm" class="space-y-6">
                @csrf

                <!-- Input Hidden Purchase Order ID -->
                <input type="hidden" name="purchase_order_id" :value="selectedPoId || ''">

                <!-- Header Form: Tarik Data dari Purchase Order (Opsional) -->
                <section class="rounded-2xl border border-amber-300 bg-gradient-to-r from-amber-50 to-orange-50/40 p-6 shadow-xs">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <div class="flex items-start gap-3.5">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-slate-950 font-black shadow-xs text-sm">
                                PO
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-950 flex items-center gap-2">
                                    <span>Tarik Data dari Purchase Order</span>
                                    <span class="rounded bg-amber-200/80 px-2 py-0.5 text-[10px] font-bold text-amber-900 uppercase">Fitur Cepat</span>
                                </h3>
                                <p class="text-xs text-slate-600 mt-0.5">
                                    Pilih PO aktif (pending / partial) untuk mengisi otomatis daftar produk, sisa kuantitas belum diterima, dan harga beli kontrak PO.
                                </p>
                            </div>
                        </div>

                        <!-- Dropdown Select Tarik Data PO -->
                        <div class="w-full lg:w-96">
                            <label for="select_po" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Tarik dari Purchase Order (Opsional)
                            </label>
                            <select
                                id="select_po"
                                x-model="selectedPoId"
                                @change="loadFromPO(selectedPoId)"
                                class="w-full rounded-xl border border-amber-300 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-900 shadow-xs outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-400 transition-colors"
                            >
                                <option value="">-- Penerimaan Mandiri (Tanpa PO) --</option>
                                @foreach ($activePOs as $po)
                                    <option value="{{ $po['id'] }}">
                                        {{ $po['reference_number'] }} | {{ $po['supplier_name'] }} ({{ count($po['items']) }} item sisa)
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Banner Informasi PO Aktif Terpilih -->
                    <template x-if="selectedPo">
                        <div class="mt-4 rounded-xl border border-amber-300 bg-white/90 p-3.5 flex flex-wrap items-center justify-between gap-3 text-xs shadow-xs">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <span class="rounded-full bg-amber-500 px-2.5 py-0.5 font-extrabold text-slate-950 uppercase text-[10px]" x-text="'Status PO: ' + selectedPo.status"></span>
                                <span class="text-slate-800">Nomor PO: <strong class="font-mono text-indigo-900" x-text="selectedPo.reference_number"></strong></span>
                                <span class="text-slate-300">|</span>
                                <span class="text-slate-800">Supplier: <strong x-text="selectedPo.supplier_name"></strong></span>
                                <span class="text-slate-300">|</span>
                                <span class="text-slate-600">Tgl Order: <span x-text="selectedPo.order_date"></span></span>
                            </div>
                            <button
                                type="button"
                                @click="resetPO()"
                                class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition-colors"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                Batalkan Tautan PO (Mode Manual)
                            </button>
                        </div>
                    </template>
                </section>

                <!-- Section 1: Informasi Dokumen Penerimaan -->
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                    <h3 class="text-base font-bold text-slate-950 border-b border-slate-100 pb-3 flex items-center gap-2">
                        <span class="flex h-6 w-6 items-center justify-center rounded-md bg-slate-900 text-xs font-bold text-amber-400">1</span>
                        Informasi Dokumen Penerimaan
                    </h3>

                    <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-5">
                        <!-- Tanggal Penerimaan -->
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                                Tanggal Dokumen <span class="text-rose-500">*</span>
                            </label>
                            <input type="date" name="date" value="{{ old('date', $todayDate) }}" required class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                        </div>

                        <!-- No. Referensi / No. Surat Jalan -->
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                                No. Referensi / Surat Jalan
                            </label>
                            <input type="text" name="reference_number" value="{{ old('reference_number') }}" placeholder="Otomatis (atau isi manual)..." class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                            <span class="mt-1 block text-[11px] text-slate-400">Biarkan kosong untuk auto-generate nomor nota</span>
                        </div>

                        <!-- Metode Pembayaran -->
                        <div>
                            <label for="payment_type" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                                Metode Pembayaran <span class="text-rose-500">*</span>
                            </label>
                            <select id="payment_type" name="payment_type" x-model="paymentType" :disabled="selectedPo !== null" required class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 disabled:bg-slate-100 disabled:text-slate-500">
                                <option value="cash">Tunai (Kas)</option>
                                <option value="credit">Tempo (Hutang Dagang / Usaha)</option>
                            </select>
                            <!-- Hidden input ensure payment_type is submitted when select is disabled -->
                            <template x-if="selectedPo !== null">
                                <input type="hidden" name="payment_type" value="credit">
                            </template>
                            <span class="mt-1 block text-[11px] text-slate-400" x-text="paymentType === 'cash' ? 'Kredit ke Akun 1110 (Kas)' : 'Kredit ke Akun 2110 (Hutang Usaha)'"></span>
                        </div>

                        <!-- Pemasok: wajib dari daftar jika Tempo, opsional jika Tunai -->
                        <div class="sm:col-span-2">
                            <label for="supplier_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                                Pemasok
                                <span x-show="isCredit" class="text-rose-500">*</span>
                                <span x-show="!isCredit" class="font-normal normal-case text-slate-400">(opsional untuk tunai)</span>
                            </label>

                            <template x-if="selectedPo !== null">
                                <div class="mt-1.5 rounded-xl border border-slate-200 bg-slate-100 px-3.5 py-2.5 text-sm font-semibold text-slate-700" x-text="selectedPo.supplier_name"></div>
                            </template>

                            <div x-show="selectedPo === null">
                                <select id="supplier_id" name="supplier_id" x-model="supplierId"
                                        :required="isCredit && selectedPo === null"
                                        :disabled="selectedPo !== null"
                                        :class="isCredit && !supplierId ? 'border-rose-300 ring-1 ring-rose-200' : 'border-slate-300'"
                                        class="mt-1.5 w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                                    <option value="" x-text="isCredit ? '-- Pilih pemasok (wajib untuk tempo) --' : '-- Tidak terdaftar / beli putus --'"></option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier['id'] }}">{{ $supplier['name'] }}</option>
                                    @endforeach
                                </select>

                                <p x-show="isCredit && !supplierId" class="mt-1 text-[11px] font-semibold text-rose-600">
                                    Pembelian tempo harus memilih pemasok terdaftar supaya hutangnya tercatat di Buku Pemasok.
                                    @if ($suppliers->isEmpty())
                                        Belum ada pemasok aktif, <a href="{{ route('backoffice.suppliers.create') }}" class="underline">tambahkan dulu</a>.
                                    @endif
                                </p>

                                <div x-show="!isCredit && !supplierId" class="mt-2">
                                    <input type="text" name="supplier_name" x-model="supplierName"
                                           :disabled="isCredit || supplierId !== ''"
                                           maxlength="255"
                                           placeholder="Nama toko/warung (jika tidak terdaftar)..."
                                           class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                                </div>
                            </div>
                        </div>

                        <!-- Catatan Tambahan -->
                        <div class="sm:col-span-2 lg:col-span-5">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                                Catatan / Memo Tambahan
                            </label>
                            <input type="text" name="notes" value="{{ old('notes') }}" placeholder="Catatan nomor truk, pengemudi, atau keterangan penerimaan..." class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                        </div>
                    </div>
                </section>

                <!-- Section 2: Baris Produk Masuk (Tabel Interaktif Alpine.js) -->
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
                    <div class="border-b border-slate-100 bg-slate-50/70 p-5 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-base font-bold text-slate-950 flex items-center gap-2">
                                <span class="flex h-6 w-6 items-center justify-center rounded-md bg-slate-900 text-xs font-bold text-amber-400">2</span>
                                Daftar Barang Fisik yang Diterima
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Kuantitas otomatis diisi dengan sisa barang belum diterima. Staf gudang dapat mengedit kuantitas sesuai barang aktual yang tiba.
                            </p>
                        </div>
                        <div class="mt-3 sm:mt-0">
                            <!-- Tombol Tambah Baris Manual (Backward Compatibility) -->
                            <button type="button" @click="addItem()" class="inline-flex items-center gap-1.5 rounded-xl bg-sky-600 px-4 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-sky-700">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                Tambah Baris Produk
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto p-5">
                        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-xs font-bold uppercase tracking-wider text-slate-600">
                                    <th class="py-3 px-3 w-12 text-center">#</th>
                                    <th class="py-3 px-3 min-w-[280px]">Pilih Produk</th>
                                    <th class="py-3 px-3 w-36 text-center">Kuantitas Diterima</th>
                                    <th class="py-3 px-3 w-48 text-right">Harga Beli Satuan (Rp)</th>
                                    <th class="py-3 px-3 w-48 text-right">Subtotal (Rp)</th>
                                    <th class="py-3 px-3 w-16 text-center">Hapus</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="(item, index) in items" :key="index">
                                    <tr class="transition hover:bg-slate-50/70">
                                        <!-- Index -->
                                        <td class="py-3 px-3 text-center text-xs font-bold text-slate-400 font-mono" x-text="index + 1"></td>

                                        <!-- Pilihan Produk Dropdown / Info -->
                                        <td class="py-3 px-3">
                                            <select
                                                :name="'items[' + index + '][product_id]'"
                                                x-model="item.product_id"
                                                @change="onProductSelect(index)"
                                                required
                                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                            >
                                                <option value="">-- Pilih Produk Dari Katalog --</option>
                                                <template x-for="p in products" :key="p.id">
                                                    <option :value="p.id" :selected="p.id == item.product_id" x-text="p.sku + ' - ' + p.name + ' (Stok: ' + p.stock + ')'"></option>
                                                </template>
                                                @foreach ($products as $p)
                                                    <option value="{{ $p['id'] }}" class="hidden">
                                                        {{ $p['sku'] }} - {{ $p['name'] }}
                                                    </option>
                                                @endforeach
                                            </select>

                                            <!-- Sisa PO Info Badge & Stok Saat Ini -->
                                            <div class="mt-1 flex flex-wrap items-center gap-2 text-[11px]">
                                                <template x-if="item.remaining_quantity !== undefined">
                                                    <span class="rounded bg-amber-100 px-2 py-0.5 font-bold text-amber-800">
                                                        Sisa PO: <span x-text="item.remaining_quantity"></span> unit (Pesan: <span x-text="item.total_ordered"></span>, Diterima: <span x-text="item.already_received"></span>)
                                                    </span>
                                                </template>
                                                <span class="text-slate-500" x-show="item.product_id">
                                                    Stok Gudang Pusat: <strong class="text-slate-800" x-text="getSelectedProductStock(item.product_id)"></strong> unit
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Kuantitas yang Diterima (Editable) -->
                                        <td class="py-3 px-3">
                                            <input
                                                type="number"
                                                :name="'items[' + index + '][quantity]'"
                                                x-model.number="item.quantity"
                                                @input="calculateSubtotal(index)"
                                                min="1"
                                                required
                                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-center text-sm font-bold font-mono text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                            >
                                            <template x-if="item.remaining_quantity && item.quantity > item.remaining_quantity">
                                                <span class="mt-0.5 block text-[10px] text-amber-600 font-semibold">
                                                    ⚠️ Melebihi sisa PO (<span x-text="item.remaining_quantity"></span>)
                                                </span>
                                            </template>
                                        </td>

                                        <!-- Harga Beli Satuan -->
                                        <td class="py-3 px-3">
                                            <div class="relative">
                                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-xs text-slate-400 font-mono">Rp</span>
                                                <input
                                                    type="number"
                                                    :name="'items[' + index + '][unit_price]'"
                                                    x-model.number="item.unit_price"
                                                    @input="calculateSubtotal(index)"
                                                    min="0"
                                                    step="100"
                                                    required
                                                    class="w-full rounded-lg border border-slate-300 bg-white pl-9 pr-3 py-2 text-right text-sm font-mono font-semibold text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                                >
                                            </div>
                                        </td>

                                        <!-- Subtotal Real-time -->
                                        <td class="py-3 px-3 text-right">
                                            <span class="font-mono text-sm font-extrabold text-slate-950" x-text="formatRupiah(item.subtotal)"></span>
                                        </td>

                                        <!-- Tombol Hapus Baris -->
                                        <td class="py-3 px-3 text-center">
                                            <button
                                                type="button"
                                                @click="removeItem(index)"
                                                :disabled="items.length <= 1"
                                                :class="items.length <= 1 ? 'opacity-30 cursor-not-allowed text-slate-400' : 'text-rose-500 hover:text-rose-700 hover:bg-rose-50'"
                                                class="rounded-lg p-1.5 transition"
                                                title="Hapus baris ini"
                                            >
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer Tabel: Tombol Tambah & Grand Total Real-time -->
                    <div class="border-t border-slate-200 bg-slate-50/80 p-5 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                        <div>
                            <button type="button" @click="addItem()" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-100">
                                <svg class="h-4 w-4 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                Tambah Produk Lainnya
                            </button>
                        </div>
                        <div class="flex items-center gap-4 text-right">
                            <span class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Grand Total Penerimaan:</span>
                            <span class="font-mono text-2xl font-extrabold text-sky-950" x-text="formatRupiah(grandTotal)"></span>
                        </div>
                    </div>
                </section>

                <!-- Section 3: Preview Dampak Jurnal Akuntansi (Double-Entry Live Preview) -->
                <section class="rounded-2xl border border-sky-200 bg-sky-50/40 p-6 shadow-xs">
                    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-600 text-white">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-sky-950">Otomatisasi Jurnal Akuntansi Berpasangan</h4>
                                <p class="text-xs text-sky-800 mt-0.5">
                                    Saat formulir disimpan, sistem akan langsung mencatat mutasi jurnal ganda (double-entry) otomatis:
                                </p>
                            </div>
                        </div>

                        <!-- Baris Jurnal Live Preview -->
                        <div class="flex flex-wrap items-center gap-3 text-xs font-mono">
                            <div class="rounded-xl border border-emerald-300 bg-white p-2.5 text-left shadow-xs">
                                <span class="block text-[10px] font-bold text-emerald-700 font-sans uppercase">Debit (Persediaan 1210)</span>
                                <span class="font-bold text-emerald-950" x-text="formatRupiah(grandTotal)"></span>
                            </div>
                            <span class="text-slate-400 font-sans font-bold text-lg">=</span>
                            <div class="rounded-xl border border-sky-300 bg-white p-2.5 text-left shadow-xs">
                                <span class="block text-[10px] font-bold text-sky-700 font-sans uppercase" x-text="selectedPo ? 'Kredit (Hutang Usaha 2110)' : (paymentType === 'cash' ? 'Kredit (Kas 1110)' : 'Kredit (Hutang Usaha 2110)')"></span>
                                <span class="font-bold text-sky-950" x-text="formatRupiah(grandTotal)"></span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Tombol Submit Akhir -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="/purchases/goods-receipts" class="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50">
                        Batal
                    </a>
                    <button type="submit" id="btn-submit-receipt" :disabled="needsSupplier" :title="needsSupplier ? 'Pilih pemasok terlebih dahulu untuk pembelian tempo' : ''" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-7 py-3 text-sm font-bold text-white shadow-md transition hover:bg-emerald-700 focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Simpan &amp; Tambah Stok
                    </button>
                </div>
            </form>
        </div>
    </main>

    <x-slot:scripts>
    <script>
        function goodsReceiptForm(activePOsList = [], productsList = []) {
            let activePOs = activePOsList || [];
            let products = productsList || [];

            // Fallback jika urutan argumen terbalik
            if (activePOs.length > 0 && ('purchase_price' in activePOs[0] || 'stock' in activePOs[0])) {
                const temp = activePOs;
                activePOs = Array.isArray(productsList) ? productsList : [];
                products = temp;
            }

            return {
                products: products,
                activePOs: activePOs,
                selectedPoId: '',
                selectedPo: null,
                supplierId: @js((string) old('supplier_id', '')),
                supplierName: @js((string) old('supplier_name', '')),
                paymentType: @js(old('payment_type', 'cash')),
                items: [
                    { product_id: '', product_name: '', sku: '', quantity: 1, unit_price: 0, subtotal: 0 }
                ],
                init() {
                    this.recalculateAll();
                    this.$watch('supplierId', value => { if (value) this.supplierName = ''; });
                },
                get isCredit() {
                    return this.selectedPo !== null || this.paymentType === 'credit';
                },
                get needsSupplier() {
                    return this.selectedPo === null && this.paymentType === 'credit' && !this.supplierId;
                },
                loadFromPO(poId) {
                    if (!poId) {
                        this.resetPO();
                        return;
                    }

                    const po = this.activePOs.find(p => p.id == poId);
                    if (!po) {
                        this.resetPO();
                        return;
                    }

                    this.selectedPo = po;
                    this.selectedPoId = po.id;
                    this.supplierName = po.supplier_name || '';
                    this.paymentType = 'credit';

                    // Bersihkan tabel rincian barang, lalu isi otomatis dengan sisa item dari PO
                    const validItems = [];
                    if (Array.isArray(po.items)) {
                        po.items.forEach(poItem => {
                            // Logika Kuantitas: sisa barang yang belum diterima (quantity - received_quantity)
                            // Jika sisanya 0, jangan tampilkan baris tersebut
                            const totalQty = parseInt(poItem.quantity) || 0;
                            const receivedQty = parseInt(poItem.received_quantity) || 0;
                            const remaining = poItem.remaining_quantity !== undefined 
                                ? parseInt(poItem.remaining_quantity) 
                                : Math.max(0, totalQty - receivedQty);

                            if (remaining > 0) {
                                const price = parseFloat(poItem.unit_price) || 0;
                                validItems.push({
                                    product_id: poItem.product_id,
                                    product_name: poItem.product_name || '',
                                    sku: poItem.sku || '',
                                    quantity: remaining, // default ke sisa barang belum diterima
                                    unit_price: price,
                                    subtotal: remaining * price,
                                    remaining_quantity: remaining,
                                    total_ordered: totalQty,
                                    already_received: receivedQty,
                                });
                            }
                        });
                    }

                    if (validItems.length > 0) {
                        this.items = validItems;
                    } else {
                        alert('Semua item pada Purchase Order ini telah diterima penuh.');
                        this.items = [{ product_id: '', product_name: '', sku: '', quantity: 1, unit_price: 0, subtotal: 0 }];
                    }

                    this.recalculateAll();
                },
                resetPO() {
                    this.selectedPo = null;
                    this.selectedPoId = '';
                    this.supplierId = '';
                    this.supplierName = '';
                    this.paymentType = 'cash';
                    this.items = [{ product_id: '', product_name: '', sku: '', quantity: 1, unit_price: 0, subtotal: 0 }];
                    this.recalculateAll();
                },
                addItem() {
                    this.items.push({
                        product_id: '',
                        product_name: '',
                        sku: '',
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
                        this.items[index].product_name = product.name;
                        this.items[index].sku = product.sku;
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
                    const product = this.products.find(p => p.id == productId);
                    return product ? (product.stock ?? 0) : 0;
                }
            };
        }
    </script>
    </x-slot:scripts>
</x-app-layout>
