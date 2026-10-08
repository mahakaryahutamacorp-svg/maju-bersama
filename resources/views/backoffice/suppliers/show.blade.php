<x-app-layout :title="'Buku Pemasok: '.$supplier->name" :breadcrumbs="[
    ['label' => 'Belanja Barang'],
    ['label' => 'Buku Pemasok', 'url' => route('backoffice.suppliers.index')],
    ['label' => $supplier->name],
]">
@php
    $formatRp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
    $statusStyles = [
        'Lunas' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'Dicicil' => 'bg-amber-50 text-amber-700 border-amber-200',
        'Belum Dibayar' => 'bg-rose-50 text-rose-700 border-rose-200',
        'Menunggu Barang' => 'bg-slate-100 text-slate-600 border-slate-200',
    ];
    $defaultAccountId = optional($cashBankAccounts->firstWhere('code', '1110') ?? $cashBankAccounts->first())->id;
    $hubConfig = [
        'tab' => $tab,
        'drawerOpen' => $errors->any(),
        'totalDebt' => (float) $ledger['total_debt'],
        'totalPaid' => (float) $ledger['total_paid'],
        'invoices' => $ledger['invoices']->mapWithKeys(fn ($row) => [
            $row['key'] => ['paid' => (float) $row['paid'], 'outstanding' => (float) $row['outstanding'], 'status' => $row['status']],
        ]),
        'openInvoices' => $ledger['open_invoices']->map(fn ($row) => [
            'id' => $row['key'],
            'label' => $row['description'].' ('.($row['date']?->isoFormat('D MMM Y') ?? '-').')',
            'outstanding' => (float) $row['outstanding'],
        ])->values(),
        'amountText' => old('amount') ? number_format((float) old('amount'), 0, ',', '.') : '',
        'accountId' => (string) old('account_id', $defaultAccountId),
        'paymentDate' => old('payment_date', $todayDate),
        'notes' => old('notes', ''),
    ];
