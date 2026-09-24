<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Harta Tetap | Maju Bersama ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        .font-mono-num { font-family: 'JetBrains Mono', monospace; font-variant-numeric: tabular-nums; }
    </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased flex flex-col">
    <!-- Header Utama -->
    <header class="border-b border-slate-800 bg-slate-950 text-white shrink-0">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3.5 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                <a href="/backoffice" class="block group">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-amber-400 group-hover:text-amber-300 transition">Maju Bersama ERP</p>
                    <div class="flex items-center gap-2">
                        <h1 class="text-base font-bold tracking-tight">Pendaftaran Harta Tetap Baru</h1>
                        <span class="rounded bg-purple-500/20 px-2 py-0.5 text-[10px] font-mono font-semibold text-purple-300">Depresiasi Garis Lurus</span>
                    </div>
                </a>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('backoffice.fixed-assets.index') }}" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800 hover:text-white transition">
                    &larr; Kembali ke Daftar Aset
                </a>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-1 mx-auto w-full max-w-4xl p-4 sm:p-6 lg:p-8 space-y-6">
        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-rose-900 shadow-xs">
                <div class="flex items-start gap-3">
                    <svg class="h-5 w-5 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <p class="text-sm font-bold">Harap perbaiki kesalahan berikut:</p>
                        <ul class="mt-1 list-disc list-inside text-xs text-rose-700 space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <div x-data="fixedAssetForm()" class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm">
            <div class="border-b border-slate-100 pb-5 mb-6">
                <h2 class="text-lg font-bold text-slate-900">Formulir Pengadaan &amp; Kapitalisasi Harta Tetap</h2>
                <p class="text-xs text-slate-500 mt-1">Lengkapi informasi aset tetap, harga perolehan, estimasi umur ekonomis, dan pemetaan akun akuntansi untuk depresiasi otomatis.</p>
            </div>

            <form method="POST" action="{{ route('backoffice.fixed-assets.store') }}" class="space-y-6">
                @csrf

                <!-- Cabang (Jika Master) -->
                @if ($isMaster)
                    <div class="rounded-xl bg-slate-50 p-4 border border-slate-200">
                        <label for="branch_id" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Cabang Pemilik Aset <span class="text-rose-500">*</span></label>
                        <select id="branch_id" name="branch_id" required class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none">
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}" {{ old('branch_id') == $b->id ? 'selected' : '' }}>
                                    {{ $b->name }} ({{ $b->code }})
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-slate-500 mt-1">Aset tetap dan beban depresiasi akan dialokasikan pada cabang ini.</p>
                    </div>
                @endif

                <!-- Baris 1: Nama Aset & Tanggal Perolehan -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="name" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Nama Aset Tetap <span class="text-rose-500">*</span></label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="Contoh: Mobil Operasional Pick-up Grand Max" required class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none">
                        <p class="text-[11px] text-slate-400 mt-1">Sertakan tipe, seri, atau nomor inventaris fisik bila ada.</p>
                    </div>

                    <div>
                        <label for="purchase_date" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Tanggal Perolehan (Beli) <span class="text-rose-500">*</span></label>
                        <input type="date" id="purchase_date" name="purchase_date" value="{{ old('purchase_date', now()->toDateString()) }}" required class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none">
                        <p class="text-[11px] text-slate-400 mt-1">Tanggal mulai diakui sebagai aktiva perusahaan.</p>
                    </div>
                </div>

                <!-- Baris 2: Harga Perolehan, Nilai Sisa, Umur Ekonomis -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="purchase_price" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Harga Perolehan (Rp) <span class="text-rose-500">*</span></label>
                        <input type="number" step="0.01" min="1" id="purchase_price" name="purchase_price" x-model.number="price" required class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold font-mono text-slate-900 focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none">
                        <p class="text-[11px] text-slate-400 mt-1">Total biaya kapitalisasi perolehan aset.</p>
                    </div>

                    <div>
                        <label for="salvage_value" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Nilai Residu / Sisa (Rp)</label>
                        <input type="number" step="0.01" min="0" id="salvage_value" name="salvage_value" x-model.number="salvage" value="{{ old('salvage_value', 0) }}" class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold font-mono text-slate-900 focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none">
                        <p class="text-[11px] text-slate-400 mt-1">Estimasi nilai sisa di akhir masa manfaat (0 bila habis).</p>
                    </div>

                    <div>
                        <label for="useful_life_months" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Umur Ekonomis (Bulan) <span class="text-rose-500">*</span></label>
                        <input type="number" min="1" id="useful_life_months" name="useful_life_months" x-model.number="months" required class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold font-mono text-slate-900 focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none">
                        <p class="text-[11px] text-slate-400 mt-1" x-text="formatYears()"></p>
                    </div>
                </div>

                <!-- Kalkulasi Simulasi Penyusutan Garis Lurus (Live Reactive Widget) -->
                <div class="rounded-xl border border-purple-200 bg-purple-50/70 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-purple-900">Simulasi Depresiasi Garis Lurus Bulanan</p>
                            <p class="text-xs text-purple-700 mt-0.5">Rumus: <code>(Harga Perolehan - Nilai Residu) / Umur Ekonomis</code></p>
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-purple-800 font-semibold">Estimasi Beban Bulanan:</span>
                            <p class="text-xl font-black font-mono-num text-purple-950" x-text="formatRupiah(calculateMonthly())"></p>
                        </div>
                    </div>
                </div>

                <!-- Bagian Pemetaan Akun Akuntansi (3 Dropdown) -->
                <div class="pt-4 border-t border-slate-200">
                    <h3 class="text-sm font-bold text-slate-900 mb-1">Integrasi Jurnal Akuntansi (Chart of Accounts)</h3>
                    <p class="text-xs text-slate-500 mb-4">Tentukan 3 akun buku besar untuk pencatatan perolehan dan jurnal otomatis depresiasi.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <!-- Dropdown 1: Akun Harta -->
                        <div>
                            <label for="asset_account_id" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                1. Akun Harta (Aktiva Tetap) <span class="text-rose-500">*</span>
                            </label>
                            <select id="asset_account_id" name="asset_account_id" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-900 focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none">
                                <option value="">-- Pilih Akun Harta --</option>
                                @foreach ($accounts as $acc)
                                    <option value="{{ $acc->id }}" {{ old('asset_account_id') == $acc->id ? 'selected' : '' }}>
                                        [{{ $acc->code }}] {{ $acc->name }} ({{ $acc->type }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-slate-400 mt-1">Akun neraca kelompok Aktiva Tetap (Asset).</p>
                        </div>

                        <!-- Dropdown 2: Akun Akumulasi Penyusutan -->
                        <div>
                            <label for="depreciation_account_id" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                2. Akun Akumulasi Penyusutan <span class="text-rose-500">*</span>
                            </label>
                            <select id="depreciation_account_id" name="depreciation_account_id" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-900 focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none">
                                <option value="">-- Pilih Akun Akumulasi --</option>
                                @foreach ($accounts as $acc)
                                    <option value="{{ $acc->id }}" {{ old('depreciation_account_id') == $acc->id ? 'selected' : '' }}>
                                        [{{ $acc->code }}] {{ $acc->name }} ({{ $acc->type }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-slate-400 mt-1">Kontra-aset neraca (Kredit saat depresiasi).</p>
                        </div>

                        <!-- Dropdown 3: Akun Beban Penyusutan -->
                        <div>
                            <label for="expense_account_id" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                3. Akun Beban Penyusutan <span class="text-rose-500">*</span>
                            </label>
                            <select id="expense_account_id" name="expense_account_id" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-900 focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none">
                                <option value="">-- Pilih Akun Beban --</option>
                                @foreach ($accounts as $acc)
                                    <option value="{{ $acc->id }}" {{ old('expense_account_id') == $acc->id ? 'selected' : '' }}>
                                        [{{ $acc->code }}] {{ $acc->name }} ({{ $acc->type }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-slate-400 mt-1">Beban laba rugi (Debit saat depresiasi).</p>
                        </div>
                    </div>
                </div>

                <!-- Tombol Aksi -->
                <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-200">
                    <a href="{{ route('backoffice.fixed-assets.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                        Batal
                    </a>
                    <button type="submit" id="btn-save-asset" class="inline-flex items-center gap-2 rounded-lg bg-purple-700 px-5 py-2.5 text-xs font-bold text-white shadow-xs hover:bg-purple-800 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Simpan Aset Tetap</span>
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        function fixedAssetForm() {
            return {
                price: {{ old('purchase_price', 120000000) }},
                salvage: {{ old('salvage_value', 0) }},
                months: {{ old('useful_life_months', 48) }},
                formatYears() {
                    const m = Number(this.months) || 0;
                    const y = (m / 12).toFixed(1);
                    return `Setara dengan ${y} tahun masa manfaat.`;
                },
                calculateMonthly() {
                    const p = Number(this.price) || 0;
                    const s = Number(this.salvage) || 0;
                    const m = Number(this.months) || 1;
                    if (m <= 0) return 0;
                    const base = Math.max(0, p - s);
                    return base / m;
                },
                formatRupiah(val) {
                    return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
            };
        }
    </script>
</body>
</html>
