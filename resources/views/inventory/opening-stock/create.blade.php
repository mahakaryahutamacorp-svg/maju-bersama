<x-app-layout title="Input Stok Awal" :breadcrumbs="[
    ['label' => 'Stok Gudang'],
    ['label' => 'Input Stok Awal'],
]">
    <x-slot:head>
    <style>
        [x-cloak] { display: none !important; }
    </style>
    </x-slot:head>

    <main
        class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-10"
        x-data="openingStockForm({{ Js::from($products) }}, {{ $inventoryAlreadyInOpeningBalance ? 'false' : 'true' }})"
        x-cloak
    >
        <div class="mb-6">
            <p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Stok Gudang</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Input Stok Awal</h2>
            <p class="mt-1 text-sm text-slate-500">Isi jumlah fisik setiap produk. Angka yang diisi menjadi stok sistem cabang ini. Produk yang dikosongkan tidak diubah, jadi pengisian bisa dicicil beberapa kali.</p>
        </div>

        @if ($errors->any())
            <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 shadow-sm">
                <p class="font-semibold">Stok awal belum tersimpan:</p>
                <ul class="mt-2 list-inside list-disc space-y-1 pl-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('inventory.opening-stock.store') }}" @submit="handleSubmit($event)" class="grid grid-cols-1 gap-6 lg:grid-cols-4">
            @csrf
            <input type="hidden" name="branch_id" value="{{ $currentBranch->id }}">
            <input type="hidden" name="record_journal" :value="recordJournal ? 1 : 0">
            <template x-for="(entry, entryIndex) in filledEntries" :key="entry.id">
                <div>
                    <input type="hidden" :name="'items[' + entryIndex + '][product_id]'" :value="entry.id">
                    <input type="hidden" :name="'items[' + entryIndex + '][quantity]'" :value="entry.quantity">
                </div>
            </template>

            <div class="min-w-0 space-y-4 lg:col-span-3">
                <div class="grid grid-cols-1 gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-3">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">Cabang / Gudang</label>
                        @if ($isMaster && $branches->count() > 1)
                            <select
                                class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                                @change="changeBranch($event.target.value)"
                            >
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected($currentBranch->id === $branch->id)>{{ $branch->name }} ({{ $branch->code }})</option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" readonly value="{{ $currentBranch->name }}" class="block w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-600">
                        @endif
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">Tanggal Hitung Fisik</label>
                        <input type="date" name="date" value="{{ old('date', now()->toDateString()) }}" required class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">Catatan (Opsional)</label>
                        <input type="text" name="notes" value="{{ old('notes') }}" maxlength="255" placeholder="Contoh: Rak depan, gudang belakang" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                    </div>
                </div>

                <div class="rounded-2xl border border-indigo-200 bg-indigo-50/40 p-4 shadow-sm">
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input
                            type="text"
                            x-ref="search"
                            x-model="searchQuery"
                            @keydown.enter.prevent="jumpToFirstResult()"
                            @keydown.escape="searchQuery = ''"
                            autocomplete="off"
                            placeholder="Cari produk: nama, SKU, atau kategori (misal: gramoxone, PST-012)..."
                            class="block w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-10 pr-4 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                        >
                    </div>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                        <div class="inline-flex rounded-lg border border-slate-200 bg-white p-0.5 text-xs font-semibold">
                            <button type="button" @click="setStatusFilter('all')" :class="statusFilter === 'all' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'" class="rounded-md px-3 py-1.5">Semua (<span x-text="products.length"></span>)</button>
                            <button type="button" @click="setStatusFilter('empty')" :class="statusFilter === 'empty' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'" class="rounded-md px-3 py-1.5">Belum diisi (<span x-text="products.length - filledEntries.length"></span>)</button>
                            <button type="button" @click="setStatusFilter('filled')" :class="statusFilter === 'filled' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'" class="rounded-md px-3 py-1.5">Sudah diisi (<span x-text="filledEntries.length"></span>)</button>
                        </div>
                        <p class="text-[11px] text-slate-500">Enter di kotak cari → lompat ke kolom stok. Enter di kolom stok → kembali ke kotak cari.</p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="w-full overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                            <thead class="bg-slate-900 text-xs font-semibold uppercase tracking-wider text-white">
                                <tr>
                                    <th class="px-4 py-3">Produk</th>
                                    <th class="px-4 py-3 text-right">Harga Modal</th>
                                    <th class="hidden px-4 py-3 text-right md:table-cell">Harga Jual</th>
                                    <th class="px-4 py-3 text-center">Stok Sistem</th>
                                    <th class="px-4 py-3 text-center">Stok Awal</th>
                                    <th class="hidden px-4 py-3 text-right sm:table-cell">Nilai</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="product in visibleProducts" :key="product.id">
                                    <tr :class="isFilled(product.id) ? 'bg-emerald-50/60' : ''">
                                        <td class="px-4 py-2.5">
                                            <p class="font-semibold text-slate-900" x-html="highlight(product.name)"></p>
                                            <p class="text-xs text-slate-500">
                                                <span class="font-mono" x-html="highlight(product.sku)"></span>
                                                <template x-if="product.category"><span> · <span x-html="highlight(product.category)"></span></span></template>
                                                <template x-if="product.unit"><span> · <span x-text="product.unit"></span></span></template>
                                            </p>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2.5 text-right font-mono text-xs text-slate-700" x-text="formatRupiah(product.purchase_price)"></td>
                                        <td class="hidden whitespace-nowrap px-4 py-2.5 text-right font-mono text-xs text-slate-500 md:table-cell" x-text="formatRupiah(product.selling_price)"></td>
                                        <td class="px-4 py-2.5 text-center">
                                            <span class="inline-block rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700" x-text="product.stock"></span>
                                        </td>
                                        <td class="px-4 py-2.5 text-center">
                                            <input
                                                type="number"
                                                min="0"
                                                step="1"
                                                inputmode="numeric"
                                                :id="'opening-qty-' + product.id"
                                                x-model="quantities[product.id]"
                                                @keydown.enter.prevent="backToSearch()"
                                                placeholder="-"
                                                class="w-24 rounded-lg border border-slate-300 px-2.5 py-1.5 text-center font-bold text-slate-900 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                                            >
                                        </td>
                                        <td class="hidden whitespace-nowrap px-4 py-2.5 text-right font-semibold text-slate-700 sm:table-cell" x-text="isFilled(product.id) ? formatRupiah(quantityOf(product.id) * product.purchase_price) : '-'"></td>
                                    </tr>
                                </template>
                                <template x-if="visibleProducts.length === 0">
                                    <tr>
                                        <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">
                                            <span x-show="searchQuery.trim() !== ''">Produk "<span class="font-semibold" x-text="searchQuery"></span>" tidak ditemukan.</span>
                                            <span x-show="searchQuery.trim() === ''">Tidak ada produk pada filter ini.</span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="space-y-4 lg:col-span-1">
                <div class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:sticky lg:top-6">
                    <h3 class="text-sm font-bold text-slate-900">Ringkasan</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-500">Produk diisi</dt><dd class="font-bold text-slate-900"><span x-text="filledEntries.length"></span> / <span x-text="products.length"></span></dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Total kuantitas</dt><dd class="font-bold text-slate-900" x-text="totalQuantity.toLocaleString('id-ID')"></dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Nilai (harga modal)</dt><dd class="font-bold text-emerald-700" x-text="formatRupiah(totalValue)"></dd></div>
                    </dl>

                    <label class="flex items-start gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                        <input type="checkbox" x-model="recordJournal" class="mt-0.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                        <span>Bukukan jurnal <b>Dr 1210 Persediaan / Cr 3110 Modal Awal</b> sebesar nilai stok.</span>
                    </label>
                    @if ($inventoryAlreadyInOpeningBalance)
                        <p class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">Nilai persediaan cabang ini sudah dibukukan di menu Saldo Awal, jadi jurnal dimatikan agar tidak tercatat dua kali.</p>
                    @endif

                    <button
                        type="submit"
                        :disabled="filledEntries.length === 0 || isSubmitting"
                        class="w-full rounded-xl bg-sky-600 py-3 text-center text-sm font-bold text-white shadow-lg transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <span x-show="!isSubmitting">Simpan Stok Awal</span>
                        <span x-show="isSubmitting">Menyimpan...</span>
                    </button>
                    <p class="text-center text-[11px] text-slate-400">Harga modal dan harga jual tidak diubah.</p>
                </div>
            </div>
        </form>
    </main>

    <x-slot:scripts>
    @include('inventory.partials.smart-search-script')
    <script>
        function openingStockForm(initialProducts, defaultRecordJournal) {
            return {
                products: initialProducts || [],
                quantities: Object.fromEntries((initialProducts || []).map(product => [product.id, ''])),
                searchQuery: '',
                statusFilter: 'all',
                statusSnapshot: null,
                recordJournal: defaultRecordJournal,
                isSubmitting: false,

                quantityOf(productId) {
                    const raw = this.quantities[productId];
                    if (raw === '' || raw === null || raw === undefined) return null;
                    const value = Number(raw);
                    return Number.isInteger(value) && value >= 0 ? value : null;
                },

                isFilled(productId) {
                    return this.quantityOf(productId) !== null;
                },

                get filledEntries() {
                    return this.products
                        .filter(product => this.isFilled(product.id))
                        .map(product => ({ id: product.id, quantity: this.quantityOf(product.id), cost: product.purchase_price }));
                },

                get totalQuantity() {
                    return this.filledEntries.reduce((sum, entry) => sum + entry.quantity, 0);
                },

                get totalValue() {
                    return this.filledEntries.reduce((sum, entry) => sum + entry.quantity * (parseFloat(entry.cost) || 0), 0);
                },

                // Snapshot diambil saat filter diklik agar baris tidak hilang ketika sedang diisi.
                setStatusFilter(status) {
                    this.statusFilter = status;
                    this.statusSnapshot = status === 'all'
                        ? null
                        : new Set(this.products
                            .filter(product => this.isFilled(product.id) === (status === 'filled'))
                            .map(product => product.id));
                },

                get visibleProducts() {
                    const byStatus = this.statusSnapshot
                        ? this.products.filter(product => this.statusSnapshot.has(product.id))
                        : this.products;
                    return ProductSmartSearch.search(byStatus, this.searchQuery, byStatus.length);
                },

                highlight(text) {
                    return ProductSmartSearch.highlight(text, this.searchQuery);
                },

                jumpToFirstResult() {
                    const product = ProductSmartSearch.exactSku(this.visibleProducts, this.searchQuery) ?? this.visibleProducts[0];
                    if (!product) return;
                    this.$nextTick(() => {
                        const input = document.getElementById('opening-qty-' + product.id);
                        if (!input) return;
                        input.scrollIntoView({ block: 'center', behavior: 'smooth' });
                        input.focus();
                        input.select();
                    });
                },

                backToSearch() {
                    this.$refs.search.focus();
                    this.$refs.search.select();
                },

                changeBranch(branchId) {
                    if (this.filledEntries.length > 0 && !confirm('Isian yang belum disimpan akan hilang. Pindah cabang?')) return;
                    window.location.href = @js(route('inventory.opening-stock.create')) + '?branch_id=' + encodeURIComponent(branchId);
                },

                formatRupiah(value) {
                    return 'Rp ' + Math.round(value || 0).toLocaleString('id-ID');
                },

                handleSubmit(event) {
                    if (this.filledEntries.length === 0) {
                        event.preventDefault();
                        alert('Isi stok minimal satu produk.');
                        return;
                    }

                    const message = 'Simpan stok awal ' + this.filledEntries.length + ' produk?\n'
                        + 'Total kuantitas: ' + this.totalQuantity.toLocaleString('id-ID') + '\n'
                        + 'Nilai: ' + this.formatRupiah(this.totalValue) + '\n'
                        + (this.recordJournal ? 'Jurnal Persediaan / Modal Awal akan dibukukan.' : 'Tanpa jurnal.');

                    if (!confirm(message)) {
                        event.preventDefault();
                        return;
                    }

                    this.isSubmitting = true;
                },
            };
        }
    </script>
    </x-slot:scripts>
</x-app-layout>