@endphp
    <main class="min-w-0 flex-1" x-data="supplierHub(@js($hubConfig))" @keydown.escape.window="closeDrawer()">
        <header class="border-b border-slate-200 bg-white">
            <div class="flex flex-col gap-4 px-6 py-5 lg:flex-row lg:items-center lg:justify-between lg:px-10">
                <div class="min-w-0">
                    <a href="{{ route('backoffice.suppliers.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-800">&larr; Semua Pemasok</a>
                    <p class="mt-2 text-xs font-semibold uppercase tracking-[0.2em] text-indigo-600">Buku Pemasok</p>
                    <h1 class="mt-1 truncate text-2xl font-bold tracking-tight text-slate-950">{{ $supplier->name }}</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $supplier->contact_person ?: 'Tanpa nama kontak' }}
                        @if ($supplier->phone) · <span class="font-mono">{{ $supplier->phone }}</span>@endif
                        @if ($isMaster && $supplier->branch) · {{ $supplier->branch->name }}@endif
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('backoffice.suppliers.edit', $supplier) }}" class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Ubah Profil</a>
                    <button type="button" id="btn-bayar-hutang" @click="openDrawer()"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2">
                        + Bayar Hutang
                    </button>
                </div>
            </div>
        </header>

        <div class="mx-auto max-w-6xl space-y-5 px-6 py-6 lg:px-10">
            @if (session('success'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">{{ session('success') }}</div>
            @endif

            <div x-show="toast" x-cloak x-transition.opacity
                 class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900" x-text="toast"></div>

            {{-- Kartu utama: Sisa Hutang --}}
            <section class="grid gap-4 lg:grid-cols-3">
                <div class="rounded-3xl bg-gradient-to-br from-slate-900 to-slate-800 p-6 text-white shadow-lg lg:col-span-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-300">Total Sisa Hutang Kita</p>
                    <p class="mt-2 text-4xl font-black tracking-tight sm:text-5xl" x-text="rupiah(totalDebt)">{{ $formatRp($ledger['total_debt']) }}</p>
                    <p class="mt-3 text-sm text-slate-300">
                        <template x-if="totalDebt > 0">
                            <span>Ada <span x-text="openInvoices.length">{{ $ledger['open_invoices']->count() }}</span> nota belum lunas. Pembayaran otomatis memotong nota paling lama dulu.</span>
                        </template>
                        <template x-if="totalDebt <= 0">
                            <span>Semua nota ke pemasok ini sudah lunas.</span>
                        </template>
                    </p>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:grid-cols-1">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Total Belanja</p>
                        <p class="mt-1 text-lg font-extrabold text-slate-900">{{ $formatRp($ledger['total_purchased']) }}</p>
                    </div>
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">Sudah Kita Bayar</p>
                        <p class="mt-1 text-lg font-extrabold text-emerald-900" x-text="rupiah(totalPaid)">{{ $formatRp($ledger['total_paid']) }}</p>
                    </div>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-amber-700">Retur Barang</p>
                        <p class="mt-1 text-lg font-extrabold text-amber-900">{{ $formatRp($ledger['total_returned']) }}</p>
                    </div>
                </div>
            </section>

            {{-- Tabs --}}
            <nav class="flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-2" role="tablist">
                @foreach (['riwayat_belanja' => 'Riwayat Belanja', 'riwayat_pembayaran' => 'Riwayat Pembayaran', 'riwayat_retur' => 'Riwayat Retur'] as $key => $label)
                    <button type="button" role="tab" @click="tab = '{{ $key }}'"
                            :aria-selected="tab === '{{ $key }}'"
                            :class="tab === '{{ $key }}' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'"
                            class="rounded-xl px-4 py-2 text-sm font-semibold transition">
                        {{ $label }}
                    </button>
                @endforeach
            </nav>

            {{-- TAB: Riwayat Belanja --}}
            <section x-show="tab === 'riwayat_belanja'" x-cloak class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-4 py-3">Keterangan</th>
                                <th class="px-4 py-3 text-right">Nilai Barang</th>
                                <th class="px-4 py-3 text-right">Sudah Dibayar</th>
                                <th class="px-4 py-3 text-right">Sisa Hutang</th>
                                <th class="px-4 py-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($ledger['invoices'] as $invoice)
                                <tr class="hover:bg-slate-50/70">
                                    <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-600">
                                        {{ $invoice['date']?->isoFormat('D MMM Y') ?? '-' }}
                                        @if ($invoice['due_date'] && $invoice['status'] !== 'Lunas' && $invoice['status'] !== 'Menunggu Barang')
                                            <span class="block text-[11px] {{ $invoice['due_date']->isPast() ? 'font-semibold text-rose-600' : 'text-slate-400' }}">
                                                Jatuh tempo {{ $invoice['due_date']->isoFormat('D MMM') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ $invoice['description'] }}</td>
                                    <td class="px-4 py-3 text-right">{{ $formatRp($invoice['billable']) }}</td>
                                    <td class="px-4 py-3 text-right text-emerald-700" x-text="rupiah(invoices['{{ $invoice['key'] }}'].paid)">{{ $formatRp($invoice['paid']) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-slate-900" x-text="rupiah(invoices['{{ $invoice['key'] }}'].outstanding)">{{ $formatRp($invoice['outstanding']) }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $statusStyles[$invoice['status']] ?? '' }}"
                                              :class="statusClass(invoices['{{ $invoice['key'] }}'].status)"
                                              x-text="invoices['{{ $invoice['key'] }}'].status">{{ $invoice['status'] }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-12 text-center text-slate-400">Belum ada belanja dari pemasok ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- TAB: Riwayat Pembayaran --}}
            <section x-show="tab === 'riwayat_pembayaran'" x-cloak class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-4 py-3">Keterangan</th>
                                <th class="px-4 py-3 text-right">Nominal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="(payment, index) in newPayments" :key="'new-' + index">
                                <tr class="bg-emerald-50/60">
                                    <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-600" x-text="payment.date_label"></td>
                                    <td class="px-4 py-3 font-medium text-slate-900">
                                        <span x-text="payment.description"></span>
                                        <span class="ml-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700">Baru</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-emerald-700" x-text="rupiah(payment.amount)"></td>
                                </tr>
                            </template>
                            @forelse ($ledger['payments'] as $payment)
                                <tr class="hover:bg-slate-50/70">
                                    <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-600">{{ $payment['date']?->isoFormat('D MMM Y') ?? '-' }}</td>
                                    <td class="px-4 py-3 font-medium text-slate-900">
                                        {{ $payment['description'] }}
                                        @if ($payment['description'] !== $payment['channel'])
                                            <span class="block text-[11px] font-normal text-slate-500">{{ $payment['channel'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-emerald-700">{{ $formatRp($payment['amount']) }}</td>
                                </tr>
                            @empty
                                <tr x-show="newPayments.length === 0"><td colspan="3" class="px-4 py-12 text-center text-slate-400">Belum ada uang yang kita setorkan ke pemasok ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- TAB: Riwayat Retur --}}
            <section x-show="tab === 'riwayat_retur'" x-cloak class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-4 py-3">Keterangan</th>
                                <th class="px-4 py-3 text-right">Jumlah Barang</th>
                                <th class="px-4 py-3 text-right">Mengurangi Hutang</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($ledger['returns'] as $return)
                                <tr class="hover:bg-slate-50/70">
                                    <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-600">{{ $return['date']?->isoFormat('D MMM Y') ?? '-' }}</td>
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ $return['description'] }}</td>
                                    <td class="px-4 py-3 text-right text-slate-600">{{ number_format((int) $return['item_count'], 0, ',', '.') }} pcs</td>
                                    <td class="px-4 py-3 text-right font-semibold text-amber-700">{{ $formatRp($return['amount']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-12 text-center text-slate-400">Belum ada retur barang ke pemasok ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        {{-- Laci Bayar Hutang (slide-over) --}}
        <div x-show="drawerOpen" x-cloak class="relative z-50" role="dialog" aria-modal="true" aria-labelledby="drawer-title">
            <div x-show="drawerOpen" x-transition.opacity class="fixed inset-0 bg-slate-950/50" @click="closeDrawer()"></div>
            <div class="fixed inset-y-0 right-0 flex w-full max-w-md">
                <div x-show="drawerOpen"
                     x-transition:enter="transform transition ease-out duration-200"
                     x-transition:enter-start="translate-x-full"
                     x-transition:enter-end="translate-x-0"
                     x-transition:leave="transform transition ease-in duration-150"
                     x-transition:leave-start="translate-x-0"
                     x-transition:leave-end="translate-x-full"
                     class="flex h-full w-full flex-col bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-600">{{ $supplier->name }}</p>
                            <h2 id="drawer-title" class="text-lg font-bold text-slate-900">Bayar Hutang</h2>
                        </div>
                        <button type="button" @click="closeDrawer()" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Tutup">✕</button>
                    </div>

                    <form method="POST" action="{{ route('backoffice.suppliers.payments.store', $supplier) }}"
                          @submit.prevent="submit($event)" class="flex flex-1 flex-col overflow-y-auto">
                        @csrf
                        <input type="hidden" name="tab" :value="tab">
                        <div class="space-y-4 p-5">
                            <div class="rounded-2xl bg-slate-900 px-4 py-3 text-white">
                                <p class="text-[11px] uppercase tracking-wide text-slate-400">Sisa hutang saat ini</p>
                                <p class="text-2xl font-black" x-text="rupiah(totalDebt)">{{ $formatRp($ledger['total_debt']) }}</p>
                            </div>

                            <template x-if="errorMessages.length">
                                <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800">
                                    <template x-for="message in errorMessages" :key="message"><p x-text="message"></p></template>
                                </div>
                            </template>
                            @if ($errors->any())
                                <div x-show="errorMessages.length === 0" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800">
                                    @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                                </div>
                            @endif

                            <div>
                                <label for="amount" class="text-[11px] font-bold uppercase text-slate-500">Nominal Dibayar</label>
                                <div class="mt-1 flex items-center rounded-xl border border-slate-300 focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500">
                                    <span class="pl-3 text-sm font-semibold text-slate-500">Rp</span>
                                    <input id="amount" type="text" name="amount" inputmode="numeric" autocomplete="off" required
                                           x-model="amountText" @input="formatAmount()"
                                           placeholder="Contoh: 3.250.000"
                                           class="w-full rounded-xl border-0 px-2 py-2.5 text-lg font-bold focus:outline-none focus:ring-0">
                                </div>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button type="button" @click="setAmount(totalDebt)" class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">Lunasi semua</button>
                                    <template x-if="openInvoices.length > 0">
                                        <button type="button" @click="setAmount(openInvoices[0].outstanding)" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">Lunasi nota terlama</button>
                                    </template>
                                </div>
                                <p x-show="amountValue > totalDebt" x-cloak class="mt-1 text-xs font-semibold text-rose-600">Nominal melebihi sisa hutang.</p>
                            </div>

                            <div>
                                <label for="account_id" class="text-[11px] font-bold uppercase text-slate-500">Uang diambil dari</label>
                                <select id="account_id" name="account_id" x-model="accountId" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                                    @foreach ($cashBankAccounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="payment_date" class="text-[11px] font-bold uppercase text-slate-500">Tanggal bayar</label>
                                <input id="payment_date" type="date" name="payment_date" x-model="paymentDate" max="{{ $todayDate }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                            </div>

                            <div>
                                <label for="notes" class="text-[11px] font-bold uppercase text-slate-500">Catatan (opsional)</label>
                                <input id="notes" type="text" name="notes" x-model="notes" maxlength="255"
                                       placeholder="Contoh: Pembayaran Tunai ke Kurir"
                                       class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                            </div>

                            <div x-show="allocationPreview.length > 0" x-cloak class="rounded-xl border border-indigo-100 bg-indigo-50 p-3">
                                <p class="text-[11px] font-bold uppercase text-indigo-700">Uang ini akan memotong nota</p>
                                <ul class="mt-2 space-y-1.5 text-xs text-indigo-900">
                                    <template x-for="row in allocationPreview" :key="row.id">
                                        <li class="flex items-start justify-between gap-3">
                                            <span x-text="row.label"></span>
                                            <span class="shrink-0 text-right font-semibold">
                                                <span x-text="rupiah(row.portion)"></span>
                                                <span class="block text-[10px] font-normal" x-text="row.settled ? 'lunas' : 'sisa ' + rupiah(row.left)"></span>
                                            </span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </div>

                        <div class="mt-auto flex gap-2 border-t border-slate-200 p-5">
                            <button type="button" @click="closeDrawer()" class="flex-1 rounded-xl border border-slate-300 py-2.5 text-sm font-semibold">Batal</button>
                            <button type="submit" :disabled="submitting || amountValue <= 0 || amountValue > totalDebt"
                                    class="flex-1 rounded-xl bg-emerald-600 py-2.5 text-sm font-bold text-white hover:bg-emerald-500 disabled:opacity-40">
                                <span x-text="submitting ? 'Menyimpan...' : 'Simpan Pembayaran'">Simpan Pembayaran</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

<x-slot:scripts>
<script>
    function supplierHub(config) {
        return {
            ...config,
            newPayments: [],
            errorMessages: [],
            submitting: false,
            toast: '',
            get amountValue() {
                return parseInt(String(this.amountText).replace(/[^\d]/g, ''), 10) || 0;
            },
            get allocationPreview() {
                let remaining = Math.min(this.amountValue, this.totalDebt);
                const rows = [];
                for (const invoice of this.openInvoices) {
                    if (remaining <= 0) break;
                    const portion = Math.min(remaining, invoice.outstanding);
                    remaining -= portion;
                    const left = invoice.outstanding - portion;
                    rows.push({ id: invoice.id, label: invoice.label, portion, left, settled: left < 0.5 });
                }
                return rows;
            },
            rupiah(value) {
                return 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Math.round(value || 0));
            },
            statusClass(status) {
                return {
                    'Lunas': 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    'Dicicil': 'bg-amber-50 text-amber-700 border-amber-200',
                    'Belum Dibayar': 'bg-rose-50 text-rose-700 border-rose-200',
                    'Menunggu Barang': 'bg-slate-100 text-slate-600 border-slate-200',
                }[status] || '';
            },
            formatAmount() {
                const digits = String(this.amountText).replace(/[^\d]/g, '');
                this.amountText = digits ? new Intl.NumberFormat('id-ID').format(parseInt(digits, 10)) : '';
            },
            setAmount(value) {
                this.amountText = new Intl.NumberFormat('id-ID').format(Math.round(value || 0));
            },
            openDrawer() {
                this.errorMessages = [];
                this.drawerOpen = true;
                this.$nextTick(() => document.getElementById('amount')?.focus());
            },
            closeDrawer() {
                if (!this.submitting) this.drawerOpen = false;
            },
            async submit(event) {
                if (this.submitting) return;
                this.submitting = true;
                this.errorMessages = [];

                try {
                    const response = await fetch(event.target.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: new FormData(event.target),
                    });
                    const data = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        this.errorMessages = data.errors
                            ? Object.values(data.errors).flat()
                            : [data.message || 'Pembayaran gagal disimpan. Coba lagi.'];
                        return;
                    }

                    this.totalDebt = data.total_debt;
                    this.totalPaid = data.total_paid;
                    this.invoices = { ...this.invoices, ...data.invoices };
                    this.openInvoices = data.open_invoices;
                    this.newPayments.unshift(data.payment);
                    this.amountText = '';
                    this.notes = '';
                    this.toast = data.message;
                    this.drawerOpen = false;
                    setTimeout(() => { this.toast = ''; }, 5000);
                } catch (e) {
                    this.errorMessages = ['Koneksi terputus. Periksa jaringan lalu coba lagi.'];
                } finally {
                    this.submitting = false;
                }
            },
        };
    }
</script>
</x-slot:scripts>
</x-app-layout>
