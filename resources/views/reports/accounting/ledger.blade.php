<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buku Besar (General Ledger) | Maju Bersama ERP</title>
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
                <a href="/reports/accounting/ledger" class="rounded-lg bg-sky-600 px-3.5 py-2 font-semibold text-white shadow-sm">
                    Buku Besar
                </a>
                <a href="/reports/accounting/trial-balance" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
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
        <!-- Judul & Breadcrumb -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Laporan Keuangan</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Buku Besar (General Ledger)</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Cakupan: <span class="font-semibold text-slate-800">{{ $report['branch']['name'] }}</span>
                    @if ($startDate || $endDate)
                        &middot; Periode: <span class="font-semibold text-slate-800">{{ $startDate ? \Carbon\Carbon::parse($startDate)->format('d M Y') : 'Awal' }}</span> s/d <span class="font-semibold text-slate-800">{{ $endDate ? \Carbon\Carbon::parse($endDate)->format('d M Y') : 'Hari ini' }}</span>
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-3">
                <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-2.5 text-right">
                    <p class="text-xs font-medium uppercase tracking-wider text-sky-700">Akun Aktif</p>
                    <p class="text-lg font-bold text-sky-900">{{ count($report['accounts']) }} Akun</p>
                </div>
            </div>
        </div>

        <!-- Form Filter -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm no-print">
            <form method="GET" action="/reports/accounting/ledger" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
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

                <!-- Pilihan Akun -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Akun Perkiraan</label>
                    <select name="account_id" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                        <option value="">Semua Akun Aktif</option>
                        @foreach ($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ (string) $accountId === (string) $acc->id ? 'selected' : '' }}>
                                {{ $acc->code }} - {{ $acc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

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

                <!-- Tombol Submit -->
                <div class="flex items-end gap-2">
                    <button type="submit" class="w-full rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                        Terapkan
                    </button>
                    <a href="/reports/accounting/ledger" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <!-- Daftar Akun Buku Besar -->
        <div class="space-y-8">
            @forelse ($report['accounts'] as $item)
                @php
                    $acc = $item['account'];
                @endphp
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <!-- Header Akun -->
                    <div class="flex flex-col justify-between gap-4 border-b border-slate-200 bg-slate-50 px-6 py-4 sm:flex-row sm:items-center">
                        <div class="flex items-center gap-3">
                            <span class="rounded-lg bg-slate-900 px-3 py-1 font-mono text-sm font-bold text-amber-400">
                                {{ $acc['code'] }}
                            </span>
                            <div>
                                <h3 class="text-lg font-bold text-slate-950">{{ $acc['name'] }}</h3>
                                <p class="text-xs text-slate-500">
                                    Tipe: <span class="font-semibold uppercase text-slate-700">{{ $acc['type'] }}</span> &middot; 
                                    Saldo Normal: <span class="font-semibold uppercase {{ $acc['normal_balance'] === 'debit' ? 'text-sky-700' : 'text-emerald-700' }}">{{ $acc['normal_balance'] }}</span>
                                </p>
                            </div>
                        </div>
                        <!-- Stat Mini Saldo -->
                        <div class="flex flex-wrap items-center gap-4 text-xs sm:text-right">
                            <div>
                                <span class="text-slate-500">Saldo Awal:</span>
                                <span class="ml-1 font-bold text-slate-900">Rp {{ number_format($item['beginning_balance'], 0, ',', '.') }}</span>
                            </div>
                            <div class="text-sky-700">
                                <span class="text-slate-500">Total Debit:</span>
                                <span class="ml-1 font-bold">Rp {{ number_format($item['total_debit'], 0, ',', '.') }}</span>
                            </div>
                            <div class="text-amber-700">
                                <span class="text-slate-500">Total Kredit:</span>
                                <span class="ml-1 font-bold">Rp {{ number_format($item['total_credit'], 0, ',', '.') }}</span>
                            </div>
                            <div class="rounded-lg border border-slate-300 bg-white px-2.5 py-1">
                                <span class="text-slate-500">Saldo Akhir:</span>
                                <span class="ml-1 font-bold text-slate-950">Rp {{ number_format($item['ending_balance'], 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Transaksi -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                            <thead class="bg-slate-100 text-xs font-semibold uppercase tracking-wider text-slate-600">
                                <tr>
                                    <th class="px-5 py-3">Tanggal</th>
                                    <th class="px-5 py-3">No. Referensi</th>
                                    <th class="px-5 py-3">Keterangan</th>
                                    <th class="px-5 py-3">Memo / Catatan</th>
                                    @if ($isMaster && $branchId === null)
                                        <th class="px-5 py-3">Cabang</th>
                                    @endif
                                    <th class="px-5 py-3 text-right">Debit</th>
                                    <th class="px-5 py-3 text-right">Kredit</th>
                                    <th class="px-5 py-3 text-right">Saldo Berjalan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <!-- Baris Saldo Awal -->
                                <tr class="bg-amber-50/40 text-xs font-medium text-slate-600">
                                    <td class="px-5 py-3 font-mono">{{ $startDate ? \Carbon\Carbon::parse($startDate)->format('d/m/Y') : '-' }}</td>
                                    <td class="px-5 py-3 font-mono text-slate-400">—</td>
                                    <td colspan="{{ ($isMaster && $branchId === null) ? '3' : '2' }}" class="px-5 py-3 font-semibold text-slate-800">
                                        SALDO AWAL (SEBELUM {{ $startDate ? \Carbon\Carbon::parse($startDate)->format('d M Y') : 'PERIODE' }})
                                    </td>
                                    <td class="px-5 py-3 text-right text-slate-400">—</td>
                                    <td class="px-5 py-3 text-right text-slate-400">—</td>
                                    <td class="px-5 py-3 text-right font-bold text-slate-900">
                                        Rp {{ number_format($item['beginning_balance'], 0, ',', '.') }}
                                    </td>
                                </tr>

                                @forelse ($item['lines'] as $line)
                                    <tr class="transition hover:bg-slate-50">
                                        <td class="whitespace-nowrap px-5 py-3.5 font-mono text-xs text-slate-700">
                                            {{ \Carbon\Carbon::parse($line['date'])->format('d/m/Y') }}
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3.5 font-mono text-xs font-semibold text-sky-700">
                                            {{ $line['reference_number'] }}
                                        </td>
                                        <td class="px-5 py-3.5 text-slate-800 font-medium">
                                            {{ $line['description'] }}
                                        </td>
                                        <td class="px-5 py-3.5 text-xs text-slate-500">
                                            {{ $line['memo'] ?? '—' }}
                                        </td>
                                        @if ($isMaster && $branchId === null)
                                            <td class="whitespace-nowrap px-5 py-3.5 text-xs text-slate-600">
                                                <span class="rounded bg-slate-100 px-2 py-0.5">{{ $line['branch_name'] }}</span>
                                            </td>
                                        @endif
                                        <td class="whitespace-nowrap px-5 py-3.5 text-right font-mono text-xs {{ $line['debit'] > 0 ? 'font-semibold text-slate-900' : 'text-slate-300' }}">
                                            {{ $line['debit'] > 0 ? 'Rp '.number_format($line['debit'], 0, ',', '.') : '—' }}
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3.5 text-right font-mono text-xs {{ $line['credit'] > 0 ? 'font-semibold text-slate-900' : 'text-slate-300' }}">
                                            {{ $line['credit'] > 0 ? 'Rp '.number_format($line['credit'], 0, ',', '.') : '—' }}
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3.5 text-right font-mono text-xs font-bold text-slate-950">
                                            Rp {{ number_format($line['balance'], 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ ($isMaster && $branchId === null) ? '8' : '7' }}" class="px-5 py-4 text-center text-xs text-slate-400 italic">
                                            Tidak ada mutasi transaksi pada periode yang dipilih.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <!-- Footer Ringkasan Akun -->
                            <tfoot class="border-t-2 border-slate-200 bg-slate-50 text-xs font-bold">
                                <tr>
                                    <td colspan="{{ ($isMaster && $branchId === null) ? '5' : '4' }}" class="px-5 py-3.5 text-right text-slate-700 uppercase">
                                        Total Mutasi &amp; Saldo Akhir {{ $acc['name'] }}:
                                    </td>
                                    <td class="px-5 py-3.5 text-right text-sky-900">
                                        Rp {{ number_format($item['total_debit'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right text-amber-900">
                                        Rp {{ number_format($item['total_credit'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right text-slate-950 bg-slate-100 font-extrabold">
                                        Rp {{ number_format($item['ending_balance'], 0, ',', '.') }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <h3 class="mt-4 text-base font-semibold text-slate-900">Tidak ada data buku besar</h3>
                    <p class="mt-1 text-sm text-slate-500">Belum ada transaksi atau saldo pada rentang tanggal dan cabang yang dipilih.</p>
                </div>
            @endforelse
        </div>

        <!-- Grand Total Summary -->
        @if (count($report['accounts']) > 0)
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Grand Total Mutasi Periode</p>
                        <p class="text-sm text-slate-600">Akumulasi seluruh debit dan kredit akun aktif pada periode ini</p>
                    </div>
                    <div class="flex items-center gap-6 text-sm font-bold">
                        <div>
                            <span class="text-slate-500 font-medium">Grand Total Debit:</span>
                            <span class="ml-2 font-mono text-sky-700">Rp {{ number_format($report['grand_total_debit'], 0, ',', '.') }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500 font-medium">Grand Total Kredit:</span>
                            <span class="ml-2 font-mono text-amber-700">Rp {{ number_format($report['grand_total_credit'], 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </main>
</body>
</html>
