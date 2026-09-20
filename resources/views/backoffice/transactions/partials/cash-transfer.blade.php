<div class="space-y-6">
    <!-- Header Modal Info -->
    <div class="border-b border-slate-200 pb-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                    Mutasi Antar Kas &amp; Bank
                </span>
                <h3 class="mt-1 font-mono text-xl font-bold tracking-tight text-slate-900">{{ $cashTransfer->reference_number }}</h3>
            </div>
            <div class="text-right">
                <span class="inline-flex rounded-md bg-indigo-100 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-indigo-800">
                    Cash Mutation
                </span>
                <p class="mt-1 text-xs text-slate-500">{{ $cashTransfer->transfer_date?->format('d M Y') ?? $cashTransfer->created_at?->format('d M Y') }}</p>
            </div>
        </div>

        <!-- Transfer Flow Card -->
        <div class="mt-4 rounded-xl border border-indigo-100 bg-indigo-50/50 p-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-3 text-center sm:text-left">
                <div class="rounded-lg bg-white p-3 shadow-xs">
                    <p class="text-[11px] font-semibold uppercase text-slate-400">Sumber Dana (Akun Asal)</p>
                    <p class="mt-1 text-sm font-bold text-slate-900">{{ $cashTransfer->fromAccount?->name ?? '-' }}</p>
                    <p class="font-mono text-xs text-slate-500">{{ $cashTransfer->fromAccount?->code }}</p>
                </div>
                <div class="flex flex-col items-center justify-center">
                    <svg class="h-6 w-6 text-indigo-500 rotate-90 sm:rotate-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    <span class="font-mono text-xs font-bold text-indigo-700 mt-1">Rp {{ number_format($cashTransfer->amount, 0, ',', '.') }}</span>
                </div>
                <div class="rounded-lg bg-white p-3 shadow-xs">
                    <p class="text-[11px] font-semibold uppercase text-slate-400">Tujuan Dana (Akun Tujuan)</p>
                    <p class="mt-1 text-sm font-bold text-slate-900">{{ $cashTransfer->toAccount?->name ?? '-' }}</p>
                    <p class="font-mono text-xs text-slate-500">{{ $cashTransfer->toAccount?->code }}</p>
                </div>
            </div>
        </div>

        <!-- Detail Metadata Grid -->
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 rounded-xl bg-slate-50 p-3.5 text-xs">
            <div>
                <span class="text-slate-500 block">Cabang</span>
                <span class="font-semibold text-slate-900">{{ $cashTransfer->branch?->name ?? '-' }}</span>
            </div>
            <div>
                <span class="text-slate-500 block">Tanggal Mutasi</span>
                <span class="font-semibold text-slate-900">{{ $cashTransfer->transfer_date?->format('d M Y') ?? '-' }}</span>
            </div>
            <div>
                <span class="text-slate-500 block">Nominal Ditransfer</span>
                <span class="font-bold text-indigo-700 font-mono">Rp {{ number_format($cashTransfer->amount, 0, ',', '.') }}</span>
            </div>
        </div>

        @if($cashTransfer->notes)
            <div class="mt-3 rounded-lg border border-slate-200 bg-white p-3 text-xs text-slate-600">
                <span class="font-semibold text-slate-700">Catatan Mutasi:</span> {{ $cashTransfer->notes }}
            </div>
        @endif
    </div>

    <!-- Double-entry Journal Lines -->
    @if($cashTransfer->journalHeader && $cashTransfer->journalHeader->journalLines->isNotEmpty())
        <div>
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Entri Jurnal Akuntansi (Double-entry)</h4>
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5">Akun</th>
                            <th class="px-4 py-2.5">Keterangan</th>
                            <th class="px-4 py-2.5 text-right">Debit</th>
                            <th class="px-4 py-2.5 text-right">Kredit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach($cashTransfer->journalHeader->journalLines as $line)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-4 py-2.5">
                                    <div class="font-mono text-xs text-slate-400">{{ $line->chartOfAccount?->code }}</div>
                                    <div class="font-medium text-slate-900">{{ $line->chartOfAccount?->name }}</div>
                                </td>
                                <td class="px-4 py-2.5 text-xs text-slate-600">{{ $line->memo ?? '-' }}</td>
                                <td class="px-4 py-2.5 text-right font-mono font-semibold {{ $line->debit > 0 ? 'text-slate-900' : 'text-slate-400' }}">
                                    {{ $line->debit > 0 ? 'Rp ' . number_format($line->debit, 0, ',', '.') : '-' }}
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono font-semibold {{ $line->credit > 0 ? 'text-slate-900' : 'text-slate-400' }}">
                                    {{ $line->credit > 0 ? 'Rp ' . number_format($line->credit, 0, ',', '.') : '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
