<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Produk Baru | Maju Bersama ERP</title>
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
                <span class="font-semibold text-slate-900">Tambah Produk Baru</span>
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
            <div class="border-b border-slate-100 pb-5 mb-6">
                <h2 class="text-lg font-bold text-slate-900">Formulir Tambah Produk Baru</h2>
                <p class="text-xs text-slate-500 mt-1">Isi rincian produk yang akan didaftarkan ke dalam sistem multi-cabang.</p>
            </div>

            <form method="POST" action="{{ route('backoffice.products.store') }}" class="space-y-6">
                @csrf

                <!-- Nama Produk dengan Real-time Search & Duplicate Detection -->
                <div x-data="{
                    query: '{{ old('name') }}',
                    matches: [],
                    isLoading: false,
                    hasChecked: false,
                    checkDuplicates() {
                        const q = (this.query || '').trim();
                        if (q.length < 2) {
                            this.matches = [];
                            this.hasChecked = false;
                            return;
                        }
                        this.isLoading = true;
                        fetch('{{ route('backoffice.products.check-duplicate') }}?name=' + encodeURIComponent(q))
                            .then(res => res.json())
                            .then(data => {
                                this.matches = data.matches || [];
                                this.hasChecked = true;
                            })
                            .catch(() => { this.matches = []; })
                            .finally(() => { this.isLoading = false; });
                    }
                }" class="relative">
                    <div class="flex items-center justify-between">
                        <label for="name" class="block text-sm font-semibold text-slate-800">
                            Nama Produk <span class="text-rose-500">*</span>
                        </label>
                        <span x-show="isLoading" class="text-xs text-sky-600 font-medium animate-pulse" style="display: none;">
                            Memeriksa ketersediaan nama...
                        </span>
                    </div>

                    <div class="relative mt-1.5">
                        <input
                            type="text"
                            id="name"
                            name="name"
                            x-model="query"
                            @input.debounce.300ms="checkDuplicates()"
                            required
                            placeholder="Contoh: AM-500SC @1L atau Pupuk Urea 50kg"
                            class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                        >
                        <div x-show="matches.length > 0" class="absolute right-3 top-2.5 text-amber-500" style="display: none;" title="Produk dengan nama serupa ditemukan">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                        </div>
                    </div>

                    <!-- Notifikasi Peringatan Produk Serupa / Pernah Didaftarkan -->
                    <div x-show="matches.length > 0" x-cloak class="mt-2.5 rounded-xl border border-amber-300 bg-amber-50/90 p-3.5 shadow-sm text-xs text-amber-900" style="display: none;">
                        <div class="flex items-center gap-1.5 font-bold text-amber-800 mb-2">
                            <svg class="h-4 w-4 text-amber-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>
                            <span>Perhatian: Ditemukan <span x-text="matches.length"></span> produk dengan nama serupa yang SUDAH PERNAH terdaftar:</span>
                        </div>
                        <ul class="space-y-1.5 divide-y divide-amber-200/60 max-h-48 overflow-y-auto pr-1">
                            <template x-for="item in matches" :key="item.id">
                                <li class="pt-1.5 flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <span class="font-bold text-slate-900" x-text="item.name"></span>
                                        <span class="text-[11px] text-slate-600 font-mono" x-text="'(SKU: ' + item.sku + ' | ' + item.unit + ')'"></span>
                                        <span class="text-[11px] text-amber-700 ml-1" x-text="'• ' + item.branch_name"></span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="font-semibold text-emerald-700" x-text="'Rp ' + Number(item.selling_price).toLocaleString('id-ID')"></span>
                                        <a :href="item.edit_url" target="_blank" class="rounded bg-amber-600 hover:bg-amber-700 px-2 py-1 text-[11px] font-bold text-white shadow-xs">
                                            Edit Produk Ini ↗
                                        </a>
                                    </div>
                                </li>
                            </template>
                        </ul>
                        <p class="text-[11px] text-amber-700/80 mt-2 italic">
                            💡 Tips: Jika Anda hanya ingin mengubah harga atau stok, klik tombol "Edit Produk Ini" di atas agar tidak terjadi duplikasi master data.
                        </p>
                    </div>
                </div>

                <!-- SKU / Barcode (Opsional Auto-generate) -->
                <div>
                    <div class="flex items-center justify-between">
                        <label for="sku" class="block text-sm font-semibold text-slate-800">
                            Kode SKU / Barcode
                        </label>
                        <span class="text-xs text-slate-500 italic">Kosongkan untuk generate otomatis sistem</span>
                    </div>
                    <input type="text" id="sku" name="sku" value="{{ old('sku') }}" placeholder="Contoh: PRD-00129 (atau biarkan kosong)" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-mono focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                    <p class="text-[11px] text-slate-400 mt-1">Jika dikosongkan, SKU akan otomatis dibuat dengan format <code>BR{Cabang}-{KodeUnik}</code>.</p>
                </div>

                <!-- Kategori, Satuan & Cabang -->
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="category_id" class="block text-sm font-semibold text-slate-800">
                            Kategori Produk <span class="text-rose-500">*</span>
                        </label>
                        <select id="category_id" name="category_id" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                            <option value="">Pilih Kategori...</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
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
                                <option value="{{ $u }}" {{ old('unit', 'pcs') == $u ? 'selected' : '' }}>
                                    {{ $u }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-800">
                            Cabang Penempatan <span class="text-rose-500">*</span>
                        </label>
                        @if ($isMaster)
                            <select id="branch_id" name="branch_id" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                                <option value="">Pilih Cabang...</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>
                                        {{ $branch->name }} ({{ $branch->code }})
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" disabled value="{{ $currentUser->branch?->name }} ({{ $currentUser->branch?->code }})" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-2.5 text-sm text-slate-600">
                            <p class="text-[11px] text-slate-400 mt-1">Admin cabang otomatis terikat pada cabangnya sendiri.</p>
                        @endif
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
                        <input type="number" step="any" min="0" id="purchase_price" name="purchase_price" value="{{ old('purchase_price') }}" required placeholder="0" class="w-full rounded-xl border border-slate-300 pl-10 pr-4 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
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
                                $fieldValue = old("prices.{$level->id}", $isDefault ? old('selling_price') : '');
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
                                            value="{{ old("customer_group_prices.{$cg->id}") }}"
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

                <!-- Section: Penetapan Harga Override Per Cabang (Branch-Level Pricing) -->
                @if (isset($allBranches) && $allBranches->isNotEmpty())
                    <div x-data="{ openBranchPrices: true }" class="rounded-2xl border border-indigo-200 bg-indigo-50/50 p-5">
                        <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                                    <svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                    Harga Jual Per Cabang (Branch-Level Pricing)
                                </h3>
                                <p class="text-xs text-slate-500 mt-0.5">Tentukan harga jual spesifik untuk masing-masing cabang. Jika dikosongkan, kasir cabang otomatis menggunakan harga standar pusat.</p>
                            </div>
                            <span class="inline-flex self-start sm:self-auto items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-[11px] font-semibold text-indigo-800">
                                Multi-Branch
                            </span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            @foreach ($allBranches as $b)
                                <div class="rounded-xl border border-indigo-200 bg-white p-3.5 shadow-xs">
                                    <div class="flex items-center justify-between mb-1.5">
                                        <label for="branch_price_{{ $b->id }}" class="text-xs font-bold text-slate-800">
                                            {{ $b->name }}
                                        </label>
                                        <span class="text-[10px] text-slate-400 font-mono">{{ $b->code }}</span>
                                    </div>
                                    <div class="relative mt-1">
                                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                            <span class="text-xs font-semibold text-slate-400">Rp</span>
                                        </div>
                                        <input
                                            type="number"
                                            step="any"
                                            min="0"
                                            id="branch_price_{{ $b->id }}"
                                            name="branch_prices[{{ $b->id }}]"
                                            value="{{ old("branch_prices.{$b->id}") }}"
                                            placeholder="Sama dgn harga pusat"
                                            class="w-full rounded-lg border border-slate-300 pl-9 pr-3 py-2 text-sm font-semibold text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                        >
                                    </div>
                                    <p class="text-[10px] text-slate-400 mt-1">
                                        Override harga untuk kasir {{ $b->name }}.
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Stok Awal -->
                <div>
                    <label for="stock" class="block text-sm font-semibold text-slate-800">
                        Kuantitas Stok Awal
                    </label>
                    <input type="number" min="0" id="stock" name="stock" value="{{ old('stock', 0) }}" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                    <p class="text-[11px] text-slate-400 mt-1">Stok awal akan otomatis dicatat ke tabel persediaan cabang.</p>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
                    <a href="{{ route('backoffice.products.index') }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                        Batal
                    </a>
                    <button type="submit" class="rounded-xl bg-sky-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-500 transition-colors">
                        Simpan Produk
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
