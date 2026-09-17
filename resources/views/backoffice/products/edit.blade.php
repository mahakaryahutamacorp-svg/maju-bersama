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

                <!-- Harga Beli (HPP) & Harga Jual -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="purchase_price" class="block text-sm font-semibold text-slate-800">
                            Harga Beli / HPP (Rp) <span class="text-rose-500">*</span>
                        </label>
                        <input type="number" step="any" min="0" id="purchase_price" name="purchase_price" value="{{ old('purchase_price', $product->purchase_price) }}" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                    </div>

                    <div>
                        <label for="selling_price" class="block text-sm font-semibold text-slate-800">
                            Harga Jual Kasir (Rp) <span class="text-rose-500">*</span>
                        </label>
                        <input type="number" step="any" min="0" id="selling_price" name="selling_price" value="{{ old('selling_price', $product->selling_price) }}" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                    </div>
                </div>

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
