<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Kas &amp; Mutasi Bank | Maju Bersama ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <!-- Header Utama -->
    <header class="border-b border-slate-800 bg-slate-950 text-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
            <div class="flex items-center gap-4">
                <a href="/backoffice" class="block">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-400">Maju Bersama ERP</p>
                    <h1 class="text-xl font-bold tracking-tight">Kas &amp; Bank (Treasury)</h1>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <nav class="hidden items-center gap-4 text-sm text-slate-300 md:flex">
                    <a href="/pos" class="hover:text-white">POS Kasir</a>
                    <a href="/inventory" class="hover:text-white">Inventory</a>
                    <a href="{{ route('backoffice.cash-transfers.index') }}" class="text-amber-400 font-semibold">Mutasi Kas &amp; Bank</a>
                    <a href="{{ route('backoffice.expenses.index') }}" class="hover:text-white">Biaya Operasional</a>
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

    <!-- Sub-Navbar Modul Kas -->
    <div class="border-b border-slate-200 bg-white shadow-xs">
        <div class="mx-auto flex max-w-7xl items-center justify-between overflow-x-auto px-6 py-2.5 lg:px-8">
            <div class="flex items-center gap-2 text-sm font-medium">
                <a href="{{ route('backoffice.cash-transfers.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    &larr; Riwayat Mutasi Kas
                </a>
                <a href="{{ route('backoffice.cash-transactions.create') }}" class="rounded-lg bg-amber-500 px-3.5 py-2 font-bold text-slate-950 shadow-xs">
                    + Transaksi &amp; Mutasi Kas
                </a>
                <a href="{{ route('backoffice.expenses.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Biaya Operasional
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
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        <p class="text-sm font-bold">Harap periksa kesalahan input berikut:</p>
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
                <p class="text-xs font-semibold uppercase tracking-wider text-amber-600 font-bold">Treasury &amp; Cash Management</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Formulir Transaksi &amp; Mutasi Kas</h2>
                <p class="mt-1 text-xs text-slate-500">Kelola pemindahan dana antar kas/bank (Transfer Kas) maupun penerimaan dan pengeluaran kas dengan jurnal ganda otomatis.</p>
            </div>
            <a href="{{ route('backoffice.cash-transfers.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50">
                &larr; Riwayat Mutasi
            </a>
        </div>

        <div 
            x-data="cashTransactionApp(@js($cashBankAccounts), @js($allAccounts), '{{ old('type', $defaultType ?? 'TRANSFER') }}', {{ (float) old('amount', 0) }})" 
            class="space-y-6"
        >
            <form method="POST" action="{{ route('backoffice.cash-transactions.store') }}" class="space-y-6">
                @csrf

                <!-- Section 0: Pilihan Tipe Transaksi -->
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Jenis Transaksi Kas</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Pilih tipe transaksi: Transfer Dana Antar Rekening, Kas Masuk, atau Kas Keluar.</p>
                        </div>
                        <span class="rounded-lg bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-700 uppercase tracking-wider border border-amber-200" x-text="type"></span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label 
                            class="relative flex cursor-pointer flex-col rounded-xl border p-4 text-center transition-all"
                            :class="type === 'TRANSFER' ? 'border-amber-500 bg-amber-50/60 ring-2 ring-amber-400' : 'border-slate-200 hover:border-slate-300 bg-white'"
                        >
                            <input type="radio" name="type" value="TRANSFER" x-model="type" class="sr-only">
                            <span class="text-sm font-bold text-slate-900">Transfer Kas (Mutasi)</span>
                            <span class="mt-1 text-xs text-slate-500">Pindah dana antar rekening kas &amp; bank</span>
                        </label>

                        <label 
                            class="relative flex cursor-pointer flex-col rounded-xl border p-4 text-center transition-all"
                            :class="type === 'IN' ? 'border-emerald-500 bg-emerald-50/60 ring-2 ring-emerald-400' : 'border-slate-200 hover:border-slate-300 bg-white'"
                        >
                            <input type="radio" name="type" value="IN" x-model="type" class="sr-only">
                            <span class="text-sm font-bold text-slate-900">Kas Masuk (Receipt)</span>
                            <span class="mt-1 text-xs text-slate-500">Penerimaan dana selain dari kasir POS</span>
                        </label>

                        <label 
                            class="relative flex cursor-pointer flex-col rounded-xl border p-4 text-center transition-all"
                            :class="type === 'OUT' ? 'border-rose-500 bg-rose-50/60 ring-2 ring-rose-400' : 'border-slate-200 hover:border-slate-300 bg-white'"
                        >
                            <input type="radio" name="type" value="OUT" x-model="type" class="sr-only">
                            <span class="text-sm font-bold text-slate-900">Kas Keluar (Disbursement)</span>
                            <span class="mt-1 text-xs text-slate-500">Pengeluaran kas selain biaya operasional</span>
                        </label>
                    </div>
                </section>

                <!-- Section 1: Data Umum Transaksi -->
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 space-y-6">
                    <div class="border-b border-slate-100 pb-4">
                        <h3 class="text-base font-bold text-slate-900">Informasi Umum</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Tanggal dan nomor referensi transaksi.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <!-- Tanggal Transaksi -->
                        <div>
                            <label for="transaction_date" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Tanggal Transaksi <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="date"
                                id="transaction_date"
                                name="transaction_date"
                                x-model="transactionDate"
                                required
                                class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-900 shadow-2xs outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                            >
                        </div>

                        <!-- Nomor Referensi -->
                        <div>
                            <label for="reference_number" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Nomor Referensi <span class="text-slate-400 font-normal lowercase">(opsional)</span>
                            </label>
                            <input
                                type="text"
                                id="reference_number"
                                name="reference_number"
                                x-model="referenceNumber"
                                placeholder="Contoh: TRF-BCA-001 (kosongkan untuk otomatis)"
                                class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-2xs outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                            >
                            <span class="mt-1 block text-[11px] text-slate-400">Jika dikosongkan, sistem akan mengenerate nomor otomatis.</span>
                        </div>
                    </div>

                    <!-- KONDISI TYPE == 'TRANSFER' -->
                    <div x-show="type === 'TRANSFER'" class="space-y-6 pt-2">
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <!-- Dropdown Akun Sumber (Dari) -->
                            <div>
                                <label for="from_account_id" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                    Akun Sumber (Dari) <span class="text-rose-500">*</span>
                                </label>
                                <select
                                    id="from_account_id"
                                    name="from_account_id"
                                    x-model="fromAccountId"
                                    :required="type === 'TRANSFER'"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-900 shadow-2xs outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                >
                                    <option value="">-- Pilih Akun Sumber Dana (Kas/Bank) --</option>
                                    <template x-for="acc in cashBankAccounts" :key="'from-' + acc.id">
                                        <option :value="acc.id" x-text="`[${acc.code}] ${acc.name}`"></option>
                                    </template>
                                </select>
                                <span class="mt-1 block text-[11px] text-slate-400">Posisi Kredit pada Jurnal (Saldo kas/bank asal berkurang).</span>
                            </div>

                            <!-- Dropdown Akun Tujuan (Ke) -->
                            <div>
                                <label for="to_account_id" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                    Akun Tujuan (Ke) <span class="text-rose-500">*</span>
                                </label>
                                <select
                                    id="to_account_id"
                                    name="to_account_id"
                                    x-model="toAccountId"
                                    :required="type === 'TRANSFER'"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-900 shadow-2xs outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                >
                                    <option value="">-- Pilih Akun Tujuan Penerima (Kas/Bank) --</option>
                                    <template x-for="acc in cashBankAccounts" :key="'to-' + acc.id">
                                        <option :value="acc.id" x-text="`[${acc.code}] ${acc.name}`" :disabled="acc.id == fromAccountId"></option>
                                    </template>
                                </select>
                                <span class="mt-1 block text-[11px] text-slate-400">Posisi Debit pada Jurnal (Saldo kas/bank tujuan bertambah).</span>
                            </div>
                        </div>

                        <!-- Satu Field Input Nominal Transfer -->
                        <div>
                            <label for="amount" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Nominal Transfer (Rp) <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-sm font-bold text-slate-400">Rp</span>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    id="amount"
                                    name="amount"
                                    x-model.number="amount"
                                    :required="type === 'TRANSFER'"
                                    placeholder="0"
                                    class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-12 pr-4 text-lg font-bold text-slate-900 shadow-2xs outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                >
                            </div>
                            <div class="mt-1.5 flex items-center justify-between text-xs">
                                <span class="font-semibold text-amber-700" x-text="formatRupiah(amount)"></span>
                                <span class="text-slate-400">Pastikan nominal transfer > 0</span>
                            </div>
                        </div>
                    </div>

                    <!-- KONDISI TYPE !== 'TRANSFER' (IN / OUT) -->
                    <div x-show="type !== 'TRANSFER'" class="space-y-6 pt-2">
                        <!-- Akun Kas Utama -->
                        <div>
                            <label for="account_id" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Akun Kas / Bank Utama <span class="text-rose-500">*</span>
                            </label>
                            <select
                                id="account_id"
                                name="account_id"
                                x-model="mainAccountId"
                                :required="type !== 'TRANSFER'"
                                class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-900 shadow-2xs outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                            >
                                <option value="">-- Pilih Akun Kas/Bank Utama --</option>
                                <template x-for="acc in cashBankAccounts" :key="'main-' + acc.id">
                                    <option :value="acc.id" x-text="`[${acc.code}] ${acc.name}`"></option>
                                </template>
                            </select>
                        </div>

                        <!-- TABEL DYNAMIC ROWS (AKUN LAWAN) -->
                        <div class="border-t border-slate-100 pt-4">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <h4 class="text-sm font-bold text-slate-900">Rincian Baris Transaksi (Akun Lawan)</h4>
                                    <p class="text-xs text-slate-500">Tentukan akun lawan penerimaan/pengeluaran serta alokasi nominalnya.</p>
                                </div>
                                <button
                                    type="button"
                                    @click="addRow()"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-sky-50 px-3 py-1.5 text-xs font-bold text-sky-700 border border-sky-200 hover:bg-sky-100"
                                >
                                    + Tambah Baris
                                </button>
                            </div>

                            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                                <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                                    <thead class="bg-slate-50 text-slate-700 font-bold uppercase tracking-wider">
                                        <tr>
                                            <th class="px-3 py-2.5">Akun Lawan</th>
                                            <th class="px-3 py-2.5 w-48">Nominal (Rp)</th>
                                            <th class="px-3 py-2.5">Keterangan / Memo</th>
                                            <th class="px-3 py-2.5 w-16 text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <template x-for="(row, index) in dynamicRows" :key="index">
                                            <tr>
                                                <td class="p-2">
                                                    <select
                                                        :name="`lines[${index}][chart_of_account_id]`"
                                                        x-model="row.chart_of_account_id"
                                                        class="w-full rounded-lg border border-slate-300 p-2 text-xs font-medium outline-none focus:border-amber-500"
                                                    >
                                                        <option value="">-- Pilih Akun Lawan --</option>
                                                        <template x-for="acc in allAccounts" :key="'opp-' + acc.id">
                                                            <option :value="acc.id" x-text="`[${acc.code}] ${acc.name}`"></option>
                                                        </template>
                                                    </select>
                                                </td>
                                                <td class="p-2">
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0.01"
                                                        :name="`lines[${index}][amount]`"
                                                        x-model.number="row.amount"
                                                        placeholder="0"
                                                        class="w-full rounded-lg border border-slate-300 p-2 text-xs font-bold outline-none focus:border-amber-500 text-right"
                                                    >
                                                </td>
                                                <td class="p-2">
                                                    <input
                                                        type="text"
                                                        :name="`lines[${index}][memo]`"
                                                        x-model="row.memo"
                                                        placeholder="Keterangan baris (opsional)"
                                                        class="w-full rounded-lg border border-slate-300 p-2 text-xs outline-none focus:border-amber-500"
                                                    >
                                                </td>
                                                <td class="p-2 text-center">
                                                    <button
                                                        type="button"
                                                        @click="removeRow(index)"
                                                        :disabled="dynamicRows.length <= 1"
                                                        class="rounded-lg p-1.5 text-rose-500 hover:bg-rose-50 disabled:opacity-30"
                                                    >
                                                        &times;
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Catatan / Uraian Transaksi -->
                    <div>
                        <label for="notes" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Catatan / Keterangan Transaksi <span class="text-slate-400 font-normal lowercase">(opsional)</span>
                        </label>
                        <textarea
                            id="notes"
                            name="notes"
                            x-model="notes"
                            rows="2"
                            placeholder="Contoh: Setoran hasil penjualan laci kasir ke rekening BCA utama toko..."
                            class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-900 shadow-2xs outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                        ></textarea>
                    </div>
                </section>

                <!-- Section 2: Live Journal Preview (Double-Entry Bookkeeping) -->
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900">Live Journal Preview (Simulasi Jurnal Otomatis)</h3>
                        </div>
                        <span 
                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-bold"
                            :class="isBalanced ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                        >
                            <span class="h-1.5 w-1.5 rounded-full" :class="isBalanced ? 'bg-emerald-600' : 'bg-rose-600'"></span>
                            <span x-text="isBalanced ? 'Jurnal Seimbang (Balanced)' : 'Belum Lengkap'"></span>
                        </span>
                    </div>

                    <!-- Simulasi Baris Jurnal Transfer -->
                    <template x-if="type === 'TRANSFER'">
                        <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50/50">
                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                <thead class="bg-slate-100 text-slate-600 font-bold uppercase">
                                    <tr>
                                        <th class="px-4 py-2.5 text-left">Kode &amp; Nama Akun</th>
                                        <th class="px-4 py-2.5 text-right w-36">Debit (Rp)</th>
                                        <th class="px-4 py-2.5 text-right w-36">Kredit (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <tr>
                                        <td class="px-4 py-2 font-medium text-slate-800">
                                            <span class="inline-block rounded bg-sky-100 px-1.5 py-0.5 text-[10px] font-bold text-sky-800 mr-1.5">DEBIT</span>
                                            <span x-text="getToAccountLabel()"></span>
                                        </td>
                                        <td class="px-4 py-2 text-right font-bold text-slate-900" x-text="formatRupiah(amount)"></td>
                                        <td class="px-4 py-2 text-right text-slate-400">0</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-2 font-medium text-slate-800 pl-8">
                                            <span class="inline-block rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800 mr-1.5">KREDIT</span>
                                            <span x-text="getFromAccountLabel()"></span>
                                        </td>
                                        <td class="px-4 py-2 text-right text-slate-400">0</td>
                                        <td class="px-4 py-2 text-right font-bold text-slate-900" x-text="formatRupiah(amount)"></td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-slate-50 font-bold text-slate-900 border-t border-slate-200">
                                    <tr>
                                        <td class="px-4 py-2 text-right uppercase tracking-wider text-[11px]">Total Jurnal:</td>
                                        <td class="px-4 py-2 text-right text-emerald-700" x-text="formatRupiah(amount)"></td>
                                        <td class="px-4 py-2 text-right text-emerald-700" x-text="formatRupiah(amount)"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </template>

                    <!-- Simulasi Baris Jurnal Non-Transfer -->
                    <template x-if="type !== 'TRANSFER'">
                        <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50/50 p-4 text-xs text-slate-600">
                            <p class="font-medium">Total Akumulasi Baris Lawan: <span class="font-bold text-slate-900" x-text="formatRupiah(getTotalDynamicAmount())"></span></p>
                            <p class="mt-1 text-slate-500">Jurnal berpasangan ganda akan dibukukan secara seimbang ke akun kas/bank utama yang dipilih.</p>
                        </div>
                    </template>
                </section>

                <!-- Tombol Submit -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <a
                        href="{{ route('backoffice.cash-transfers.index') }}"
                        class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50"
                    >
                        Batal
                    </a>
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-amber-500 px-6 py-2.5 text-xs font-bold text-slate-950 shadow-sm hover:bg-amber-400 focus:ring-2 focus:ring-amber-500 focus:ring-offset-2"
                    >
                        <span>Simpan &amp; Bukukan Transaksi</span>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        function cashTransactionApp(cashBankAccounts, allAccounts, initialType, initialAmount) {
            return {
                type: initialType || 'TRANSFER',
                cashBankAccounts: cashBankAccounts || [],
                allAccounts: allAccounts || [],
                transactionDate: '{{ $todayDate ?? now()->toDateString() }}',
                referenceNumber: '',
                fromAccountId: '',
                toAccountId: '',
                mainAccountId: '',
                amount: initialAmount || 0,
                notes: '',
                dynamicRows: [
                    { chart_of_account_id: '', amount: 0, memo: '' }
                ],

                addRow() {
                    this.dynamicRows.push({ chart_of_account_id: '', amount: 0, memo: '' });
                },

                removeRow(index) {
                    if (this.dynamicRows.length > 1) {
                        this.dynamicRows.splice(index, 1);
                    }
                },

                getTotalDynamicAmount() {
                    return this.dynamicRows.reduce((sum, r) => sum + (parseFloat(r.amount) || 0), 0);
                },

                getFromAccountLabel() {
                    const acc = this.cashBankAccounts.find(a => a.id == this.fromAccountId);
                    return acc ? `[${acc.code}] ${acc.name}` : '(Pilih Akun Sumber)';
                },

                getToAccountLabel() {
                    const acc = this.cashBankAccounts.find(a => a.id == this.toAccountId);
                    return acc ? `[${acc.code}] ${acc.name}` : '(Pilih Akun Tujuan)';
                },

                get isBalanced() {
                    if (this.type === 'TRANSFER') {
                        return this.fromAccountId && this.toAccountId && (this.fromAccountId != this.toAccountId) && this.amount > 0;
                    }
                    return this.mainAccountId && this.getTotalDynamicAmount() > 0;
                },

                formatRupiah(val) {
                    const num = parseFloat(val) || 0;
                    return 'Rp ' + Math.round(num).toLocaleString('id-ID');
                }
            }
        }
    </script>
</body>
</html>
