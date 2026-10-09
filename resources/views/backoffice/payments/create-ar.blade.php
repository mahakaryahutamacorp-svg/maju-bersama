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
                                    <tr :class="sale.selected ? 'bg-emerald-50/40' : ''">
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
            return {
                cashBankAccounts: cashBankAccounts || [],
                salesList: (unpaidSales || []).map(s => ({
                    id: s.id,
                    receipt_number: s.receipt_number,
                    customer_name: s.customer_name || 'Pelanggan Umum',
                    sale_date: s.sale_date || '-',
                    description: s.description || 'Piutang penjualan',
                    total_amount: parseFloat(s.total_amount) || 0,
                    paid_amount: parseFloat(s.paid_amount) || 0,
                    remaining: Math.max(0, (parseFloat(s.total_amount) || 0) - (parseFloat(s.paid_amount) || 0)),
                    selected: false,
                    allocated_amount: 0,
                })),
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

                autoDistributeAllocations() {
                    let unallocated = this.amount;
                    for (let s of this.salesList) {
                        if (unallocated > 0) {
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
