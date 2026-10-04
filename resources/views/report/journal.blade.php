<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jurnal Umum (General Ledger) | Maju Bersama POS &amp; Akuntansi</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
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
                    {{ $headers->total() }} transaksi
                </div>
                <form method="POST" action="/logout" class="hidden sm:block">
                    @csrf
                    <button type="submit" class="text-sm text-slate-300 hover:text-white">Keluar</button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl space-y-6 px-6 py-8 lg:px-8">
        <!-- Sub-header & Pencarian -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-amber-600">Pelaporan Keuangan &amp; Akuntansi</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Buku Jurnal Umum</h2>
                <p class="mt-1 text-xs text-slate-500 sm:text-sm">Seluruh mutasi debit dan kredit otomatis yang dihasilkan oleh transaksi operasional.</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="relative w-full sm:w-72">
                    <input x-model="query" 
                           type="search" 
                           placeholder="Cari no. referensi atau keterangan..." 
                           class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs sm:text-sm outline-none ring-sky-500 placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 shadow-xs transition">
                </div>
            </div>
        </div>

        <!-- Daftar Transaksi Jurnal (Format Accordion Compact) -->
        <div class="space-y-2">
            @forelse ($headers as $header)
                @php
                    $totalDebit = $header->journalLines->sum(fn ($line) => (float) $line->debit);
                @endphp
                <div x-data="{ expanded: false }" 
                     x-show="matches('{{ strtolower($header->reference_number.' '.$header->description) }}')" 
                     class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xs transition hover:border-slate-300">
                    
                    <!-- Baris Header (Selalu Tampil & Bisa Diklik) -->
                    <div @click="expanded = !expanded" 
                         class="group flex flex-col gap-2.5 px-4 py-3 cursor-pointer select-none transition-colors hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:px-5">
                        
                        <!-- Sisi Kiri: Ikon JU, No. Referensi, Deskripsi, Tanggal -->
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-900 text-xs font-bold text-amber-400 shadow-2xs">
                                JU
                            </span>
                            
                            <div class="flex items-center gap-2 min-w-0 flex-1 flex-wrap sm:flex-nowrap">
                                <button type="button" 
                                        @click.stop="loadTransaction('{{ $header->reference_number }}')" 
                                        class="font-mono text-xs font-bold text-sky-700 hover:text-sky-900 hover:underline shrink-0" 
                                        title="Buka Rincian Transaksi">
                                    {{ $header->reference_number }}
                                </button>
                                
                                <span class="text-slate-300 hidden sm:inline shrink-0">&middot;</span>
                                
                                <span class="text-xs sm:text-sm font-semibold text-slate-900 truncate" title="{{ $header->description }}">
                                    {{ $header->description }}
                                </span>
                                
                                <span class="text-slate-300 hidden md:inline shrink-0">&middot;</span>
                                
                                <span class="text-xs text-slate-500 hidden md:inline shrink-0">
                                    {{ $header->transaction_date->translatedFormat('d M Y') }}
                                </span>
                            </div>
                        </div>

                        <!-- Sisi Kanan: Tanggal (Mobile), Total Nominal, Chevron -->
                        <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0 pt-1.5 sm:pt-0 border-t sm:border-t-0 border-slate-100">
                            <span class="text-xs text-slate-400 md:hidden">
                                {{ $header->transaction_date->translatedFormat('d M Y') }}
                            </span>
                            
                            <div class="flex items-center gap-3">
                                <span class="font-mono text-xs sm:text-sm font-bold text-slate-900">
                                    Rp {{ number_format($totalDebit, 0, ',', '.') }}
                                </span>
                                
                                <div class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-slate-500 transition-colors group-hover:bg-slate-200">
                                    <svg :class="expanded ? 'rotate-180 text-amber-500' : 'text-slate-400'" 
                                         class="h-3.5 w-3.5 transition-transform duration-200" 
                                         fill="none" 
                                         viewBox="0 0 24 24" 
                                         stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bagian Detail (Rincian Akun Jurnal, Tersembunyi by default) -->
                    <div x-show="expanded" 
                         x-collapse 
                         class="border-t border-slate-200 bg-slate-50/60">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-xs sm:text-sm">
                                <thead class="bg-slate-100/80 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th class="px-4 py-2 sm:px-6 text-left">Kode Akun</th>
                                        <th class="px-4 py-2 sm:px-6 text-left">Nama Akun &amp; Keterangan</th>
                                        <th class="px-4 py-2 sm:px-6 text-right">Debit</th>
                                        <th class="px-4 py-2 sm:px-6 text-right">Kredit</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @foreach ($header->journalLines as $line)
                                        @php
                                            $accountName = $line->chartOfAccount?->name ?? 'Akun Tidak Dikenal';
                                            $description = $line->memo ?: $header->description;
                                        @endphp
                                        <tr class="hover:bg-slate-50/70 transition-colors">
                                            <td class="whitespace-nowrap px-4 py-2 sm:px-6 font-mono font-semibold text-sky-700">
                                                {{ $line->chartOfAccount?->code ?? '—' }}
                                            </td>
                                            <td class="px-4 py-2 sm:px-6">
                                                <div class="flex flex-col">
                                                    <span class="font-medium text-slate-800">{{ $accountName }}</span>
                                                    @if ($line->memo && $line->memo !== $header->description)
                                                        <span class="text-xs text-slate-400 truncate max-w-md" title="{{ $description }}">
                                                            {{ $description }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-2 sm:px-6 text-right font-mono {{ (float) $line->debit > 0 ? 'font-semibold text-slate-900' : 'text-slate-300' }}">
                                                {{ (float) $line->debit > 0 ? 'Rp '.number_format((float) $line->debit, 0, ',', '.') : '—' }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-2 sm:px-6 text-right font-mono {{ (float) $line->credit > 0 ? 'font-semibold text-slate-900' : 'text-slate-300' }}">
                                                {{ (float) $line->credit > 0 ? 'Rp '.number_format((float) $line->credit, 0, ',', '.') : '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="border-t border-slate-200 bg-slate-50/90 font-semibold text-slate-700">
                                    <tr>
                                        <td colspan="2" class="px-4 py-2.5 sm:px-6 text-left text-xs uppercase tracking-wider text-slate-500">
                                            Total Ayat Jurnal
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2.5 sm:px-6 text-right font-mono text-xs sm:text-sm font-bold text-slate-950">
                                            Rp {{ number_format($totalDebit, 0, ',', '.') }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2.5 sm:px-6 text-right font-mono text-xs sm:text-sm font-bold text-slate-950">
                                            Rp {{ number_format($totalDebit, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                    <p class="font-semibold text-slate-800">Belum ada transaksi jurnal tercatat</p>
                    <p class="mt-1 text-sm text-slate-500">Lakukan transaksi di Kasir (POS) atau modul operasional untuk melihat pencatatan ayat jurnal otomatis.</p>
                </div>
            @endforelse
        </div>

        <!-- Pagination Links -->
        @if ($headers->hasPages())
            <div class="pt-4">
                {{ $headers->links() }}
            </div>
        @endif
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