<x-app-layout title="Buku Besar (General Ledger)" :breadcrumbs="[
    ['label' => 'Uang & Akuntansi'],
    ['label' => 'Buku Besar'],
]">
    <x-slot:head>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        [x-cloak] { display: none !important; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; }
            .print-break { page-break-after: always; }
            [x-cloak], [x-show] { display: block !important; height: auto !important; }
        }
    </style>
    </x-slot:head>

    @php
        // Helper badge warna untuk visual grouping transaksi antar cabang
        if (!function_exists('getBranchBadgeStyle')) {
            function getBranchBadgeStyle(?string $branchName): string {
                $name = strtolower(trim((string) $branchName));
                if (str_contains($name, 'pusat')) {
                    return 'bg-blue-100 text-blue-800 border-blue-300';
                } elseif (str_contains($name, '1') || str_contains($name, 'arofah')) {
                    return 'bg-emerald-100 text-emerald-800 border-emerald-300';
                } elseif (str_contains($name, '2')) {
                    return 'bg-amber-100 text-amber-800 border-amber-300';
                } elseif (str_contains($name, '3')) {
                    return 'bg-purple-100 text-purple-800 border-purple-300';
                } elseif (str_contains($name, '4')) {
                    return 'bg-rose-100 text-rose-800 border-rose-300';
                } elseif (str_contains($name, '5')) {
                    return 'bg-teal-100 text-teal-800 border-teal-300';
                } else {
                    $palette = [
                        'bg-sky-100 text-sky-800 border-sky-300',
                        'bg-indigo-100 text-indigo-800 border-indigo-300',
                        'bg-cyan-100 text-cyan-800 border-cyan-300',
                        'bg-orange-100 text-orange-800 border-orange-300',
                        'bg-violet-100 text-violet-800 border-violet-300',
                    ];
                    $index = abs(crc32($name)) % count($palette);
                    return $palette[$index];
                }
            }
        }
    @endphp

    <main class="mx-auto w-full px-6 py-6 lg:px-10 max-w-7xl space-y-6">
        @include('reports.accounting.partials.tabs')

        <!-- 2. Header Laporan Dinamis (Bereaksi Terhadap Filter Toko / Konsolidasi) -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-semibold uppercase tracking-wider text-amber-600">Laporan Keuangan</span>
                    <span class="text-xs text-slate-400">&bull;</span>
                    @if ($branchId && $activeBranch)
                        <span class="inline-flex items-center gap-1.5 rounded-md border border-sky-200 bg-sky-50 px-2.5 py-0.5 text-xs font-semibold text-sky-800 shadow-xs">
                            <svg class="h-3.5 w-3.5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            Filter Toko: <strong class="uppercase">{{ $activeBranchName }}</strong>
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-md border border-purple-200 bg-purple-50 px-2.5 py-0.5 text-xs font-semibold text-purple-800 shadow-xs">
                            <svg class="h-3.5 w-3.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Konsolidasi Seluruh Cabang
                        </span>
                    @endif
                </div>

                <!-- Judul Utama Dinamis -->
                <h2 class="mt-1.5 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">
                    @if ($branchId && $activeBranch)
                        Buku Besar &mdash; <span class="text-sky-700 uppercase tracking-tight">{{ $activeBranchName }}</span>
                    @else
                        Buku Besar &mdash; <span class="text-slate-800">Konsolidasi Seluruh Cabang</span>
                    @endif
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Cakupan: <span class="font-semibold text-slate-800">{{ $activeBranchName }}</span>
                    @if ($startDate || $endDate)
                        &middot; Periode: <span class="font-semibold text-slate-800">{{ $startDate ? \Carbon\Carbon::parse($startDate)->format('d M Y') : 'Awal' }}</span> s/d <span class="font-semibold text-slate-800">{{ $endDate ? \Carbon\Carbon::parse($endDate)->format('d M Y') : 'Hari ini' }}</span>
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-3">
                <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-2.5 text-right shadow-xs">
                    <p class="text-xs font-medium uppercase tracking-wider text-sky-700">Akun Aktif</p>
                    <p class="text-lg font-bold text-sky-900">{{ count($report['accounts']) }} Akun</p>
                </div>
            </div>
        </div>

        <!-- 4. Seksi Filter (Pencarian Dinamis) -->
        <section class="rounded-lg border border-gray-200 bg-gray-50/50 p-5 shadow-sm no-print">
            <form method="GET" action="/reports/accounting/ledger" class="grid gap-4 sm:grid-cols-2 {{ $isMaster ? 'lg:grid-cols-5' : 'lg:grid-cols-4' }} items-end">
                <!-- Dropdown Cabang Dinamis -->
                @if ($isMaster)
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">Cabang / Toko</label>
                        <select name="branch_id" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm outline-none transition focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                            <option value="">Semua Cabang (Konsolidasi)</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" {{ (string) $branchId === (string) $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }} {{ $branch->code ? '('.$branch->code.')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">Cabang / Toko</label>
                        <input type="text" readonly value="{{ $activeBranchName }}" class="w-full rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-600 shadow-sm outline-none cursor-not-allowed">
                        <input type="hidden" name="branch_id" value="{{ $branchId }}">
                    </div>
                @endif

                <!-- Pilihan Akun -->
                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">Akun Perkiraan</label>
                    <select name="account_id" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm outline-none transition focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
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
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">Dari Tanggal</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm outline-none transition focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                </div>

                <!-- Sampai Tanggal -->
                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">Sampai Tanggal</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm outline-none transition focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                </div>

                <!-- Tombol Submit & Reset -->
                <div class="flex items-center gap-2">
                    <button type="submit" class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Terapkan
                    </button>
                    <a href="/reports/accounting/ledger" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-200">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        @php
            $autoExpand = !empty($accountId) || request()->filled('account_id');
        @endphp

        <!-- Bar Kontrol Tampilan Daftar Akun (Accordion Controls) -->
        <div class="flex items-center justify-between gap-4 pb-1">
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Daftar Akun Buku Besar</span>
                <span class="text-xs text-slate-400">&bull;</span>
                <span class="text-xs text-slate-500 hidden sm:inline">Klik baris akun untuk melihat rincian mutasi transaksi</span>
            </div>
            @if (count($report['accounts']) > 0)
                <div class="flex items-center gap-2 no-print">
                    <button 
                        type="button" 
                        @click="$dispatch('expand-all')" 
                        class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600 shadow-2xs hover:bg-slate-50 hover:text-slate-900 transition"
                    >
                        <svg class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 13l-7 7-7-7m14-8l-7 7-7-7"/></svg>
                        Buka Semua
                    </button>
                    <button 
                        type="button" 
                        @click="$dispatch('collapse-all')" 
                        class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600 shadow-2xs hover:bg-slate-50 hover:text-slate-900 transition"
                    >
                        <svg class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 11l7-7 7 7M5 19l7-7 7 7"/></svg>
                        Tutup Semua
                    </button>
                </div>
            @endif
        </div>

        <!-- Daftar Akun Buku Besar (Accordion) -->
        <div class="space-y-3">
            @forelse ($report['accounts'] as $item)
                @php
                    $acc = $item['account'];
                @endphp
                <section 
                    x-data="{ expanded: {{ $autoExpand ? 'true' : 'false' }} }" 
                    @expand-all.window="expanded = true"
                    @collapse-all.window="expanded = false"
                    class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xs transition-all duration-150"
                >
                    <!-- Header Akun (Accordion Trigger Selalu Tampil) -->
                    <div 
                        @click="expanded = !expanded"
                        class="group flex flex-col gap-3 px-5 py-3 cursor-pointer select-none transition-colors duration-150 xl:flex-row xl:items-center xl:justify-between"
                        :class="expanded ? 'border-b border-gray-200 bg-slate-50/80 hover:bg-slate-100/90' : 'bg-white hover:bg-slate-50/80'"
                    >
                        <!-- Sisi Kiri (Ikon Chevron + Badge Kode + Nama + Status) -->
                        <div class="flex items-center gap-3">
                            <!-- Ikon Chevron Indikator -->
                            <div 
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white shadow-2xs transition-transform duration-200"
                                :class="expanded ? 'rotate-180 bg-sky-50 text-sky-600 border-sky-200' : 'text-slate-400 group-hover:text-slate-600 group-hover:border-slate-300'"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </div>

                            <span class="inline-flex items-center rounded-md bg-slate-800 px-2.5 py-1 font-mono text-xs sm:text-sm font-semibold text-white shadow-xs shrink-0">
                                {{ $acc['code'] }}
                            </span>

                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 class="text-base sm:text-lg font-bold text-gray-900 leading-snug group-hover:text-sky-700 transition-colors">{{ $acc['name'] }}</h3>
                                    <span class="inline-block rounded border border-gray-200 bg-white px-2 py-0.5 text-[10px] font-medium text-gray-600 uppercase">
                                        Saldo Normal: {{ $acc['normal_balance'] }}
                                    </span>
                                    <span class="text-xs text-gray-400">&bull;</span>
                                    <span class="text-xs text-gray-500 uppercase">
                                        {{ $acc['type'] }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                                        {{ count($item['lines']) }} transaksi
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Sisi Kanan (Mini Stats 4 Kolom: Saldo Awal, Total Debit, Total Kredit, Saldo Akhir) -->
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-2.5">
                            <!-- Saldo Awal -->
                            <div class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-right shadow-2xs">
                                <span class="block text-[10px] font-medium uppercase tracking-wider text-gray-500">Saldo Awal</span>
                                <span class="block text-xs font-semibold tabular-nums {{ $item['beginning_balance'] < 0 ? 'text-red-600' : 'text-gray-900' }}">
                                    @if ($item['beginning_balance'] < 0)
                                        (Rp {{ number_format(abs($item['beginning_balance']), 0, ',', '.') }})
                                    @else
                                        Rp {{ number_format($item['beginning_balance'], 0, ',', '.') }}
                                    @endif
                                </span>
                            </div>

                            <!-- Total Debit -->
                            <div class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-right shadow-2xs">
                                <span class="block text-[10px] font-medium uppercase tracking-wider text-gray-500">Total Debit</span>
                                <span class="block text-xs font-semibold tabular-nums text-sky-700">
                                    Rp {{ number_format($item['total_debit'], 0, ',', '.') }}
                                </span>
                            </div>

                            <!-- Total Kredit -->
                            <div class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-right shadow-2xs">
                                <span class="block text-[10px] font-medium uppercase tracking-wider text-gray-500">Total Kredit</span>
                                <span class="block text-xs font-semibold tabular-nums text-amber-700">
                                    Rp {{ number_format($item['total_credit'], 0, ',', '.') }}
                                </span>
                            </div>

                            <!-- Saldo Akhir -->
                            <div class="rounded-lg border border-blue-200 bg-blue-50/70 px-3 py-1.5 text-right shadow-2xs">
                                <span class="block text-[10px] font-semibold uppercase tracking-wider text-blue-600">Saldo Akhir</span>
                                <span class="block text-xs font-bold tabular-nums {{ $item['ending_balance'] < 0 ? 'text-red-600' : 'text-blue-700' }}">
                                    @if ($item['ending_balance'] < 0)
                                        (Rp {{ number_format(abs($item['ending_balance']), 0, ',', '.') }})
                                    @else
                                        Rp {{ number_format($item['ending_balance'], 0, ',', '.') }}
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Transaksi & Total Mutasi (Disembunyikan by default, tampil dengan x-collapse) -->
                    <div x-show="expanded" x-collapse x-cloak>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                                <thead class="bg-slate-900 text-xs font-semibold uppercase tracking-wider text-white">
                                    <tr>
                                        <th class="whitespace-nowrap px-4 py-2">Tanggal</th>
                                        <th class="whitespace-nowrap px-4 py-2">No. Referensi</th>
                                        <th class="px-4 py-2">Keterangan</th>
                                        <th class="px-4 py-2">Memo / Catatan</th>
                                        <th class="whitespace-nowrap px-4 py-2">Cabang / Toko</th>
                                        <th class="whitespace-nowrap px-4 py-2 text-right">Debit</th>
                                        <th class="whitespace-nowrap px-4 py-2 text-right">Kredit</th>
                                        <th class="whitespace-nowrap px-4 py-2 text-right">Saldo Berjalan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white [&>tr:nth-child(even)]:bg-slate-50/60">
                                    <!-- Baris Saldo Awal -->
                                    <tr class="bg-amber-50/30 border-b border-gray-100 text-xs font-medium text-gray-600">
                                        <td class="whitespace-nowrap px-4 py-1.5 font-mono tabular-nums">{{ $startDate ? \Carbon\Carbon::parse($startDate)->format('d/m/Y') : '-' }}</td>
                                        <td class="px-4 py-1.5 font-mono text-gray-400">—</td>
                                        <td colspan="3" class="px-4 py-1.5 font-semibold text-gray-800">
                                            SALDO AWAL {{ $startDate ? '(SEBELUM ' . \Carbon\Carbon::parse($startDate)->format('d M Y') . ')' : '' }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-1.5 text-right font-mono tabular-nums text-gray-400">—</td>
                                        <td class="whitespace-nowrap px-4 py-1.5 text-right font-mono tabular-nums text-gray-400">—</td>
                                        <td class="whitespace-nowrap px-4 py-1.5 text-right font-mono text-xs font-bold tabular-nums {{ $item['beginning_balance'] < 0 ? 'text-red-600' : 'text-gray-900' }}">
                                            @if ($item['beginning_balance'] < 0)
                                                (Rp {{ number_format(abs($item['beginning_balance']), 0, ',', '.') }})
                                            @else
                                                Rp {{ number_format($item['beginning_balance'], 0, ',', '.') }}
                                            @endif
                                        </td>
                                    </tr>

                                    @forelse ($item['lines'] as $line)
                                        <tr class="border-b border-gray-100 transition hover:bg-slate-50/80 text-xs">
                                            <td class="whitespace-nowrap px-4 py-1.5 font-mono text-gray-600 tabular-nums">
                                                {{ \Carbon\Carbon::parse($line['date'])->format('d/m/Y') }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-1.5 font-mono font-semibold text-sky-700">
                                                {{ $line['reference_number'] }}
                                            </td>
                                            <td class="px-4 py-1.5 text-gray-800 font-medium">
                                                {{ $line['description'] }}
                                            </td>
                                            <td class="px-4 py-1.5 text-gray-500">
                                                {{ $line['memo'] ?? '—' }}
                                            </td>
                                            <!-- Badge Cabang dengan identitas visual unik -->
                                            <td class="whitespace-nowrap px-4 py-1.5">
                                                <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ getBranchBadgeStyle($line['branch_name']) }}">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>
                                                    {{ $line['branch_name'] }}
                                                </span>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-1.5 text-right font-mono tabular-nums {{ $line['debit'] > 0 ? 'font-medium text-gray-900' : 'text-gray-300' }}">
                                                {{ $line['debit'] > 0 ? 'Rp ' . number_format($line['debit'], 0, ',', '.') : '—' }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-1.5 text-right font-mono tabular-nums {{ $line['credit'] > 0 ? 'font-medium text-gray-900' : 'text-gray-300' }}">
                                                {{ $line['credit'] > 0 ? 'Rp ' . number_format($line['credit'], 0, ',', '.') : '—' }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-1.5 text-right font-mono tabular-nums font-semibold {{ $line['balance'] < 0 ? 'text-red-600 font-bold' : 'text-gray-900' }}">
                                                @if ($line['balance'] < 0)
                                                    (Rp {{ number_format(abs($line['balance']), 0, ',', '.') }})
                                                @else
                                                    Rp {{ number_format($line['balance'], 0, ',', '.') }}
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="px-4 py-4 text-center text-xs text-gray-400 italic">
                                                Tidak ada mutasi transaksi pada periode yang dipilih.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                <!-- Footer Ringkasan Akun -->
                                <tfoot class="border-t border-gray-300 bg-slate-50 text-xs font-bold text-gray-900">
                                    <tr>
                                        <td colspan="5" class="px-4 py-2 text-right uppercase tracking-wider text-gray-700">
                                            TOTAL MUTASI &amp; SALDO AKHIR {{ $acc['name'] }}:
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2 text-right font-mono tabular-nums text-sky-800">
                                            Rp {{ number_format($item['total_debit'], 0, ',', '.') }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2 text-right font-mono tabular-nums text-amber-800">
                                            Rp {{ number_format($item['total_credit'], 0, ',', '.') }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2 text-right font-mono tabular-nums bg-gray-100 font-extrabold {{ $item['ending_balance'] < 0 ? 'text-red-600' : 'text-gray-900' }}">
                                            @if ($item['ending_balance'] < 0)
                                                (Rp {{ number_format(abs($item['ending_balance']), 0, ',', '.') }})
                                            @else
                                                Rp {{ number_format($item['ending_balance'], 0, ',', '.') }}
                                            @endif
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </section>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center shadow-sm">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <h3 class="mt-4 text-base font-semibold text-gray-900">Tidak ada data buku besar</h3>
                    <p class="mt-1 text-sm text-gray-500">Belum ada transaksi atau saldo pada rentang tanggal dan cabang yang dipilih.</p>
                </div>
            @endforelse
        </div>

        <!-- Grand Total Summary -->
        @if (count($report['accounts']) > 0)
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Grand Total Mutasi Periode</p>
                        <p class="text-sm text-gray-600">Akumulasi seluruh debit dan kredit akun aktif pada periode ini</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-4 text-sm font-bold">
                        <div class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-2">
                            <span class="text-gray-500 font-medium text-xs block uppercase">Grand Total Debit</span>
                            <span class="font-mono text-sky-700 text-base font-bold tabular-nums">Rp {{ number_format($report['grand_total_debit'], 0, ',', '.') }}</span>
                        </div>
                        <div class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-2">
                            <span class="text-gray-500 font-medium text-xs block uppercase">Grand Total Kredit</span>
                            <span class="font-mono text-amber-700 text-base font-bold tabular-nums">Rp {{ number_format($report['grand_total_credit'], 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </main>
</x-app-layout>
