<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jurnal Umum (General Ledger) | Maju Bersama POS &amp; Akuntansi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body x-data="journalLedger()" class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <header class="border-b border-slate-800 bg-slate-950 text-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5 lg:px-8">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-400">Maju Bersama ERP</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight">Jurnal Umum</h1>
            </div>
            <div class="flex items-center gap-4">
                <nav class="hidden items-center gap-4 text-sm text-slate-300 md:flex">
                    <a href="/pos" class="hover:text-white">Kasir (POS)</a>
                    <a href="/inventory" class="hover:text-white">Persediaan</a>
                    <a href="/inventory/transfer" class="hover:text-white">Transfer Stok</a>
                    <a href="/reports/accounting/ledger" class="hover:text-white">Buku Besar</a>
                    <a href="/reports/journal" class="font-semibold text-white">Jurnal Umum</a>
                    <a href="/backoffice" class="hover:text-white">Panel Admin</a>
                </nav>
                <div class="rounded-full border border-sky-400/30 bg-sky-400/10 px-3 py-1 text-xs font-medium text-sky-200">
                    {{ $headers->count() }} transaksi
                </div>
                <form method="POST" action="/logout" class="hidden sm:block">
                    @csrf
                    <button type="submit" class="text-sm text-slate-300 hover:text-white">Keluar</button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl space-y-8 px-6 py-10 lg:px-8">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-amber-600">Pelaporan Keuangan &amp; Akuntansi</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Buku Jurnal Umum</h2>
                <p class="mt-2 text-slate-500">Seluruh mutasi debit dan kredit otomatis yang dihasilkan oleh transaksi operasional.</p>
            </div>
            <div class="flex flex-col gap-3 sm:items-end">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-right">
                    <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Integritas Jurnal</p>
                    <p class="mt-1 text-sm font-bold text-emerald-800">Pembukuan Berpasangan</p>
                </div>
                <input x-model="query" type="search" placeholder="Cari no. referensi atau keterangan..." class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none ring-sky-500 placeholder:text-slate-400 focus:ring-2">
            </div>
        </div>

        <div class="space-y-6">
            @forelse ($headers as $header)
                @php
                    $totalDebit = $header->journalLines->sum(fn ($line) => (float) $line->debit);
                    $totalCredit = $header->journalLines->sum(fn ($line) => (float) $line->credit);
                    $isBalanced = abs($totalDebit - $totalCredit) < 0.005;
                @endphp
                <section x-show="matches('{{ strtolower($header->reference_number.' '.$header->description) }}')" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-5 border-b border-slate-200 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-start gap-4">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-sm font-bold text-amber-400">JU</div>
                            <div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <button type="button" 
                                            @click="loadTransaction('{{ $header->reference_number }}')" 
                                            class="group inline-flex items-center gap-1.5 font-mono text-sm font-bold text-sky-700 hover:text-sky-900 hover:underline focus:outline-none transition" 
                                            title="Klik untuk melihat rincian transaksi lengkap">
                                        <span>{{ $header->reference_number }}</span>
                                        <svg class="h-3.5 w-3.5 text-sky-400 group-hover:text-sky-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </button>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">#{{ $header->id }}</span>
                                </div>
                                <p class="mt-2 text-lg font-semibold text-slate-950">{{ $header->description }}</p>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ $header->transaction_date->translatedFormat('d M Y') }}
                                    <span class="mx-1 text-slate-300">&middot;</span>
                                    {{ $header->user?->name ?? 'Sistem' }}
                                </p>
                            </div>
                        </div>
                        <span class="inline-flex w-fit items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold {{ $isBalanced ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                            <span class="h-2 w-2 rounded-full {{ $isBalanced ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                            {{ $isBalanced ? 'Seimbang (Balanced)' : 'Tidak Seimbang' }}
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kode Akun</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama Akun &amp; Keterangan</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Debit</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Kredit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($header->journalLines as $line)
                                    @php
                                        $accountName = $line->chartOfAccount?->name ?? 'Akun Tidak Dikenal';
                                        $description = $line->memo ?: $header->description;
                                    @endphp
                                    <tr class="hover:bg-slate-50">
                                        <td class="whitespace-nowrap px-6 py-4 font-mono text-sm font-semibold text-sky-700">{{ $line->chartOfAccount?->code ?? '—' }}</td>
                                        <td class="px-6 py-4 text-sm">
                                            <div class="flex flex-col">
                                                <span class="font-bold text-gray-800">{{ $accountName }}</span>
                                                <span class="text-xs text-gray-400 truncate max-w-xs" title="{{ $description }}">
                                                    {{ $description }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm {{ (float) $line->debit > 0 ? 'font-semibold text-slate-900' : 'text-slate-300' }}">{{ (float) $line->debit > 0 ? 'Rp '.number_format((float) $line->debit, 0, ',', '.') : '—' }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm {{ (float) $line->credit > 0 ? 'font-semibold text-slate-900' : 'text-slate-300' }}">{{ (float) $line->credit > 0 ? 'Rp '.number_format((float) $line->credit, 0, ',', '.') : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                                <tr>
                                    <th colspan="2" class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">Total Ayat Jurnal</th>
                                    <th class="px-6 py-4 text-right text-sm font-bold text-slate-950">Rp {{ number_format($totalDebit, 0, ',', '.') }}</th>
                                    <th class="px-6 py-4 text-right text-sm font-bold text-slate-950">Rp {{ number_format($totalCredit, 0, ',', '.') }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
            @empty
                <section class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                    <p class="font-semibold text-slate-800">Belum ada transaksi jurnal tercatat</p>
                    <p class="mt-2 text-sm text-slate-500">Lakukan transaksi di Kasir (POS) untuk melihat pencatatan ayat jurnal berpasangan otomatis.</p>
                </section>
            @endforelse
        </div>
    </main>

    <!-- Universal Transaction Viewer Modal -->
    <div x-show="showModal" 
         x-cloak 
         class="fixed inset-0 z-50 overflow-y-auto" 
         aria-labelledby="modal-title" 
         role="dialog" 
         aria-modal="true"
         style="display: none;">
        <!-- Backdrop -->
        <div x-show="showModal"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" 
             @click="showModal = false"></div>

        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <!-- Modal Panel -->
            <div x-show="showModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 @keydown.escape.window="showModal = false"
                 class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-3xl border border-slate-200">
                
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-100 text-sky-700 shadow-xs">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </span>
                        <div>
                            <h3 class="text-base font-bold text-slate-900" id="modal-title">Rincian Transaksi</h3>
                            <p class="text-xs text-slate-500">Universal Transaction Viewer</p>
                        </div>
                    </div>
                    <button type="button" 
                            @click="showModal = false" 
                            class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition">
                        <span class="sr-only">Tutup</span>
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Body Content injected via HTML partial -->
                <div class="p-6">
                    <div x-html="transactionHtml"></div>
                </div>

                <!-- Modal Footer -->
                <div class="border-t border-slate-100 bg-slate-50 px-6 py-3.5 flex justify-end">
                    <button type="button" 
                            @click="showModal = false" 
                            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 focus:outline-none transition">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function journalLedger() {
            return {
                query: '',
                showModal: false,
                transactionHtml: '',
                isLoading: false,
                matches(value) {
                    return !this.query.trim() || value.includes(this.query.toLowerCase().trim());
                },
                async loadTransaction(ref) {
                    if (!ref) return;
                    this.isLoading = true;
                    this.showModal = true;
                    this.transactionHtml = `
                        <div class="flex flex-col items-center justify-center py-12 text-slate-500">
                            <svg class="h-8 w-8 animate-spin text-sky-600 mb-3" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <p class="text-sm font-medium">Memuat rincian transaksi...</p>
                        </div>
                    `;
                    try {
                        const res = await fetch('/backoffice/transactions/' + encodeURIComponent(ref) + '/details');
                        if (res.ok) {
                            this.transactionHtml = await res.text();
                        } else {
                            this.transactionHtml = `
                                <div class="p-6 text-center text-rose-600">
                                    <p class="font-bold">Gagal memuat rincian transaksi</p>
                                    <p class="text-xs mt-1 text-slate-500">Kode respons server: ${res.status}</p>
                                </div>
                            `;
                        }
                    } catch (err) {
                        this.transactionHtml = `
                            <div class="p-6 text-center text-rose-600">
                                <p class="font-bold">Terjadi kesalahan jaringan</p>
                                <p class="text-xs mt-1 text-slate-500">Tidak dapat terhubung ke server saat memuat rincian transaksi.</p>
                            </div>
                        `;
                    } finally {
                        this.isLoading = false;
                    }
                }
            };
        }
    </script>
</body>
</html>