<x-app-layout title="Penerimaan Pembayaran Piutang (AR)" :breadcrumbs="[
    ['label' => 'Jualan'],
    ['label' => 'Terima Bayaran Piutang'],
]">
    <x-slot:head>
    <style>[x-cloak] { display: none !important; }</style>
    </x-slot:head>

    <main class="mx-auto w-full px-6 py-6 lg:px-10 max-w-5xl space-y-6">
        <!-- Error Banner -->
        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900 shadow-xs">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
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
                <p class="font-semibold uppercase font-bold text-sm tracking-wider text-amber-600">Account Receivable / Piutang</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Penerimaan Pembayaran Piutang</h2>
                <p class="mt-1 text-xs text-slate-500">Mencatat pelunasan atau cicilan piutang pelanggan dengan alokasi ke satu atau banyak faktur penjualan sekaligus.</p>
            </div>
            <a href="{{ route('backoffice.payments.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50">
                &larr; Riwayat Pembayaran
            </a>
        </div>

        <div 
            x-data="arPaymentForm(@js($cashBankAccounts), @js($unpaidSales))"
            class="space-y-6"
        >
            <form method="POST" action="{{ route('backoffice.payments.receivables.store') }}" class="space-y-6">
                @csrf

                <!-- Section 1: Informasi Header Pembayaran -->
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 space-y-6">
                    <div class="border-b border-slate-100 pb-4">
                        <h3 class="text-base font-bold text-slate-900">Header Pembayaran Piutang</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Rekening penerimaan kas/bank dan total dana yang diterima.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                        <!-- Akun Kas/Bank Penerima -->
                        <div>
                            <label for="account_id" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                                Akun Kas / Bank Penerima <span class="text-rose-500">*</span>
                            </label>
                            <select
                                id="account_id"
                                name="account_id"
                                x-model="accountId"
                                required
                                class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-900 shadow-2xs outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                            >
                                <option value="">-- Pilih Rekening Kas/Bank --</option>
                                <template x-for="acc in cashBankAccounts" :key="'acc-' + acc.id">
                                    <option :value="acc.id" x-text="`[${acc.code}] ${acc.name}`"></option>
                                </template>
                            </select>
                        </div>

                        <!-- Tanggal Pembayaran -->
                        <div>
                            <label for="payment_date" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                                Tanggal Penerimaan <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="date"
                                id="payment_date"
                                name="payment_date"
                                x-model="paymentDate"
                                required
                                class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-900 shadow-2xs outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                            >
                        </div>

                        <!-- Nomor Referensi -->
                        <div>
                            <label for="reference_number" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                                No. Referensi / Bukti <span class="text-slate-400 font-normal lowercase">(opsional)</span>
                            </label>
                            <input
                                type="text"
                                id="reference_number"
                                name="reference_number"
                                x-model="referenceNumber"
                                placeholder="Contoh: AR-BCA-001 (auto jika kosong)"
                                class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-2xs outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                            >
                        </div>
                    </div>

                    <!-- Nominal Total Diterima -->
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label for="amount" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                                Total Nominal Diterima (Rp) <span class="text-rose-500">*</span>
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
                                    @input="autoDistributeAllocations()"
                                    required
                                    placeholder="0"
                                    class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-12 pr-4 text-lg font-bold text-slate-900 shadow-2xs outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                >
                            </div>
                            <span class="mt-1 block text-xs font-semibold text-emerald-700" x-text="formatRupiah(amount)"></span>
                        </div>

                        <div>
                            <label for="notes" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                                Catatan / Memo <span class="text-slate-400 font-normal lowercase">(opsional)</span>
                            </label>
                            <textarea
                                id="notes"
                                name="notes"
                                x-model="notes"
                                rows="2"
                                placeholder="Keterangan transfer pelanggan, nomor cek/giro, dll..."
                                class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-900 shadow-2xs outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                            ></textarea>
                        </div>
                    </div>
                </section>

                <!-- Section 2: Multi-Invoice Allocation Table -->
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Alokasi Faktur Penjualan (Multi-Invoice)</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Centang dan masukkan nominal cicilan/pelunasan pada faktur terkait.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="autoDistributeAllocations()"
                                class="rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 border border-emerald-200 hover:bg-emerald-100"
                            >
                                Otomatis Alokasikan
                            </button>
                        </div>
                    </div>

                    <!-- Cari Nama Debitur (filter faktur) -->
                    <div>
                        <label for="customer_search" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                            Cari Nama Debitur <span class="text-slate-400 font-normal lowercase">(opsional, untuk menyaring faktur)</span>
                        </label>
                        <div class="relative" @click.away="closeCustomerMenu()">
                            <div class="relative">
                                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/></svg>
                                <input
                                    type="text"
                                    id="customer_search"
                                    x-ref="customerSearch"
                                    x-model="customerQuery"
                                    @input="onCustomerInput()"
                                    @focus="openCustomerMenu($event)"
                                    @click="customerOpen = true"
                                    @keydown.arrow-down.prevent="moveCustomer(1)"
                                    @keydown.arrow-up.prevent="moveCustomer(-1)"
                                    @keydown.enter.prevent="confirmCustomer()"
                                    @keydown.escape="closeCustomerMenu()"
                                    @keydown.tab="closeCustomerMenu()"
                                    autocomplete="off"
                                    role="combobox"
                                    aria-autocomplete="list"
                                    aria-controls="customer_options"
                                    :aria-expanded="customerOpen"
                                    placeholder="Semua Debitur — ketik nama debitur, no. HP, atau no. faktur..."
                                    class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-10 pr-20 text-sm font-semibold text-slate-900 shadow-2xs outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                >
                                <div class="absolute inset-y-0 right-0 flex items-center gap-1 pr-2">
                                    <button
                                        type="button"
                                        x-show="customerKey || customerQuery"
                                        x-cloak
                                        @click="selectCustomer(null)"
                                        class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                        aria-label="Hapus pilihan debitur"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                    <button
                                        type="button"
                                        @click="customerOpen ? closeCustomerMenu() : (customerOpen = true, $refs.customerSearch?.focus())"
                                        class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                        aria-label="Tampilkan daftar debitur"
                                    >
                                        <svg class="h-4 w-4 transition-transform" :class="customerOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                </div>
                            </div>

                            <ul
                                id="customer_options"
                                x-show="customerOpen"
                                x-cloak
                                x-ref="customerList"
                                role="listbox"
                                class="absolute z-30 mt-1 max-h-72 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-xl"
                            >
                                <template x-if="!customerQuery.trim() || customerQuery === selectedCustomerName()">
                                    <li>
                                        <button
                                            type="button"
                                            @click="selectCustomer(null)"
                                            class="flex w-full items-center justify-between gap-3 px-4 py-2 text-left text-sm hover:bg-sky-50"
                                            :class="!customerKey ? 'font-bold text-sky-700' : 'text-slate-700'"
                                        >
                                            <span>Semua Debitur</span>
                                            <span class="shrink-0 text-xs text-slate-500" x-text="salesList.length + ' faktur'"></span>
                                        </button>
                                    </li>
                                </template>
                                <template x-for="(customer, resultIndex) in customerResults()" :key="customer.key">
                                    <li>
                                        <button
                                            type="button"
                                            role="option"
                                            :data-customer-index="resultIndex"
                                            :aria-selected="customer.key === customerKey"
                                            @mouseenter="customerActive = resultIndex"
                                            @click="selectCustomer(customer)"
                                            :class="resultIndex === customerActive ? 'bg-sky-50' : ''"
                                            class="flex w-full items-center justify-between gap-3 px-4 py-2 text-left text-sm hover:bg-sky-50"
                                        >
                                            <span class="min-w-0">
                                                <span class="block truncate font-semibold text-slate-900" :class="customer.key === customerKey ? 'text-sky-700' : ''" x-html="highlightMatch(customer.name)"></span>
                                                <span class="block truncate text-xs text-slate-500">
                                                    <span x-show="customer.phone" x-html="highlightMatch(customer.phone || '')"></span>
                                                    <span x-show="customer.phone && matchedReceipt(customer)"> · </span>
                                                    <span x-show="matchedReceipt(customer)" class="font-mono" x-html="highlightMatch(matchedReceipt(customer) || '')"></span>
                                                </span>
                                            </span>
                                            <span class="shrink-0 text-right">
                                                <span class="block text-xs font-bold text-amber-600" x-text="formatRupiah(customer.remaining)"></span>
                                                <span class="block text-[11px] text-slate-500" x-text="customer.invoice_count + ' faktur'"></span>
                                            </span>
                                        </button>
                                    </li>
                                </template>
                                <li x-show="remoteLoading" class="px-4 py-2 text-xs italic text-slate-400">
                                    Mencari di seluruh data piutang...
                                </li>
                                <li x-show="!remoteLoading && customerResults().length === 0" class="px-4 py-3 text-sm text-slate-500">
                                    Debitur tidak ditemukan. Coba nama, no. HP, atau no. faktur.
                                </li>
                            </ul>
                        </div>
                        <p class="mt-1 text-xs italic text-slate-400" x-show="customerKey && debtorSalesLoading" x-cloak>
                            Memuat seluruh faktur belum lunas debitur ini...
                        </p>
                        <p class="mt-1 text-xs font-semibold text-rose-600" x-show="customerKey && debtorSalesError" x-cloak x-text="debtorSalesError"></p>
                        <p class="mt-1 text-xs text-slate-500" x-show="customerKey && !debtorSalesLoading" x-cloak>
                            Menampilkan <span class="font-semibold text-slate-700" x-text="visibleSales().length"></span> faktur milik debitur ini · sisa piutang
                            <span class="font-semibold text-amber-600" x-text="formatRupiah(visibleSales().reduce((sum, s) => sum + s.remaining, 0))"></span>
                        </p>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                            <thead class="bg-slate-900 text-xs font-semibold uppercase tracking-wider text-white">
                                <tr>
                                    <th class="px-4 py-3 w-12 text-center">Pilih</th>
                                    <th class="px-4 py-3">Nama Debitur</th>
                                    <th class="px-4 py-3">Tanggal</th>
                                    <th class="px-4 py-3">Keterangan Piutang</th>
                                    <th class="px-4 py-3 text-right">Total Faktur</th>
                                    <th class="px-4 py-3 text-right">Sudah Dibayar</th>
                                    <th class="px-4 py-3 text-right">Sisa Piutang</th>
                                    <th class="px-4 py-3 text-right w-48">Nominal Dialokasikan (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white [&>tr:nth-child(even)]:bg-slate-50/60">
                                <template x-for="(sale, index) in salesList" :key="sale.id">
                                    <tr x-show="isSaleVisible(sale)" :class="sale.selected ? 'bg-emerald-50/40' : ''">
                                        <td class="px-4 py-3 text-center">
                                            <input 
                                                type="checkbox" 
                                                x-model="sale.selected"
                                                @change="toggleSelectSale(sale)"
                                                class="rounded border-slate-300 text-emerald-600 focus:ring-sky-500"
                                            >
                                        </td>
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-slate-900" x-text="sale.customer_name"></p>
                                            <p class="mt-0.5 font-mono text-[10px] text-slate-400" x-text="sale.receipt_number"></p>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap font-medium text-slate-700" x-text="sale.sale_date"></td>
                                        <td class="px-4 py-3 max-w-xs text-slate-600" x-text="sale.description"></td>
                                        <td class="px-4 py-3 text-right font-medium text-slate-700" x-text="formatRupiah(sale.total_amount)"></td>
                                        <td class="px-4 py-3 text-right font-medium text-slate-500" x-text="formatRupiah(sale.paid_amount || 0)"></td>
                                        <td class="px-4 py-3 text-right font-bold text-amber-600" x-text="formatRupiah(sale.remaining)"></td>
                                        <td class="px-4 py-3 text-right">
                                            <template x-if="sale.selected">
                                                <div>
                                                    <input type="hidden" :name="`allocations[${index}][sale_id]`" :value="sale.id">
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0.01"
                                                        :max="sale.remaining"
                                                        :name="`allocations[${index}][allocated_amount]`"
                                                        x-model.number="sale.allocated_amount"
                                                        placeholder="0"
                                                        class="w-full rounded-lg border border-slate-300 p-2 text-right text-xs font-bold text-slate-900 outline-none focus:border-sky-500"
                                                    >
                                                </div>
                                            </template>
                                            <template x-if="!sale.selected">
                                                <span class="text-slate-400 italic">-</span>
                                            </template>
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="salesList.length === 0">
                                    <tr>
                                        <td colspan="8" class="px-4 py-8 text-center text-slate-400 italic">
                                            Tidak ada faktur penjualan yang memiliki piutang (semua lunas).
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="salesList.length > 0 && visibleSales().length === 0 && !debtorSalesLoading">
                                    <tr>
                                        <td colspan="8" class="px-4 py-8 text-center text-slate-400 italic">
                                            Tidak ada faktur piutang untuk debitur yang dipilih.
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot class="bg-slate-50 font-bold border-t border-slate-200">
                                <tr>
                                    <td colspan="7" class="px-4 py-2.5 text-right uppercase tracking-wider text-[11px] text-slate-600">Total Dialokasikan:</td>
                                    <td class="px-4 py-2.5 text-right font-bold text-emerald-700" x-text="formatRupiah(getTotalAllocated())"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                <!-- Section 3: Live Journal Preview -->
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900">Live Journal Preview (Jurnal Otomatis)</h3>
                        </div>
                        <span 
                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-bold"
                            :class="isBalanced ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                        >
                            <span class="h-1.5 w-1.5 rounded-full" :class="isBalanced ? 'bg-emerald-600' : 'bg-rose-600'"></span>
                            <span x-text="isBalanced ? 'Jurnal Seimbang (Balanced)' : 'Belum Lengkap'"></span>
                        </span>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50/50">
                        <table class="min-w-full divide-y divide-slate-200 text-xs">
                            <thead class="bg-slate-900 text-xs font-semibold uppercase tracking-wider text-white">
                                <tr>
                                    <th class="px-4 py-2.5 text-left">Kode &amp; Nama Akun</th>
                                    <th class="px-4 py-2.5 text-right w-36">Debit (Rp)</th>
                                    <th class="px-4 py-2.5 text-right w-36">Kredit (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white [&>tr:nth-child(even)]:bg-slate-50/60">
                                <tr>
                                    <td class="px-4 py-2 font-medium text-slate-800">
                                        <span class="inline-block rounded bg-sky-100 px-1.5 py-0.5 text-[10px] font-bold text-sky-800 mr-1.5">DEBIT</span>
                                        <span x-text="getAccountLabel()"></span>
                                    </td>
                                    <td class="px-4 py-2 text-right font-bold text-slate-900" x-text="formatRupiah(amount)"></td>
                                    <td class="px-4 py-2 text-right text-slate-400">0</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-2 font-medium text-slate-800 pl-8">
                                        <span class="inline-block rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800 mr-1.5">KREDIT</span>
                                        <span>[1130] Piutang Usaha</span>
                                    </td>
                                    <td class="px-4 py-2 text-right text-slate-400">0</td>
                                    <td class="px-4 py-2 text-right font-bold text-slate-900" x-text="formatRupiah(amount)"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Tombol Submit -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <a
                        href="{{ route('backoffice.payments.index') }}"
                        class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50"
                    >
                        Batal
                    </a>
                    <button
                        type="submit"
                        :disabled="!isBalanced"
                        class="inline-flex items-center gap-2 rounded-xl bg-sky-600 px-6 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-sky-700 focus:ring-2 focus:ring-sky-500 disabled:opacity-50"
                    >
                        <span>Simpan &amp; Bukukan Penerimaan AR</span>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>
            </form>
        </div>
    </main>

    <x-slot:scripts>
    <script>
        function arPaymentForm(cashBankAccounts, unpaidSales) {
            const normalizeSearch = (value) => String(value ?? '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .trim();

            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');

            const escapeRegExp = (value) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

            const debtorSearchUrl = @js(route('backoffice.payments.receivables.debtors'));
            const debtorSalesUrl = @js(route('backoffice.payments.receivables.debtor-sales'));

            const customerKeyOf = (customerId) => customerId ? 'customer-' + customerId : 'walk-in';

            const prepareCustomer = (customer) => ({
                ...customer,
                receipts: customer.receipts || [],
                searchName: normalizeSearch(customer.name),
                searchText: normalizeSearch([
                    customer.name,
                    customer.phone,
                    String(customer.phone || '').replace(/\D/g, ''),
                    ...(customer.receipts || []),
                ].join(' ')),
            });

            const rankCustomers = (list, tokens) => {
                const fullQuery = tokens.join(' ');
                return list
                    .map(customer => {
                        let rank = 3;
                        if (customer.searchName.startsWith(fullQuery)) rank = 0;
                        else if (customer.searchName.split(/\s+/).some(word => word.startsWith(tokens[0]))) rank = 1;
                        else if (tokens.every(token => customer.searchName.includes(token))) rank = 2;
                        return { customer, rank };
                    })
                    .sort((a, b) => a.rank - b.rank || a.customer.name.localeCompare(b.customer.name, 'id', { sensitivity: 'base' }))
                    .map(result => result.customer);
            };

            const mapSale = (s) => ({
                id: s.id,
                receipt_number: s.receipt_number,
                customer_key: customerKeyOf(s.customer_id),
                customer_phone: s.customer_phone || '',
                customer_name: s.customer_name || 'Pelanggan Umum',
                sale_date: s.sale_date || '-',
                description: s.description || 'Piutang penjualan',
                total_amount: parseFloat(s.total_amount) || 0,
                paid_amount: parseFloat(s.paid_amount) || 0,
                remaining: Math.max(0, (parseFloat(s.total_amount) || 0) - (parseFloat(s.paid_amount) || 0)),
                selected: false,
                allocated_amount: 0,
            });

            const salesList = (unpaidSales || []).map(mapSale);

            const customerMap = new Map();
            for (const sale of salesList) {
                if (!customerMap.has(sale.customer_key)) {
                    customerMap.set(sale.customer_key, {
                        key: sale.customer_key,
                        name: sale.customer_name,
                        phone: sale.customer_phone,
                        receipts: [],
                        invoice_count: 0,
                        remaining: 0,
                    });
                }
                const customer = customerMap.get(sale.customer_key);
                customer.receipts.push(sale.receipt_number || '');
                customer.invoice_count += 1;
                customer.remaining += sale.remaining;
            }
            const customers = [...customerMap.values()]
                .map(prepareCustomer)
                .sort((a, b) => a.name.localeCompare(b.name, 'id', { sensitivity: 'base' }));

            return {
                cashBankAccounts: cashBankAccounts || [],
                salesList,
                customers,
                customerKey: '',
                customerQuery: '',
                customerOpen: false,
                customerActive: 0,
                remoteDebtors: [],
                remoteQueryKey: '',
                remoteLoading: false,
                remoteSearchTimer: null,
                debtorSalesLoading: false,
                debtorSalesError: '',
                accountId: '',
                paymentDate: '{{ $todayDate ?? now()->toDateString() }}',
                referenceNumber: '',
                amount: 0,
                notes: '',

                toggleSelectSale(sale) {
                    if (!sale.selected) {
                        sale.allocated_amount = 0;
                    } else if (sale.allocated_amount <= 0) {
                        sale.allocated_amount = sale.remaining;
                    }
                },

                selectedCustomerName() {
                    const customer = this.customers.find(c => c.key === this.customerKey);
                    return customer ? customer.name : '';
                },

                searchTokens() {
                    const query = this.customerQuery === this.selectedCustomerName() ? '' : this.customerQuery;
                    return normalizeSearch(query).split(/\s+/).filter(Boolean);
                },

                customerResults() {
                    const tokens = this.searchTokens();
                    if (tokens.length === 0) {
                        return this.customers;
                    }

                    const merged = new Map();
                    for (const customer of this.customers) {
                        if (tokens.every(token => customer.searchText.includes(token))) {
                            merged.set(customer.key, customer);
                        }
                    }
                    if (this.remoteQueryKey === tokens.join(' ')) {
                        for (const debtor of this.remoteDebtors) {
                            const local = merged.get(debtor.key);
                            merged.set(debtor.key, local ? { ...debtor, receipts: local.receipts } : debtor);
                        }
                    }

                    return rankCustomers([...merged.values()], tokens);
                },

                onCustomerInput() {
                    this.customerOpen = true;
                    this.customerActive = 0;

                    clearTimeout(this.remoteSearchTimer);
                    const queryKey = this.searchTokens().join(' ');
                    if (queryKey === '') {
                        this.remoteLoading = false;
                        return;
                    }
                    this.remoteLoading = true;
                    this.remoteSearchTimer = setTimeout(() => this.fetchRemoteDebtors(queryKey), 250);
                },

                async fetchRemoteDebtors(queryKey) {
                    try {
                        const url = new URL(debtorSearchUrl, window.location.origin);
                        url.searchParams.set('q', queryKey);
                        const response = await fetch(url, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        if (!response.ok) throw new Error('HTTP ' + response.status);
                        const data = await response.json();

                        if (queryKey !== this.searchTokens().join(' ')) return;

                        this.remoteDebtors = (data.debtors || []).map(debtor => prepareCustomer({
                            key: customerKeyOf(debtor.customer_id),
                            name: debtor.name || 'Pelanggan Umum',
                            phone: debtor.phone || '',
                            invoice_count: Number(debtor.invoice_count) || 0,
                            remaining: parseFloat(debtor.remaining) || 0,
                        }));
                        this.remoteQueryKey = queryKey;
                    } catch (error) {
                        console.error('Pencarian debitur gagal:', error);
                    } finally {
                        if (queryKey === this.searchTokens().join(' ')) {
                            this.remoteLoading = false;
                        }
                    }
                },

                async loadDebtorSales(customer) {
                    const requestedKey = customer.key;
                    this.debtorSalesLoading = true;
                    this.debtorSalesError = '';

                    try {
                        const url = new URL(debtorSalesUrl, window.location.origin);
                        url.searchParams.set('customer', requestedKey === 'walk-in' ? 'walk-in' : requestedKey.replace('customer-', ''));
                        const response = await fetch(url, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        if (!response.ok) throw new Error('HTTP ' + response.status);
                        const data = await response.json();

                        const loadedIds = new Set(this.salesList.map(sale => sale.id));
                        for (const sale of data.sales || []) {
                            if (!loadedIds.has(sale.id)) {
                                this.salesList.push(mapSale(sale));
                            }
                        }

                        const index = this.customers.findIndex(c => c.key === requestedKey);
                        if (index !== -1) {
                            const ownedSales = this.salesList.filter(sale => sale.customer_key === requestedKey);
                            this.customers.splice(index, 1, prepareCustomer({
                                ...this.customers[index],
                                receipts: ownedSales.map(sale => sale.receipt_number || ''),
                                invoice_count: ownedSales.length,
                                remaining: ownedSales.reduce((sum, sale) => sum + sale.remaining, 0),
                            }));
                        }
                    } catch (error) {
                        console.error('Gagal memuat faktur debitur:', error);
                        if (this.customerKey === requestedKey) {
                            this.debtorSalesError = 'Sebagian faktur lama debitur ini gagal dimuat. Muat ulang halaman bila faktur yang dicari tidak tampil.';
                        }
                    } finally {
                        if (this.customerKey === requestedKey) {
                            this.debtorSalesLoading = false;
                        }
                    }
                },

                matchedReceipt(customer) {
                    const tokens = this.searchTokens();
                    if (tokens.length === 0) return '';
                    return customer.receipts.find(receipt => tokens.some(token => normalizeSearch(receipt).includes(token))) || '';
                },

                highlightMatch(text) {
                    const tokens = this.searchTokens();
                    const safeText = escapeHtml(text);
                    if (tokens.length === 0) return safeText;

                    const pattern = new RegExp('(' + tokens.map(token => escapeRegExp(escapeHtml(token))).join('|') + ')', 'gi');
                    return safeText.replace(pattern, '<mark class="rounded bg-amber-100 px-0.5 text-slate-900">$1</mark>');
                },

                openCustomerMenu(event) {
                    this.customerOpen = true;
                    this.customerActive = Math.max(0, this.customerResults().findIndex(c => c.key === this.customerKey));
                    event?.target?.select?.();
                },

                closeCustomerMenu() {
                    this.customerOpen = false;
                    this.customerQuery = this.selectedCustomerName();
                },

                moveCustomer(step) {
                    const total = this.customerResults().length;
                    if (!this.customerOpen) {
                        this.customerOpen = true;
                        return;
                    }
                    if (total === 0) return;
                    this.customerActive = (this.customerActive + step + total) % total;
                    this.$nextTick(() => {
                        this.$refs.customerList
                            ?.querySelector(`[data-customer-index="${this.customerActive}"]`)
                            ?.scrollIntoView({ block: 'nearest' });
                    });
                },

                confirmCustomer() {
                    if (!this.customerOpen) {
                        this.customerOpen = true;
                        return;
                    }
                    if (this.customerQuery.trim() === '') {
                        this.selectCustomer(null);
                        return;
                    }
                    const customer = this.customerResults()[this.customerActive];
                    if (customer) this.selectCustomer(customer);
                },

                async selectCustomer(customer) {
                    if (customer) {
                        const index = this.customers.findIndex(c => c.key === customer.key);
                        if (index === -1) {
                            this.customers.push(customer);
                            this.customers.sort((a, b) => a.name.localeCompare(b.name, 'id', { sensitivity: 'base' }));
                        }
                    }

                    clearTimeout(this.remoteSearchTimer);
                    this.remoteLoading = false;
                    this.debtorSalesLoading = false;
                    this.debtorSalesError = '';
                    this.customerKey = customer ? customer.key : '';
                    this.customerQuery = customer ? customer.name : '';
                    this.customerOpen = false;
                    this.customerActive = 0;

                    for (const sale of this.salesList) {
                        if (!this.isSaleVisible(sale)) {
                            sale.selected = false;
                            sale.allocated_amount = 0;
                        }
                    }

                    if (customer) {
                        await this.loadDebtorSales(customer);
                        if (this.customerKey !== customer.key) return;
                    }

                    if (this.amount > 0) {
                        this.autoDistributeAllocations();
                    }
                },

                isSaleVisible(sale) {
                    return !this.customerKey || sale.customer_key === this.customerKey;
                },

                visibleSales() {
                    return this.salesList.filter(sale => this.isSaleVisible(sale));
                },

                autoDistributeAllocations() {
                    let unallocated = this.amount;
                    for (let s of this.salesList) {
                        if (!this.isSaleVisible(s)) {
                            s.selected = false;
                            s.allocated_amount = 0;
                        } else if (unallocated > 0) {
                            s.selected = true;
                            let alloc = Math.min(unallocated, s.remaining);
                            s.allocated_amount = alloc;
                            unallocated -= alloc;
                        } else {
                            s.selected = false;
                            s.allocated_amount = 0;
                        }
                    }
                },

                getTotalAllocated() {
                    return this.salesList
                        .filter(s => s.selected)
                        .reduce((sum, s) => sum + (parseFloat(s.allocated_amount) || 0), 0);
                },

                getAccountLabel() {
                    const acc = this.cashBankAccounts.find(a => a.id == this.accountId);
                    return acc ? `[${acc.code}] ${acc.name}` : '(Pilih Akun Kas/Bank)';
                },

                get isBalanced() {
                    return this.accountId && this.amount > 0 && this.getTotalAllocated() > 0;
                },

                formatRupiah(val) {
                    const num = parseFloat(val) || 0;
                    return 'Rp ' + Math.round(num).toLocaleString('id-ID');
                }
            }
        }
    </script>
    </x-slot:scripts>
</x-app-layout>
