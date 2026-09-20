<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher Mutasi Kas {{ $transfer->reference_number }} | Maju Bersama ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <!-- Header Utama -->
    <header class="border-b border-slate-800 bg-slate-950 text-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
            <div class="flex items-center gap-4">
                <a href="/backoffice" class="block">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-400">Maju Bersama ERP</p>
                    <h1 class="text-xl font-bold tracking-tight">Kas &amp; Bank (Treasury)</h1>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <nav class="hidden items-center gap-4 text-sm text-slate-300 md:flex">
                    <a href="/pos" class="hover:text-white">POS Kasir</a>
                    <a href="/inventory" class="hover:text-white">Inventory</a>
                    <a href="{{ route('backoffice.cash-transfers.index') }}" class="text-amber-400 font-semibold">Mutasi Kas &amp; Bank</a>
                    <a href="{{ route('backoffice.expenses.index') }}" class="hover:text-white">Biaya Operasional</a>
                    <a href="/reports/accounting/ledger" class="hover:text-white">Buku Besar</a>
                    <a href="/backoffice" class="hover:text-white">Backoffice</a>
                </nav>
                <div class="rounded-full border border-sky-400/30 bg-sky-400/10 px-3 py-1 text-xs font-medium text-sky-200">
                    {{ $currentUser->branch?->name ?? 'Pusat' }} ({{ $currentUser->role }})
                </div>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-4xl space-y-6 px-6 py-8 lg:px-8">
        <!-- Breadcrumb & Nav -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <a href="{{ route('backoffice.cash-transfers.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-800">
                    &larr; Kembali ke Riwayat Mutasi
                </a>
                <h2 class="mt-1 text-2xl font-bold text-slate-950">Voucher Mutasi Kas &amp; Bank</h2>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Cetak Bukti
                </button>
                <a href="{{ route('backoffice.cash-transfers.create') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-amber-500 px-4 py-2 text-xs font-bold text-slate-950 shadow-xs hover:bg-amber-400">
                    + Catat Mutasi Baru
                </a>
            </div>
        </div>

        <!-- Voucher Card -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <!-- Header Voucher -->
            <div class="border-b border-slate-100 bg-slate-900 px-6 py-5 text-white sm:px-8">
                <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                    <div>
                        <span class="text-xs font-semibold uppercase tracking-wider text-amber-400">Bukti Mutasi Kas Antar Rekening</span>
                        <h3 class="font-mono text-xl font-bold text-white">{{ $transfer->reference_number }}</h3>
                    </div>
                    <div class="text-right sm:text-right">
                        <span class="inline-flex items-center rounded-full bg-emerald-400/20 px-3 py-1 text-xs font-bold text-emerald-300 border border-emerald-400/30">
                            Jurnal Otomatis Terposting
                        </span>
                        <p class="mt-1 text-xs text-slate-400">Tanggal: {{ $transfer->transfer_date->format('d F Y') }}</p>
                    </div>
                </div>
            </div>

            <!-- Body Ringkasan Mutasi -->
            <div class="p-6 sm:p-8 space-y-6">
                <!-- Nominal & Info Cabang -->
                <div class="flex flex-col justify-between gap-4 rounded-2xl border border-amber-200 bg-amber-50/40 p-6 sm:flex-row sm:items-center">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-amber-700">Nominal Transfer / Mutasi</p>
                        <p class="mt-1 font-mono text-3xl font-black text-slate-950">
                            Rp {{ number_format((float) $transfer->amount, 0, ',', '.') }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">Cabang: <span class="font-semibold text-slate-700">{{ $transfer->branch?->name ?? 'Pusat' }}</span></p>
                    </div>
                    <div class="flex items-center gap-3">
                        <!-- Akun Asal -->
                        <div class="rounded-xl border border-rose-200 bg-white p-3 text-center min-w-[140px]">
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-rose-600">Sumber Dana</span>
                            <span class="block font-bold text-sm text-slate-900 mt-0.5">{{ $transfer->fromAccount?->name }}</span>
                            <span class="block font-mono text-xs text-slate-400">[{{ $transfer->fromAccount?->code }}]</span>
                        </div>
                        <div class="text-slate-400 font-black">&rarr;</div>
                        <!-- Akun Tujuan -->
                        <div class="rounded-xl border border-emerald-200 bg-white p-3 text-center min-w-[140px]">
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-emerald-600">Tujuan Dana</span>
                            <span class="block font-bold text-sm text-slate-900 mt-0.5">{{ $transfer->toAccount?->name }}</span>
                            <span class="block font-mono text-xs text-slate-400">[{{ $transfer->toAccount?->code }}]</span>
                        </div>
                    </div>
                </div>

                <!-- Catatan Transaksi -->
                @if ($transfer->notes)
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <span class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Catatan / Memo:</span>
                        <p class="text-sm text-slate-800">{{ $transfer->notes }}</p>
                    </div>
                @endif

                <!-- Jurnal Akuntansi Terkait -->
                @if ($transfer->journalHeader)
                    <div class="space-y-3 pt-2">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-sm font-bold text-slate-900">Rincian Buku Jurnal Double-Entry</h4>
                                <p class="text-xs text-slate-500">Keterangan: {{ $transfer->journalHeader->description }}</p>
                            </div>
                            <span class="font-mono text-xs font-semibold text-slate-500">Ref Jurnal: {{ $transfer->journalHeader->reference_number }}</span>
                        </div>

                        <div class="overflow-hidden rounded-xl border border-slate-200 shadow-2xs">
                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                <thead class="bg-slate-50 font-bold uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th class="px-4 py-2.5 text-left">Kode Akun</th>
                                        <th class="px-4 py-2.5 text-left">Nama Akun</th>
                                        <th class="px-4 py-2.5 text-right">Debit (Rp)</th>
                                        <th class="px-4 py-2.5 text-right">Kredit (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 font-mono">
                                    @foreach ($transfer->journalHeader->journalLines as $line)
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-4 py-2.5 text-slate-600 font-semibold">{{ $line->chartOfAccount?->code }}</td>
                                            <td class="px-4 py-2.5 text-slate-900 font-sans font-medium">{{ $line->chartOfAccount?->name }}</td>
                                            <td class="px-4 py-2.5 text-right font-bold text-emerald-700">
                                                {{ (float) $line->debit > 0 ? number_format((float) $line->debit, 0, ',', '.') : '-' }}
                                            </td>
                                            <td class="px-4 py-2.5 text-right font-bold text-rose-700">
                                                {{ (float) $line->credit > 0 ? number_format((float) $line->credit, 0, ',', '.') : '-' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-slate-50 font-mono font-bold text-slate-900">
                                    <tr>
                                        <td colspan="2" class="px-4 py-2 text-right font-sans uppercase text-[10px] text-slate-500">Total Keseimbangan:</td>
                                        <td class="px-4 py-2 text-right text-emerald-700">
                                            Rp {{ number_format((float) $transfer->journalHeader->journalLines->sum('debit'), 0, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-2 text-right text-rose-700">
                                            Rp {{ number_format((float) $transfer->journalHeader->journalLines->sum('credit'), 0, ',', '.') }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </main>
</body>
</html>
