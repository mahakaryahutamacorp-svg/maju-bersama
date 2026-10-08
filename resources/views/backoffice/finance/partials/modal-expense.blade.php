<div x-show="expenseOpen" x-cloak class="relative z-50" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-950/50" @click="expenseOpen = false"></div>
    <div class="fixed inset-y-0 right-0 flex w-full max-w-lg">
        <div class="flex h-full w-full flex-col bg-white shadow-2xl" @click.stop>
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-rose-600">Quick Action</p>
                    <h2 class="text-lg font-bold text-slate-900">Catat Pengeluaran</h2>
                </div>
                <button type="button" @click="expenseOpen = false" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100">✕</button>
            </div>
            <form method="POST" action="{{ route('backoffice.expenses.store') }}" class="flex flex-1 flex-col overflow-y-auto" x-data="expenseForm(@js($expenseCategories), @js($cashBankAccounts), {{ (float) old('amount', 0) }})">
                @csrf
                <input type="hidden" name="return_to" value="finance">
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
                    <div>
                        <label class="text-[11px] font-bold uppercase text-slate-500">Tanggal</label>
                        <input type="date" name="expense_date" value="{{ old('expense_date', $todayDate) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-[11px] font-bold uppercase text-slate-500">Kategori biaya</label>
                        <select name="expense_category_id" x-model="selectedCategoryId" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Pilih kategori</option>
                            @foreach ($expenseCategories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold uppercase text-slate-500">Sumber kas / bank</label>
                        <select name="account_id" x-model="selectedAccountId" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Pilih akun</option>
                            @foreach ($cashBankAccounts as $account)
                                <option value="{{ $account->id }}">[{{ $account->code }}] {{ $account->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold uppercase text-slate-500">Nominal</label>
                        <input type="text" :value="formattedAmount" @input="formatInput($event)" placeholder="0" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        <input type="hidden" name="amount" :value="rawAmount">
                    </div>
                    <div>
                        <label class="text-[11px] font-bold uppercase text-slate-500">Catatan</label>
                        <textarea name="notes" x-model="notes" required rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Contoh: Token PLN toko"></textarea>
                    </div>
                    <div class="rounded-xl border border-indigo-100 bg-indigo-50 p-3 text-xs text-indigo-900">
                        Jurnal otomatis: Dr Beban / Cr Kas — <span x-text="computedJournalDescription"></span>
                    </div>
                </div>
                <div class="mt-auto flex gap-2 border-t border-slate-200 p-5">
                    <button type="button" @click="$dispatch('close-expense')" class="flex-1 rounded-xl border border-slate-300 py-2.5 text-sm font-semibold">Batal</button>
                    <button type="submit" class="flex-1 rounded-xl bg-rose-600 py-2.5 text-sm font-bold text-white">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    function expenseForm(categories = [], accounts = [], initialAmount = 0) {
        return {
            categories, accounts,
            rawAmount: initialAmount || 0,
            formattedAmount: initialAmount > 0 ? new Intl.NumberFormat('id-ID').format(initialAmount) : '',
            selectedCategoryId: '{{ old('expense_category_id', '') }}',
            selectedAccountId: '{{ old('account_id', '') }}',
            notes: @json(old('notes', '')),
            get selectedCategoryName() {
                const c = this.categories.find(x => x.id == this.selectedCategoryId);
                return c ? c.name : '';
            },
            get computedJournalDescription() {
                let desc = 'Biaya Operasional: ' + (this.selectedCategoryName || '[Kategori]');
                if ((this.notes || '').trim()) desc += ' - ' + this.notes.trim();
                return desc;
            },
            formatInput(event) {
                let value = event.target.value.replace(/[^\d]/g, '');
                this.rawAmount = value ? parseInt(value, 10) : 0;
                this.formattedAmount = value ? new Intl.NumberFormat('id-ID').format(this.rawAmount) : '';
            }
        };
    }
</script>
