<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dasbor Akuntansi | Maju Bersama ERP</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
@php
    $formatRp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
    $openExpense = $errors->any() && old('return_to') === 'finance';
    $openJournal = $errors->any() && old('journal_form') === '1';
@endphp
<div class="min-h-screen lg:flex">
    @include('layouts.sidebar')

    <main
        class="min-w-0 flex-1"
        x-data="{
            tab: '{{ $tab }}',
            expenseOpen: {{ $openExpense ? 'true' : 'false' }},
            journalOpen: {{ $openJournal ? 'true' : 'false' }},
            setTab(name) {
                this.tab = name;
                const url = new URL(window.location.href);
                url.searchParams.set('tab', name);
                window.history.replaceState({}, '', url);
            }
        }"
        @close-journal.window="journalOpen = false"
        @close-expense.window="expenseOpen = false"
    >
        <header class="border-b border-slate-200 bg-white">
            <div class="flex flex-col gap-4 px-6 py-5 lg:flex-row lg:items-center lg:justify-between lg:px-10">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-600">Keuangan &amp; Akuntansi</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">Dasbor Akuntansi</h1>
                    <p class="mt-1 text-sm text-slate-500">Empat pilar dalam satu halaman: kas, jurnal, pengeluaran, dan laporan.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="expenseOpen = true" class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-3.5 py-2 text-xs font-bold text-white shadow-xs hover:bg-rose-500">
                        + Pengeluaran
                    </button>
                    <button type="button" @click="journalOpen = true" class="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-3.5 py-2 text-xs font-bold text-amber-300 shadow-xs hover:bg-slate-800">
                        + Jurnal Manual
                    </button>
                    <a href="{{ route('reports.index') }}" class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Pusat Laporan</a>
                </div>
            </div>
        </header>

        <div class="mx-auto max-w-7xl space-y-5 px-6 py-6 lg:px-10">
            @if (session('success'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-rose-900">
                    <p class="text-sm font-bold">Periksa isian form:</p>
                    <ul class="mt-1 list-disc pl-5 text-xs">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="GET" action="{{ route('backoffice.finance.dashboard') }}" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
                <input type="hidden" name="tab" :value="tab">
                <div>
                    <label class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Dari</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Sampai</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                </div>
                @if ($isMaster)
                    <div>
                        <label class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Cabang</label>
                        <select name="branch_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((int) $selectedBranchId === (int) $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div>
                        <label class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Cabang</label>
                        <input type="text" readonly value="{{ $currentUser->branch?->name }}" class="mt-1 w-full cursor-not-allowed rounded-xl border border-slate-200 bg-slate-100 px-3 py-2 text-sm text-slate-600">
                    </div>
                @endif
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Terapkan</button>
            </form>

            <div class="flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-2">
                @foreach (['kas_bank' => 'Kas & Bank', 'jurnal' => 'Jurnal & Buku Besar', 'pengeluaran' => 'Pengeluaran', 'laporan' => 'Laporan Keuangan'] as $key => $label)
                    <button type="button" @click="setTab('{{ $key }}')"
                            :class="tab === '{{ $key }}' ? 'bg-amber-500 text-slate-950 shadow-sm' : 'text-slate-600 hover:bg-slate-100'"
                            class="rounded-xl px-4 py-2 text-sm font-semibold transition">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            {{-- TAB: KAS & BANK --}}
            <section x-show="tab === 'kas_bank'" x-cloak class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 sm:col-span-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-800">Total Kas &amp; Bank</p>
                        <p class="mt-1 text-2xl font-black text-emerald-900">{{ $formatRp($dashboard['cash_total']) }}</p>
                    </div>
                    @foreach ($dashboard['cash_accounts'] as $account)
                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <p class="text-[11px] font-semibold text-slate-500 font-mono">{{ $account['code'] }}</p>
                            <p class="text-sm font-bold text-slate-900">{{ $account['name'] }}</p>
                            <p class="mt-2 text-lg font-extrabold text-slate-900">{{ $formatRp($account['balance']) }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-sm font-bold text-slate-900">Mutasi Kas &amp; Bank</h3>
                            <a href="{{ route('backoffice.cash-transfers.index') }}" class="text-xs font-semibold text-sky-700 hover:underline">Lihat semua</a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-xs">
                                <thead class="bg-slate-50 text-[10px] uppercase text-slate-500">
                                    <tr>
                                        <th class="px-3 py-2">Tanggal</th>
                                        <th class="px-3 py-2">Dari → Ke</th>
                                        <th class="px-3 py-2 text-right">Nominal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($dashboard['transfers'] as $transfer)
                                        <tr>
                                            <td class="px-3 py-2 whitespace-nowrap">{{ $transfer->transfer_date?->isoFormat('D MMM Y') }}</td>
                                            <td class="px-3 py-2">{{ $transfer->fromAccount?->name }} → {{ $transfer->toAccount?->name }}</td>
                                            <td class="px-3 py-2 text-right font-semibold">{{ $formatRp($transfer->amount) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="px-3 py-6 text-center text-slate-400">Belum ada mutasi.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5">
                        <h3 class="mb-3 text-sm font-bold text-slate-900">Laci Kasir (Shift)</h3>
                        <div class="space-y-2">
                            @forelse ($dashboard['shifts'] as $shift)
                                <div class="flex items-center justify-between rounded-xl border border-slate-100 px-3 py-2">
                                    <div>
                                        <p class="text-xs font-bold text-slate-900">{{ $shift->cashRegister?->name ?? 'Laci' }} · {{ $shift->user?->name }}</p>
                                        <p class="text-[11px] text-slate-500">{{ $shift->opened_at?->isoFormat('D MMM HH:mm') }} · {{ $shift->status }}</p>
                                    </div>
                                    <p class="text-xs font-bold">{{ $formatRp($shift->opening_balance) }}</p>
                                </div>
                            @empty
                                <p class="py-6 text-center text-xs text-slate-400">Tidak ada shift kasir.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </section>

            {{-- TAB: JURNAL & BUKU BESAR --}}
            <section x-show="tab === 'jurnal'" x-cloak class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900">Buku Besar per Tipe Akun</h3>
                    <button type="button" @click="journalOpen = true" class="text-xs font-semibold text-sky-700 hover:underline">Tambah jurnal manual</button>
                </div>
                <div class="space-y-2">
                    @foreach ($dashboard['ledger_groups'] as $typeKey => $group)
                        <div x-data="{ open: {{ $typeKey === 'asset' ? 'true' : 'false' }} }" class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                            <button type="button" @click="open = !open" class="flex w-full items-center justify-between px-4 py-3 text-left">
                                <span class="text-sm font-bold text-slate-900">{{ $group['label'] }}</span>
                                <span class="text-xs text-slate-500">Dr {{ $formatRp($group['total_debit']) }} · Cr {{ $formatRp($group['total_credit']) }}</span>
                            </button>
                            <div x-show="open" x-cloak class="border-t border-slate-100 overflow-x-auto">
                                <table class="min-w-full text-left text-xs">
                                    <thead class="bg-slate-50 text-[10px] uppercase text-slate-500">
                                        <tr>
                                            <th class="px-4 py-2">Kode</th>
                                            <th class="px-4 py-2">Nama Akun</th>
                                            <th class="px-4 py-2 text-right">Debit</th>
                                            <th class="px-4 py-2 text-right">Kredit</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @forelse ($group['rows'] as $row)
                                            <tr>
                                                <td class="px-4 py-2 font-mono">{{ $row->code }}</td>
                                                <td class="px-4 py-2">{{ $row->name }}</td>
                                                <td class="px-4 py-2 text-right">{{ $formatRp($row->total_debit) }}</td>
                                                <td class="px-4 py-2 text-right">{{ $formatRp($row->total_credit) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Tidak ada mutasi {{ strtolower($group['label']) }} pada periode ini.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 px-4 py-3">
                        <h3 class="text-sm font-bold text-slate-900">Histori Jurnal Umum</h3>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @forelse ($dashboard['journals'] as $journal)
                            <div x-data="{ open: false }" class="px-4 py-3">
                                <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-3 text-left">
                                    <div class="min-w-0">
                                        <p class="font-mono text-xs font-bold text-sky-700">{{ $journal->reference_number }}</p>
                                        <p class="truncate text-sm font-medium text-slate-900">{{ $journal->description }}</p>
                                    </div>
                                    <span class="shrink-0 text-[11px] text-slate-500">{{ $journal->transaction_date?->isoFormat('D MMM Y') }}</span>
                                </button>
                                <div x-show="open" x-cloak class="mt-2 overflow-x-auto rounded-xl bg-slate-50">
                                    <table class="min-w-full text-xs">
                                        <tbody>
                                            @foreach ($journal->journalLines as $line)
                                                <tr class="border-t border-slate-100">
                                                    <td class="px-3 py-1.5">{{ $line->chartOfAccount?->code }} {{ $line->chartOfAccount?->name }}</td>
                                                    <td class="px-3 py-1.5 text-right text-emerald-700">{{ (float) $line->debit > 0 ? $formatRp($line->debit) : '' }}</td>
                                                    <td class="px-3 py-1.5 text-right text-rose-700">{{ (float) $line->credit > 0 ? $formatRp($line->credit) : '' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @empty
                            <p class="px-4 py-8 text-center text-sm text-slate-400">Belum ada jurnal pada periode ini.</p>
                        @endforelse
                    </div>
                </div>
            </section>

            {{-- TAB: PENGELUARAN --}}
            <section x-show="tab === 'pengeluaran'" x-cloak class="space-y-4">
                <div class="flex items-center justify-between rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3">
                    <div>
                        <p class="text-[11px] font-bold uppercase text-rose-800">Total pengeluaran periode</p>
                        <p class="text-xl font-black text-rose-900">{{ $formatRp($dashboard['expense_total']) }}</p>
                    </div>
                    <button type="button" @click="expenseOpen = true" class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white">Catat Pengeluaran</button>
                </div>
                <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-900 text-[10px] uppercase tracking-wider text-white">
                            <tr>
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-4 py-3">Referensi</th>
                                <th class="px-4 py-3">Keterangan</th>
                                <th class="px-4 py-3 text-right">Nominal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($dashboard['expenses'] as $expense)
                                <tr>
                                    <td class="px-4 py-2.5 text-xs whitespace-nowrap">{{ $expense->expense_date?->isoFormat('D MMM Y') }}</td>
                                    <td class="px-4 py-2.5 font-mono text-xs font-bold text-sky-700">{{ $expense->reference_number }}</td>
                                    <td class="px-4 py-2.5 text-sm">Pengeluaran: {{ $expense->expenseCategory?->name ?? 'Biaya' }}@if($expense->notes) - {{ $expense->notes }}@endif</td>
                                    <td class="px-4 py-2.5 text-right font-semibold text-rose-700">{{ $formatRp($expense->amount) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-10 text-center text-slate-400">Belum ada pengeluaran pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- TAB: LAPORAN --}}
            <section x-show="tab === 'laporan'" x-cloak class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <a href="{{ route('reports.trial-balance') }}" class="rounded-2xl border border-slate-200 bg-white p-4 hover:border-amber-400 hover:shadow-sm">
                        <p class="text-sm font-bold text-slate-900">Neraca Saldo</p>
                        <p class="mt-1 text-xs text-slate-500">Cek keseimbangan debit dan kredit.</p>
                    </a>
                    <a href="{{ route('reports.income-statement') }}" class="rounded-2xl border border-slate-200 bg-white p-4 hover:border-amber-400 hover:shadow-sm">
                        <p class="text-sm font-bold text-slate-900">Laba / Rugi</p>
                        <p class="mt-1 text-xs text-slate-500">Pendapatan dikurangi HPP dan beban.</p>
                    </a>
                    <a href="{{ route('reports.cash-flow') }}" class="rounded-2xl border border-slate-200 bg-white p-4 hover:border-amber-400 hover:shadow-sm">
                        <p class="text-sm font-bold text-slate-900">Arus Kas</p>
                        <p class="mt-1 text-xs text-slate-500">Kas masuk dan kas keluar periode.</p>
                    </a>
                    <a href="{{ route('reports.balance-sheet') }}" class="rounded-2xl border border-slate-200 bg-white p-4 hover:border-amber-400 hover:shadow-sm">
                        <p class="text-sm font-bold text-slate-900">Neraca</p>
                        <p class="mt-1 text-xs text-slate-500">Posisi aset, kewajiban, dan modal.</p>
                    </a>
                </div>
                <p class="text-xs text-slate-500">Ringkasan buku besar di bawah ini dikelompokkan per tipe akun (accordion).</p>
                @foreach ($dashboard['ledger_groups'] as $typeKey => $group)
                    <div x-data="{ open: false }" class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        <button type="button" @click="open = !open" class="flex w-full items-center justify-between px-4 py-3 text-left">
                            <span class="text-sm font-bold">{{ $group['label'] }}</span>
                            <span class="text-xs text-slate-500">{{ $group['rows']->count() }} akun</span>
                        </button>
                        <div x-show="open" x-cloak class="border-t border-slate-100 px-4 py-2 text-xs text-slate-600">
                            Debit {{ $formatRp($group['total_debit']) }} · Kredit {{ $formatRp($group['total_credit']) }}
                        </div>
                    </div>
                @endforeach
            </section>
        </div>

        @include('backoffice.finance.partials.modal-expense')
        @include('backoffice.finance.partials.modal-journal')
    </main>
</div>
</body>
</html>
