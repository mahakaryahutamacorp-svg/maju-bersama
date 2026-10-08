<x-app-layout :title="'Bukti Penyesuaian Stok #'.($adjustment->reference_number)" :breadcrumbs="[
    ['label' => 'Stok Gudang'],
    ['label' => 'Hitung Ulang Stok (Opname)', 'url' => route('inventory.adjustments.index')],
    ['label' => 'Bukti Penyesuaian Stok #'.($adjustment->reference_number)],
]">
    <x-slot:head>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; font-size: 12px; }
            .print-shadow-none { box-shadow: none !important; border-color: #cbd5e1 !important; }
        }
    </style>
    </x-slot:head>

    <main class="mx-auto w-full px-6 py-6 lg:px-10 max-w-5xl">
        <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm print-shadow-none">
            <!-- Header Dokumen / Surat Berita Acara -->
            <div class="flex flex-col justify-between gap-4 border-b border-slate-200 pb-6 sm:flex-row sm:items-start">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center rounded-md bg-indigo-100 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-indigo-800">
                            Berita Acara Stock Opname
                        </span>
                    </div>
                    <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900">
                        {{ $adjustment->reference_number }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Lokasi Audit: <strong class="text-slate-800">{{ $adjustment->branch?->name ?? 'Kantor Pusat' }}</strong>
                    </p>
                </div>

                <div class="text-left sm:text-right">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Tanggal Pemeriksaan</p>
                    <p class="mt-1 text-base font-bold text-slate-900">
                        {{ $adjustment->date?->format('d F Y') ?? '-' }}
                    </p>
                    <p class="text-xs text-slate-500">
                        Dicatat: {{ $adjustment->created_at?->format('d/m/Y H:i') }}
                    </p>
                </div>
            </div>

            <!-- Catatan Dokumen -->
            @if ($adjustment->notes)
                <div class="mt-4 rounded-xl border border-slate-100 bg-slate-50 p-3.5 text-sm text-slate-700">
                    <span class="font-semibold text-slate-900">Catatan / Alasan Opname:</span> {{ $adjustment->notes }}
                </div>
            @endif

            <!-- Ringkasan Nilai Finansial -->
            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-rose-200 bg-rose-50/60 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-rose-700">Total Kerugian Stok (Minus)</p>
                    <p class="mt-1 text-xl font-bold text-rose-800">
                        -Rp {{ number_format($adjustment->total_loss_value, 0, ',', '.') }}
                    </p>
                    <p class="text-[11px] text-rose-600">Dicatat ke Beban Selisih (5120)</p>
                </div>

                <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Total Keuntungan Stok (Plus)</p>
                    <p class="mt-1 text-xl font-bold text-emerald-800">
                        +Rp {{ number_format($adjustment->total_gain_value, 0, ',', '.') }}
                    </p>
                    <p class="text-[11px] text-emerald-600">Dicatat ke Pendapatan Lain (4120)</p>
                </div>

                @php
                    $net = $adjustment->total_gain_value - $adjustment->total_loss_value;
                @endphp
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-600">Dampak Nilai Persediaan</p>
                    <p class="mt-1 text-xl font-bold {{ $net >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                        {{ $net >= 0 ? '+' : '' }}Rp {{ number_format($net, 0, ',', '.') }}
                    </p>
                    <p class="text-[11px] text-slate-500">Koreksi bersih akun Persediaan (1210)</p>
                </div>
            </div>

            <!-- Tabel Detail Item Opname -->
            <div class="mt-8">
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900 mb-3">
                    Rincian Pemeriksaan Fisik Barang ({{ $adjustment->items->count() }} Produk)
                </h3>
                <div class="overflow-hidden rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-900 text-xs font-semibold uppercase tracking-wider text-white">
                            <tr>
                                <th class="px-4 py-3">No</th>
                                <th class="px-4 py-3">Produk &amp; SKU</th>
                                <th class="px-4 py-3 text-center">Stok Sistem</th>
                                <th class="px-4 py-3 text-center">Stok Fisik</th>
                                <th class="px-4 py-3 text-center">Selisih</th>
                                <th class="px-4 py-3 text-right">HPP Satuan</th>
                                <th class="px-4 py-3 text-right">Total Nilai</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 [&>tr:nth-child(even)]:bg-slate-50/60">
                            @foreach ($adjustment->items as $idx => $item)
                                <tr class="{{ $item->difference_qty < 0 ? 'bg-rose-50/40' : ($item->difference_qty > 0 ? 'bg-emerald-50/40' : '') }}">
                                    <td class="px-4 py-3 text-slate-400 font-mono text-xs">{{ $idx + 1 }}</td>
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-slate-900">{{ $item->product?->name ?? 'Produk Dihapus' }}</p>
                                        <p class="font-mono text-xs text-slate-500">{{ $item->product?->sku ?? '-' }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-center font-semibold text-slate-600">{{ $item->expected_qty }}</td>
                                    <td class="px-4 py-3 text-center font-bold text-slate-900">{{ $item->actual_qty }}</td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap font-bold">
                                        @if ($item->difference_qty < 0)
                                            <span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs text-rose-800">
                                                {{ $item->difference_qty }} (Minus)
                                            </span>
                                        @elseif ($item->difference_qty > 0)
                                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">
                                                +{{ $item->difference_qty }} (Plus)
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                                                0 (Klop)
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-xs text-slate-700">
                                        Rp {{ number_format($item->unit_cost, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold whitespace-nowrap">
                                        @if ($item->difference_qty < 0)
                                            <span class="text-rose-700">-Rp {{ number_format(abs($item->subtotal_value), 0, ',', '.') }}</span>
                                        @elseif ($item->difference_qty > 0)
                                            <span class="text-emerald-700">+Rp {{ number_format($item->subtotal_value, 0, ',', '.') }}</span>
                                        @else
                                            <span class="text-slate-400">Rp 0</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tabel Bukti Jurnal Akuntansi Ganda Terkait -->
            @if ($adjustment->journal)
                <div class="mt-8 border-t border-slate-200 pt-6">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900 flex items-center gap-2">
                                <span>Pencatatan Jurnal Akuntansi</span>
                                <span class="font-mono text-indigo-700 font-semibold normal-case">#{{ $adjustment->journal->reference_number }}</span>
                            </h3>
                            <p class="text-xs text-slate-500">{{ $adjustment->journal->description }}</p>
                        </div>
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">
                            ✓ SEIMBANG
                        </span>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-left text-xs font-mono">
                            <thead class="bg-slate-900 text-xs font-semibold uppercase tracking-wider text-white">
                                <tr>
                                    <th class="px-4 py-2.5 font-sans">Kode Akun</th>
                                    <th class="px-4 py-2.5 font-sans">Nama Akun Buku Besar</th>
                                    <th class="px-4 py-2.5 text-right font-sans">Debit (Rp)</th>
                                    <th class="px-4 py-2.5 text-right font-sans">Kredit (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 [&>tr:nth-child(even)]:bg-slate-50/60">
                                @php
                                    $totDebit = 0;
                                    $totCredit = 0;
                                @endphp
                                @foreach ($adjustment->journal->journalLines as $line)
                                    @php
                                        $totDebit += $line->debit;
                                        $totCredit += $line->credit;
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-2 font-bold text-indigo-700">{{ $line->chartOfAccount?->code ?? '-' }}</td>
                                        <td class="px-4 py-2 font-sans font-medium text-slate-800 {{ $line->credit > 0 ? 'pl-8 text-slate-600' : '' }}">
                                            {{ $line->chartOfAccount?->name ?? 'Akun' }}
                                        </td>
                                        <td class="px-4 py-2 text-right font-bold {{ $line->debit > 0 ? 'text-slate-900' : 'text-slate-300' }}">
                                            {{ $line->debit > 0 ? number_format($line->debit, 0, ',', '.') : '-' }}
                                        </td>
                                        <td class="px-4 py-2 text-right font-bold {{ $line->credit > 0 ? 'text-slate-900' : 'text-slate-300' }}">
                                            {{ $line->credit > 0 ? number_format($line->credit, 0, ',', '.') : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-slate-50 font-bold border-t border-slate-200 text-slate-900">
                                <tr>
                                    <td colspan="2" class="px-4 py-2 font-sans text-right">Total:</td>
                                    <td class="px-4 py-2 text-right text-indigo-700">Rp {{ number_format($totDebit, 0, ',', '.') }}</td>
                                    <td class="px-4 py-2 text-right text-indigo-700">Rp {{ number_format($totCredit, 0, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endif

            <!-- Area Tanda Tangan Otorisasi Fisik (Cetak) -->
            <div class="mt-12 border-t border-dashed border-slate-300 pt-6">
                <div class="grid grid-cols-3 gap-6 text-center text-xs">
                    <div>
                        <p class="font-semibold text-slate-600">Petugas Stock Opname</p>
                        <div class="mt-16 border-b border-slate-400 mx-auto w-36"></div>
                        <p class="mt-1 text-slate-500">( Nama &amp; Paraf )</p>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-600">Supervisor Operasional</p>
                        <div class="mt-16 border-b border-slate-400 mx-auto w-36"></div>
                        <p class="mt-1 text-slate-500">( Nama &amp; Paraf )</p>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-600">Pimpinan / Akuntansi</p>
                        <div class="mt-16 border-b border-slate-400 mx-auto w-36"></div>
                        <p class="mt-1 text-slate-500">( Tanda Tangan &amp; Cap )</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</x-app-layout>
