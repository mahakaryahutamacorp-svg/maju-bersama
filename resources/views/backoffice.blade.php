<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backoffice | Maju Bersama ERP</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body x-data="backoffice()" class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <div class="min-h-screen lg:flex">
        @include('layouts.sidebar')


        <main class="min-w-0 flex-1">
            <header class="border-b border-slate-200 bg-white">
                <div class="flex flex-col justify-between gap-4 px-6 py-5 sm:flex-row sm:items-center lg:px-10">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-amber-600">Operational control center</p>
                        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 truncate" x-text="pageTitle"></h1>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center gap-2 sm:flex-nowrap sm:gap-3">
                        @if ($isMaster)
                            <a href="{{ route('preview') }}" class="hidden sm:inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap text-xs font-semibold text-amber-600 bg-amber-50 border border-amber-200 px-2.5 py-1.5 rounded-lg hover:bg-amber-100 transition">
                                <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <span>Preview Data Sistem</span>
                            </a>
                        @endif
                        {{-- Tidak lagi terpotong: shrink-0 + whitespace-nowrap. Di layar kecil tampil sebagai ikon dengan tooltip. --}}
                        <a href="/" target="_blank" rel="noopener noreferrer"
                           id="btn-lihat-website"
                           title="Lihat Website (tab baru)"
                           aria-label="Lihat Website (buka di tab baru)"
                           class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg border border-transparent px-2.5 py-2 text-sm font-medium text-slate-500 hover:border-slate-200 hover:bg-slate-50 hover:text-slate-900 transition">
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                            <span class="hidden md:inline">Lihat Website</span>
                        </a>
                        <form method="POST" action="/logout" class="shrink-0">
                            @csrf
                            <button type="submit" class="whitespace-nowrap rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Logout</button>
                        </form>
                    </div>
                </div>
            </header>

            <div class="mx-auto max-w-7xl space-y-8 px-6 py-8 lg:px-10">
                @if (session('success'))
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900 shadow-sm flex items-start gap-3">
                        <svg class="h-5 w-5 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <div>
                            <p class="text-sm font-semibold">Operasi Berhasil</p>
                            <p class="text-xs text-emerald-700 mt-0.5">{{ session('success') }}</p>
                        </div>
                    </div>
                @endif

                @if (session('error'))
                    <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-rose-900 shadow-sm flex items-start gap-3">
                        <svg class="h-5 w-5 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <p class="text-sm font-semibold">Terjadi Kendala</p>
                            <p class="text-xs text-rose-700 mt-0.5">{{ session('error') }}</p>
                        </div>
                    </div>
                @endif

                <section x-show="active === 'overview'" x-transition>
                    {{-- RBAC: Widget backup (dan scan storage-nya) HANYA dirender untuk role master. --}}
                    @can('manage-system')
                    @php
                        $backupDisk = \Illuminate\Support\Facades\Storage::disk(config('backup.backup.destination.disks.0', 'local'));
                        $backupName = config('backup.backup.name', config('app.name', 'Laravel'));
                        $latestBackupFile = null;
                        $latestBackupTime = null;
                        try {
                            $backupFiles = $backupDisk->allFiles($backupName);
                            foreach ($backupFiles as $bf) {
                                if (str_ends_with(strtolower($bf), '.zip')) {
                                    $mtime = $backupDisk->lastModified($bf);
                                    if (! $latestBackupTime || $mtime > $latestBackupTime) {
                                        $latestBackupTime = $mtime;
                                        $latestBackupFile = basename($bf);
                                    }
                                }
                            }
                        } catch (\Throwable $e) {}
                    @endphp

                    <!-- Card Sistem & Keamanan (Cadangan Database) -->
                    <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-start gap-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-600 border border-sky-100 shadow-xs">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold uppercase tracking-wider text-amber-600">Sistem &amp; Keamanan</span>
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">Backup Otomatis Siap</span>
                                    </div>
                                    <h2 class="mt-1 text-lg font-bold text-slate-950">Cadangan Database Sistem (Manual Backup)</h2>
                                    <p class="mt-1 text-xs text-slate-500 max-w-2xl">
                                        Amankan seluruh data transaksi, jurnal akuntansi, dan master data toko dengan membuat snapshot database (.zip) yang dapat diunduh langsung.
                                    </p>
                                    @if ($latestBackupFile)
                                        <p class="mt-2 text-xs font-medium text-slate-600 flex items-center gap-1.5">
                                            <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span>
                                            <span>Cadangan terakhir: <span class="font-mono text-slate-800 font-semibold">{{ $latestBackupFile }}</span> ({{ date('d M Y H:i', $latestBackupTime) }} WIB)</span>
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <!-- Tombol Aksi Backup -->
                            <div class="flex flex-wrap items-center gap-3 sm:shrink-0" x-data="{ loading: false }">
                                <form method="POST" action="/backoffice/backup/generate" @submit="loading = true">
                                    @csrf
                                    <button type="submit" 
                                            :disabled="loading" 
                                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-700 active:bg-sky-800 disabled:opacity-60 disabled:cursor-not-allowed transition">
                                        <svg x-show="!loading" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                        </svg>
                                        <svg x-show="loading" x-cloak class="h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                        </svg>
                                        <span x-text="loading ? 'Sedang Mem-backup...' : 'Buat Backup Baru'">Buat Backup Baru</span>
                                    </button>
                                </form>

                                <a href="/backoffice/backup/download" 
                                   class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 active:bg-emerald-800 transition">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    <span>Unduh Backup Terakhir</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    @endcan

                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <template x-for="stat in stats" :key="stat.label">
                            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <p class="text-sm text-slate-500" x-text="stat.label"></p>
                                <p class="mt-3 text-3xl font-bold tracking-tight text-slate-950" x-text="stat.value"></p>
                                <p class="mt-2 text-xs text-slate-400" x-text="stat.caption"></p>
                            </div>
                        </template>
                    </div>

                    @if ($isMaster)
                        <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                                <div>
                                    <p class="text-sm font-medium text-amber-600">Master monitoring</p>
                                    <h2 class="mt-1 text-xl font-bold text-slate-950">Inventory seluruh branch</h2>
                                </div>
                                <span class="text-sm text-slate-500">{{ $branchSummaries->count() }} branches terpantau</span>
                            </div>
                            <div class="mt-5 overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Branch</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Parent</th>
                                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Products</th>
                                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Stock</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach ($branchSummaries as $branch)
                                            <tr class="hover:bg-slate-50">
                                                <td class="px-4 py-3 text-sm font-semibold text-slate-900">{{ $branch->name }}<span class="ml-2 font-mono text-xs text-slate-400">{{ $branch->code }}</span></td>
                                                <td class="px-4 py-3 text-sm text-slate-500">{{ $branch->parent?->name ?? 'Root pusat' }}</td>
                                                <td class="px-4 py-3 text-right text-sm text-slate-700">{{ $branch->products_count }}</td>
                                                <td class="px-4 py-3 text-right text-sm font-semibold text-emerald-700">{{ number_format($branch->products_sum_stock ?? 0) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    @endif

                    <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-end justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-amber-600">{{ $isMaster ? 'Cross-branch activity' : 'Aktivitas transaksi' }}</p>
                                <h2 class="mt-1 text-xl font-bold text-slate-950">{{ $isMaster ? 'Transaksi terbaru seluruh branch' : 'Transaksi terbaru branch ini' }}</h2>
                            </div>
                            @can('access-enterprise')
                                <a href="/reports/journal" class="text-sm font-semibold text-sky-700 hover:text-sky-800">Buka ledger</a>
                            @endcan
                        </div>
                            <div class="mt-5 overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Reference</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Branch</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Description</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">User</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @forelse ($recentJournals as $journal)
                                            <tr class="hover:bg-slate-50">
                                                <td class="px-4 py-3 font-mono text-xs font-semibold">
                                                    <button type="button" 
                                                            @click="loadTransaction('{{ $journal->reference_number }}')" 
                                                            class="group inline-flex items-center gap-1.5 font-semibold text-sky-600 hover:text-sky-800 hover:underline focus:outline-none transition text-left" 
                                                            title="Klik untuk melihat detail transaksi">
                                                        <span>{{ $journal->reference_number }}</span>
                                                        <svg class="h-3.5 w-3.5 text-sky-400 group-hover:text-sky-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                        </svg>
                                                    </button>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-slate-700">{{ $journal->branch?->name }}</td>
                                                <td class="px-4 py-3 text-sm text-slate-700">{{ $journal->description }}</td>
                                                <td class="px-4 py-3 text-sm text-slate-500">{{ $journal->user?->name }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">Belum ada transaksi antar branch.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </section>

                    <div class="mt-8 grid gap-6 xl:grid-cols-[1.4fr_0.6fr]">
                        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-medium text-amber-600">Daily workflow</p>
                                    <h2 class="mt-1 text-xl font-bold text-slate-950">Quick actions</h2>
                                </div>
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Ready</span>
                            </div>
                            <div class="mt-6 grid gap-3 {{ auth()->user()?->can('access-inventory') ? 'sm:grid-cols-3' : 'sm:grid-cols-2' }}">
                                <a href="/pos" class="rounded-xl border border-slate-200 p-4 transition hover:border-sky-300 hover:bg-sky-50">
                                    <p class="font-semibold text-slate-900">New sale</p>
                                    <p class="mt-1 text-xs text-slate-500">Open POS checkout</p>
                                </a>
                                @can('access-inventory')
                                    <a href="/inventory" class="rounded-xl border border-slate-200 p-4 transition hover:border-sky-300 hover:bg-sky-50">
                                        <p class="font-semibold text-slate-900">Check stock</p>
                                        <p class="mt-1 text-xs text-slate-500">Review branch inventory</p>
                                    </a>
                                @endcan
                                {{-- Card ke-3 dinamis per role: master → Buku Besar; branch_admin/cashier → Shift Kasir (POS). --}}
                                @can('access-enterprise')
                                    <a href="/reports/journal" id="quick-action-ledger" class="rounded-xl border border-slate-200 p-4 transition hover:border-sky-300 hover:bg-sky-50">
                                        <p class="font-semibold text-slate-900">Review ledger</p>
                                        <p class="mt-1 text-xs text-slate-500">Verify double-entry journals (Buku Besar)</p>
                                    </a>
                                @else
                                    <a href="{{ route('pos') }}" id="quick-action-shift" class="rounded-xl border border-slate-200 p-4 transition hover:border-sky-300 hover:bg-sky-50">
                                        <p class="font-semibold text-slate-900">Shift Kasir</p>
                                        <p class="mt-1 text-xs text-slate-500">Buka / tutup shift &amp; rekap kas laci</p>
                                    </a>
                                @endcan
                            </div>
                        </section>
                        <section class="rounded-2xl bg-slate-900 p-6 text-white shadow-sm">
                            <p class="text-sm font-medium text-sky-300">Branch access</p>
                            <h2 class="mt-2 text-xl font-bold">{{ $currentUser->branch->name }}</h2>
                            <p class="mt-3 text-sm leading-6 text-slate-300">Semua transaksi operasional dan laporan pada workspace ini mengikuti branch yang sedang aktif.</p>
                        </section>
                    </div>
                </section>

                <section x-show="active !== 'overview'" x-transition class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                        <div>
                            <p class="text-sm font-medium text-amber-600" x-text="selectedModule.group"></p>
                            <h2 class="mt-1 text-2xl font-bold text-slate-950" x-text="selectedModule.name"></h2>
                            <p class="mt-3 max-w-2xl text-slate-500" x-text="selectedModule.description"></p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-bold" :class="selectedModule.status === 'ready' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'" x-text="selectedModule.status === 'ready' ? 'Active module' : 'Planned module'"></span>
                    </div>
                    <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <template x-for="action in selectedModule.actions" :key="action">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4">
                                <p class="text-sm font-semibold text-slate-800" x-text="action"></p>
                                <p class="mt-1 text-xs text-slate-500">Workflow siap dikembangkan dengan kontrol branch aktif.</p>
                            </div>
                        </template>
                    </div>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a x-show="selectedModule.id === 'inventory'" href="/inventory" class="rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-sky-700">Open inventory</a>
                        <a x-show="selectedModule.id === 'sales'" href="/pos" class="rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-sky-700">Open POS</a>
                        @can('access-enterprise')
                            <a x-show="selectedModule.id === 'reports'" href="/reports/journal" class="rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-sky-700">Open ledger</a>
                        @endcan
                    </div>
                </section>
            </div>
        </main>
    </div>

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
        function backoffice() {
            const modules = [
                { id: 'purchases', group: 'Purchases', name: 'Pembelian', status: 'planned', description: 'Kelola hutang usaha, pembayaran hutang, dan retur pembelian dari satu alur kerja.', actions: ['Hutang usaha', 'Pembayaran hutang usaha', 'Retur pembelian'] },
                { id: 'sales', group: 'Sales', name: 'Penjualan', status: 'ready', description: 'Jalankan penjualan dari POS dan siapkan alur piutang serta retur penjualan.', actions: ['Piutang usaha', 'Pembayaran piutang usaha', 'Retur penjualan'] },
                { id: 'cash', group: 'Treasury', name: 'Kas dan bank', status: 'planned', description: 'Pantau arus kas masuk, kas keluar, kas besar, kas kecil, dan kas bank.', actions: ['Kas masuk', 'Kas keluar', 'Kas besar', 'Kas kecil', 'Kas bank'] },
                { id: 'assets', group: 'Assets', name: 'Harta tetap', status: 'ready', description: 'Catat total aset, umur manfaat, dan akumulasi penyusutan secara terkontrol.', actions: ['Daftar aset tetap', 'Penyusutan periodik', 'Akumulasi penyusutan'] },
                { id: 'inventory', group: 'Inventory', name: 'Persediaan', status: 'ready', description: 'Kelola stok branch dan siapkan klasifikasi produk berdasarkan kategori serta status pajak.', actions: ['Pupuk', 'Insektisida', 'Pestisida', 'Fungisida', 'Pakan', 'Penyesuaian persediaan'] },
                { id: 'warehouses', group: 'Inventory', name: 'Multi gudang', status: 'planned', description: 'Pindahkan persediaan antar gudang dengan jejak transfer yang jelas.', actions: ['Daftar gudang', 'Transfer persediaan antar gudang', 'Riwayat transfer'] },
                { id: 'pricing', group: 'Commercial', name: 'Multi price', status: 'planned', description: 'Sediakan harga berbeda untuk retailer dan petani dalam satu katalog produk.', actions: ['Harga retailer', 'Harga petani', 'Riwayat perubahan harga'] },
                { id: 'reports', group: 'Reports', name: 'Laporan', status: 'ready', description: 'Akses laporan persediaan, laba rugi, neraca, hutang, piutang, dan kas keluar.', actions: ['Persediaan per gudang', 'Laba rugi', 'Neraca', 'Hutang', 'Piutang', 'Kas keluar'] },
            ];

            return {
                modules,
                active: 'overview',
                mobileMenu: true,
                showModal: false,
                transactionHtml: '',
                isLoading: false,
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
                },
                stats: [
                    { label: '{{ $isMaster ? 'Branch terpantau' : 'Produk aktif' }}', value: '{{ $isMaster ? $stats['branches'] : $stats['products'] }}', caption: '{{ $isMaster ? 'Pusat dan cabang' : 'Pada branch aktif' }}' },
                    { label: 'Total stok', value: '{{ number_format($stats['stock']) }}', caption: '{{ $isMaster ? 'Seluruh branch' : 'Unit tersedia' }}' },
                    { label: 'Jurnal', value: '{{ $stats['journals'] }}', caption: '{{ $isMaster ? 'Lintas branch' : 'Transaksi tercatat' }}' },
                    { label: 'Penjualan POS', value: '{{ $stats['sales'] }}', caption: 'Transaksi otomatis' },
                ],
                get selectedModule() {
                    return this.modules.find((module) => module.id === this.active) || this.modules[0];
                },
                get pageTitle() {
                    return this.active === 'overview' ? 'Overview' : this.selectedModule.name;
                },
                select(module) {
                    this.active = module;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },
            };
        }
    </script>
</body>
</html>