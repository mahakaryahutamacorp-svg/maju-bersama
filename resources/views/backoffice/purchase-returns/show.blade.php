<x-app-layout :title="'Detail Retur '.($purchaseReturn->reference_number)" :breadcrumbs="[
    ['label' => 'Belanja Barang'],
    ['label' => 'Retur ke Pemasok', 'url' => route('backoffice.purchase-returns.index')],
    ['label' => $purchaseReturn->reference_number],
]">
    <main class="mx-auto w-full px-6 py-6 lg:px-10 max-w-5xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <a href="{{ route('backoffice.purchase-returns.index') }}" class="text-xs font-semibold text-rose-600 hover:underline">
                    &larr; Kembali ke Riwayat Retur
                </a>
                <h2 class="mt-1 text-2xl font-bold text-slate-950 font-mono">{{ $purchaseReturn->reference_number }}</h2>
            </div>
            <a href="javascript:window.print()" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50">
                Cetak Dokumen
            </a>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
            @include('backoffice.transactions.partials.purchase-return', ['purchaseReturn' => $purchaseReturn])
        </div>
    </main>
</x-app-layout>
