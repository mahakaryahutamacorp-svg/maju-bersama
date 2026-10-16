<x-app-layout title="Rekap Nilai Buku Aset" :breadcrumbs="[
    ['label' => 'Pusat Laporan', 'url' => route('reports.index')],
    ['label' => 'Nilai Buku Aset'],
]">
    @php
        $formatRupiah = function ($val) {
            $formatted = number_format(abs((float) $val), 2, ',', '.');
            return ((float) $val < 0 ? '(Rp '.$formatted.')' : 'Rp '.$formatted);
        };
        $groups = $assets->groupBy('branch_name');
        $totalAcquisition = (float) $assets->sum('acquisition_value');
        $totalDepreciation = (float) $assets->sum('accumulated_depreciation');
        $totalBook = (float) $assets->sum('book_value');
    @endphp

    <main class="mx-auto w-full max-w-6xl px-6 py-6 lg:px-10">
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm print:hidden">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Buku Rekapitulasi Nilai Buku Aset</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Harga perolehan, akumulasi penyusutan yang sudah dibukukan, dan sisa nilai buku. Tidak ada jurnal baru.</p>
                </div>
                <form method="GET" action="{{ route('reports.asset-book-value') }}" class="flex flex-wrap items-center gap-2">
                    <label class="text-xs text-slate-500">Per tanggal
                        <input type="date" name="as_of_date" value="{{ $asOfDate }}" class="ml-1 rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs text-slate-800">
                    </label>
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
                <h1 class="text-2xl font-extrabold text-slate-950">REKAP NILAI BUKU ASET</h1>
                <p class="mt-1 text-xs text-slate-500">Posisi {{ \Carbon\Carbon::parse($asOfDate)->isoFormat('D MMMM Y') }}</p>
            </header>

            <div class="mb-6 grid grid-cols-3 gap-3">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-[11px] font-semibold uppercase text-slate-500">Perolehan</p>
                    <p class="text-xl font-extrabold">{{ $formatRupiah($totalAcquisition) }}</p>
                </div>
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-3">
                    <p class="text-[11px] font-semibold uppercase text-rose-800">Akumulasi</p>
                    <p class="text-xl font-extrabold text-rose-800">{{ $formatRupiah($totalDepreciation) }}</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                    <p class="text-[11px] font-semibold uppercase text-emerald-800">Nilai Buku</p>
                    <p class="text-xl font-extrabold text-emerald-800">{{ $formatRupiah($totalBook) }}</p>
                </div>
            </div>

            @forelse ($groups as $branchName => $branchAssets)
                <h2 class="mb-2 mt-6 text-sm font-bold text-slate-900">{{ $branchName }}</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead class="bg-slate-900 text-[10px] uppercase tracking-wider text-white">
                            <tr>
                                <th class="px-3 py-3">Aset</th>
                                <th class="px-3 py-3 text-right">Perolehan</th>
                                <th class="px-3 py-3 text-right">Akumulasi</th>
                                <th class="px-3 py-3 text-right">Nilai Buku</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($branchAssets as $asset)
                                <tr>
                                    <td class="px-3 py-2.5">
                                        <p class="font-medium">{{ $asset->name }}</p>
                                        <p class="text-[11px] text-slate-500">{{ $asset->purchase_date }} · {{ $asset->status }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-right">{{ $formatRupiah($asset->acquisition_value) }}</td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-right">{{ $formatRupiah($asset->accumulated_depreciation) }}</td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-right font-semibold">{{ $formatRupiah($asset->book_value) }}</td>
                                </tr>
                            @endforeach
                            <tr class="bg-slate-50 font-bold">
                                <td class="px-3 py-2.5">Jumlah {{ $branchName }}</td>
                                <td class="px-3 py-2.5 text-right">{{ $formatRupiah($branchAssets->sum('acquisition_value')) }}</td>
                                <td class="px-3 py-2.5 text-right">{{ $formatRupiah($branchAssets->sum('accumulated_depreciation')) }}</td>
                                <td class="px-3 py-2.5 text-right">{{ $formatRupiah($branchAssets->sum('book_value')) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @empty
                <p class="py-10 text-center text-sm text-slate-400">Belum ada aset tetap yang tercatat.</p>
            @endforelse
        </div>
    </main>
</x-app-layout>
