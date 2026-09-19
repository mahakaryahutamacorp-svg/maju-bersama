<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Purchase Order (PO) | Maju Bersama ERP</title>
    <meta name="description" content="Formulir penerbitan Purchase Order (PO) pemesanan barang ke supplier.">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">

    <!-- Header Utama -->
    <header class="border-b border-slate-800 bg-slate-950 text-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
            <div class="flex items-center gap-4">
                <a href="/backoffice" class="block">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-400">Maju Bersama ERP</p>
                    <h1 class="text-xl font-bold tracking-tight">Pengadaan &amp; Pembelian</h1>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <nav class="hidden items-center gap-4 text-sm text-slate-300 md:flex">
                    <a href="/pos" class="hover:text-white">POS Kasir</a>
                    <a href="/inventory" class="hover:text-white">Inventory</a>
                    <a href="{{ route('backoffice.suppliers.index') }}" class="hover:text-white">Supplier</a>
                    <a href="/backoffice" class="hover:text-white">Backoffice</a>
                </nav>
                @if ($isMaster)
                    <div class="rounded-full border border-amber-400/40 bg-amber-400/10 px-3 py-1 text-xs font-semibold text-amber-300">
                        👑 Master Pusat
                    </div>
                @else
                    <div class="rounded-full border border-sky-400/40 bg-sky-400/10 px-3 py-1 text-xs font-semibold text-sky-300">
                        🏪 {{ $currentUser->branch->name }}
                    </div>
                @endif
            </div>
        </div>
    </header>

    <!-- Sub-Navbar -->
    <div class="border-b border-slate-200 bg-white shadow-xs">
        <div class="mx-auto flex max-w-7xl items-center justify-between overflow-x-auto px-6 py-2.5 lg:px-8">
            <div class="flex items-center gap-2 text-sm font-medium">
                <a href="{{ route('backoffice.purchase-orders.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    &larr; Riwayat Purchase Order
                </a>
                <a href="{{ route('backoffice.purchase-orders.create') }}" class="rounded-lg bg-amber-500 px-3.5 py-2 font-bold text-slate-950 shadow-xs">
                    + Buat PO Baru
                </a>
                <a href="{{ route('backoffice.suppliers.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Data Supplier
                </a>
            </div>
        </div>
    </div>

    <!-- Main Container dengan Alpine.js -->
    <main class="mx-auto max-w-7xl px-6 py-8 lg:px-8">

        <!-- Error Alerts -->
        @if ($errors->any())
            <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900 shadow-xs">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <div>
                        <p class="text-sm font-bold">Harap perbaiki kesalahan berikut:</p>
                        <ul class="mt-1 list-inside list-disc text-xs text-rose-800 space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <!-- Form Alpine.js Root -->
        <div x-data="poForm(@js($products))" class="space-y-6">
            <form method="POST" action="{{ route('backoffice.purchase-orders.store') }}" id="poFormElement" class="space-y-6">
                @csrf

                <!-- Judul Halaman -->
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-amber-600">Purchase Order Dokumen</p>
                        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Penerbitan Purchase Order (PO)</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Dokumen resmi pemesanan barang ke Supplier. PO tidak menambah stok dan belum mencatat hutang hingga barang diterima.
                        </p>
                    </div>
                    <div>
                        <a href="{{ route('backoffice.purchase-orders.index') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50">
                            Batal
                        </a>
                    </div>
                </div>

                <!-- Bagian 1: Header Form (Informasi Dokumen & Supplier) -->
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                    <h3 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3 flex items-center gap-2">
                        <span class="flex h-6 w-6 items-center justify-center rounded-md bg-slate-900 text-xs font-bold text-amber-400">1</span>
                        Informasi Utama Pemesanan
                    </h3>

                    <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        <!-- Dropdown Supplier -->
                        <div class="lg:col-span-2">
                            <label for="supplier_id" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Supplier / Vendor <span class="text-rose-500">*</span>
                            </label>
                            @if ($suppliers->isEmpty())
                                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                                    Belum ada supplier aktif. Silakan <a href="{{ route('backoffice.suppliers.create') }}" class="font-bold underline text-amber-900">tambah supplier baru</a> terlebih dahulu.
                                </div>
                            @else
                                <select id="supplier_id" name="supplier_id" required class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                                    <option value="">-- Pilih Supplier --</option>
                                    @foreach ($suppliers as $sup)
                                        <option value="{{ $sup->id }}" {{ old('supplier_id') == $sup->id ? 'selected' : '' }}>
                                            {{ $sup->name }} {{ $sup->phone ? '('.$sup->phone.')' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                        </div>

                        <!-- Tanggal Order -->
                        <div>
                            <label for="order_date" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Tanggal Order <span class="text-rose-500">*</span>
                            </label>
                            <input type="date" id="order_date" name="order_date" value="{{ old('order_date', $today) }}" required class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                        </div>

                        <!-- Tanggal Estimasi -->
                        <div>
                            <label for="expected_date" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Estimasi Kedatangan
                            </label>
                            <input type="date" id="expected_date" name="expected_date" value="{{ old('expected_date') }}" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                        </div>

                        @if ($isMaster)
                            <div class="lg:col-span-2">
                                <label for="branch_id" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                    Cabang Pemesan (Master Mode)
                                </label>
                                <select id="branch_id" name="branch_id" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ old('branch_id', $currentUser->branch_id) == $branch->id ? 'selected' : '' }}>
                                            {{ $branch->name }} ({{ $branch->code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <!-- Catatan Pemesanan -->
                        <div class="{{ $isMaster ? 'lg:col-span-2' : 'lg:col-span-4' }}">
                            <label for="notes" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Catatan / Instruksi Pengiriman
                            </label>
                            <input type="text" id="notes" name="notes" value="{{ old('notes') }}" placeholder="Contoh: Pengiriman via ekspedisi langganan, harap kemas dengan bubble wrap..." class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                        </div>
                    </div>
                </section>

                <!-- Bagian 2: Detail Items (Tabel Dinamis Alpine.js) -->
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                        <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-slate-900 text-xs font-bold text-amber-400">2</span>
                            Rincian Barang yang Dipesan
                        </h3>
                        <button type="button" @click="addItem()" id="btn-tambah-baris" class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 px-3.5 py-2 text-xs font-bold text-slate-950 shadow-xs hover:bg-amber-400 transition-colors">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Tambah Baris
                        </button>
                    </div>

                    <!-- Tabel Items -->
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wider text-slate-600">
                                <tr>
                                    <th class="px-3 py-3 text-center w-12">No</th>
                                    <th class="px-3 py-3 text-left min-w-[240px]">Produk</th>
                                    <th class="px-3 py-3 text-right w-24">Stok Saat Ini</th>
                                    <th class="px-3 py-3 text-right w-32">Kuantitas</th>
                                    <th class="px-3 py-3 text-right w-44">Harga Beli Satuan (Rp)</th>
                                    <th class="px-3 py-3 text-right w-48">Subtotal (Rp)</th>
                                    <th class="px-3 py-3 text-center w-16">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="(item, index) in items" :key="index">
                                    <tr class="hover:bg-slate-50/80 transition-colors">
                                        <!-- Nomor Urut -->
                                        <td class="px-3 py-3.5 text-center font-bold text-slate-400 text-xs" x-text="index + 1"></td>

                                        <!-- Dropdown Produk -->
                                        <td class="px-3 py-3.5">
                                            <select
                                                :name="'items[' + index + '][product_id]'"
                                                x-model="item.product_id"
                                                @change="onProductSelect(index)"
                                                required
                                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                            >
                                                <option value="">-- Pilih Produk --</option>
                                                <template x-for="prod in products" :key="prod.id">
                                                    <option :value="prod.id" x-text="prod.name + ' (' + prod.sku + ')'"></option>
                                                </template>
                                            </select>
                                        </td>

                                        <!-- Stok Saat Ini Info -->
                                        <td class="px-3 py-3.5 text-right font-mono text-xs text-slate-500">
                                            <span x-text="getSelectedProductStock(item.product_id)"></span>
                                        </td>

                                        <!-- Kuantitas -->
                                        <td class="px-3 py-3.5 text-right">
                                            <input
                                                type="number"
                                                :name="'items[' + index + '][quantity]'"
                                                x-model.number="item.quantity"
                                                @input="calculateSubtotal(index)"
                                                min="1"
                                                required
                                                placeholder="1"
                                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-right font-mono text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                            >
                                        </td>

                                        <!-- Harga Beli Satuan -->
                                        <td class="px-3 py-3.5 text-right">
                                            <input
                                                type="number"
                                                step="any"
                                                :name="'items[' + index + '][unit_price]'"
                                                x-model.number="item.unit_price"
                                                @input="calculateSubtotal(index)"
                                                min="0"
                                                required
                                                placeholder="0"
                                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-right font-mono text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                            >
                                        </td>

                                        <!-- Subtotal Kalkulasi Otomatis -->
                                        <td class="px-3 py-3.5 text-right font-mono font-bold text-slate-900">
                                            <span x-text="formatRupiah(item.subtotal)"></span>
                                        </td>

                                        <!-- Tombol Hapus Baris -->
                                        <td class="px-3 py-3.5 text-center">
                                            <button
                                                type="button"
                                                @click="removeItem(index)"
                                                :disabled="items.length <= 1"
                                                :class="items.length <= 1 ? 'opacity-30 cursor-not-allowed text-slate-400' : 'text-rose-500 hover:text-rose-700 hover:bg-rose-50'"
                                                class="rounded-lg p-1.5 transition-colors"
                                                title="Hapus baris ini"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                            <!-- Footer Tabel: Total Keseluruhan Realtime -->
                            <tfoot class="border-t-2 border-slate-300 bg-slate-50">
                                <tr>
                                    <td colspan="5" class="px-4 py-4 text-right font-bold uppercase tracking-wider text-slate-700 text-xs">
                                        Total Keseluruhan PO:
                                    </td>
                                    <td class="px-4 py-4 text-right font-mono text-xl font-black text-amber-700">
                                        <span x-text="formatRupiah(grandTotal)"></span>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                <!-- Box Konsep Arsitektur PO -->
                <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4 shadow-xs">
                    <div class="flex items-start gap-3">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-500 text-slate-950 font-bold">
                            i
                        </div>
                        <div class="text-xs text-amber-900 leading-relaxed">
                            <p class="font-bold">Konsep Dokumen Purchase Order:</p>
                            <p class="mt-0.5">
                                Penerbitan dokumen Purchase Order ini berstatus <strong>Pending</strong>. Dokumen ini belum menambah stok di gudang dan belum mencatat jurnal hutang usaha. Stok dan jurnal hutang akan otomatis terbentuk saat dokumen <strong>Penerimaan Barang (Goods Receipt)</strong> dibuat dengan menautkan nomor PO ini.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Tombol Submit Akhir -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="{{ route('backoffice.purchase-orders.index') }}" class="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50">
                        Batal
                    </a>
                    <button type="submit" id="btn-submit-po" class="inline-flex items-center gap-2 rounded-xl bg-slate-950 px-8 py-3 text-sm font-bold text-white shadow-md transition hover:bg-slate-800 focus:ring-2 focus:ring-slate-900 focus:ring-offset-2">
                        <svg class="h-5 w-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Terbitkan Purchase Order
                    </button>
                </div>
            </form>
        </div>
    </main>

    <!-- Alpine.js Component Script -->
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
</body>
</html>
