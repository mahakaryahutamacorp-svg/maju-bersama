<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pelanggan | Maju Bersama ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased flex flex-col">
    <!-- Header Utama -->
    <header class="border-b border-slate-800 bg-slate-950 text-white shrink-0">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3.5 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                <a href="/backoffice" class="block group">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-amber-400 group-hover:text-amber-300 transition">Maju Bersama ERP</p>
                    <div class="flex items-center gap-2">
                        <h1 class="text-base font-bold tracking-tight">Manajemen Pelanggan &amp; Multi-Price</h1>
                    </div>
                </a>
            </div>
            <div class="flex items-center gap-3">
                <a href="/backoffice" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800 hover:text-white transition">
                    &larr; Backoffice
                </a>
            </div>
        </div>
    </header>

    <main class="flex-1 mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8 space-y-6">
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900 shadow-xs flex items-center gap-3">
                <svg class="h-5 w-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span class="text-sm font-semibold">{{ session('success') }}</span>
            </div>
        @endif

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-slate-950">Daftar Pelanggan</h2>
                <p class="text-xs text-slate-500 mt-0.5">Kelola data pelanggan dan penetapan grup harga khusus (Retail, Petani, Grosir).</p>
            </div>
            <a href="{{ route('backoffice.customers.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-sky-600 px-4 py-2.5 text-xs font-bold text-white shadow-xs hover:bg-sky-500 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Tambah Pelanggan Baru
            </a>
        </div>

        <!-- Filter & Search -->
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <form method="GET" action="{{ route('backoffice.customers.index') }}" class="grid grid-cols-1 {{ $isMaster && count($branches) > 1 ? 'sm:grid-cols-4' : 'sm:grid-cols-3' }} gap-3 items-end">
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Cari Nama / Kontak</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Ketik nama atau nomor telepon..." class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 outline-none focus:border-sky-500">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Grup Pelanggan</label>
                    <select name="customer_group_id" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 outline-none focus:border-sky-500">
                        <option value="">Semua Grup</option>
                        @foreach ($customerGroups as $cg)
                            <option value="{{ $cg->id }}" {{ (string)$selectedGroupId === (string)$cg->id ? 'selected' : '' }}>
                                {{ $cg->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @if ($isMaster && count($branches) > 1)
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Cabang Pendaftaran</label>
                    <select name="branch_id" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 outline-none focus:border-sky-500">
                        <option value="">Semua Cabang</option>
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" {{ (string)$selectedBranchId === (string)$b->id ? 'selected' : '' }}>
                                {{ $b->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="flex items-center gap-2">
                    <button type="submit" class="w-full rounded-xl bg-slate-900 py-2.5 text-xs font-bold text-white shadow-xs hover:bg-slate-800">
                        Filter
                    </button>
                    @if ($search || $selectedGroupId || ($isMaster && $selectedBranchId))
                        <a href="{{ route('backoffice.customers.index') }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Tabel Pelanggan -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-900 text-white uppercase text-[10px] tracking-wider">
                            <th class="py-3 px-4">Nama Pelanggan</th>
                            <th class="py-3 px-4">Kontak</th>
                            <th class="py-3 px-4">Grup Pelanggan (Pricing Tier)</th>
                            <th class="py-3 px-4">Cabang</th>
                            <th class="py-3 px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($customers as $c)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-3 px-4">
                                    <span class="block font-bold text-slate-900 text-sm">{{ $c->name }}</span>
                                    <span class="text-[11px] text-slate-400">{{ $c->address ?? 'Alamat belum diatur' }}</span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="block font-mono text-slate-700">{{ $c->phone ?? '-' }}</span>
                                    <span class="text-[11px] text-slate-400">{{ $c->email ?? '-' }}</span>
                                </td>
                                <td class="py-3 px-4">
                                    @if ($c->customerGroup)
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[11px] font-bold text-emerald-700">
                                            {{ $c->customerGroup->name }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-500">
                                            Umum (Tanpa Grup)
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-slate-600">
                                    {{ $c->branch?->name ?? 'Pusat' }}
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <a href="{{ route('backoffice.customers.edit', $c->id) }}" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 shadow-2xs">
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-10 text-center text-slate-400">
                                    Belum ada data pelanggan tercatat.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($customers->hasPages())
                <div class="border-t border-slate-200 bg-slate-50 px-6 py-4">
                    {{ $customers->links() }}
                </div>
            @endif
        </div>
    </main>
</body>
</html>
