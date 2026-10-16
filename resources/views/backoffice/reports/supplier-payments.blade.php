<x-app-layout title="Riwayat Pembayaran Hutang Supplier" :breadcrumbs="[
    ['label' => 'Pusat Laporan', 'url' => route('reports.index')],
    ['label' => 'Pembayaran Hutang'],
]">
    @php
        $formatRupiah = function ($val) {
            $formatted = number_format(abs((float) $val), 2, ',', '.');
            return ((float) $val < 0 ? '(Rp '.$formatted.')' : 'Rp '.$formatted);
        };
        $payments = $report['payments'] ?? collect();
    @endphp

    <main class="mx-auto w-full max-w-6xl px-6 py-6 lg:px-10">
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm print:hidden">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Riwayat Pembayaran Hutang Supplier</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Pembayaran kas atau bank kepada supplier, beserta nota yang dipotong. Data tidak diubah dari halaman ini.</p>
                </div>
                <form method="GET" action="{{ route('reports.supplier-payments') }}" class="flex flex-wrap items-center gap-2">
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
                <h1 class="text-2xl font-extrabold text-slate-950">PEMBAYARAN HUTANG SUPPLIER</h1>
                <p class="mt-1 text-xs text-slate-500">{{ \Carbon\Carbon::parse($startDate)->isoFormat('D MMMM Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->isoFormat('D MMMM Y') }}</p>
            </header>

            <div class="mb-6 grid grid-cols-2 gap-3">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-[11px] font-semibold uppercase text-slate-500">Jumlah Bukti</p>
                    <p class="text-xl font-extrabold">{{ number_format($report['total_documents'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div class="rounded-xl border border-teal-200 bg-teal-50 p-3">
                    <p class="text-[11px] font-semibold uppercase text-teal-800">Total Dibayar</p>
                    <p class="text-xl font-extrabold text-teal-800">{{ $formatRupiah($report['total_amount'] ?? 0) }}</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-xs">
                    <thead class="bg-slate-900 text-[10px] uppercase tracking-wider text-white">
                        <tr>
                            <th class="px-3 py-3">Tanggal</th>
                            <th class="px-3 py-3">Referensi</th>
                            <th class="px-3 py-3">Supplier</th>
                            <th class="px-3 py-3">Sumber Kas</th>
                            <th class="px-3 py-3 text-right">Nominal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($payments as $row)
                            <tr>
                                <td class="whitespace-nowrap px-3 py-2.5">{{ $row['date'] ? \Carbon\Carbon::parse($row['date'])->isoFormat('D MMM Y') : '-' }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 font-mono font-semibold">{{ $row['reference'] }}</td>
                                <td class="px-3 py-2.5">
                                    <p class="font-medium text-slate-900">{{ $row['supplier_name'] }}</p>
                                    <p class="text-[11px] text-slate-500">
                                        {{ $row['branch_name'] }} · {{ $row['source'] }}
                                        @if (! empty($row['allocations']))
                                            · Nota {{ implode(', ', $row['allocations']) }}
                                        @endif
                                    </p>
                                    @if (! empty($row['notes']))
                                        <p class="text-[11px] text-slate-500">{{ $row['notes'] }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5">{{ $row['method'] }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right font-semibold text-teal-800">{{ $formatRupiah($row['amount']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-10 text-center text-sm text-slate-400">Tidak ada pembayaran hutang pada periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</x-app-layout>
