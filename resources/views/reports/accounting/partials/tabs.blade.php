@php
    $accountingTabs = [
        ['label' => 'Jurnal Umum', 'url' => '/reports/journal', 'active' => request()->is('reports/journal*')],
        ['label' => 'Buku Besar', 'url' => route('reports.accounting.ledger'), 'active' => request()->is('reports/accounting/ledger*')],
        ['label' => 'Neraca Saldo', 'url' => route('reports.accounting.trial-balance'), 'active' => request()->is('reports/accounting/trial-balance*')],
        ['label' => 'Laba Rugi', 'url' => route('reports.accounting.income-statement'), 'active' => request()->is('reports/accounting/income-statement*')],
    ];
@endphp

<div class="no-print flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm sm:flex-row sm:items-center sm:justify-between print:hidden">
    <nav class="flex items-center gap-1 overflow-x-auto text-sm font-semibold" aria-label="Laporan akuntansi">
        @foreach ($accountingTabs as $tab)
            <a href="{{ $tab['url'] }}"
               @if ($tab['active']) aria-current="page" @endif
               class="whitespace-nowrap rounded-xl px-4 py-2 transition {{ $tab['active'] ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>
    <button type="button" onclick="window.print()" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
        <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        Cetak Laporan
    </button>
</div>
