<div x-show="journalOpen" x-cloak class="relative z-50" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-950/50" @click="journalOpen = false"></div>
    <div class="fixed inset-y-0 right-0 flex w-full max-w-2xl">
        <div class="flex h-full w-full flex-col bg-white shadow-2xl" @click.stop>
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-amber-600">Quick Action</p>
                    <h2 class="text-lg font-bold text-slate-900">Jurnal Manual</h2>
                </div>
                <button type="button" @click="journalOpen = false" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100">✕</button>
            </div>
            <form method="POST" action="{{ route('backoffice.finance.journals.store') }}" class="flex flex-1 flex-col overflow-y-auto" x-data="manualJournalForm()">
                @csrf
                <input type="hidden" name="journal_form" value="1">
                <div class="space-y-4 p-5">
                    @if ($isMaster)
                        <div>
                            <label class="text-[11px] font-bold uppercase text-slate-500">Cabang</label>
                            <select name="branch_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected((int) $selectedBranchId === (int) $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-[11px] font-bold uppercase text-slate-500">Tanggal</label>
                            <input type="date" name="transaction_date" value="{{ old('transaction_date', $todayDate) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="text-[11px] font-bold uppercase text-slate-500">No. Referensi</label>
                            <input type="text" name="reference_number" value="{{ old('reference_number') }}" placeholder="Otomatis jika kosong" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold uppercase text-slate-500">Keterangan</label>
                        <input type="text" name="description" value="{{ old('description') }}" required placeholder="Contoh: Koreksi saldo kas" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <p class="text-[11px] font-bold uppercase text-slate-500">Baris jurnal</p>
                            <button type="button" @click="addLine()" class="text-xs font-semibold text-sky-700">+ Baris</button>
                        </div>
                        <template x-for="(line, index) in lines" :key="index">
                            <div class="grid grid-cols-12 gap-2 rounded-xl border border-slate-100 bg-slate-50 p-2">
                                <select class="col-span-12 sm:col-span-5 rounded-lg border border-slate-300 px-2 py-1.5 text-xs" :name="'lines['+index+'][chart_of_account_id]'" x-model="line.chart_of_account_id" required>
                                    <option value="">Akun</option>
                                    @foreach ($coaAccounts as $account)
                                        <option value="{{ $account->id }}">[{{ $account->code }}] {{ $account->name }}</option>
                                    @endforeach
                                </select>
                                <input type="number" step="0.01" min="0" class="col-span-6 sm:col-span-3 rounded-lg border border-slate-300 px-2 py-1.5 text-xs" :name="'lines['+index+'][debit]'" x-model.number="line.debit" placeholder="Debit">
                                <input type="number" step="0.01" min="0" class="col-span-6 sm:col-span-3 rounded-lg border border-slate-300 px-2 py-1.5 text-xs" :name="'lines['+index+'][credit]'" x-model.number="line.credit" placeholder="Kredit">
                                <button type="button" @click="removeLine(index)" class="col-span-12 sm:col-span-1 rounded-lg text-rose-600 text-xs" x-show="lines.length > 2">✕</button>
                            </div>
                        </template>
                        <p class="text-xs" :class="balanced ? 'text-emerald-700' : 'text-rose-700'">
                            Debit <span x-text="format(totalDebit)"></span> · Kredit <span x-text="format(totalCredit)"></span>
                            <span x-text="balanced ? ' (seimbang)' : ' (belum seimbang)'"></span>
                        </p>
                    </div>
                </div>
                <div class="mt-auto flex gap-2 border-t border-slate-200 p-5">
                    <button type="button" @click="$dispatch('close-journal')" class="flex-1 rounded-xl border border-slate-300 py-2.5 text-sm font-semibold">Batal</button>
                    <button type="submit" :disabled="!balanced" class="flex-1 rounded-xl bg-slate-900 py-2.5 text-sm font-bold text-amber-300 disabled:opacity-40">Posting Jurnal</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    function manualJournalForm() {
        return {
            lines: [
                { chart_of_account_id: '', debit: 0, credit: 0 },
                { chart_of_account_id: '', debit: 0, credit: 0 },
            ],
            addLine() { this.lines.push({ chart_of_account_id: '', debit: 0, credit: 0 }); },
            removeLine(index) { if (this.lines.length > 2) this.lines.splice(index, 1); },
            get totalDebit() { return this.lines.reduce((s, l) => s + (parseFloat(l.debit) || 0), 0); },
            get totalCredit() { return this.lines.reduce((s, l) => s + (parseFloat(l.credit) || 0), 0); },
            get balanced() { return this.totalDebit > 0 && Math.abs(this.totalDebit - this.totalCredit) < 0.009; },
            format(v) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(v || 0); }
        };
    }
</script>
