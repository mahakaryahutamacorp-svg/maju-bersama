<x-app-layout title="Riwayat Pembayaran Hutang & Piutang (AR/AP)" :breadcrumbs="[
    ['label' => 'Jualan'],
    ['label' => 'Riwayat Pembayaran'],
]">
    <main class="mx-auto w-full px-6 py-6 lg:px-10 max-w-7xl space-y-6">
        <!-- Flash Alert -->
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900 shadow-xs flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <p class="text-sm font-semibold">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        <!-- Action Bar -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Riwayat Pembayaran AR/AP</h2>
                <p class="mt-1 text-xs text-slate-500">Kelola dan pantau seluruh transaksi penerimaan piutang dan pembayaran hutang beserta status alokasi faktur.</p>
            </div>
            <div class="flex items-center gap-3">
                <a 
                    href="{{ route('backoffice.payments.receivables.create') }}"
                    class="inline-flex items-center gap-2 rounded-xl bg-sky-600 px-4 py-2.5 text-xs font-bold text-white shadow-xs hover:bg-sky-700"
                >
                    + Terima Piutang (AR)
                </a>
                <a 
                    href="{{ route('backoffice.payments.payables.create') }}"
                    class="inline-flex items-center gap-2 rounded-xl bg-sky-600 px-4 py-2.5 text-xs font-bold text-white shadow-xs hover:bg-sky-700"
                >
                    + Bayar Hutang (AP)
                </a>
            </div>
        </div>

        <!-- Filter Tab & Search -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-4">
            <div class="flex items-center gap-2">
                <a 
                    href="{{ route('backoffice.payments.index') }}" 
                    class="rounded-lg px-3 py-1.5 text-xs font-bold transition-all {{ empty($activeType) ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-200' }}"
                >
                    Semua Transaksi
                </a>
                <a 
                    href="{{ route('backoffice.payments.index', ['type' => 'AR']) }}" 
                    class="rounded-lg px-3 py-1.5 text-xs font-bold transition-all {{ $activeType === 'AR' ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-200' }}"
                >
                    Penerimaan Piutang (AR)
                </a>
                <a 
                    href="{{ route('backoffice.payments.index', ['type' => 'AP']) }}" 
                    class="rounded-lg px-3 py-1.5 text-xs font-bold transition-all {{ $activeType === 'AP' ? 'bg-amber-500 text-slate-950' : 'text-slate-600 hover:bg-slate-200' }}"
                >
                    Pembayaran Hutang (AP)
                </a>
            </div>

            <form method="GET" action="{{ route('backoffice.payments.index') }}" class="flex items-center gap-2">
                @if ($activeType)
                    <input type="hidden" name="type" value="{{ $activeType }}">
                @endif
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Cari nomor ref / catatan..."
                    class="rounded-xl border border-slate-300 bg-white px-3.5 py-1.5 text-xs font-medium text-slate-900 outline-none focus:border-sky-500"
                >
                <button type="submit" class="rounded-xl bg-sky-600 px-3.5 py-1.5 text-xs font-bold text-white hover:bg-sky-700">
                    Cari
                </button>
            </form>
        </div>

        <!-- Tabel Pembayaran -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                <thead class="bg-slate-900 text-xs font-semibold uppercase tracking-wider text-white">
                    <tr>
                        <th class="px-4 py-3.5">Tanggal</th>
                        <th class="px-4 py-3.5">No. Referensi</th>
                        <th class="px-4 py-3.5">Tipe</th>
                        <th class="px-4 py-3.5">Pihak / Rekanan</th>
                        <th class="px-4 py-3.5">Rekening Kas / Bank</th>
                        <th class="px-4 py-3.5 text-right">Nominal</th>
                        <th class="px-4 py-3.5">Faktur / PO Dialokasikan</th>
                        <th class="px-4 py-3.5">No. Jurnal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white [&>tr:nth-child(even)]:bg-slate-50/60">
                    @forelse ($payments as $payment)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-4 py-3 font-medium text-slate-700 whitespace-nowrap">
                                {{ $payment->payment_date->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3 font-bold text-slate-900 whitespace-nowrap">
                                {{ $payment->reference_number }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($payment->type === 'AR')
                                    <span class="inline-block rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">
                                        AR (Piutang)
                                    </span>
                                @else
                                    <span class="inline-block rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800">
                                        AP (Hutang)
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                {{ $payment->supplier?->name ?? ($payment->type === 'AR' ? 'Pelanggan Umum' : '-') }}
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-800">
                                [{{ $payment->account?->code }}] {{ $payment->account?->name }}
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-slate-900 whitespace-nowrap">
                                Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                @foreach ($payment->allocations as $alloc)
                                    <span class="inline-block bg-slate-100 px-1.5 py-0.5 rounded text-[11px] font-mono mr-1 mb-1">
                                        {{ $alloc->sale?->receipt_number ?? $alloc->purchaseOrder?->reference_number ?? '-' }}
                                        (Rp {{ number_format((float)$alloc->allocated_amount, 0, ',', '.') }})
                                    </span>
                                @endforeach
                            </td>
                            <td class="px-4 py-3 font-mono text-[11px] text-slate-500 whitespace-nowrap">
                                {{ $payment->journalHeader?->reference_number ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-slate-400 italic">
                                Belum ada catatan transaksi pembayaran hutang atau piutang.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $payments->links() }}
        </div>
    </main>
</x-app-layout>
