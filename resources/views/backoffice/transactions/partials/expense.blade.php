<div class="space-y-6">
    <!-- Header Modal Info -->
    <div class="border-b border-slate-200 pb-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                    Biaya Operasional (Kas Keluar)
                </span>
                <h3 class="mt-1 font-mono text-xl font-bold tracking-tight text-slate-900">{{ $expense->reference_number }}</h3>
            </div>
            <div class="text-right">
                <span class="inline-flex rounded-md bg-rose-100 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-rose-800">
                    Expense
                </span>
                <p class="mt-1 text-xs text-slate-500">{{ $expense->expense_date?->format('d M Y') ?? $expense->created_at?->format('d M Y') }}</p>
            </div>
        </div>

        <!-- Detail Metadata Grid -->
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 rounded-xl bg-slate-50 p-3.5 text-xs">
            <div>
                <span class="text-slate-500 block">Cabang</span>
                <span class="font-semibold text-slate-900">{{ $expense->branch?->name ?? '-' }}</span>
            </div>
            <div>
                <span class="text-slate-500 block">Kategori Beban</span>
                <span class="font-semibold text-slate-900">{{ $expense->expenseCategory?->name ?? '-' }}</span>
            </div>
            <div>
                <span class="text-slate-500 block">Sumber Dana (Akun Kas/Bank)</span>
                <span class="font-semibold text-slate-900">{{ $expense->account?->name ?? '-' }}</span>
            </div>
            <div>
                <span class="text-slate-500 block">Nominal Beban</span>
                <span class="font-bold text-rose-600 font-mono">Rp {{ number_format($expense->amount, 0, ',', '.') }}</span>
            </div>
        </div>

        @if($expense->notes)
            <div class="mt-3 rounded-lg border border-slate-200 bg-white p-3 text-xs text-slate-600">
                <span class="font-semibold text-slate-700">Keterangan / Keperluan:</span> {{ $expense->notes }}
            </div>
        @endif
    </div>

    <!-- Double-entry Journal Lines (if available) -->
    @if($expense->journalHeader && $expense->journalHeader->journalLines->isNotEmpty())
        <div>
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Entri Jurnal Terkait (Double-entry)</h4>
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5">Akun</th>
                            <th class="px-4 py-2.5">Keterangan Memo</th>
                            <th class="px-4 py-2.5 text-right">Debit</th>
                            <th class="px-4 py-2.5 text-right">Kredit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach($expense->journalHeader->journalLines as $line)
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
