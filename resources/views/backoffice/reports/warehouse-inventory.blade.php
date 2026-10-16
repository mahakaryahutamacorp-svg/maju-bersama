<x-app-layout title="Daftar Barang per Gudang" :breadcrumbs="[
    ['label' => 'Pusat Laporan', 'url' => route('reports.index')],
    ['label' => 'Barang per Gudang'],
]">
    @php
        $formatRupiah = function ($val) {
            $formatted = number_format(abs((float) $val), 2, ',', '.');
            return ((float) $val < 0 ? '(Rp '.$formatted.')' : 'Rp '.$formatted);
        };
        $rows = $report['rows'] ?? collect();
    @endphp

    <main class="mx-auto w-full max-w-6xl px-6 py-6 lg:px-10">
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm print:hidden">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Daftar Barang per Gudang</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Kuantitas dari kartu persediaan dan nilai menurut harga beli. Baris bersaldo nol disembunyikan. Stok tidak diubah.</p>
                </div>
                <form method="GET" action="{{ route('reports.warehouse-inventory') }}" class="flex flex-wrap items-center gap-2">
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
                <h1 class="text-2xl font-extrabold text-slate-950">PERSEDIAAN PER GUDANG</h1>
                <p class="mt-1 text-xs text-slate-500">{{ number_format($report['hidden_zero_rows'] ?? 0, 0, ',', '.') }} baris stok nol tidak ditampilkan.</p>
            </header>

            <div class="mb-6 grid grid-cols-3 gap-3">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-[11px] font-semibold uppercase text-slate-500">Gudang</p>
                    <p class="text-xl font-extrabold">{{ number_format($report['warehouse_count'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                    <p class="text-[11px] font-semibold uppercase text-emerald-800">Total Kuantitas</p>
                    <p class="text-xl font-extrabold text-emerald-800">{{ number_format($report['total_quantity'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-[11px] font-semibold uppercase text-slate-500">Nilai Beli</p>
                    <p class="text-xl font-extrabold">{{ $formatRupiah($report['total_value'] ?? 0) }}</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-xs">
                    <thead class="bg-slate-900 text-[10px] uppercase tracking-wider text-white">
                        <tr>
                            <th class="px-3 py-3">Gudang</th>
                            <th class="px-3 py-3">Barang</th>
                            <th class="px-3 py-3 text-right">Qty</th>
                            <th class="px-3 py-3 text-right">Harga Beli</th>
                            <th class="px-3 py-3 text-right">Nilai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-3 py-2.5">
                                    <p class="font-medium">{{ $row['warehouse_name'] }}</p>
                                    <p class="text-[11px] text-slate-500">{{ $row['branch_name'] }}{{ $row['warehouse_code'] ? ' · '.$row['warehouse_code'] : '' }}</p>
                                </td>
                                <td class="px-3 py-2.5">
                                    <p class="font-medium text-slate-900">{{ $row['product_name'] }}</p>
                                    <p class="font-mono text-[11px] text-slate-500">{{ $row['sku'] ?: '-' }} {{ $row['unit'] ? '· '.$row['unit'] : '' }}</p>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right font-semibold">{{ number_format($row['quantity'], 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right">{{ $formatRupiah($row['unit_cost']) }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right font-semibold">{{ $formatRupiah($row['value']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-10 text-center text-sm text-slate-400">Tidak ada barang dengan saldo selain nol.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</x-app-layout>
