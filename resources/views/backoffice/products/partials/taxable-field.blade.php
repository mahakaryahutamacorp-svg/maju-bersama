<div id="taxable-field" class="{{ $showTaxField ? '' : 'hidden' }}">
    <label class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50/70 px-4 py-3">
        <input
            type="checkbox"
            name="is_taxable"
            value="1"
            class="mt-0.5 h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
            @checked(old('is_taxable', $isTaxable ?? false))
        >
        <span>
            <span class="block text-sm font-semibold text-slate-900">Kena PPN</span>
            <span class="mt-0.5 block text-[11px] text-slate-500">Harga jual sudah termasuk PPN. Kasir tidak menambahkan pajak di meja. Tanda ini hanya berlaku di cabang yang pajaknya dinyalakan.</span>
        </span>
    </label>
</div>
