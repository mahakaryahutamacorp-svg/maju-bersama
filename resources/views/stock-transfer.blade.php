<x-app-layout title="Transfer Stok (Stock Transfer)" :breadcrumbs="[
    ['label' => 'Stok Gudang'],
    ['label' => 'Stok Barang', 'url' => '/inventory'],
    ['label' => 'Transfer Stok'],
]">
    <div x-data="stockTransfer({
        token: @js($apiToken),
        stock: @js($stockLevels),
        defaultSource: @js($sourceBranches->first()?->id),
        defaultDestination: @js($destinationBranches->first()?->id),
    })">
    <main class="mx-auto w-full px-6 py-6 lg:px-10 max-w-7xl space-y-8">
        <div>
            <p class="text-sm font-medium text-amber-600">Distribusi &amp; Mutasi Barang</p>
            <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Mutasi Persediaan Antarcabang</h2>
            <p class="mt-2 text-slate-500">
                Barang keluar dari cabang asal dan masuk ke cabang tujuan dalam satu transaksi atomik terpadu.
            </p>
        </div>

        <template x-if="feedback">
            <div
                class="rounded-xl border px-4 py-3 text-sm"
                :class="feedback.type === 'success'
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                    : 'border-rose-200 bg-rose-50 text-rose-800'"
                x-text="feedback.message"
            ></div>
        </template>

        <section class="grid gap-6 lg:grid-cols-[1fr_1.4fr]">
            <div class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Rincian Transfer</h3>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Cabang Asal (Pengirim)</span>
                    <select x-model.number="sourceBranchId" @change="resetLines()" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-sky-500 focus:ring-2">
                        @foreach ($sourceBranches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Cabang Tujuan (Penerima)</span>
                    <select x-model.number="destinationBranchId" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-sky-500 focus:ring-2">
                        @foreach ($destinationBranches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Catatan / Keterangan</span>
                    <textarea x-model="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-sky-500 focus:ring-2" placeholder="Keterangan transfer (opsional)"></textarea>
                </label>

                <button
                    type="button"
                    @click="submit()"
                    :disabled="submitting || lines.length === 0 || sourceBranchId === destinationBranchId"
                    class="w-full rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-300"
                >
                    <span x-show="!submitting">Proses Transfer Stok</span>
                    <span x-show="submitting" x-cloak>Memproses...</span>
                </button>

                <p x-show="sourceBranchId === destinationBranchId" x-cloak class="text-xs text-rose-600">
                    Cabang asal dan cabang tujuan tidak boleh sama.
                </p>
            </div>

            <div class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-slate-900">Daftar Barang Transfer</h3>
                    <span class="text-xs text-slate-500" x-text="`${lines.length} baris barang`"></span>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-start">
                    <div class="relative min-w-0 flex-1" @click.away="picker.open = false">
                        <input
                            type="text"
                            x-model="picker.query"
                            @input="onSearchInput()"
                            @focus="picker.open = true"
                            @keydown.arrow-down.prevent="moveHighlight(1)"
                            @keydown.arrow-up.prevent="moveHighlight(-1)"
                            @keydown.enter.prevent="chooseHighlighted()"
                            @keydown.escape="picker.open = false"
                            placeholder="Ketik nama atau SKU barang..."
                            autocomplete="off"
                            role="combobox"
                            aria-autocomplete="list"
                            :aria-expanded="picker.open"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-sky-500 focus:ring-2"
                        >
                        <div
                            x-show="picker.open"
                            x-cloak
                            x-ref="productList"
                            role="listbox"
                            class="absolute z-30 mt-1 max-h-60 w-full overflow-y-auto overscroll-contain rounded-xl border border-slate-200 bg-white py-1 shadow-xl"
                        >
                            <template x-for="(option, index) in filteredStock" :key="option.product_id">
                                <button
                                    type="button"
                                    role="option"
                                    :aria-selected="index === picker.highlight"
                                    @mouseenter="picker.highlight = index"
                                    @click="selectProduct(option)"
                                    class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left text-sm transition hover:bg-amber-50"
                                    :class="index === picker.highlight ? 'bg-amber-50' : ''"
                                >
                                    <span class="min-w-0">
                                        <span class="block truncate font-semibold text-slate-900" x-text="option.name"></span>
                                        <span class="block font-mono text-xs text-slate-500" x-text="option.sku"></span>
                                    </span>
                                    <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700" x-text="`Stok ${option.quantity}`"></span>
                                </button>
                            </template>
                            <p x-show="filteredStock.length === 0" class="px-3 py-3 text-sm text-slate-500">Tidak ada barang yang cocok.</p>
                        </div>
                    </div>
                    <input x-model.number="picker.quantity" type="number" min="1" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-sky-500 focus:ring-2 sm:w-28" placeholder="Jumlah">
                    <button type="button" @click="addLine()" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-600">Tambah</button>
                </div>

                <p x-show="lineError" x-cloak class="text-xs text-rose-600" x-text="lineError"></p>

                <div class="max-h-80 overflow-y-auto overscroll-contain rounded-xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="sticky top-0 z-10 bg-slate-900 text-xs font-semibold uppercase tracking-wider text-white">
                        <tr class="text-left text-xs uppercase tracking-wider">
                            <th class="px-3 py-2">Nama Produk &amp; SKU</th>
                            <th class="px-3 py-2 text-right">Jumlah (Qty)</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 [&>tr:nth-child(even)]:bg-slate-50/60">
                        <template x-for="(line, index) in lines" :key="line.product_id">
                            <tr>
                                <td class="px-3 py-3">
                                    <div class="font-medium text-slate-900" x-text="line.name"></div>
                                    <div class="font-mono text-xs text-slate-500" x-text="line.sku"></div>
                                </td>
                                <td class="px-3 py-3 text-right font-semibold" x-text="line.quantity"></td>
                                <td class="px-3 py-3 text-right">
                                    <button type="button" @click="lines.splice(index, 1)" class="text-xs text-rose-600 hover:underline">Hapus</button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="lines.length === 0">
                            <td colspan="3" class="py-8 text-center text-slate-500">Belum ada barang yang ditambahkan.</td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Riwayat Transfer Terkini</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-900 text-xs font-semibold uppercase tracking-wider text-white">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">No. Referensi</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Tanggal</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Dari Cabang</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Ke Cabang</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider">Baris Barang</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 [&>tr:nth-child(even)]:bg-slate-50/60">
                        @forelse ($transfers as $transfer)
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-4 font-mono text-sm font-semibold text-sky-700">{{ $transfer->reference_number }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $transfer->transfer_date->translatedFormat('d M Y') }}</td>
                                <td class="px-6 py-4 text-sm text-slate-900">{{ $transfer->sourceBranch->name }}</td>
                                <td class="px-6 py-4 text-sm text-slate-900">{{ $transfer->destinationBranch->name }}</td>
                                <td class="px-6 py-4 text-right text-sm font-semibold text-slate-900">{{ $transfer->items->count() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-slate-500">Belum ada riwayat transfer stok yang tercatat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    </div>

    <x-slot:scripts>
    <script>
        function stockTransfer({ token, stock, defaultSource, defaultDestination }) {
            return {
                token,
                stock,
                sourceBranchId: defaultSource ?? null,
                destinationBranchId: defaultDestination ?? null,
                notes: '',
                lines: [],
                picker: { productId: '', quantity: 1, query: '', open: false, highlight: 0 },
                submitting: false,
                feedback: null,
                lineError: '',

                get availableStock() {
                    return this.stock.filter((row) => row.branch_id === this.sourceBranchId);
                },

                get filteredStock() {
                    const query = (this.picker.query || '').trim().toLowerCase();

                    if (!query || this.picker.productId) {
                        return this.availableStock;
                    }

                    return this.availableStock.filter((row) =>
                        row.name.toLowerCase().includes(query) || row.sku.toLowerCase().includes(query)
                    );
                },

                onSearchInput() {
                    this.picker.productId = '';
                    this.picker.open = true;
                    this.picker.highlight = 0;
                },

                moveHighlight(step) {
                    const total = this.filteredStock.length;

                    if (total === 0) {
                        return;
                    }

                    this.picker.open = true;
                    const next = this.picker.highlight + step;
                    this.picker.highlight = (next + total) % total;
                    this.$nextTick(() => {
                        this.$refs.productList?.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest' });
                    });
                },

                chooseHighlighted() {
                    if (!this.picker.open) {
                        return;
                    }

                    const option = this.filteredStock[this.picker.highlight];

                    if (option) {
                        this.selectProduct(option);
                    }
                },

                selectProduct(option) {
                    this.picker.productId = option.product_id;
                    this.picker.query = `${option.sku} - ${option.name}`;
                    this.picker.open = false;
                    this.lineError = '';
                },

                resetLines() {
                    this.lines = [];
                    this.picker = { productId: '', quantity: 1, query: '', open: false, highlight: 0 };
                },

                addLine() {
                    this.lineError = '';

                    const productId = Number(this.picker.productId);
                    const quantity = Number(this.picker.quantity);

                    if (!productId || quantity < 1) {
                        this.lineError = 'Pilih produk dan masukkan kuantitas minimal 1.';
                        return;
                    }

                    const source = this.availableStock.find((row) => row.product_id === productId);

                    if (!source) {
                        this.lineError = 'Produk tersebut tidak memiliki stok di cabang asal.';
                        return;
                    }

                    const existing = this.lines.find((line) => line.product_id === productId);
                    const requested = (existing?.quantity ?? 0) + quantity;

                    if (requested > source.quantity) {
                        this.lineError = `Hanya tersedia ${source.quantity} unit untuk produk ${source.name}.`;
                        return;
                    }

                    if (existing) {
                        existing.quantity = requested;
                    } else {
                        this.lines.push({
                            product_id: productId,
                            sku: source.sku,
                            name: source.name,
                            quantity,
                        });
                    }

                    this.picker = { productId: '', quantity: 1, query: '', open: false, highlight: 0 };
                },

                async submit() {
                    if (this.submitting || this.lines.length === 0) {
                        return;
                    }

                    this.submitting = true;
                    this.feedback = null;

                    try {
                        const response = await fetch('/api/stock-transfers', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                Authorization: `Bearer ${this.token}`,
                            },
                            body: JSON.stringify({
                                source_branch_id: this.sourceBranchId,
                                destination_branch_id: this.destinationBranchId,
                                notes: this.notes || null,
                                items: this.lines.map((line) => ({
                                    product_id: line.product_id,
                                    quantity: line.quantity,
                                })),
                            }),
                        });

                        const payload = await response.json();

                        if (!response.ok) {
                            const firstError = payload.errors
                                ? Object.values(payload.errors).flat()[0]
                                : payload.message;

                            this.feedback = { type: 'error', message: firstError ?? 'Transfer stok gagal diproses.' };
                            return;
                        }

                        this.feedback = {
                            type: 'success',
                            message: `Transfer ${payload.reference_number} berhasil. Memuat ulang data...`,
                        };

                        window.setTimeout(() => window.location.reload(), 1200);
                    } catch (error) {
                        this.feedback = { type: 'error', message: 'Gangguan koneksi. Silakan coba kembali.' };
                    } finally {
                        this.submitting = false;
                    }
                },
            };
        }
    </script>
    </x-slot:scripts>
</x-app-layout>
