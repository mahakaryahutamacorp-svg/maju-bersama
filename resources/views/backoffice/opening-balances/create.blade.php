<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Saldo Awal Perusahaan | Maju Bersama ERP</title>
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
                    <a href="{{ route('backoffice.cash-transfers.index') }}" class="hover:text-white">Mutasi Kas</a>
                    <a href="{{ route('backoffice.expenses.index') }}" class="hover:text-white">Biaya Operasional</a>
                    <a href="{{ route('backoffice.opening-balances.create') }}" class="text-amber-400 font-semibold">Saldo Awal</a>
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

    <main class="mx-auto max-w-5xl space-y-6 px-6 py-8 lg:px-8">
        <!-- Flash Messages -->
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900 shadow-xs">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span class="text-sm font-semibold">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        <!-- Error Banner -->
        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900 shadow-xs">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
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

        <!-- Judul Halaman -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <div class="flex items-center gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-amber-600 font-bold">Setup Go-Live Perusahaan</p>
                    <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-extrabold text-amber-900">Step 1</span>
                </div>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Input Saldo Awal (Opening Balance)</h2>
                <p class="mt-1 text-xs text-slate-500">Membukukan posisi awal aset, kewajiban, dan modal bersih perusahaan secara otomatis dan seimbang ke dalam buku besar akuntansi.</p>
            </div>
            <a href="/backoffice" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50">
                &larr; Dashboard Backoffice
            </a>
        </div>

        @if ($hasOpeningBalance && $existingJournal)
            <!-- Status Sudah Terinput / Readonly Summary -->
            <section class="rounded-2xl border border-emerald-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-emerald-100 bg-emerald-50/70 px-6 py-5">
                    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-xs">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Saldo Awal Sistem Telah Dibukukan</h3>
                                <p class="text-xs text-slate-600">Cabang <span class="font-bold">{{ $currentUser->branch?->name }}</span> telah menyelesaikan setup saldo awal buku besar.</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="font-mono text-xs font-bold text-emerald-800 bg-emerald-100 px-3 py-1 rounded-lg border border-emerald-200">
                                Ref: {{ $existingJournal->reference_number }}
                            </span>
                            <p class="mt-1 text-[11px] text-slate-500">Tanggal: {{ $existingJournal->transaction_date->format('d F Y') }}</p>
                        </div>
                    </div>
                </div>

                <div class="p-6 sm:p-8 space-y-5">
                    <p class="text-xs text-slate-500">
                        Berikut adalah rincian jurnal pembukuan saldo awal yang aktif pada buku besar cabang ini:
                    </p>

                    <div class="overflow-hidden rounded-xl border border-slate-200 shadow-2xs">
                        <table class="min-w-full divide-y divide-slate-200 text-xs">
                            <thead class="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-3 text-left">Kode Akun</th>
                                    <th class="px-4 py-3 text-left">Nama Akun Buku Besar</th>
                                    <th class="px-4 py-3 text-left">Keterangan / Memo</th>
                                    <th class="px-4 py-3 text-right">Debit (Rp)</th>
                                    <th class="px-4 py-3 text-right">Kredit (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-mono">
                                @foreach ($existingJournal->journalLines as $line)
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 font-semibold text-slate-700">{{ $line->chartOfAccount?->code }}</td>
                                        <td class="px-4 py-3 font-sans font-bold text-slate-900">{{ $line->chartOfAccount?->name }}</td>
                                        <td class="px-4 py-3 font-sans text-slate-500">{{ $line->memo }}</td>
                                        <td class="px-4 py-3 text-right font-bold text-emerald-700">
                                            {{ (float) $line->debit > 0 ? number_format((float) $line->debit, 0, ',', '.') : '-' }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-bold text-rose-700">
                                            {{ (float) $line->credit > 0 ? number_format((float) $line->credit, 0, ',', '.') : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-slate-50 font-bold text-slate-900 border-t border-slate-200">
                                <tr>
                                    <td colspan="3" class="px-4 py-2.5 text-right font-sans uppercase text-[11px] text-slate-500">Total Keseimbangan:</td>
                                    <td class="px-4 py-2.5 text-right text-emerald-700">
                                        Rp {{ number_format((float) $existingJournal->journalLines->sum('debit'), 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-rose-700">
                                        Rp {{ number_format((float) $existingJournal->journalLines->sum('credit'), 0, ',', '.') }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="flex items-center justify-between pt-2 text-xs text-slate-400">
                        <span>Untuk melihat buku besar lengkap, silakan kunjungi menu Buku Besar Akuntansi.</span>
                        <a href="/reports/accounting/ledger" class="font-semibold text-sky-700 hover:text-sky-900 hover:underline">
                            Buka Buku Besar (Ledger) &rarr;
                        </a>
                    </div>
                </div>
            </section>
        @else
            <!-- Formulir Input Saldo Awal -->
            <div 
                x-data="openingBalanceForm()" 
                class="space-y-6"
            >
                <form method="POST" action="{{ route('backoffice.opening-balances.store') }}" class="space-y-6">
                    @csrf

                    <!-- Card Informasi Transaksi Header -->
                    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 space-y-4">
                        <div class="border-b border-slate-100 pb-3">
                            <h3 class="text-base font-bold text-slate-900">Tanggal &amp; Metadata Pembukuan</h3>
                            <p class="text-xs text-slate-500">Tanggal efektif dimulainya pencatatan keuangan sistem untuk cabang ini.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <!-- Tanggal Efektif -->
                            <div>
                                <label for="transaction_date" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                    Tanggal Saldo Awal <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    type="date"
                                    id="transaction_date"
                                    name="transaction_date"
                                    value="{{ old('transaction_date', $todayDate) }}"
                                    required
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-900 shadow-2xs outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                >
                            </div>

                            <!-- Nomor Referensi -->
                            <div>
                                <label for="reference_number" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                    Nomor Referensi Jurnal <span class="text-slate-400 font-normal lowercase">(opsional)</span>
                                </label>
                                <input
                                    type="text"
                                    id="reference_number"
                                    name="reference_number"
                                    value="{{ old('reference_number') }}"
                                    placeholder="Contoh: OB-INITIAL-01 (kosongkan untuk otomatis)"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-2xs outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                >
                            </div>

                            <!-- Catatan Tambahan -->
                            <div class="sm:col-span-2">
                                <label for="notes" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                    Catatan / Keterangan Pembukuan
                                </label>
                                <input
                                    type="text"
                                    id="notes"
                                    name="notes"
                                    value="{{ old('notes') }}"
                                    placeholder="Contoh: Setup saldo awal hari pertama operasional toko..."
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-2xs outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                >
                            </div>
                        </div>
                    </section>

                    <!-- Kelompok 1: ASET (HARTA) -->
                    <section class="rounded-2xl border border-emerald-200 bg-white p-6 shadow-sm sm:p-8 space-y-6">
                        <div class="flex items-center justify-between border-b border-emerald-100 pb-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-700 font-black text-sm">
                                    1
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-slate-900">Aset (Harta Perusahaan) - Posisi Debit</h3>
                                    <p class="text-xs text-slate-500">Masukkan nilai uang tunai di kasir, saldo bank operasional, dan nilai modal persediaan barang.</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-xs text-slate-400 block">Subtotal Aset:</span>
                                <span class="font-mono text-lg font-black text-emerald-700" x-text="formatRupiah(totalAssets)"></span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                            <!-- 1. Saldo Kas Laci -->
                            <div>
                                <label for="display_cash" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                    Saldo Kas Laci / Kasir
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm font-bold text-slate-400 font-mono">Rp</span>
                                    <input
                                        type="text"
                                        id="display_cash"
                                        x-model="formattedCash"
                                        @input="formatInput('cashInDrawer', $event)"
                                        placeholder="0"
                                        class="w-full rounded-xl border border-slate-300 bg-white pl-11 pr-3.5 py-2.5 text-base font-mono font-bold text-slate-900 shadow-2xs outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                                    >
                                </div>
                                <input type="hidden" name="cash_in_drawer" :value="cashInDrawer">
                                <span class="mt-1 block text-[11px] text-slate-400">Akun Kas Toko Utama [1110]</span>
                            </div>

                            <!-- 2. Saldo Bank BCA -->
                            <div>
                                <label for="display_bank" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                    Saldo Rekening Bank BCA
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm font-bold text-slate-400 font-mono">Rp</span>
                                    <input
                                        type="text"
                                        id="display_bank"
                                        x-model="formattedBank"
                                        @input="formatInput('bankBca', $event)"
                                        placeholder="0"
                                        class="w-full rounded-xl border border-slate-300 bg-white pl-11 pr-3.5 py-2.5 text-base font-mono font-bold text-slate-900 shadow-2xs outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                                    >
                                </div>
                                <input type="hidden" name="bank_bca" :value="bankBca">
                                <span class="mt-1 block text-[11px] text-slate-400">Akun Bank BCA Operasional [1120]</span>
                            </div>

                            <!-- 3. Nilai Persediaan Barang -->
                            <div>
                                <label for="display_inv" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                    Nilai Persediaan Barang
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm font-bold text-slate-400 font-mono">Rp</span>
                                    <input
                                        type="text"
                                        id="display_inv"
                                        x-model="formattedInventory"
                                        @input="formatInput('inventory', $event)"
                                        placeholder="0"
                                        class="w-full rounded-xl border border-slate-300 bg-white pl-11 pr-3.5 py-2.5 text-base font-mono font-bold text-slate-900 shadow-2xs outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                                    >
                                </div>
                                <input type="hidden" name="inventory" :value="inventory">
                                <span class="mt-1 block text-[11px] text-slate-400">Akun Persediaan Barang [1210]</span>
                            </div>
                        </div>
                    </section>

                    <!-- Kelompok 2: KEWAJIBAN (HUTANG) -->
                    <section class="rounded-2xl border border-rose-200 bg-white p-6 shadow-sm sm:p-8 space-y-6">
                        <div class="flex items-center justify-between border-b border-rose-100 pb-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-500/10 text-rose-700 font-black text-sm">
                                    2
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-slate-900">Kewajiban (Hutang Perusahaan) - Posisi Kredit</h3>
                                    <p class="text-xs text-slate-500">Masukkan total sisa hutang usaha kepada supplier atau vendor yang belum terlunasi.</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-xs text-slate-400 block">Subtotal Kewajiban:</span>
                                <span class="font-mono text-lg font-black text-rose-700" x-text="formatRupiah(totalLiabilities)"></span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <!-- Sisa Hutang Supplier -->
                            <div>
                                <label for="display_payable" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                    Total Sisa Hutang ke Supplier
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm font-bold text-slate-400 font-mono">Rp</span>
                                    <input
                                        type="text"
                                        id="display_payable"
                                        x-model="formattedPayable"
                                        @input="formatInput('payable', $event)"
                                        placeholder="0"
                                        class="w-full rounded-xl border border-slate-300 bg-white pl-11 pr-3.5 py-2.5 text-base font-mono font-bold text-slate-900 shadow-2xs outline-none focus:border-rose-500 focus:ring-1 focus:ring-rose-500"
                                    >
                                </div>
                                <input type="hidden" name="payable" :value="payable">
                                <span class="mt-1 block text-[11px] text-slate-400">Akun Hutang Dagang [2110]</span>
                            </div>

                            <!-- Deskripsi Mekanisme Ekuitas -->
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-600">
                                <p class="font-bold text-slate-800">Prinsip Keseimbangan Akuntansi:</p>
                                <p class="mt-1 font-mono text-[11px]">Aset = Kewajiban + Ekuitas</p>
                                <p class="mt-1 text-[11px] text-slate-500">Sistem otomatis menghitung selisih bersih antara Aset dan Hutang, lalu membukukannya ke Akun Modal Awal [3110].</p>
                            </div>
                        </div>
                    </section>

                    <!-- Kelompok 3: KALKULASI REAKTIF EKUITAS & PREVIEW JURNAL -->
                    <section class="rounded-2xl border border-indigo-200 bg-indigo-50/40 p-6 shadow-xs sm:p-8 space-y-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-xs">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-base font-bold text-slate-900">Kalkulasi Otomatis Modal Bersih (Ekuitas)</h3>
                                        <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-bold text-emerald-800">Otomatis Seimbang</span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-0.5">Rumus: Total Aset - Total Hutang = Modal Bersih Perusahaan.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Ringkasan Kartu Metrik Persamaan Akuntansi -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div class="rounded-xl border border-emerald-200 bg-white p-4">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Total Aset (Debit)</span>
                                <p class="mt-1 font-mono text-xl font-black text-slate-900" x-text="formatRupiah(totalAssets)"></p>
                            </div>
                            <div class="rounded-xl border border-rose-200 bg-white p-4">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-rose-700">Total Hutang (Kredit)</span>
                                <p class="mt-1 font-mono text-xl font-black text-slate-900" x-text="formatRupiah(totalLiabilities)"></p>
                            </div>
                            <div class="rounded-xl border border-indigo-300 bg-indigo-50 p-4">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-indigo-800">Modal Bersih / Ekuitas (Kredit)</span>
                                <p class="mt-1 font-mono text-xl font-black text-indigo-900" x-text="formatRupiah(netEquity)"></p>
                            </div>
                        </div>

                        <!-- Tabel Pratinjau Jurnal yang Akan Dibukukan -->
                        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xs">
                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                <thead class="bg-slate-50 font-bold uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th class="px-4 py-2.5 text-left">Akun Buku Besar</th>
                                        <th class="px-4 py-2.5 text-left">Posisi Akuntansi</th>
                                        <th class="px-4 py-2.5 text-right">Debit (Rp)</th>
                                        <th class="px-4 py-2.5 text-right">Kredit (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 font-mono">
                                    <!-- Kas -->
                                    <tr x-show="cashInDrawer > 0">
                                        <td class="px-4 py-2.5 font-sans font-medium text-slate-900">[1110] Kas Toko Utama</td>
                                        <td class="px-4 py-2.5 font-sans text-emerald-700 font-bold">DEBIT (Aset)</td>
                                        <td class="px-4 py-2.5 text-right font-bold text-emerald-700" x-text="formatRupiah(cashInDrawer)"></td>
                                        <td class="px-4 py-2.5 text-right text-slate-400">-</td>
                                    </tr>
                                    <!-- Bank BCA -->
                                    <tr x-show="bankBca > 0">
                                        <td class="px-4 py-2.5 font-sans font-medium text-slate-900">[1120] Bank BCA Operasional</td>
                                        <td class="px-4 py-2.5 font-sans text-emerald-700 font-bold">DEBIT (Aset)</td>
                                        <td class="px-4 py-2.5 text-right font-bold text-emerald-700" x-text="formatRupiah(bankBca)"></td>
                                        <td class="px-4 py-2.5 text-right text-slate-400">-</td>
                                    </tr>
                                    <!-- Persediaan -->
                                    <tr x-show="inventory > 0">
                                        <td class="px-4 py-2.5 font-sans font-medium text-slate-900">[1210] Persediaan Barang</td>
                                        <td class="px-4 py-2.5 font-sans text-emerald-700 font-bold">DEBIT (Aset)</td>
                                        <td class="px-4 py-2.5 text-right font-bold text-emerald-700" x-text="formatRupiah(inventory)"></td>
                                        <td class="px-4 py-2.5 text-right text-slate-400">-</td>
                                    </tr>
                                    <!-- Hutang -->
                                    <tr x-show="payable > 0">
                                        <td class="px-4 py-2.5 font-sans font-medium text-slate-900">[2110] Hutang Dagang</td>
                                        <td class="px-4 py-2.5 font-sans text-rose-700 font-bold">KREDIT (Kewajiban)</td>
                                        <td class="px-4 py-2.5 text-right text-slate-400">-</td>
                                        <td class="px-4 py-2.5 text-right font-bold text-rose-700" x-text="formatRupiah(payable)"></td>
                                    </tr>
                                    <!-- Modal Awal (Penyeimbang) -->
                                    <tr x-show="netEquity > 0">
                                        <td class="px-4 py-2.5 font-sans font-bold text-indigo-900">[3110] Modal Awal</td>
                                        <td class="px-4 py-2.5 font-sans text-indigo-700 font-bold">KREDIT (Penyeimbang Ekuitas)</td>
                                        <td class="px-4 py-2.5 text-right text-slate-400">-</td>
                                        <td class="px-4 py-2.5 text-right font-bold text-indigo-700" x-text="formatRupiah(netEquity)"></td>
                                    </tr>
                                    <tr x-show="!canSubmit">
                                        <td colspan="4" class="px-4 py-6 text-center text-slate-400 font-sans">
                                            Masukkan nominal di atas untuk melihat simulasi jurnal saldo awal.
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot x-show="canSubmit" class="bg-slate-50 font-bold text-slate-900 border-t border-slate-200">
                                    <tr>
                                        <td colspan="2" class="px-4 py-2 text-right font-sans uppercase text-[10px] text-slate-500">Total Keseimbangan:</td>
                                        <td class="px-4 py-2 text-right text-emerald-700" x-text="formatRupiah(totalAssets)"></td>
                                        <td class="px-4 py-2 text-right text-rose-700" x-text="formatRupiah(totalAssets)"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </section>

                    <!-- Tombol Aksi Submit -->
                    <div class="flex flex-col-reverse justify-end gap-3 sm:flex-row sm:items-center pt-2">
                        <a
                            href="/backoffice"
                            class="rounded-xl border border-slate-300 bg-white px-6 py-3 text-center text-sm font-bold text-slate-700 shadow-2xs hover:bg-slate-50 transition"
                        >
                            Batal
                        </a>
                        <button
                            type="submit"
                            id="btn-submit-opening-balance"
                            :disabled="!canSubmit"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-amber-500 px-7 py-3 text-sm font-bold text-slate-950 shadow-md transition hover:bg-amber-400 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Posting Saldo Awal ke Buku Besar
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </main>

    <!-- Alpine.js Component Script -->
    <script>
        function openingBalanceForm() {
            return {
                cashInDrawer: {{ (float) old('cash_in_drawer', 0) }},
                formattedCash: '{{ old("cash_in_drawer") ? number_format((float) old("cash_in_drawer"), 0, ",", ".") : "" }}',

                bankBca: {{ (float) old('bank_bca', 0) }},
                formattedBank: '{{ old("bank_bca") ? number_format((float) old("bank_bca"), 0, ",", ".") : "" }}',

                inventory: {{ (float) old('inventory', 0) }},
                formattedInventory: '{{ old("inventory") ? number_format((float) old("inventory"), 0, ",", ".") : "" }}',

                payable: {{ (float) old('payable', 0) }},
                formattedPayable: '{{ old("payable") ? number_format((float) old("payable"), 0, ",", ".") : "" }}',

                get totalAssets() {
                    return (this.cashInDrawer || 0) + (this.bankBca || 0) + (this.inventory || 0);
                },

                get totalLiabilities() {
                    return (this.payable || 0);
                },

                get netEquity() {
                    return this.totalAssets - this.totalLiabilities;
                },

                get canSubmit() {
                    return (this.totalAssets > 0 || this.totalLiabilities > 0);
                },

                formatInput(field, event) {
                    let value = event.target.value.replace(/[^\d]/g, '');
                    let num = value ? parseInt(value, 10) : 0;
                    this[field] = num;
                    let formatted = num > 0 ? new Intl.NumberFormat('id-ID').format(num) : '';
                    if (field === 'cashInDrawer') this.formattedCash = formatted;
                    if (field === 'bankBca') this.formattedBank = formatted;
                    if (field === 'inventory') this.formattedInventory = formatted;
                    if (field === 'payable') this.formattedPayable = formatted;
                },

                formatRupiah(val) {
                    return 'Rp ' + new Intl.NumberFormat('id-ID').format(val || 0);
                }
            };
        }
    </script>
</body>
</html>
