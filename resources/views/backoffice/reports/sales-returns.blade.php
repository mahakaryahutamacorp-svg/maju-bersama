<x-app-layout title="Laporan Retur Penjualan" :breadcrumbs="[
    ['label' => 'Pusat Laporan', 'url' => route('reports.index')],
    ['label' => 'Retur Penjualan'],
]">
    @php
        $formatRupiah = function ($val) {
            $formatted = number_format(abs((float) $val), 2, ',', '.');
            return ((float) $val < 0 ? '(Rp '.$formatted.')' : 'Rp '.$formatted);
        };
        $returns = $report['returns'] ?? collect();
    @endphp

    <main class="mx-auto w-full max-w-6xl px-6 py-6 lg:px-10">
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm print:hidden">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Laporan Retur Penjualan</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Pengembalian barang, nilai refund, dan HPP yang dikembalikan ke persediaan. Laporan ini hanya membaca data.</p>
                </div>
                <form method="GET" action="{{ route('reports.sales-returns') }}" class="flex flex-wrap items-center gap-2">
                    <input type="date" name="start_date" value="{{ $startDate }}" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
                    <input type="date" name="end_date" value="{{ $endDate }}" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
                    @if ($isMaster)
                        <select name="branch_id" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
                            <option value="">Semua Cabang</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((string) $selectedBranchId === (string) $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    <button type="submit" class="rounded-lg bg-sky-600 px-3.5 py-2 text-xs font-bold text-white">Filter</button>
                    <button type="button" onclick="window.print()" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-bold text-slate-700">Cetak</button>
                </form>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-300 bg-white p-6 shadow-lg sm:p-8">
            <header class="mb-6 border-b-2 border-slate-900 pb-4">
                <p class="text-sm font-bold uppercase tracking-wider text-amber-600">Maju Bersama Grup</p>
                <h1 class="text-2xl font-extrabold text-slate-950">LAPORAN RETUR PENJUALAN</h1>
                <p class="mt-1 text-xs text-slate-500">{{ \Carbon\Carbon::parse($startDate)->isoFormat('D MMMM Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->isoFormat('D MMMM Y') }}</p>
            </header>

            <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-[11px] font-semibold uppercase text-slate-500">Dokumen</p>
                    <p class="text-xl font-extrabold">{{ number_format($report['total_documents'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-3">
                    <p class="text-[11px] font-semibold uppercase text-rose-800">Nilai Refund</p>
                    <p class="text-xl font-extrabold text-rose-800">{{ $formatRupiah($report['total_amount'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-[11px] font-semibold uppercase text-slate-500">HPP Dikembalikan</p>
                    <p class="text-xl font-extrabold">{{ $formatRupiah($report['total_cost'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-[11px] font-semibold uppercase text-slate-500">Jumlah Barang</p>
                    <p class="text-xl font-extrabold">{{ number_format($report['total_quantity'] ?? 0, 0, ',', '.') }}</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-xs">
                    <thead class="bg-slate-900 text-[10px] uppercase tracking-wider text-white">
                        <tr>
                            <th class="px-3 py-3">Tanggal</th>
                            <th class="px-3 py-3">Referensi</th>
                            <th class="px-3 py-3">Pelanggan / Barang</th>
                            <th class="px-3 py-3">Refund</th>
                            <th class="px-3 py-3 text-right">Nilai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($returns as $row)
                            <tr>
                                <td class="whitespace-nowrap px-3 py-2.5">{{ $row->return_date?->isoFormat('D MMM Y') }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 font-mono font-semibold">{{ $row->reference_number }}</td>
                                <td class="px-3 py-2.5">
                                    <p class="font-medium text-slate-900">{{ $row->customer_name ?: 'Pelanggan Umum' }}</p>
                                    <p class="text-[11px] text-slate-500">
                                        {{ $row->branch?->name ?? '-' }}
                                        @if ($row->sale?->receipt_number)
                                            · Nota {{ $row->sale->receipt_number }}
                                        @endif
                                        · {{ $row->items->sum('quantity') }} barang
                                    </p>
                                    @if ($row->reason)
                                        <p class="text-[11px] text-slate-500">{{ $row->reason }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5">{{ strtoupper((string) $row->refund_method) }} · {{ $row->status }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right font-semibold text-rose-700">{{ $formatRupiah($row->total_amount) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-10 text-center text-sm text-slate-400">Tidak ada retur penjualan pada periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</x-app-layout>
