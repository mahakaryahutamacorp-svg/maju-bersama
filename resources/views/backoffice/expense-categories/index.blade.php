<x-app-layout title="Master Kategori Biaya Operasional" :breadcrumbs="[
    ['label' => 'Pengaturan'],
    ['label' => 'Kategori Biaya'],
]">
    <main class="mx-auto w-full px-6 py-6 lg:px-10 max-w-7xl space-y-6">
        <!-- Flash Messages -->
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900 shadow-xs">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span class="text-sm font-semibold">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        <!-- Header Halaman & Pencarian -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <p class="font-semibold uppercase text-sm tracking-wider text-amber-600">Master Data Akuntansi</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">Kategori Biaya Operasional</h2>
                <p class="text-xs text-slate-500 mt-0.5">Pemetaan jenis pengeluaran toko ke akun bagan akun (COA) beban.</p>
            </div>
            <form method="GET" action="{{ route('backoffice.expense-categories.index') }}" class="flex items-center gap-2">
                <div class="relative w-64 sm:w-80">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Cari kategori atau kode COA..."
                        class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-9 pr-3 text-xs text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 shadow-xs"
                    >
                    <svg class="absolute left-3 top-2.5 h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <button type="submit" class="rounded-xl bg-sky-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-sky-700">
                    Cari
                </button>
                @if ($search)
                    <a href="{{ route('backoffice.expense-categories.index') }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100">
                        Reset
                    </a>
                @endif
            </form>
        </div>

        <!-- Tabel Kategori Biaya -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-xs">
                    <thead class="bg-slate-900 text-xs font-semibold uppercase tracking-wider text-white">
                        <tr>
                            <th class="py-3.5 pl-6 pr-3">Nama Kategori</th>
                            <th class="px-4 py-3.5">Akun Beban (Chart of Account)</th>
                            <th class="px-4 py-3.5 text-center">Status</th>
                            <th class="px-4 py-3.5 text-center">Total Transaksi</th>
                            <th class="py-3.5 pl-3 pr-6 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700 [&>tr:nth-child(even)]:bg-slate-50/60">
                        @forelse ($categories as $cat)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-4 pl-6 pr-3 font-bold text-slate-900">
                                    <div class="flex items-center gap-2.5">
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-600 border border-rose-100">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                        </div>
                                        <span>{{ $cat->name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    @if ($cat->chartOfAccount)
                                        <div class="flex items-center gap-2">
                                            <span class="rounded bg-indigo-50 border border-indigo-200 px-2 py-0.5 font-mono text-[11px] font-bold text-indigo-700">
                                                {{ $cat->chartOfAccount->code }}
                                            </span>
                                            <span class="text-slate-800 font-semibold">{{ $cat->chartOfAccount->name }}</span>
                                        </div>
                                    @else
                                        <span class="text-slate-400 italic">Belum dikaitkan</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-center">
                                    @if ($cat->is_active)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700 border border-emerald-200">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            Aktif
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-500 border border-slate-200">
                                            <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                            Non-Aktif
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-center font-bold text-slate-800">
                                    {{ $cat->expenses_count }}x pengeluaran
                                </td>
                                <td class="py-4 pl-3 pr-6 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('backoffice.expense-categories.edit', $cat->id) }}" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:border-slate-300">
                                            Edit
                                        </a>
                                        <form method="POST" action="{{ route('backoffice.expense-categories.destroy', $cat->id) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus/menonaktifkan kategori ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-12 text-center text-slate-400">
                                    <p class="text-sm font-semibold">Belum ada kategori biaya yang terdaftar.</p>
                                    <p class="text-xs mt-1">Tambahkan kategori baru untuk mulai mencatat pengeluaran kas.</p>
                                    <a href="{{ route('backoffice.expense-categories.create') }}" class="mt-4 inline-block rounded-xl bg-sky-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-sky-700">
                                        + Tambah Kategori Pertama
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($categories->hasPages())
                <div class="border-t border-slate-200 bg-slate-50 px-6 py-4">
                    {{ $categories->links() }}
                </div>
            @endif
        </div>
    </main>
</x-app-layout>
