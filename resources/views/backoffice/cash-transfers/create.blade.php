<x-app-layout title="Formulir Mutasi Kas & Bank" :breadcrumbs="[
    ['label' => 'Uang & Akuntansi'],
    ['label' => 'Kas & Bank', 'url' => route('backoffice.cash-transfers.index')],
    ['label' => 'Pindah Kas'],
]">
    <main class="mx-auto w-full px-6 py-6 lg:px-10 max-w-5xl space-y-6">
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
                <p class="font-semibold uppercase font-bold text-sm tracking-wider text-amber-600">Treasury / Mutasi Kas</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Transfer Antar Kas &amp; Bank</h2>
                <p class="mt-1 text-xs text-slate-500">Pindahkan dana antar rekening toko (misal: setoran kas kasir ke bank, pengisian kas kecil) dengan pencatatan jurnal ganda otomatis.</p>
            </div>
            <a href="{{ route('backoffice.cash-transfers.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50">
                &larr; Riwayat Mutasi
            </a>
        </div>

        <div 
            x-data="cashTransferForm(@js($accounts), {{ (float) old('amount', 0) }})" 
            class="space-y-6"
        >
            <form method="POST" action="{{ route('backoffice.cash-transfers.store') }}" class="space-y-6">
                @csrf

                <!-- Section 1: Formulir Mutasi Kas -->
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 space-y-6">
                    <div class="border-b border-slate-100 pb-4">
                        <h3 class="text-base font-bold text-slate-900">Rincian Mutasi Dana</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Pilih rekening asal (sumber dana) dan rekening tujuan transfer.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <!-- Tanggal Transfer -->
                        <div>
                            <label for="transfer_date" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                                Tanggal Transfer <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="date"
                                id="transfer_date"
                                name="transfer_date"
                                x-model="transferDate"
                                required
                                class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-900 shadow-2xs outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                            >
                        </div>

                        <!-- Nomor Referensi (Opsional/Otomatis) -->
                        <div>
                            <label for="reference_number" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                                Nomor Referensi <span class="text-slate-400 font-normal lowercase">(opsional)</span>
                            </label>
                            <input
                                type="text"
                                id="reference_number"
                                name="reference_number"
                                x-model="referenceNumber"
                                placeholder="Contoh: TRF-BCA-001 (kosongkan untuk otomatis)"
                                class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-2xs outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                            >
                            <span class="mt-1 block text-[11px] text-slate-400">Jika dikosongkan, sistem akan membuat nomor acak: TRF-YYYYMMDD-XXXX</span>
                        </div>

                        <!-- Akun Asal (Sumber Dana) -->
                        <div>
                            <label for="from_account_id" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                                Sumber Dana / Akun Asal (Kredit) <span class="text-rose-500">*</span>
                            </label>
                            <select
                                id="from_account_id"
                                name="from_account_id"
                                x-model="fromAccountId"
                                required
                                class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-900 shadow-2xs outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                            >
                                <option value="">-- Pilih Akun Sumber Dana --</option>
                                @foreach ($accounts as $acc)
                                    <option value="{{ $acc->id }}" {{ old('from_account_id') == $acc->id ? 'selected' : '' }}>
                                        [{{ $acc->code }}] {{ $acc->name }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="mt-1 block text-[11px] text-slate-500">Saldo kas ini akan berkurang (posisi Kredit di jurnal).</span>
                        </div>

                        <!-- Akun Tujuan (Tujuan Dana) -->
                        <div>
                            <label for="to_account_id" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                                Tujuan Dana / Akun Tujuan (Debit) <span class="text-rose-500">*</span>
                            </label>
                            <select
                                id="to_account_id"
                                name="to_account_id"
                                x-model="toAccountId"
                                required
                                :class="isSameAccount ? 'border-rose-400 focus:border-sky-500 focus:ring-sky-500 bg-rose-50/20' : 'border-slate-300 focus:border-sky-500 focus:ring-sky-500 bg-white'"
                                class="w-full rounded-xl border px-3.5 py-2.5 text-sm font-semibold text-slate-900 shadow-2xs outline-none focus:ring-1 transition"
                            >
                                <option value="">-- Pilih Akun Tujuan Transfer --</option>
                                @foreach ($accounts as $acc)
                                    <option value="{{ $acc->id }}" {{ old('to_account_id') == $acc->id ? 'selected' : '' }}>
                                        [{{ $acc->code }}] {{ $acc->name }}
                                    </option>
                                @endforeach
                            </select>
                            
                            <!-- Validasi UI Instan Peringatan Akun Sama -->
                            <div x-show="isSameAccount" x-cloak class="mt-1.5 flex items-center gap-1.5 text-xs font-semibold text-rose-600">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <span>Akun Asal dan Akun Tujuan tidak boleh sama! Silakan pilih akun berbeda.</span>
                            </div>
                            <span x-show="!isSameAccount" class="mt-1 block text-[11px] text-slate-500">Saldo kas ini akan bertambah (posisi Debit di jurnal).</span>
                        </div>

                        <!-- Nominal Mutasi (Live Rupiah Formatter) -->
                        <div class="sm:col-span-2">
                            <label for="display_amount" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                                Nominal Mutasi / Transfer (Rp) <span class="text-rose-500">*</span>
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
                                    class="w-full rounded-xl border border-amber-300 bg-amber-50/20 pl-12 pr-4 py-3.5 text-xl font-mono font-black text-slate-950 outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition"
                                >
                            </div>
                            <!-- Raw Numeric Value sent to Backend -->
                            <input type="hidden" name="amount" :value="rawAmount">

                            <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                                <span>Ketik angka nominal, sistem otomatis menambahkan pemisah ribuan.</span>
                                <span x-show="rawAmount > 0" class="font-medium text-amber-800">
                                    Nominal terinput: <strong class="font-mono text-slate-900 text-sm" x-text="formatRupiah(rawAmount)"></strong>
                                </span>
                            </div>

                            <!-- Shortcut Tombol Nominal Cepat -->
                            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-5">
                                <button type="button" @click="setAmount(50000)" class="rounded-lg border border-slate-200 bg-slate-50 py-1.5 text-center text-xs font-semibold text-slate-700 hover:bg-amber-50 hover:text-amber-800 hover:border-amber-300 transition">
                                    50.000
                                </button>
                                <button type="button" @click="setAmount(100000)" class="rounded-lg border border-slate-200 bg-slate-50 py-1.5 text-center text-xs font-semibold text-slate-700 hover:bg-amber-50 hover:text-amber-800 hover:border-amber-300 transition">
                                    100.000
                                </button>
                                <button type="button" @click="setAmount(500000)" class="rounded-lg border border-slate-200 bg-slate-50 py-1.5 text-center text-xs font-semibold text-slate-700 hover:bg-amber-50 hover:text-amber-800 hover:border-amber-300 transition">
                                    500.000
                                </button>
                                <button type="button" @click="setAmount(1000000)" class="rounded-lg border border-slate-200 bg-slate-50 py-1.5 text-center text-xs font-semibold text-slate-700 hover:bg-amber-50 hover:text-amber-800 hover:border-amber-300 transition">
                                    1.000.000
                                </button>
                                <button type="button" @click="setAmount(5000000)" class="rounded-lg border border-slate-200 bg-slate-50 py-1.5 text-center text-xs font-semibold text-slate-700 hover:bg-amber-50 hover:text-amber-800 hover:border-amber-300 transition">
                                    5.000.000
                                </button>
                            </div>
                        </div>

                        <!-- Catatan / Keterangan Mutasi -->
                        <div class="sm:col-span-2">
                            <label for="notes" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                                Catatan / Keterangan Transfer <span class="text-slate-400 font-normal lowercase">(opsional)</span>
                            </label>
                            <textarea
                                id="notes"
                                name="notes"
                                rows="2"
                                x-model="notes"
                                placeholder="Contoh: Setoran hasil penjualan kasir shift pagi, atau pengisian dana kas kecil operasional..."
                                class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-2xs outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                            >{{ old('notes') }}</textarea>
                            <p class="mt-1 text-[11px] text-slate-400">Catatan ini akan otomatis dicantumkan pada memo jurnal akuntansi buku besar.</p>
                        </div>
                    </div>
                </section>

                <!-- Section 2: Live Journal Preview (Otomatisasi Jurnal Akuntansi) -->
                <section class="rounded-2xl border border-indigo-200 bg-indigo-50/40 p-6 shadow-xs">
                    <div class="flex items-start gap-3 mb-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-xs">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-bold text-slate-900">Pratinjau Jurnal Akuntansi Ganda (Live Preview)</h3>
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">Seimbang 100%</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">Sistem akan membukukan mutasi dana kas ini secara otomatis ke dalam buku besar tanpa perlu input manual.</p>
                        </div>
                    </div>

                    <!-- Keterangan Jurnal -->
                    <div class="mb-4 rounded-xl border border-indigo-100 bg-white p-3 text-xs">
                        <span class="font-semibold text-slate-500 uppercase tracking-wider text-[10px]">Keterangan Transaksi:</span>
                        <p class="mt-0.5 font-semibold text-slate-800 font-mono" x-text="computedJournalDescription"></p>
                    </div>

                    <!-- Tabel Jurnal Debit & Kredit -->
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xs">
                        <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                            <thead class="bg-slate-900 text-xs font-semibold uppercase tracking-wider text-white">
                                <tr>
                                    <th class="px-4 py-2.5">Akun Buku Besar</th>
                                    <th class="px-4 py-2.5">Posisi / Keterangan</th>
                                    <th class="px-4 py-2.5 text-right">Debit (Rp)</th>
                                    <th class="px-4 py-2.5 text-right">Kredit (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-mono [&>tr:nth-child(even)]:bg-slate-50/60">
                                <!-- Baris Debit: Akun Tujuan -->
                                <tr class="hover:bg-slate-50/80">
                                    <td class="px-4 py-3 font-sans">
                                        <div class="font-bold text-slate-900" x-text="toAccountName || '-- Pilih Akun Tujuan --'"></div>
                                        <div class="text-[11px] text-slate-400" x-text="toAccountCode ? 'Kode: ' + toAccountCode : ''"></div>
                                    </td>
                                    <td class="px-4 py-3 font-sans">
                                        <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700">
                                            DEBIT (Kas Masuk)
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold text-emerald-700 text-sm" x-text="formatRupiah(rawAmount)"></td>
                                    <td class="px-4 py-3 text-right text-slate-400">0</td>
                                </tr>

                                <!-- Baris Kredit: Akun Asal -->
                                <tr class="hover:bg-slate-50/80">
                                    <td class="px-4 py-3 font-sans">
                                        <div class="font-bold text-slate-900" x-text="fromAccountName || '-- Pilih Akun Asal --'"></div>
                                        <div class="text-[11px] text-slate-400" x-text="fromAccountCode ? 'Kode: ' + fromAccountCode : ''"></div>
                                    </td>
                                    <td class="px-4 py-3 font-sans">
                                        <span class="inline-flex items-center rounded-md bg-rose-50 px-2 py-0.5 text-[11px] font-bold text-rose-700">
                                            KREDIT (Kas Keluar)
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-slate-400">0</td>
                                    <td class="px-4 py-3 text-right font-bold text-rose-700 text-sm" x-text="formatRupiah(rawAmount)"></td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-slate-50/90 font-mono font-bold text-slate-900 border-t border-slate-200">
                                <tr>
                                    <td colspan="2" class="px-4 py-2.5 text-right font-sans uppercase tracking-wider text-[11px] text-slate-500">Total Keseimbangan:</td>
                                    <td class="px-4 py-2.5 text-right text-emerald-700 text-sm" x-text="formatRupiah(rawAmount)"></td>
                                    <td class="px-4 py-2.5 text-right text-rose-700 text-sm" x-text="formatRupiah(rawAmount)"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                <!-- Action Buttons -->
                <div class="flex flex-col-reverse justify-end gap-3 sm:flex-row sm:items-center pt-2">
                    <a
                        href="{{ route('backoffice.cash-transfers.index') }}"
                        class="rounded-xl border border-slate-300 bg-white px-6 py-3 text-center text-sm font-bold text-slate-700 shadow-2xs hover:bg-slate-50 transition"
                    >
                        Batal
                    </a>
                    <button
                        type="submit"
                        id="btn-submit-transfer"
                        :disabled="!canSubmit"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-sky-600 px-7 py-3 text-sm font-bold text-white shadow-md transition hover:bg-sky-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                        Proses &amp; Bukukan Mutasi Kas
                    </button>
                </div>
            </form>
        </div>
    </main>

    <x-slot:scripts>
    <script>
        function cashTransferForm(accounts = [], initialAmount = 0) {
            return {
                accounts: accounts,
                rawAmount: initialAmount || 0,
                formattedAmount: initialAmount > 0 ? new Intl.NumberFormat('id-ID').format(initialAmount) : '',
                fromAccountId: '{{ old("from_account_id", "") }}',
                toAccountId: '{{ old("to_account_id", "") }}',
                referenceNumber: '{{ old("reference_number", "") }}',
                transferDate: '{{ old("transfer_date", $todayDate) }}',
                notes: '{{ old("notes", "") }}',

                get fromAccount() {
                    return this.accounts.find(a => a.id == this.fromAccountId) || null;
                },

                get toAccount() {
                    return this.accounts.find(a => a.id == this.toAccountId) || null;
                },

                get fromAccountName() {
                    return this.fromAccount ? this.fromAccount.name : '';
                },

                get fromAccountCode() {
                    return this.fromAccount ? this.fromAccount.code : '';
                },

                get toAccountName() {
                    return this.toAccount ? this.toAccount.name : '';
                },

                get toAccountCode() {
                    return this.toAccount ? this.toAccount.code : '';
                },

                get isSameAccount() {
                    return Boolean(this.fromAccountId && this.toAccountId && this.fromAccountId === this.toAccountId);
                },

                get canSubmit() {
                    return Boolean(
                        this.fromAccountId &&
                        this.toAccountId &&
                        !this.isSameAccount &&
                        this.rawAmount > 0
                    );
                },

                get computedJournalDescription() {
                    const fromStr = this.fromAccountName || '[Akun Asal]';
                    const toStr = this.toAccountName || '[Akun Tujuan]';
                    let desc = `Mutasi Kas: dari ${fromStr} ke ${toStr}`;
                    if (this.notes && this.notes.trim()) {
                        desc += ` - ${this.notes.trim()}`;
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
    </x-slot:scripts>
</x-app-layout>
