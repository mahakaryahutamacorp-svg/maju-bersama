<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher Kas Keluar #{{ $expense->reference_number }} | Maju Bersama ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <!-- Header Utama -->
    <header class="border-b border-slate-800 bg-slate-950 text-white print:hidden">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
            <div class="flex items-center gap-4">
                <a href="/backoffice" class="block">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-400">Maju Bersama ERP</p>
                    <h1 class="text-xl font-bold tracking-tight">Keuangan &amp; Akuntansi</h1>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <div class="rounded-full border border-sky-400/30 bg-sky-400/10 px-3 py-1 text-xs font-medium text-sky-200">
                    {{ $currentUser->branch?->name ?? 'Pusat' }} ({{ $currentUser->role }})
                </div>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-4xl space-y-6 px-6 py-8 lg:px-8">
        <!-- Action Bar -->
        <div class="flex items-center justify-between print:hidden">
            <a href="{{ route('backoffice.expenses.index') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-2xs">
                &larr; Kembali ke Daftar
            </a>
            <div class="flex items-center gap-2">
                <button onclick="window.print()" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white shadow-2xs hover:bg-slate-800 flex items-center gap-1.5">
                    🖨️ Cetak Voucher
                </button>
            </div>
        </div>

        <!-- Voucher Card -->
        <div class="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm space-y-6">
            <div class="flex flex-col justify-between gap-4 border-b border-slate-100 pb-6 sm:flex-row sm:items-center">
                <div>
                    <span class="rounded-full bg-rose-50 border border-rose-200 px-3 py-0.5 text-xs font-bold text-rose-700 uppercase tracking-wider">
                        Voucher Kas Keluar
                    </span>
                    <h2 class="mt-2 text-2xl font-black text-slate-900 font-mono">{{ $expense->reference_number }}</h2>
                    <p class="text-xs text-slate-500 mt-0.5">{{ $expense->branch?->name ?? 'Cabang Pusat' }} &bull; Tanggal: {{ $expense->expense_date?->format('d F Y') }}</p>
                </div>
                <div class="text-left sm:text-right">
                    <p class="text-xs text-slate-400 uppercase tracking-wider">Total Pengeluaran</p>
                    <p class="text-3xl font-black font-mono text-rose-600">Rp {{ number_format($expense->amount, 0, ',', '.') }}</p>
                </div>
            </div>

            <!-- Detail Grid -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 text-xs">
                <div class="rounded-2xl border border-slate-100 bg-slate-50/50 p-4 space-y-2">
                    <p class="font-bold text-slate-500 uppercase tracking-wider text-[10px]">Pos Beban / Kategori</p>
                    <p class="text-sm font-bold text-slate-900">{{ $expense->expenseCategory?->name }}</p>
                    <p class="text-slate-600 font-mono">
                        COA: [{{ $expense->expenseCategory?->chartOfAccount?->code }}] {{ $expense->expenseCategory?->chartOfAccount?->name }}
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-100 bg-slate-50/50 p-4 space-y-2">
                    <p class="font-bold text-slate-500 uppercase tracking-wider text-[10px]">Sumber Rekening Kas/Bank</p>
                    <p class="text-sm font-bold text-slate-900">{{ $expense->account?->name }}</p>
                    <p class="text-slate-600 font-mono">
                        COA: [{{ $expense->account?->code }}] {{ strtoupper($expense->account?->type ?? 'asset') }}
                    </p>
                </div>

                <div class="sm:col-span-2 rounded-2xl border border-slate-100 bg-slate-50/50 p-4 space-y-1">
                    <p class="font-bold text-slate-500 uppercase tracking-wider text-[10px]">Keterangan / Catatan</p>
                    <p class="text-slate-800 text-sm">{{ $expense->notes ?: '-' }}</p>
                </div>
            </div>

            <!-- Jurnal Akuntansi Terkait -->
            @if ($expense->journalHeader)
                <div class="rounded-2xl border border-indigo-200 bg-indigo-50/30 p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-bold text-indigo-950">Jurnal Akuntansi Otomatis #{{ $expense->journalHeader->id }}</h4>
                            <p class="text-xs text-indigo-700 font-mono mt-0.5">{{ $expense->journalHeader->description }}</p>
                        </div>
                        <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800 border border-emerald-300">
                            Balanced
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-left text-xs font-mono">
                            <thead class="border-b border-indigo-200 bg-white/60 text-[10px] font-bold uppercase text-indigo-900 font-sans">
                                <tr>
                                    <th class="py-2.5 px-3">Kode &amp; Akun</th>
                                    <th class="py-2.5 px-3">Memo Baris</th>
                                    <th class="py-2.5 px-3 text-right">Debit (Rp)</th>
                                    <th class="py-2.5 px-3 text-right">Kredit (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-indigo-100 bg-white">
                                @foreach ($expense->journalHeader->journalLines as $line)
                                    <tr>
                                        <td class="py-3 px-3 font-semibold text-slate-900">
                                            [{{ $line->chartOfAccount?->code }}] {{ $line->chartOfAccount?->name }}
                                        </td>
                                        <td class="py-3 px-3 text-slate-500 text-[11px] font-sans">
                                            {{ $line->memo }}
                                        </td>
                                        <td class="py-3 px-3 text-right font-bold text-slate-900">
                                            {{ $line->debit > 0 ? number_format($line->debit, 0, ',', '.') : '-' }}
                                        </td>
                                        <td class="py-3 px-3 text-right font-bold text-slate-900">
                                            {{ $line->credit > 0 ? number_format($line->credit, 0, ',', '.') : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </main>
</body>
</html>
