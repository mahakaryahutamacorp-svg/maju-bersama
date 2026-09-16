<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neraca Saldo (Trial Balance) | Maju Bersama ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; }
            .print-break { page-break-after: always; }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    @php
        $isBalanced = isset($is_balanced) ? (bool) $is_balanced : (bool) ($report['is_balanced'] ?? false);
        $diff = $report['difference'] ?? 0.0;
    @endphp

    <!-- Header Utama -->
    <header class="border-b border-slate-800 bg-slate-950 text-white no-print">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
            <div class="flex items-center gap-4">
                <a href="/backoffice" class="block">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-400">Maju Bersama ERP</p>
                    <h1 class="text-xl font-bold tracking-tight">Akuntansi &amp; Keuangan</h1>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <nav class="hidden items-center gap-4 text-sm text-slate-300 md:flex">
                    <a href="/pos" class="hover:text-white">POS</a>
                    <a href="/inventory" class="hover:text-white">Inventory</a>
                    <a href="/inventory/transfer" class="hover:text-white">Transfer</a>
                    <a href="/backoffice" class="hover:text-white">Backoffice</a>
                </nav>
                <div class="rounded-full border border-sky-400/30 bg-sky-400/10 px-3 py-1 text-xs font-medium text-sky-200">
                    {{ $currentUser->branch?->name ?? 'Pusat' }} ({{ $currentUser->role }})
                </div>
                <form method="POST" action="/logout" class="hidden sm:block">
                    @csrf
                    <button type="submit" class="text-sm text-slate-300 hover:text-white">Logout</button>
                </form>
            </div>
        </div>
    </header>

    <!-- Sub-Navbar Navigasi Laporan -->
    <div class="border-b border-slate-200 bg-white no-print">
        <div class="mx-auto flex max-w-7xl items-center justify-between overflow-x-auto px-6 py-2.5 lg:px-8">
            <div class="flex items-center gap-2 text-sm font-medium">
                <a href="/reports/accounting/ledger" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Buku Besar
                </a>
                <a href="/reports/accounting/trial-balance" class="rounded-lg bg-sky-600 px-3.5 py-2 font-semibold text-white shadow-sm">
                    Neraca Saldo
                </a>
                <a href="/reports/accounting/income-statement" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Laba Rugi
                </a>
                <a href="/reports/journal" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Jurnal Umum
                </a>
            </div>
            <button type="button" onclick="window.print()" class="flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Cetak Laporan
            </button>
        </div>
    </div>

    <main class="mx-auto max-w-7xl space-y-6 px-6 py-8 lg:px-8">
        <!-- Judul & Status Badge -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Laporan Keuangan</p>
                <div class="mt-1 flex flex-wrap items-center gap-3">
                    <h2 class="text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Neraca Saldo (Trial Balance)</h2>
                    <!-- Indikator Visual Badge SEIMBANG / TIDAK SEIMBANG -->
                    @if ($isBalanced)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-300 bg-emerald-50 px-3.5 py-1 text-xs font-bold text-emerald-700 shadow-sm">
                            <span class="relative flex h-2 w-2">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                            </span>
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            SEIMBANG
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-rose-300 bg-rose-50 px-3.5 py-1 text-xs font-bold text-rose-700 shadow-sm">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            TIDAK SEIMBANG
                        </span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-slate-500">
                    Cakupan: <span class="font-semibold text-slate-800">{{ $report['branch']['name'] }}</span>
                    @if ($startDate || $endDate)
                        &middot; Periode: <span class="font-semibold text-slate-800">{{ $startDate ? \Carbon\Carbon::parse($startDate)->format('d M Y') : 'Awal' }}</span> s/d <span class="font-semibold text-slate-800">{{ $endDate ? \Carbon\Carbon::parse($endDate)->format('d M Y') : 'Hari ini' }}</span>
                    @endif
                </p>
            </div>

            <!-- Kartu Status Ringkas -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 shadow-sm text-right">
                    <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Total Akun</p>
                    <p class="text-lg font-bold text-slate-900">{{ count($report['accounts']) }} Akun</p>
                </div>
                <div class="rounded-xl border {{ $isBalanced ? 'border-emerald-200 bg-emerald-50/60' : 'border-rose-200 bg-rose-50/60' }} px-4 py-2.5 text-right">
                    <p class="text-xs font-medium uppercase tracking-wider {{ $isBalanced ? 'text-emerald-700' : 'text-rose-700' }}">Keseimbangan Buku</p>
                    <p class="text-lg font-bold font-mono {{ $isBalanced ? 'text-emerald-900' : 'text-rose-900' }}">
                        {{ $isBalanced ? 'Rp 0 (Klop)' : 'Selisih Rp ' . number_format($diff, 0, ',', '.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Form Filter -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm no-print">
            <form method="GET" action="/reports/accounting/trial-balance" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Pilihan Cabang (Hanya untuk Master) -->
                @if ($isMaster)
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Cabang</label>
                        <select name="branch_id" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                            <option value="">Semua Cabang (Konsolidasi)</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" {{ (string) $branchId === (string) $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <!-- Dari Tanggal -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Dari Tanggal</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                </div>

                <!-- Sampai Tanggal -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Sampai Tanggal</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                </div>

                <!-- Tombol Submit & Reset -->
                <div class="flex items-end gap-2 {{ !$isMaster ? 'sm:col-span-2 lg:col-span-2' : '' }}">
                    <button type="submit" class="w-full rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                        Terapkan
                    </button>
                    <a href="/reports/accounting/trial-balance" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <!-- Tabel Neraca Saldo -->
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead>
                        <!-- Baris Group Header -->
                        <tr class="bg-slate-900 text-xs font-semibold uppercase tracking-wider text-white">
                            <th rowspan="2" class="border-r border-slate-800 px-4 py-3">Kode</th>
                            <th rowspan="2" class="border-r border-slate-800 px-4 py-3">Nama Akun</th>
                            <th rowspan="2" class="border-r border-slate-800 px-3 py-3 text-center">Tipe</th>
                            <th colspan="2" class="border-r border-slate-800 px-4 py-2.5 text-center bg-slate-800/80">Saldo Awal</th>
                            <th colspan="2" class="border-r border-slate-800 px-4 py-2.5 text-center bg-slate-800/50">Mutasi Periode</th>
                            <th colspan="2" class="px-4 py-2.5 text-center bg-sky-950">Saldo Akhir</th>
                        </tr>
                        <!-- Baris Sub Header Debit / Kredit -->
                        <tr class="bg-slate-100 text-xs font-semibold uppercase tracking-wider text-slate-700 border-b border-slate-200">
                            <th class="px-3 py-2 text-right border-r border-slate-200">Debit</th>
                            <th class="px-3 py-2 text-right border-r border-slate-200">Kredit</th>
                            <th class="px-3 py-2 text-right border-r border-slate-200">Debit</th>
                            <th class="px-3 py-2 text-right border-r border-slate-200">Kredit</th>
                            <th class="px-3 py-2 text-right border-r border-slate-200 bg-sky-50 text-sky-900">Debit</th>
                            <th class="px-3 py-2 text-right bg-sky-50 text-sky-900">Kredit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($report['accounts'] as $row)
                            <tr class="transition hover:bg-slate-50/80">
                                <!-- Kode Akun -->
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs font-bold text-slate-900 border-r border-slate-100">
                                    <span class="rounded bg-slate-100 px-2 py-1">{{ $row['code'] }}</span>
                                </td>

                                <!-- Nama Akun -->
                                <td class="px-4 py-3 font-medium text-slate-900 border-r border-slate-100">
                                    {{ $row['name'] }}
                                </td>

                                <!-- Tipe Akun -->
                                <td class="whitespace-nowrap px-3 py-3 text-center text-xs border-r border-slate-100">
                                    <span class="rounded-full px-2 py-0.5 font-medium uppercase
                                        @if(in_array(strtolower($row['type']), ['asset'])) bg-blue-50 text-blue-700 border border-blue-200
                                        @elseif(in_array(strtolower($row['type']), ['liability'])) bg-purple-50 text-purple-700 border border-purple-200
                                        @elseif(in_array(strtolower($row['type']), ['equity'])) bg-indigo-50 text-indigo-700 border border-indigo-200
                                        @elseif(in_array(strtolower($row['type']), ['revenue'])) bg-emerald-50 text-emerald-700 border border-emerald-200
                                        @else bg-amber-50 text-amber-700 border border-amber-200
                                        @endif">
                                        {{ $row['type'] }}
                                    </span>
                                </td>

                                <!-- Saldo Awal Debit -->
                                <td class="whitespace-nowrap px-3 py-3 text-right font-mono text-xs border-r border-slate-100 {{ $row['opening_debit'] > 0 ? 'text-slate-900 font-semibold' : 'text-slate-300' }}">
                                    {{ $row['opening_debit'] > 0 ? 'Rp ' . number_format($row['opening_debit'], 0, ',', '.') : '—' }}
                                </td>

                                <!-- Saldo Awal Kredit -->
                                <td class="whitespace-nowrap px-3 py-3 text-right font-mono text-xs border-r border-slate-100 {{ $row['opening_credit'] > 0 ? 'text-slate-900 font-semibold' : 'text-slate-300' }}">
                                    {{ $row['opening_credit'] > 0 ? 'Rp ' . number_format($row['opening_credit'], 0, ',', '.') : '—' }}
                                </td>

                                <!-- Mutasi Periode Debit -->
                                <td class="whitespace-nowrap px-3 py-3 text-right font-mono text-xs border-r border-slate-100 {{ $row['movement_debit'] > 0 ? 'text-sky-700 font-semibold' : 'text-slate-300' }}">
                                    {{ $row['movement_debit'] > 0 ? 'Rp ' . number_format($row['movement_debit'], 0, ',', '.') : '—' }}
                                </td>

                                <!-- Mutasi Periode Kredit -->
                                <td class="whitespace-nowrap px-3 py-3 text-right font-mono text-xs border-r border-slate-100 {{ $row['movement_credit'] > 0 ? 'text-amber-700 font-semibold' : 'text-slate-300' }}">
                                    {{ $row['movement_credit'] > 0 ? 'Rp ' . number_format($row['movement_credit'], 0, ',', '.') : '—' }}
                                </td>

                                <!-- Saldo Akhir Debit -->
                                <td class="whitespace-nowrap px-3 py-3 text-right font-mono text-xs border-r border-slate-100 bg-sky-50/30 {{ $row['ending_debit'] > 0 ? 'font-bold text-slate-950' : 'text-slate-300' }}">
                                    {{ $row['ending_debit'] > 0 ? 'Rp ' . number_format($row['ending_debit'], 0, ',', '.') : '—' }}
                                </td>

                                <!-- Saldo Akhir Kredit -->
                                <td class="whitespace-nowrap px-3 py-3 text-right font-mono text-xs bg-sky-50/30 {{ $row['ending_credit'] > 0 ? 'font-bold text-slate-950' : 'text-slate-300' }}">
                                    {{ $row['ending_credit'] > 0 ? 'Rp ' . number_format($row['ending_credit'], 0, ',', '.') : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-12 text-center text-slate-500">
                                    <svg class="mx-auto h-10 w-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <p class="mt-3 text-sm font-semibold text-slate-800">Tidak ada data untuk ditampilkan</p>
                                    <p class="text-xs text-slate-500">Belum ada akun yang memiliki saldo atau mutasi pada kriteria filter ini.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                    <!-- Footer Total Baris -->
                    <tfoot class="border-t-2 border-slate-300 bg-slate-50 text-xs font-bold">
                        <!-- Subtotal Saldo Awal & Mutasi -->
                        <tr class="border-b border-slate-200">
                            <td colspan="3" class="px-4 py-3 text-right uppercase tracking-wider text-slate-600">
                                Total Saldo Awal &amp; Mutasi:
                            </td>
                            <td class="px-3 py-3 text-right font-mono text-slate-900 border-r border-slate-200">
                                Rp {{ number_format($report['total_opening_debit'], 0, ',', '.') }}
                            </td>
                            <td class="px-3 py-3 text-right font-mono text-slate-900 border-r border-slate-200">
                                Rp {{ number_format($report['total_opening_credit'], 0, ',', '.') }}
                            </td>
                            <td class="px-3 py-3 text-right font-mono text-sky-900 border-r border-slate-200">
                                Rp {{ number_format($report['total_movement_debit'], 0, ',', '.') }}
                            </td>
                            <td class="px-3 py-3 text-right font-mono text-amber-900 border-r border-slate-200">
                                Rp {{ number_format($report['total_movement_credit'], 0, ',', '.') }}
                            </td>
                            <td colspan="2" class="px-3 py-3 bg-slate-100"></td>
                        </tr>

                        <!-- Grand Total Ending Balance -->
                        <tr class="bg-slate-900 text-white">
                            <td colspan="3" class="px-4 py-4 text-right font-extrabold uppercase tracking-wider">
                                GRAND TOTAL SALDO AKHIR:
                            </td>
                            <td colspan="4" class="px-3 py-4 text-center text-xs font-normal text-slate-400">
                                Penjumlahan Saldo Akhir Semua Akun
                            </td>
                            <td class="px-3 py-4 text-right font-mono text-sm font-extrabold text-amber-300 border-r border-slate-800 bg-slate-950">
                                Rp {{ number_format($report['total_ending_debit'], 0, ',', '.') }}
                            </td>
                            <td class="px-3 py-4 text-right font-mono text-sm font-extrabold text-amber-300 bg-slate-950">
                                Rp {{ number_format($report['total_ending_credit'], 0, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <!-- Banner Validasi Keseimbangan (Double-Entry Audit) -->
        <section class="rounded-2xl border {{ $isBalanced ? 'border-emerald-200 bg-emerald-50/50' : 'border-rose-200 bg-rose-50/50' }} p-6 shadow-sm">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $isBalanced ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                        @if ($isBalanced)
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @else
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        @endif
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold {{ $isBalanced ? 'text-emerald-950' : 'text-rose-950' }}">
                                {{ $isBalanced ? 'Status Audit: Neraca Saldo Seimbang (Balanced)' : 'Status Audit: Neraca Saldo Belum Seimbang (Discrepancy Detected)' }}
                            </h3>
                            @if ($isBalanced)
                                <span class="rounded bg-emerald-200/80 px-2 py-0.5 text-xs font-extrabold uppercase tracking-wide text-emerald-900">SEIMBANG</span>
                            @else
                                <span class="rounded bg-rose-200/80 px-2 py-0.5 text-xs font-extrabold uppercase tracking-wide text-rose-900">SELISIH</span>
                            @endif
                        </div>
                        <p class="mt-1 text-xs text-slate-600">
                            @if ($isBalanced)
                                Seluruh jurnal pembukuan telah memenuhi prinsip double-entry accounting. Total Debit (Rp {{ number_format($report['total_ending_debit'], 0, ',', '.') }}) tepat sama dengan Total Kredit (Rp {{ number_format($report['total_ending_credit'], 0, ',', '.') }}).
                            @else
                                Ditemukan selisih sebesar <span class="font-bold text-rose-700 font-mono">Rp {{ number_format($diff, 2, ',', '.') }}</span> antara sisi Debit dan Kredit. Mohon tinjau kembali jurnal umum yang diposting.
                            @endif
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-4 text-xs font-mono">
                    <div class="rounded-xl border border-slate-200 bg-white p-3 text-right shadow-sm">
                        <span class="text-slate-500 block text-[10px] uppercase font-sans">Ending Debit</span>
                        <span class="font-bold text-slate-900">Rp {{ number_format($report['total_ending_debit'], 0, ',', '.') }}</span>
                    </div>
                    <div class="text-slate-400 font-sans font-bold text-lg">=</div>
                    <div class="rounded-xl border border-slate-200 bg-white p-3 text-right shadow-sm">
                        <span class="text-slate-500 block text-[10px] uppercase font-sans">Ending Kredit</span>
                        <span class="font-bold text-slate-900">Rp {{ number_format($report['total_ending_credit'], 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
