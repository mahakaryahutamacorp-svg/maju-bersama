<x-app-layout title="Laporan Penyusutan Aset" :breadcrumbs="[
    ['label' => 'Pusat Laporan', 'url' => route('reports.index')],
    ['label' => 'Penyusutan Aset'],
]">
    @php
        $formatRupiah = function ($val) {
            $formatted = number_format(abs((float) $val), 2, ',', '.');
            return ((float) $val < 0 ? '(Rp '.$formatted.')' : 'Rp '.$formatted);
        };
    @endphp

    <main class="mx-auto w-full max-w-6xl px-6 py-6 lg:px-10">
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm print:hidden">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Laporan Penyusutan &amp; Depresiasi Aset</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Jadwal garis lurus dibanding penyusutan yang sudah dibukukan. Halaman ini tidak membuat jurnal.</p>
                </div>
                <form method="GET" action="{{ route('reports.depreciation') }}" class="flex flex-wrap items-center gap-2">
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
                <h1 class="text-2xl font-extrabold text-slate-950">JADWAL PENYUSUTAN</h1>
                <p class="mt-1 text-xs text-slate-500">Posisi {{ \Carbon\Carbon::parse($asOfDate)->isoFormat('D MMMM Y') }}. Bulan dihitung sebagai bulan penuh sejak tanggal perolehan.</p>
            </header>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-xs">
                    <thead class="bg-slate-900 text-[10px] uppercase tracking-wider text-white">
                        <tr>
                            <th class="px-3 py-3">Aset</th>
                            <th class="px-3 py-3 text-right">Perolehan</th>
                            <th class="px-3 py-3 text-right">Per Bulan</th>
                            <th class="px-3 py-3 text-right">Jadwal</th>
                            <th class="px-3 py-3 text-right">Terbukukan</th>
                            <th class="px-3 py-3 text-right">Selisih</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($assets as $asset)
                            <tr>
                                <td class="px-3 py-2.5">
                                    <p class="font-medium text-slate-900">{{ $asset->name }}</p>
                                    <p class="text-[11px] text-slate-500">{{ $asset->branch_name }} · {{ $asset->months_elapsed }}/{{ $asset->useful_life_months }} bulan · {{ $asset->status }}</p>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right">{{ $formatRupiah($asset->acquisition_value) }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right">{{ $formatRupiah($asset->monthly_depreciation) }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right">{{ $formatRupiah($asset->scheduled_depreciation) }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right">{{ $formatRupiah($asset->accumulated_depreciation) }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right font-semibold {{ $asset->unposted_depreciation > 0 ? 'text-amber-700' : 'text-slate-700' }}">{{ $formatRupiah($asset->unposted_depreciation) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-10 text-center text-sm text-slate-400">Belum ada aset tetap yang tercatat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</x-app-layout>
