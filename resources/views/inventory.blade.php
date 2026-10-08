<x-app-layout title="Persediaan Barang" :breadcrumbs="[
    ['label' => 'Stok Gudang'],
    ['label' => 'Stok Barang'],
]">
    <div x-data="inventoryTable({{ $products->toJson() }})">
    <main class="mx-auto w-full px-6 py-6 lg:px-10 max-w-7xl space-y-8">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-amber-600">Kontrol Stok &amp; Gudang</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Katalog Persediaan Produk</h2>
                <p class="mt-2 text-slate-500">Ketersediaan stok barang pada cabang {{ auth()->user()->branch->name }}.</p>
            </div>
            <div class="flex flex-col gap-3 sm:items-end">
                <div class="text-sm text-slate-500">Diperbarui {{ now()->translatedFormat('d M Y, H:i') }}</div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input x-model="query" type="search" placeholder="Cari SKU atau nama produk..." class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none ring-sky-500 placeholder:text-slate-400 focus:ring-2">
                    <select x-model="category" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none ring-sky-500 focus:ring-2">
                        <option value="all">Semua Kategori</option>
                        <template x-for="name in categories" :key="name"><option :value="name" x-text="name"></option></template>
                    </select>
                </div>
            </div>
        </div>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-900 text-xs font-semibold uppercase tracking-wider text-white">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Kode Barang (SKU)</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Nama Produk</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Kategori</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider">Harga Jual</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider">Stok</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white [&>tr:nth-child(even)]:bg-slate-50/60">
                        @foreach ($products as $product)
                            <tr x-show="matches({{ $product->toJson() }})" class="transition-colors hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-5 font-mono text-sm font-semibold text-sky-700">{{ $product->sku }}</td>
                                <td class="px-6 py-5">
                                    <div class="font-semibold text-slate-900">{{ $product->name }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $product->branch->name }}</div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-5">
                                    <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">{{ $product->category->name }}</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-5 text-right font-medium text-slate-900">Rp {{ number_format((float) $product->selling_price, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-6 py-5 text-right">
                                    <span class="font-semibold {{ $product->stock < 10 ? 'text-rose-600' : 'text-emerald-600' }}">{{ number_format($product->stock) }}</span>
                                    <span class="ml-1 text-xs text-slate-400">unit</span>
                                </td>
                            </tr>
                        @endforeach
                        <tr x-show="filteredProducts.length === 0">
                            <td colspan="5" class="px-6 py-12 text-center text-slate-500">Tidak ada produk yang sesuai dengan filter pencarian.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    </div>

    <x-slot:scripts>
    <script>
        function inventoryTable(products) {
            return {
                products,
                query: '',
                category: 'all',
                get categories() {
                    return [...new Set(this.products.map((product) => product.category?.name).filter(Boolean))];
                },
                get filteredProducts() {
                    return this.products.filter((product) => this.matches(product));
                },
                matches(product) {
                    const query = this.query.toLowerCase().trim();
                    const matchesQuery = !query || product.name.toLowerCase().includes(query) || product.sku.toLowerCase().includes(query);
                    const matchesCategory = this.category === 'all' || product.category?.name === this.category;
                    return matchesQuery && matchesCategory;
                },
            };
        }
    </script>
    </x-slot:scripts>
</x-app-layout>
