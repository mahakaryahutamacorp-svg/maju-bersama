<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penyesuaian Stok / Opname | Maju Bersama ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; }
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
                    <h1 class="text-xl font-bold tracking-tight">Manajemen Persediaan &amp; Opname</h1>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <nav class="hidden items-center gap-4 text-sm text-slate-300 md:flex">
                    <a href="/pos" class="hover:text-white">POS</a>
                    <a href="/inventory" class="hover:text-white">Inventory</a>
                    <a href="/inventory/transfer" class="hover:text-white">Transfer</a>
                    <a href="/inventory/adjustments" class="text-white font-medium">Stock Opname</a>
                    <a href="/reports/accounting/ledger" class="hover:text-white">Akuntansi</a>
                    <a href="/backoffice" class="hover:text-white">Backoffice</a>
                </nav>
                <div class="rounded-full border border-sky-400/30 bg-sky-400/10 px-3 py-1 text-xs font-medium text-sky-200">
                    {{ $currentUser->branch?->name ?? 'Semua Cabang' }} ({{ $currentUser->role }})
                </div>
                <form method="POST" action="/logout" class="hidden sm:block">
                    @csrf
                    <button type="submit" class="text-sm text-slate-300 hover:text-white">Logout</button>
                </form>
            </div>
        </div>
    </header>

    <!-- Sub-Navbar Modul Persediaan -->
    <div class="border-b border-slate-200 bg-white no-print">
        <div class="mx-auto flex max-w-7xl items-center justify-between overflow-x-auto px-6 py-2.5 lg:px-8">
            <div class="flex items-center gap-2 text-sm font-medium">
                <a href="{{ route('inventory.adjustments.index') }}" class="rounded-lg bg-indigo-600 px-3.5 py-2 font-semibold text-white shadow-sm">
                    Riwayat Opname
                </a>
                <a href="{{ route('inventory.adjustments.create') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    + Buat Opname Baru
                </a>
                <a href="/inventory" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Katalog Stok
                </a>
                <a href="/inventory/transfer" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Transfer Stok
                </a>
                <a href="/purchases/goods-receipts" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Penerimaan Barang
                </a>
            </div>
            <div>
                <a href="{{ route('inventory.adjustments.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-500">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Input Hasil Opname
                </a>
            </div>
        </div>
    </div>

    <!-- Konten Utama -->
    <main class="mx-auto max-w-7xl px-6 py-8 lg:px-8">
        <!-- Flash Alert Sukses -->
        @if (session('success'))
            <div class="mb-6 flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-200 text-emerald-800">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </span>
                    <span class="font-medium">{{ session('success') }}</span>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900">&times;</button>
            </div>
        @endif

        <!-- Header Info Halaman -->
        <div class="mb-6 flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-slate-900">Riwayat Penyesuaian Stok (Opname)</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Catatan rekonsiliasi antara stok fisik dan stok sistem beserta jurnal kerugian/keuntungan otomatis.
                </p>
            </div>
        </div>

        <!-- Kartu Metrik Ringkasan -->
        <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Total Dokumen -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Sesi Opname</p>
                <div class="mt-2 flex items-baseline justify-between">
                    <p class="text-2xl font-bold text-slate-900">{{ number_format($totalAdjustments) }}</p>
                    <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">Dokumen</span>
                </div>
            </div>

            <!-- Total Kerugian Stok (Loss) -->
            <div class="rounded-2xl border border-rose-200 bg-rose-50/50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-rose-700">Total Kerugian Stok (Minus)</p>
                <div class="mt-2 flex items-baseline justify-between">
                    <p class="text-2xl font-bold text-rose-800">Rp {{ number_format($totalLoss, 0, ',', '.') }}</p>
                    <span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700">Dr 5120</span>
                </div>
                <p class="mt-1 text-xs text-rose-600">Stok rusak / hilang dari gudang</p>
            </div>

            <!-- Total Keuntungan Stok (Gain) -->
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Total Keuntungan Stok (Plus)</p>
                <div class="mt-2 flex items-baseline justify-between">
                    <p class="text-2xl font-bold text-emerald-800">Rp {{ number_format($totalGain, 0, ',', '.') }}</p>
                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Cr 4120</span>
                </div>
                <p class="mt-1 text-xs text-emerald-600">Stok ekstra / surplus fisik</p>
            </div>

            <!-- Dampak Bersih Nilai Stok -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Dampak Nilai Persediaan</p>
                <div class="mt-2 flex items-baseline justify-between">
                    <p class="text-2xl font-bold {{ $netImpact >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                        {{ $netImpact >= 0 ? '+' : '' }}Rp {{ number_format($netImpact, 0, ',', '.') }}
                    </p>
                    <span class="rounded-full {{ $netImpact >= 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }} px-2 py-0.5 text-xs font-semibold">
                        Net Impact
                    </span>
                </div>
                <p class="mt-1 text-xs text-slate-500">Perubahan bersih nilai aset 1210</p>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm no-print">
            <form method="GET" action="{{ route('inventory.adjustments.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                @if ($isMaster && $branches->count() > 1)
                    <div>
                        <label class="block text-xs font-medium text-slate-700">Cabang</label>
                        <select name="branch_id" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            <option value="">-- Semua Cabang --</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" {{ $selectedBranchId == $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div>
                    <label class="block text-xs font-medium text-slate-700">Tanggal Mulai</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700">Tanggal Selesai</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700">Cari No. Ref / Catatan</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Misal: ADJ-2026..." class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        Filter
                    </button>
                    <a href="{{ route('inventory.adjustments.index') }}" class="rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-200">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Tabel Riwayat Dokumen Opname -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600">
                        <tr>
                            <th class="px-6 py-3.5">Tanggal</th>
                            <th class="px-6 py-3.5">No. Referensi</th>
                            <th class="px-6 py-3.5">Cabang</th>
                            <th class="px-6 py-3.5 text-center">Jml Item</th>
                            <th class="px-6 py-3.5 text-right">Rugi Stok (Minus)</th>
                            <th class="px-6 py-3.5 text-right">Untung Stok (Plus)</th>
                            <th class="px-6 py-3.5 text-center">Status Jurnal</th>
                            <th class="px-6 py-3.5 text-center no-print">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-800">
                        @forelse ($adjustments as $adj)
                            <tr class="transition hover:bg-slate-50/75">
                                <td class="whitespace-nowrap px-6 py-4 font-medium text-slate-900">
                                    {{ $adj->date?->format('d/m/Y') ?? '-' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 font-mono font-semibold text-indigo-700">
                                    <a href="{{ route('inventory.adjustments.show', $adj->id) }}" class="hover:underline">
                                        {{ $adj->reference_number }}
                                    </a>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600">
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">
                                        {{ $adj->branch?->name ?? 'Pusat' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-center">
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">
                                        {{ $adj->items->count() }} produk
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right font-medium text-rose-600">
                                    @if ($adj->total_loss_value > 0)
                                        -Rp {{ number_format($adj->total_loss_value, 0, ',', '.') }}
                                    @else
                                        <span class="text-slate-400">Rp 0</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right font-medium text-emerald-600">
                                    @if ($adj->total_gain_value > 0)
                                        +Rp {{ number_format($adj->total_gain_value, 0, ',', '.') }}
                                    @else
                                        <span class="text-slate-400">Rp 0</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-center">
                                    @if ($adj->journal)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800" title="Jurnal #{{ $adj->journal->reference_number }}">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            Terjurnal
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500">
                                            Tanpa Selisih
                                        </span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-center no-print">
                                    <a href="{{ route('inventory.adjustments.show', $adj->id) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 hover:text-indigo-600">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        Lihat Slip
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-slate-500">
                                    <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                    </svg>
                                    <p class="mt-3 text-base font-semibold text-slate-800">Belum Ada Riwayat Opname Stok</p>
                                    <p class="text-xs text-slate-500">Mulai catat pemeriksaan fisik persediaan barang toko atau gudang Anda.</p>
                                    <div class="mt-4">
                                        <a href="{{ route('inventory.adjustments.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                                            + Buat Dokumen Opname Baru
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($adjustments->hasPages())
                <div class="border-t border-slate-200 px-6 py-4">
                    {{ $adjustments->links() }}
                </div>
            @endif
        </div>
    </main>
</body>
</html>
