<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Produk - {{ $product->name }} | Maju Bersama ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <!-- Header Utama -->
    <header class="border-b border-slate-800 bg-slate-950 text-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
            <div class="flex items-center gap-4">
                <a href="/backoffice" class="block">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-400">Maju Bersama ERP</p>
                    <h1 class="text-xl font-bold tracking-tight">Manajemen Master Data</h1>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <div class="rounded-full border border-sky-400/30 bg-sky-400/10 px-3 py-1 text-xs font-medium text-sky-200">
                    {{ $currentUser->branch?->name ?? 'Pusat' }} ({{ $currentUser->role }})
                </div>
            </div>
        </div>
    </header>

    <!-- Sub-Navbar Master Data -->
    <div class="border-b border-slate-200 bg-white shadow-sm">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-2.5 lg:px-8">
            <div class="flex items-center gap-2 text-sm font-medium">
                <a href="{{ route('backoffice.products.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    ← Kembali ke Katalog
                </a>
                <span class="text-slate-300">/</span>
                <span class="font-semibold text-slate-900">Edit Produk: {{ $product->name }}</span>
            </div>
        </div>
    </div>

    <!-- Main Form Container -->
    <main class="mx-auto max-w-3xl px-6 py-8 lg:px-8">
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

            <form method="POST" action="{{ route('backoffice.products.update', $product->id) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <!-- Nama Produk -->
                <div>
                    <label for="name" class="block text-sm font-semibold text-slate-800">
                        Nama Produk <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="name" name="name" value="{{ old('name', $product->name) }}" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                </div>

                <!-- SKU / Barcode -->
                <div>
                    <label for="sku" class="block text-sm font-semibold text-slate-800">
                        Kode SKU / Barcode <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="sku" name="sku" value="{{ old('sku', $product->sku) }}" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-mono focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                </div>

                <!-- Kategori, Satuan & Cabang -->
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="category_id" class="block text-sm font-semibold text-slate-800">
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
                        <label for="unit" class="block text-sm font-semibold text-slate-800">
                            Satuan Barang <span class="text-rose-500">*</span>
                        </label>
                        <select id="unit" name="unit" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                            @foreach (['pcs', 'kg', 'gram', 'liter', 'botol', 'sak', 'kardus', 'karung', 'ton', 'renceng', 'pak'] as $u)
                                <option value="{{ $u }}" {{ old('unit', $product->unit ?? 'pcs') == $u ? 'selected' : '' }}>
                                    {{ $u }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-800">
                            Cabang Kepemilikan
                        </label>
                        <input type="text" disabled value="{{ $product->branch?->name }} ({{ $product->branch?->code }})" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-2.5 text-sm text-slate-600">
                        <p class="text-[11px] text-slate-400 mt-1">Cabang produk terikat pada unit pencatat inventori awal.</p>
                    </div>
                </div>

                <!-- Harga Beli (HPP) -->
                <div>
                    <label for="purchase_price" class="block text-sm font-semibold text-slate-800">
                        Harga Beli / HPP (Rp) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative mt-1.5">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                            <span class="text-sm font-semibold text-slate-400">Rp</span>
                        </div>
                        <input type="number" step="any" min="0" id="purchase_price" name="purchase_price" value="{{ old('purchase_price', $product->purchase_price) }}" required class="w-full rounded-xl border border-slate-300 pl-10 pr-4 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Biaya pokok pembelian untuk perhitungan laba kotor.</p>
                </div>

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
                                        placeholder="{{ $isDefault ? '0' : 'Sama dgn eceran' }}"
                                        class="w-full rounded-lg border border-slate-300 pl-9 pr-3 py-2 text-sm font-semibold text-slate-900 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 {{ $isDefault ? 'bg-sky-50/20' : '' }}"
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
                                            placeholder="Sama dgn harga dasar"
                                            class="w-full rounded-lg border border-slate-300 pl-9 pr-3 py-2 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
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
</body>
</html>
