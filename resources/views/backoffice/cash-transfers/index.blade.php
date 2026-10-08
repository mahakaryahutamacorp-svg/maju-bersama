<x-app-layout title="Mutasi Kas & Bank" :breadcrumbs="[
    ['label' => 'Uang & Akuntansi'],
    ['label' => 'Kas & Bank'],
]">
    <main class="mx-auto w-full px-6 py-6 lg:px-10 max-w-7xl space-y-6">
        <div class="mb-6">
            <p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Uang &amp; Akuntansi</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Kas &amp; Bank (Treasury)</h2>
        </div>

        <!-- Flash Messages -->
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900 shadow-xs">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span class="text-sm font-semibold">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        <!-- Kartu Metrik KPI -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Mutasi Dana</p>
                    <div class="rounded-xl bg-amber-500/10 p-2 text-amber-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-2xl font-black font-mono text-slate-900">
                    Rp {{ number_format($totalTransferAmount, 0, ',', '.') }}
                </p>
                <p class="mt-1 text-xs text-slate-400">Total akumulasi dana kas berpindah terfilter</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Frekuensi Mutasi</p>
                    <div class="rounded-xl bg-sky-500/10 p-2 text-sky-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-2xl font-black font-mono text-slate-900">
                    {{ number_format($transferCount) }} transaksi
                </p>
                <p class="mt-1 text-xs text-slate-400">Mutasi kas &amp; bank tercatat di sistem</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs sm:col-span-2 lg:col-span-1">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Otomatisasi Jurnal</p>
                    <div class="rounded-xl bg-emerald-500/10 p-2 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-2xl font-black text-emerald-600">
                    100% Balanced
                </p>
                <p class="mt-1 text-xs text-slate-400">Setiap mutasi otomatis membukukan Debit &amp; Kredit</p>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
            <form method="GET" action="{{ route('backoffice.cash-transfers.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Search Keyword -->
                <div>
                    <label for="search" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">Cari Kata Kunci</label>
                    <input
                        type="text"
                        name="search"
                        id="search"
                        value="{{ $search }}"
                        placeholder="No. Referensi / Catatan / Akun..."
                        class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-2xs outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                    >
                </div>

                <!-- Filter Akun Kas / Bank -->
                <div>
                    <label for="account_id" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">Akun Kas / Bank</label>
                    <select
                        name="account_id"
                        id="account_id"
                        class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-2xs outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                    >
                        <option value="">Semua Akun Kas/Bank</option>
                        @foreach ($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ $selectedAccountId == $acc->id ? 'selected' : '' }}>
                                [{{ $acc->code }}] {{ $acc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Tanggal Mulai -->
                <div>
                    <label for="start_date" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">Dari Tanggal</label>
                    <input
                        type="date"
                        name="start_date"
                        id="start_date"
                        value="{{ $startDate }}"
                        class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-2xs outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                    >
                </div>

                <!-- Tanggal Sampai & Tombol Submit -->
                <div>
                    <label for="end_date" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">Sampai Tanggal</label>
                    <div class="flex items-center gap-2">
                        <input
                            type="date"
                            name="end_date"
                            id="end_date"
                            value="{{ $endDate }}"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-2xs outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                        >
                        <button type="submit" class="rounded-xl bg-sky-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-sky-700 transition">
                            Filter
                        </button>
                        @if ($search || $startDate || $endDate || $selectedAccountId)
                            <a href="{{ route('backoffice.cash-transfers.index') }}" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 transition" title="Reset Filter">
                                Reset
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </section>

        <!-- Tabel Riwayat Mutasi Kas -->
        <section class="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
            <div class="border-b border-slate-100 px-6 py-4 flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Riwayat Mutasi Kas &amp; Bank</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Daftar pemindahan dana kas beserta rincian debit/kredit akuntansinya.</p>
                </div>
                <div class="text-xs font-semibold text-slate-500">
                    Menampilkan <span class="font-bold text-slate-900">{{ $transfers->firstItem() ?? 0 }}</span> - <span class="font-bold text-slate-900">{{ $transfers->lastItem() ?? 0 }}</span> dari <span class="font-bold text-slate-900">{{ $transfers->total() }}</span> mutasi
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-900 text-xs font-semibold uppercase tracking-wider text-white">
                        <tr>
                            <th class="px-6 py-3.5">Tanggal</th>
                            <th class="px-6 py-3.5">No. Referensi</th>
                            <th class="px-6 py-3.5">Sumber Dana (Akun Asal)</th>
                            <th class="px-3 py-3.5 text-center">Arah</th>
                            <th class="px-6 py-3.5">Tujuan Dana (Akun Tujuan)</th>
                            <th class="px-6 py-3.5 text-right">Nominal Mutasi</th>
                            <th class="px-6 py-3.5">Catatan / Keterangan</th>
                            <th class="px-6 py-3.5 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 [&>tr:nth-child(even)]:bg-slate-50/60">
                        @forelse ($transfers as $transfer)
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <!-- Tanggal -->
                                <td class="px-6 py-4 whitespace-nowrap text-xs font-medium text-slate-600">
                                    {{ $transfer->transfer_date->format('d M Y') }}
                                </td>

                                <!-- No. Referensi -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a href="{{ route('backoffice.cash-transfers.show', $transfer->id) }}" class="font-mono text-xs font-bold text-sky-700 hover:text-sky-900 hover:underline">
                                        {{ $transfer->reference_number }}
                                    </a>
                                    @if ($isMaster && $transfer->branch)
                                        <div class="mt-0.5 text-[10px] text-slate-400">
                                            {{ $transfer->branch->name }}
                                        </div>
                                    @endif
                                </td>

                                <!-- Akun Asal (Badge Rose/Kredit) -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-bold text-rose-800">
                                        <span class="font-mono text-[11px] text-rose-500">[{{ $transfer->fromAccount?->code }}]</span>
                                        {{ $transfer->fromAccount?->name ?? 'N/A' }}
                                    </span>
                                </td>

                                <!-- Arah Transfer -->
                                <td class="px-3 py-4 whitespace-nowrap text-center text-slate-400">
                                    <svg class="h-4 w-4 inline-block text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                    </svg>
                                </td>

                                <!-- Akun Tujuan (Badge Emerald/Debit) -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-800">
                                        <span class="font-mono text-[11px] text-emerald-600">[{{ $transfer->toAccount?->code }}]</span>
                                        {{ $transfer->toAccount?->name ?? 'N/A' }}
                                    </span>
                                </td>

                                <!-- Nominal Mutasi (Rata Kanan & Tabular Nums) -->
                                <td class="px-6 py-4 whitespace-nowrap text-right tabular-nums font-mono font-black text-slate-900 text-sm">
                                    Rp {{ number_format((float) $transfer->amount, 0, ',', '.') }}
                                </td>

                                <!-- Catatan -->
                                <td class="px-6 py-4 text-xs text-slate-500 max-w-xs truncate" title="{{ $transfer->notes }}">
                                    {{ $transfer->notes ?: '-' }}
                                </td>

                                <!-- Aksi -->
                                <td class="px-6 py-4 whitespace-nowrap text-center text-xs">
                                    <a
                                        href="{{ route('backoffice.cash-transfers.show', $transfer->id) }}"
                                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 hover:text-sky-700 transition"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-slate-400">
                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                        </svg>
                                    </div>
                                    <p class="mt-3 text-sm font-semibold text-slate-600">Belum ada data mutasi kas &amp; bank</p>
                                    <p class="mt-1 text-xs text-slate-400">Gunakan tombol di atas untuk mencatat perpindahan dana antar rekening.</p>
                                    <div class="mt-4">
                                        <a href="{{ route('backoffice.cash-transfers.create') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-sky-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-sky-700 transition">
                                            + Catat Mutasi Pertama
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if ($transfers->hasPages())
                <div class="border-t border-slate-100 px-6 py-4">
                    {{ $transfers->links() }}
                </div>
            @endif
        </section>
    </main>
</x-app-layout>
