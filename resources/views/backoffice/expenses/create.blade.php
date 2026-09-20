<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulir Kas Keluar / Biaya Operasional | Maju Bersama ERP</title>
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
                    <h1 class="text-xl font-bold tracking-tight">Keuangan &amp; Akuntansi</h1>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <nav class="hidden items-center gap-4 text-sm text-slate-300 md:flex">
                    <a href="/pos" class="hover:text-white">POS Kasir</a>
                    <a href="/inventory" class="hover:text-white">Inventory</a>
                    <a href="{{ route('backoffice.expenses.index') }}" class="text-amber-400 font-semibold">Biaya Operasional</a>
                    <a href="{{ route('backoffice.expense-categories.index') }}" class="hover:text-white">Kategori Biaya</a>
                    <a href="/reports/accounting/ledger" class="hover:text-white">Buku Besar</a>
                    <a href="/backoffice" class="hover:text-white">Backoffice</a>
                </nav>
                <div class="rounded-full border border-sky-400/30 bg-sky-400/10 px-3 py-1 text-xs font-medium text-sky-200">
                    {{ $currentUser->branch?->name ?? 'Pusat' }} ({{ $currentUser->role }})
                </div>
                <form method="POST" action="/logout" class="hidden sm:block">
                    @csrf
                    <button type="submit" class="text-sm text-slate-300 hover:text-white">Logout</button>
                </form>
            </div>
        </div>
    </header>

    <!-- Sub-Navbar Modul Pengeluaran -->
    <div class="border-b border-slate-200 bg-white shadow-xs">
        <div class="mx-auto flex max-w-7xl items-center justify-between overflow-x-auto px-6 py-2.5 lg:px-8">
            <div class="flex items-center gap-2 text-sm font-medium">
                <a href="{{ route('backoffice.expenses.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    &larr; Riwayat Kas Keluar
                </a>
                <a href="{{ route('backoffice.expenses.create') }}" class="rounded-lg bg-amber-500 px-3.5 py-2 font-bold text-slate-950 shadow-xs">
                    + Catat Kas Keluar
                </a>
                <a href="{{ route('backoffice.expense-categories.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Kategori Biaya
                </a>
                <a href="/reports/accounting/ledger" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Buku Besar Akuntansi
                </a>
            </div>
        </div>
    </div>

    <main class="mx-auto max-w-5xl space-y-6 px-6 py-8 lg:px-8">
        <!-- Error Banner -->
        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900 shadow-xs">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <div>
                        <p class="text-sm font-bold">Harap periksa kesalahan berikut:</p>
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
                <p class="text-xs font-semibold uppercase tracking-wider text-rose-600 font-bold">Pengeluaran Kas Toko</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Formulir Biaya Operasional</h2>
                <p class="mt-1 text-xs text-slate-500">Mencatat pengeluaran uang kas toko (listrik, bensin, konsumsi, dsb) secara akurat dengan jurnal otomatis.</p>
            </div>
            <a href="{{ route('backoffice.expenses.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50">
                &larr; Riwayat Kas Keluar
            </a>
        </div>

        <div 
            x-data="expenseForm(@js($categories), @js($accounts), {{ (float) old('amount', 0) }})" 
            class="space-y-6"
        >
            <form method="POST" action="{{ route('backoffice.expenses.store') }}" class="space-y-6">
                @csrf

                <!-- Section 1: Formulir Utama -->
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 space-y-6">
                    <div class="border-b border-slate-100 pb-4">
                        <h3 class="text-base font-bold text-slate-900">Rincian Pengeluaran Kas</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Pilih pos pengeluaran dan rekening sumber dana kas yang digunakan.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <!-- Tanggal Pengeluaran -->
                        <div>
                            <label for="expense_date" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Tanggal Pengeluaran <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="date"
                                id="expense_date"
                                name="expense_date"
                                value="{{ old('expense_date', $todayDate) }}"
                                required
                                class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-900 shadow-2xs outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                            >
                        </div>

                        <!-- Kategori Biaya Operasional -->
                        <div>
                            <label for="expense_category_id" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Kategori Biaya Operasional <span class="text-rose-500">*</span>
                            </label>
                            <select
                                id="expense_category_id"
                                name="expense_category_id"
                                x-model="selectedCategoryId"
                                required
                                class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-900 shadow-2xs outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                            >
                                <option value="">-- Pilih Kategori Pengeluaran --</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ old('expense_category_id') == $cat->id ? 'selected' : '' }}>
                                        {{ $cat->name }} (Debet: [{{ $cat->chartOfAccount?->code }}] {{ $cat->chartOfAccount?->name }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Rekening / Sumber Dana Kas/Bank -->
                        <div>
                            <label for="account_id" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Sumber Dana Kas / Rekening <span class="text-rose-500">*</span>
                            </label>
                            <select
                                id="account_id"
                                name="account_id"
                                x-model="selectedAccountId"
                                required
                                class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-900 shadow-2xs outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                            >
                                <option value="">-- Pilih Rekening Kas / Bank --</option>
                                @foreach ($accounts as $acc)
                                    <option value="{{ $acc->id }}" {{ old('account_id') == $acc->id ? 'selected' : '' }}>
                                        [{{ $acc->code }}] {{ $acc->name }} ({{ strtoupper($acc->type) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Nomor Referensi (Opsional/Otomatis) -->
                        <div>
                            <label for="reference_number" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Nomor Referensi / No. Bukti Nota
                            </label>
                            <input
                                type="text"
                                id="reference_number"
                                name="reference_number"
                                value="{{ old('reference_number') }}"
                                placeholder="Contoh: NOTA-PLN-0920, KWT-BENSIN-01..."
                                class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-2xs outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                            >
                            <span class="mt-1 block text-[11px] text-slate-400">Kosongkan untuk nomor referensi otomatis dari sistem</span>
                        </div>

                        <!-- Nominal Pengeluaran (Live Rupiah Formatter) -->
                        <div class="sm:col-span-2">
                            <label for="display_amount" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Nominal Pengeluaran (Rp) <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-base font-extrabold text-slate-400 font-mono">Rp</span>
                                <input
                                    type="text"
                                    id="display_amount"
                                    x-model="formattedAmount"
                                    @input="formatInput($event)"
                                    placeholder="0"
                                    required
                                    class="w-full rounded-xl border border-rose-300 bg-rose-50/20 pl-12 pr-4 py-3.5 text-xl font-mono font-black text-slate-950 outline-none focus:border-rose-500 focus:ring-2 focus:ring-rose-400/20 transition"
                                >
                            </div>
                            <input type="hidden" name="amount" :value="rawAmount">

                            <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                                <span>Ketik nominal angka, sistem otomatis menambahkan pemisah ribuan.</span>
                                <span x-show="rawAmount > 0" class="font-medium text-rose-800">
                                    Nominal terinput: <strong class="font-mono text-slate-900 text-sm" x-text="formatRupiah(rawAmount)"></strong>
                                </span>
                            </div>

                            <!-- Shortcut Tombol Nominal Cepat -->
                            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-5">
                                <button type="button" @click="setAmount(20000)" class="rounded-lg border border-slate-200 bg-slate-50 py-1 text-center text-xs font-semibold text-slate-700 hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 transition">
                                    20.000
                                </button>
                                <button type="button" @click="setAmount(50000)" class="rounded-lg border border-slate-200 bg-slate-50 py-1 text-center text-xs font-semibold text-slate-700 hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 transition">
                                    50.000
                                </button>
                                <button type="button" @click="setAmount(100000)" class="rounded-lg border border-slate-200 bg-slate-50 py-1 text-center text-xs font-semibold text-slate-700 hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 transition">
                                    100.000
                                </button>
                                <button type="button" @click="setAmount(250000)" class="rounded-lg border border-slate-200 bg-slate-50 py-1 text-center text-xs font-semibold text-slate-700 hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 transition">
                                    250.000
                                </button>
                                <button type="button" @click="setAmount(500000)" class="rounded-lg border border-slate-200 bg-slate-50 py-1 text-center text-xs font-semibold text-slate-700 hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 transition">
                                    500.000
                                </button>
                            </div>
                        </div>

                        <!-- Catatan / Keterangan Pengeluaran (Wajib) -->
                        <div class="sm:col-span-2">
                            <label for="notes" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Catatan / Keterangan Pengeluaran <span class="text-rose-500">*</span>
                            </label>
                            <textarea
                                id="notes"
                                name="notes"
                                rows="3"
                                x-model="notes"
                                required
                                placeholder="Tuliskan tujuan pengeluaran dengan rinci (misal: Beli token PLN 200rb untuk AC toko lantai 1)..."
                                class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-2xs outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                            >{{ old('notes') }}</textarea>
                            <p class="mt-1 text-[11px] text-slate-400">Catatan ini akan otomatis dicantumkan pada memo jurnal akuntansi buku besar.</p>
                        </div>
                    </div>
                </section>

                <!-- Section 2: Live Journal Preview (Otomatisasi Jurnal Akuntansi) -->
                <section class="rounded-2xl border border-indigo-200 bg-indigo-50/40 p-6 shadow-xs">
                    <div class="flex items-start gap-3 mb-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-xs">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h4 class="text-sm font-bold text-indigo-950">Live Journal Preview (Double-Entry Bookkeeping)</h4>
                                <span class="rounded-full bg-emerald-500/20 px-2 py-0.5 text-[10px] font-bold text-emerald-800 border border-emerald-300">
                                    Balanced
                                </span>
                            </div>
                            <p class="text-xs text-indigo-800 mt-0.5">
                                Sistem secara otomatis akan membukukan jurnal berpasangan seimbang ke Buku Besar saat form disubmit:
                            </p>
                        </div>
                    </div>

                    <!-- Kartu Live Preview Baris Jurnal -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <!-- Sisi Debit -->
                        <div class="rounded-xl border border-emerald-300 bg-white p-4 shadow-2xs">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Posisi DEBET (Beban Bertambah)</span>
                                <span class="text-xs font-mono font-bold text-emerald-800" x-text="formatRupiah(rawAmount)"></span>
                            </div>
                            <div class="mt-2 text-xs">
                                <p class="font-bold text-slate-900" x-text="selectedCategoryName ? selectedCategoryName : 'Pilih kategori biaya...'"></p>
                                <p class="text-slate-500 font-mono text-[11px] mt-0.5" x-text="selectedAccountBeban ? selectedAccountBeban : 'Akun COA 6xxx'"></p>
                            </div>
                        </div>

                        <!-- Sisi Kredit -->
                        <div class="rounded-xl border border-rose-300 bg-white p-4 shadow-2xs">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-rose-700">Posisi KREDIT (Kas Berkurang)</span>
                                <span class="text-xs font-mono font-bold text-rose-800" x-text="formatRupiah(rawAmount)"></span>
                            </div>
                            <div class="mt-2 text-xs">
                                <p class="font-bold text-slate-900" x-text="selectedAccountName ? selectedAccountName : 'Pilih akun kas/bank...'"></p>
                                <p class="text-slate-500 font-mono text-[11px] mt-0.5" x-text="selectedAccountKas ? selectedAccountKas : 'Akun COA 11xx'"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Keterangan Memo Jurnal Terkini -->
                    <div class="mt-4 rounded-xl border border-indigo-100 bg-white/70 p-3 text-xs text-slate-600">
                        <span class="font-bold text-indigo-900">Keterangan Memo Jurnal:</span>
                        <span class="font-mono text-slate-800 italic" x-text="computedJournalDescription"></span>
                    </div>
                </section>

                <!-- Tombol Aksi Form -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="{{ route('backoffice.expenses.index') }}" class="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition-colors">
                        Batal
                    </a>
                    <button
                        type="submit"
                        id="btn-submit-expense"
                        :disabled="rawAmount <= 0"
                        class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-7 py-3 text-sm font-bold text-white shadow-md transition hover:bg-rose-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Simpan &amp; Bukukan Kas Keluar
                    </button>
                </div>
            </form>
        </div>
    </main>

    <!-- Alpine.js Component Script -->
    <script>
        function expenseForm(categories = [], accounts = [], initialAmount = 0) {
            return {
                categories: categories,
                accounts: accounts,
                rawAmount: initialAmount || 0,
                formattedAmount: initialAmount > 0 ? new Intl.NumberFormat('id-ID').format(initialAmount) : '',
                selectedCategoryId: '{{ old("expense_category_id", "") }}',
                selectedAccountId: '{{ old("account_id", "") }}',
                notes: '{{ old("notes", "") }}',

                get currentCategory() {
                    return this.categories.find(c => c.id == this.selectedCategoryId) || null;
                },

                get currentAccount() {
                    return this.accounts.find(a => a.id == this.selectedAccountId) || null;
                },

                get selectedCategoryName() {
                    return this.currentCategory ? this.currentCategory.name : '';
                },

                get selectedAccountBeban() {
                    if (this.currentCategory && this.currentCategory.chart_of_account) {
                        return '[' + this.currentCategory.chart_of_account.code + '] ' + this.currentCategory.chart_of_account.name;
                    }
                    return '';
                },

                get selectedAccountName() {
                    return this.currentAccount ? this.currentAccount.name : '';
                },

                get selectedAccountKas() {
                    return this.currentAccount ? '[' + this.currentAccount.code + '] ' + this.currentAccount.name : '';
                },

                get computedJournalDescription() {
                    let desc = 'Biaya Operasional: ' + (this.selectedCategoryName || '[Kategori]');
                    if (this.notes.trim()) {
                        desc += ' - ' + this.notes.trim();
                    }
                    return desc;
                },

                formatInput(event) {
                    let value = event.target.value.replace(/[^\d]/g, '');
                    this.rawAmount = value ? parseInt(value, 10) : 0;
                    this.formattedAmount = value ? new Intl.NumberFormat('id-ID').format(this.rawAmount) : '';
                },

                setAmount(val) {
                    this.rawAmount = val;
                    this.formattedAmount = new Intl.NumberFormat('id-ID').format(val);
                },

                formatRupiah(val) {
                    return 'Rp ' + new Intl.NumberFormat('id-ID').format(val || 0);
                }
            };
        }
    </script>
</body>
</html>
