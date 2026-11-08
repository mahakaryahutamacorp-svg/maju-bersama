<x-app-layout :title="'Edit Produk - '.($product->name)" :breadcrumbs="[
    ['label' => 'Pengaturan'],
    ['label' => 'Katalog Produk', 'url' => route('backoffice.products.index')],
    ['label' => 'Edit Produk'],
]">
    <main class="mx-auto w-full px-6 py-6 lg:px-10 max-w-3xl">
        <div class="mb-6">
            <p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Pengaturan</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Edit Produk - {{ $product->name }}</h2>
        </div>

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800 shadow-sm">
                <div class="flex items-center gap-2 font-semibold text-sm">
                    <svg class="h-5 w-5 text-rose-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                    <span>Mohon perbaiki kesalahan berikut:</span>
                </div>
                <ul class="mt-2 list-inside list-disc text-xs space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="border-b border-slate-100 pb-5 mb-6 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Edit Data Produk</h2>
                    <p class="text-xs text-slate-500 mt-1">Perbarui informasi katalog, harga beli/HPP, dan harga jual produk.</p>
                </div>
                <span class="font-mono text-xs font-semibold bg-sky-50 text-sky-700 px-3 py-1 rounded-full border border-sky-200">
                    {{ $product->sku }}
                </span>
            </div>

            @if ($editsSourceFromBranch)
                <div class="mb-6 rounded-xl border border-sky-200 bg-sky-50 p-4 text-xs text-sky-800">
                    Anda mengedit sebagai owner dari cabang {{ $product->branch?->name }}. Perubahan nama, SKU, harga beli, dan harga jual diterapkan ke cabang acuan {{ $sourceBranch?->name }} ({{ $sourceBranch?->code }}) lalu disamakan ke semua cabang. Kategori, satuan, pajak, dan koreksi stok hanya berlaku untuk cabang ini.
                </div>
            @elseif ($canEditMasterFields)
                <div class="mb-6 rounded-xl border border-sky-200 bg-sky-50 p-4 text-xs text-sky-800">
                    Nama, SKU, harga beli, dan harga jual yang Anda simpan akan disamakan ke semua cabang dalam grup yang sama.
                </div>
            @elseif ($sourceBranch)
                <div class="mb-6 flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800 sm:flex-row sm:items-center sm:justify-between">
                    <span>
                        Nama, SKU, harga beli, dan harga jual mengikuti cabang acuan {{ $sourceBranch->name }} ({{ $sourceBranch->code }}) secara otomatis. Di cabang ini Anda dapat mengubah kategori, satuan, dan status pajak.
                    </span>
                    @if ($canEditSourceProduct)
                        <a href="{{ route('backoffice.products.edit', $sourceProduct->id) }}" class="shrink-0 rounded-lg bg-amber-600 px-3 py-1.5 font-bold text-white hover:bg-amber-700">
                            Ubah di cabang acuan
                        </a>
                    @endif
                </div>
            @else
                <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800">
                    Nama, SKU, harga beli, dan harga jual diatur oleh akun master dan berlaku sama di semua cabang. Anda dapat mengubah kategori, satuan, dan status pajak.
                </div>
            @endif

            <form method="POST" action="{{ route('backoffice.products.update', $product->id) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <!-- Nama Produk -->
                <div>
                    <label for="name" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                        Nama Produk <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="name" name="name" value="{{ old('name', $product->name) }}" required @disabled(! $canEditMasterFields) class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 disabled:bg-slate-100 disabled:text-slate-500">
                </div>

                <!-- SKU / Barcode -->
                <div>
                    <label for="sku" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                        Kode SKU / Barcode <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="sku" name="sku" value="{{ old('sku', $product->sku) }}" required @disabled(! $canEditMasterFields) class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-mono focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 disabled:bg-slate-100 disabled:text-slate-500">
                </div>

                <!-- Kategori, Satuan & Cabang -->
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="category_id" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                            Kategori Produk <span class="text-rose-500">*</span>
                        </label>
                        <select id="category_id" name="category_id" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="unit" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                            Satuan Barang <span class="text-rose-500">*</span>
                        </label>
                        @php
                            $unitOptions = ['pcs', 'kg', 'gram', 'liter', 'botol', 'sak', 'kardus', 'karung', 'ton', 'renceng', 'pak'];
                            $selectedUnit = old('unit', $product->unit ?? 'pcs');
                            if ($selectedUnit && ! in_array($selectedUnit, $unitOptions, true)) {
                                array_unshift($unitOptions, $selectedUnit);
                            }
                        @endphp
                        <select id="unit" name="unit" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                            @foreach ($unitOptions as $u)
                                <option value="{{ $u }}" {{ $selectedUnit == $u ? 'selected' : '' }}>
                                    {{ $u }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                            Cabang Kepemilikan
                        </label>
                        <input type="text" disabled value="{{ $product->branch?->name }} ({{ $product->branch?->code }})" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-2.5 text-sm text-slate-600">
                        <p class="text-[11px] text-slate-400 mt-1">Cabang produk terikat pada unit pencatat inventori awal.</p>
                    </div>
                </div>

                <!-- Harga Beli (HPP) -->
                <div>
                    <label for="purchase_price" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                        Harga Beli / HPP (Rp) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative mt-1.5">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                            <span class="text-sm font-semibold text-slate-400">Rp</span>
                        </div>
                        <input type="number" step="any" min="0" id="purchase_price" name="purchase_price" value="{{ old('purchase_price', $product->purchase_price) }}" required @disabled(! $canEditMasterFields) class="w-full rounded-xl border border-slate-300 pl-10 pr-4 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 disabled:bg-slate-100 disabled:text-slate-500">
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Biaya pokok pembelian untuk perhitungan laba kotor.</p>
                </div>

                @include('backoffice.products.partials.taxable-field', ['isTaxExempt' => ! $product->is_taxable])

                <!-- Section: Penetapan Tingkat Harga -->
                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-5">
                    <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                                <svg class="h-4 w-4 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                </svg>
                                Penetapan Tingkat Harga
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">Tentukan harga jual bertingkat untuk kategori pelanggan eceran, grosir, dan member.</p>
                        </div>
                        <span class="inline-flex self-start sm:self-auto items-center rounded-full bg-sky-100 px-2.5 py-0.5 text-[11px] font-semibold text-sky-700">
                            Multi Price
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @foreach ($priceLevels as $level)
                            @php
                                $isDefault = (bool) $level->is_default;
                                $existingTierPrice = $product->productPrices->firstWhere('price_level_id', $level->id)?->price;
                                $defaultPrice = $isDefault ? ($existingTierPrice ?? $product->selling_price) : $existingTierPrice;
                                $fieldValue = old("prices.{$level->id}", $defaultPrice);
                            @endphp
                            <div class="rounded-xl border bg-white p-3.5 shadow-sm transition-all {{ $isDefault ? 'border-sky-300 ring-1 ring-sky-200' : 'border-slate-200 hover:border-slate-300' }}">
                                <div class="flex items-center justify-between mb-1.5">
                                    <label for="price_level_{{ $level->id }}" class="text-xs font-bold text-slate-800">
                                        {{ $level->name }}
                                        @if ($isDefault)
                                            <span class="text-rose-500">*</span>
                                        @endif
                                    </label>
                                    @if ($isDefault)
                                        <span class="rounded bg-sky-100 px-1.5 py-0.5 text-[10px] font-bold text-sky-700">Default</span>
                                    @else
                                        <span class="text-[10px] text-slate-400">Opsional</span>
                                    @endif
                                </div>

                                <div class="relative mt-1">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <span class="text-xs font-semibold text-slate-400">Rp</span>
                                    </div>
                                    <input
                                        type="number"
                                        step="any"
                                        min="0"
                                        id="price_level_{{ $level->id }}"
                                        name="prices[{{ $level->id }}]"
                                        value="{{ $fieldValue }}"
                                        {{ $isDefault ? 'required' : '' }}
                                        @disabled(! $canEditMasterFields)
                                        placeholder="{{ $isDefault ? '0' : 'Sama dgn eceran' }}"
                                        class="w-full rounded-lg border border-slate-300 pl-9 pr-3 py-2 text-sm font-semibold text-slate-900 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 disabled:bg-slate-100 disabled:text-slate-500 {{ $isDefault ? 'bg-sky-50/20' : '' }}"
                                    >
                                </div>
                                <p class="text-[10px] text-slate-400 mt-1">
                                    @if ($isDefault)
                                        Harga dasar kasir &amp; fallback produk.
                                    @else
                                        Kosongkan jika sama dengan harga eceran.
                                    @endif
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Section: Penetapan Harga Khusus Grup Pelanggan (Customer Groups: Retail, Petani, Grosir) -->
                @if (isset($customerGroups) && $customerGroups->isNotEmpty())
                    <div x-data="{ openGroupPrices: true }" class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-5">
                        <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-600 text-white text-[10px] font-bold">Rp</span>
                                    Harga Khusus Grup Pelanggan (Pricing Tiers)
                                </h3>
                                <p class="text-xs text-slate-500 mt-0.5">Atur harga berbeda untuk Retail, Petani, dan Grosir. Jika dikosongkan, sistem otomatis memakai Harga Dasar.</p>
                            </div>
                            <span class="inline-flex self-start sm:self-auto items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-800">
                                Pricing Tiers
                            </span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            @foreach ($customerGroups as $cg)
                                @php
                                    $savedGroupPrice = $product->productPrices->firstWhere('customer_group_id', $cg->id)?->price;
                                    $cgVal = old("customer_group_prices.{$cg->id}", $savedGroupPrice);
                                @endphp
                                <div class="rounded-xl border border-emerald-200 bg-white p-3.5 shadow-xs">
                                    <div class="flex items-center justify-between mb-1.5">
                                        <label for="cg_price_{{ $cg->id }}" class="text-xs font-bold text-slate-800">
                                            {{ $cg->name }}
                                        </label>
                                        <span class="text-[10px] text-slate-400 font-medium">Khusus Grup</span>
                                    </div>
                                    <div class="relative mt-1">
                                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                            <span class="text-xs font-semibold text-slate-400">Rp</span>
                                        </div>
                                        <input
                                            type="number"
                                            step="any"
                                            min="0"
                                            id="cg_price_{{ $cg->id }}"
                                            name="customer_group_prices[{{ $cg->id }}]"
                                            value="{{ $cgVal }}"
                                            @disabled(! $canEditMasterFields)
                                            placeholder="Sama dgn harga dasar"
                                            class="w-full rounded-lg border border-slate-300 pl-9 pr-3 py-2 text-sm font-semibold text-slate-900 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 disabled:bg-slate-100 disabled:text-slate-500"
                                        >
                                    </div>
                                    <p class="text-[10px] text-slate-400 mt-1">
                                        {{ $cg->notes ?? 'Harga khusus anggota grup ' . $cg->name }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($canCorrectStock)
                    <div class="rounded-2xl border border-violet-200 bg-violet-50/60 p-5">
                        <div class="mb-4">
                            <h3 class="text-sm font-bold text-slate-900">Koreksi Stok Cepat (Owner)</h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Stok sistem di {{ $product->branch?->name }} saat ini <b>{{ number_format($currentStock, 0, ',', '.') }}</b>. Isi jumlah fisik yang benar untuk mengoreksinya; kosongkan bila stok tidak diubah.
                            </p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label for="stock_actual" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">Stok Fisik</label>
                                <input type="number" min="0" step="1" id="stock_actual" name="stock_actual" value="{{ old('stock_actual') }}" placeholder="{{ $currentStock }}" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500">
                            </div>
                            <div>
                                <label for="stock_reason" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">Jenis Koreksi</label>
                                <select id="stock_reason" name="stock_reason" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500">
                                    <option value="correction" @selected(old('stock_reason', 'correction') === 'correction')>Selisih stok (opname)</option>
                                    <option value="opening" @selected(old('stock_reason') === 'opening')>Stok awal</option>
                                </select>
                            </div>
                            <div>
                                <label for="stock_note" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">Catatan</label>
                                <input type="text" id="stock_note" name="stock_note" value="{{ old('stock_note') }}" maxlength="255" placeholder="Opsional" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500">
                            </div>
                        </div>
                        <p class="mt-3 text-[11px] text-slate-500">
                            Koreksi dicatat sebagai dokumen penyesuaian stok di Kartu Stok dengan jurnal otomatis senilai selisih × harga beli:
                            selisih lebih Dr 1210 Persediaan / Cr 4120 Pendapatan Lain-lain, selisih kurang Dr 5120 Beban Selisih Persediaan / Cr 1210 Persediaan.
                            Untuk stok awal, kreditnya 3110 Modal Awal, atau tanpa jurnal bila persediaan cabang sudah ada di Saldo Awal.
                        </p>
                    </div>
                @endif

                <!-- Action Buttons -->
                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
                    <a href="{{ route('backoffice.products.index') }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                        Batal
                    </a>
                    <button type="submit" class="rounded-xl bg-sky-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-500 transition-colors">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </main>
</x-app-layout>
