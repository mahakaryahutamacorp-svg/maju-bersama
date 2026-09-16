<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Cabang Toko | Maju Bersama ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <!-- Header Utama -->
    <header class="border-b border-slate-800 bg-slate-950 text-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
            <div class="flex items-center gap-4">
                <a href="/backoffice" class="block">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-400">Maju Bersama ERP</p>
                    <h1 class="text-xl font-bold tracking-tight">Manajemen Master Data</h1>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <nav class="hidden items-center gap-4 text-sm text-slate-300 md:flex">
                    <a href="/pos" class="hover:text-white">POS Kasir</a>
                    <a href="/inventory" class="hover:text-white">Inventory</a>
                    <a href="/reports/journal" class="hover:text-white">Jurnal</a>
                    <a href="/backoffice" class="hover:text-white">Backoffice</a>
                </nav>
                <div class="rounded-full border border-amber-400/40 bg-amber-400/10 px-3 py-1 text-xs font-semibold text-amber-300">
                    👑 Akses Master Pusat
                </div>
            </div>
        </div>
    </header>

    <!-- Sub-Navbar Master Data -->
    <div class="border-b border-slate-200 bg-white shadow-sm">
        <div class="mx-auto flex max-w-7xl items-center justify-between overflow-x-auto px-6 py-2.5 lg:px-8">
            <div class="flex items-center gap-2 text-sm font-medium">
                <a href="{{ route('backoffice.products.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Katalog Produk
                </a>
                <a href="{{ route('backoffice.categories.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Kategori
                </a>
                <a href="{{ route('backoffice.users.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Staf &amp; Kasir
                </a>
                <a href="{{ route('backoffice.branches.index') }}" class="rounded-lg bg-amber-500 px-3.5 py-2 font-bold text-slate-950 shadow-sm">
                    Manajemen Cabang
                </a>
            </div>
            <a href="{{ route('backoffice.branches.create') }}" class="hidden sm:inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-500">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Daftarkan Cabang Baru
            </a>
        </div>
    </div>

    <!-- Main Container -->
    <main class="mx-auto max-w-7xl px-6 py-8 lg:px-8">
        <!-- Flash Message Alerts -->
        @if (session('success'))
            <div class="mb-6 flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 shadow-sm">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                    <span class="text-sm font-medium">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 flex items-center justify-between rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800 shadow-sm">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 text-rose-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                    <span class="text-sm font-medium">{{ session('error') }}</span>
                </div>
            </div>
        @endif

        <!-- Filter & Action Bar -->
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
            <form method="GET" action="{{ route('backoffice.branches.index') }}" class="flex items-center gap-3 w-full sm:w-auto">
                <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama atau kode cabang..." class="rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 w-full sm:w-72">
                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                    Cari
                </button>
                @if ($search)
                    <a href="{{ route('backoffice.branches.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                        Reset
                    </a>
                @endif
            </form>
            <a href="{{ route('backoffice.branches.create') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-bold text-slate-950 shadow-sm hover:bg-amber-400">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Daftarkan Cabang Baru
            </a>
        </div>

        <!-- Branches Table -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 class="font-bold text-slate-900">Jaringan Cabang Toko Maju Bersama</h2>
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">Total: {{ $branches->total() }} cabang</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-6 py-3">Kode Cabang</th>
                            <th class="px-6 py-3">Nama Cabang Toko</th>
                            <th class="px-6 py-3">Induk / Hirarki</th>
                            <th class="px-6 py-3 text-center">Produk</th>
                            <th class="px-6 py-3 text-center">Staf</th>
                            <th class="px-6 py-3 text-center">Status</th>
                            <th class="px-6 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($branches as $branch)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-6 py-4 font-mono font-bold text-sky-700">
                                    {{ $branch->code }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-slate-900">{{ $branch->name }}</div>
                                    @if ($branch->phone || $branch->address)
                                        <div class="text-xs text-slate-400 mt-0.5">{{ $branch->phone }} {{ $branch->address ? '· ' . Str::limit($branch->address, 35) : '' }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-xs">
                                    @if ($branch->parent)
                                        <span class="font-medium text-slate-700">{{ $branch->parent->name }}</span>
                                    @else
                                        <span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-[10px] text-slate-500">Kantor Pusat</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-center font-semibold text-slate-800">
                                    {{ $branch->products_count }}
                                </td>
                                <td class="px-6 py-4 text-center font-semibold text-slate-800">
                                    {{ $branch->users_count }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $branch->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $branch->is_active ? 'Aktif' : 'Non-aktif' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="inline-flex items-center gap-1.5">
                                        <a href="{{ route('backoffice.branches.edit', $branch->id) }}" class="rounded-lg bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 hover:bg-sky-100">
                                            Edit
                                        </a>
                                        <form method="POST" action="{{ route('backoffice.branches.destroy', $branch->id) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus cabang \'{{ $branch->name }}\'?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-slate-500">
                                    Tidak ada cabang yang ditemukan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($branches->hasPages())
                <div class="border-t border-slate-100 px-6 py-4">
                    {{ $branches->links() }}
                </div>
            @endif
        </div>
    </main>
</body>
</html>
