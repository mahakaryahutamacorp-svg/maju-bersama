<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Staf &amp; Kasir | Maju Bersama ERP</title>
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
                <div class="rounded-full border border-sky-400/30 bg-sky-400/10 px-3 py-1 text-xs font-medium text-sky-200">
                    {{ $currentUser->branch?->name ?? 'Pusat' }} ({{ $currentUser->role }})
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
                <a href="{{ route('backoffice.users.index') }}" class="rounded-lg bg-sky-600 px-3.5 py-2 font-semibold text-white shadow-sm">
                    Staf &amp; Kasir
                </a>
                <a href="{{ route('backoffice.users.create') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    + Tambah Pengguna
                </a>
                @if ($isMaster)
                    <a href="{{ route('backoffice.branches.index') }}" class="rounded-lg px-3.5 py-2 text-amber-700 hover:bg-amber-50">
                        Manajemen Cabang
                    </a>
                @endif
            </div>
            <a href="{{ route('backoffice.users.create') }}" class="hidden sm:inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-500">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Tambah Pengguna Baru
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

        <!-- Filter & Search Bar -->
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('backoffice.users.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-12">
                <div class="lg:col-span-4">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Pencarian</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama atau email..." class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                </div>

                <div class="lg:col-span-3">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Role / Jabatan</label>
                    <select name="role" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                        <option value="">Semua Role</option>
                        @if ($isMaster)
                            <option value="master" {{ $selectedRole === 'master' ? 'selected' : '' }}>Master / Superadmin</option>
                        @endif
                        <option value="admin" {{ $selectedRole === 'admin' ? 'selected' : '' }}>Admin Cabang</option>
                        <option value="cashier" {{ $selectedRole === 'cashier' ? 'selected' : '' }}>Kasir POS</option>
                    </select>
                </div>

                @if ($isMaster)
                    <div class="lg:col-span-3">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Cabang</label>
                        <select name="branch_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                            <option value="">Seluruh Cabang</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" {{ (string)$selectedBranchId === (string)$branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div class="lg:col-span-3">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Cabang Aktif</label>
                        <div class="rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 text-sm text-slate-700 font-medium">
                            {{ $currentUser->branch?->name }}
                        </div>
                    </div>
                @endif

                <div class="flex items-end gap-2 lg:col-span-2">
                    <button type="submit" class="w-full rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 shadow-sm">
                        Filter
                    </button>
                    @if ($search || $selectedRole || $selectedBranchId)
                        <a href="{{ route('backoffice.users.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50" title="Reset filter">
                            ↺
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 class="font-bold text-slate-900">Daftar Akun Staf &amp; Kasir</h2>
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">Total: {{ $users->total() }} pengguna</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-6 py-3">Nama Pengguna</th>
                            <th class="px-6 py-3">Email</th>
                            <th class="px-6 py-3">Role / Wewenang</th>
                            <th class="px-6 py-3">Cabang Penempatan</th>
                            <th class="px-6 py-3 text-center">Status</th>
                            <th class="px-6 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($users as $user)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-slate-900">{{ $user->name }}</div>
                                    @if ($user->id === $currentUser->id)
                                        <span class="inline-block mt-0.5 rounded bg-sky-100 px-1.5 py-0.2 text-[10px] font-bold text-sky-800">Akun Anda</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 font-mono text-xs text-slate-600">
                                    {{ $user->email }}
                                </td>
                                <td class="px-6 py-4">
                                    @if ($user->role === 'master' || $user->role === 'superadmin')
                                        <span class="inline-flex items-center rounded-full bg-purple-50 border border-purple-200 px-2.5 py-0.5 text-xs font-semibold text-purple-700">
                                            👑 Master Pusat
                                        </span>
                                    @elseif ($user->role === 'admin')
                                        <span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-200 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                                            🛡️ Admin Cabang
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-sky-50 border border-sky-200 px-2.5 py-0.5 text-xs font-semibold text-sky-700">
                                            🛒 Kasir POS
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-xs font-medium text-slate-700">
                                    {{ $user->branch?->name ?? 'Kantor Pusat' }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $user->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $user->is_active ? 'Aktif' : 'Non-aktif' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="inline-flex items-center gap-1.5">
                                        <a href="{{ route('backoffice.users.edit', $user->id) }}" class="rounded-lg bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 hover:bg-sky-100">
                                            Edit
                                        </a>
                                        @if ($user->id !== $currentUser->id)
                                            <form method="POST" action="{{ route('backoffice.users.destroy', $user->id) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun \'{{ $user->name }}\'?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100">
                                                    Hapus
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                    Tidak ada pengguna/kasir yang sesuai filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div class="border-t border-slate-100 px-6 py-4">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </main>
</body>
</html>
