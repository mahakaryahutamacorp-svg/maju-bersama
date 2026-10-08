<x-app-layout title="Buku Pemasok" :breadcrumbs="[['label' => 'Belanja Barang'], ['label' => 'Buku Pemasok']]">
    <header class="border-b border-slate-200 bg-white">
        <div class="flex flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center sm:justify-between lg:px-10">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-600">Belanja Barang</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">Buku Pemasok</h1>
                <p class="mt-1 text-sm text-slate-500">Semua pemasok tempat toko berbelanja barang.</p>
            </div>
            <a href="{{ route('backoffice.suppliers.create') }}" id="btn-tambah-supplier" class="inline-flex items-center gap-1.5 self-start rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-xs transition-colors hover:bg-emerald-500 sm:self-auto">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Tambah Pemasok
            </a>
        </div>
    </header>

    <main class="mx-auto w-full max-w-7xl px-6 py-6 lg:px-10">
        <!-- Flash Messages -->
        @if (session('success'))
            <div class="mb-6 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 shadow-xs">
                <svg class="h-5 w-5 shrink-0 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                <span class="text-sm font-medium">{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 flex items-center gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800 shadow-xs">
                <svg class="h-5 w-5 shrink-0 text-rose-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                <span class="text-sm font-medium">{{ session('error') }}</span>
            </div>
        @endif

        <!-- Banner Info -->
        <div class="mb-6 rounded-2xl border border-indigo-200 bg-gradient-to-r from-indigo-50 to-sky-50 p-5 shadow-xs">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-indigo-600">Cara pakai</p>
                    <p class="mt-1 text-sm text-slate-700">
                        Klik <span class="font-semibold">Buka Buku</span> untuk melihat sisa hutang, riwayat belanja, dan membayar hutang ke pemasok tersebut di satu layar.
                    </p>
                </div>
                <div class="text-right">
                    <span class="text-2xl font-black text-indigo-950">{{ $suppliers->total() }}</span>
                    <span class="block text-xs font-medium text-slate-500">Pemasok terdaftar</span>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <form method="GET" action="{{ route('backoffice.suppliers.index') }}" class="grid gap-3 sm:grid-cols-2 md:grid-cols-4">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Pencarian</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Nama / PIC / Telepon..." class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
                    <select name="status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        <option value="">Semua Status</option>
                        <option value="1" {{ $status === '1' ? 'selected' : '' }}>Aktif</option>
                        <option value="0" {{ $status === '0' ? 'selected' : '' }}>Nonaktif</option>
                    </select>
                </div>
                @if ($isMaster)
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Cabang</label>
                        <select name="branch_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                            <option value="">Semua Cabang</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" {{ $filterBranch == $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }} ({{ $branch->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="flex items-end gap-2">
                    <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 transition-colors">
                        Filter
                    </button>
                    @if ($search || $status !== null || $filterBranch)
                        <a href="{{ route('backoffice.suppliers.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Tabel Supplier -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-3.5 text-left">Nama Supplier</th>
                            <th class="px-5 py-3.5 text-left">Kontak Person</th>
                            <th class="px-5 py-3.5 text-left">Telepon</th>
                            <th class="px-5 py-3.5 text-left">Alamat</th>
                            @if ($isMaster)
                                <th class="px-5 py-3.5 text-left">Cabang</th>
                            @endif
                            <th class="px-5 py-3.5 text-center">Status</th>
                            <th class="px-5 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($suppliers as $supplier)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-5 py-4 font-semibold text-slate-900">
                                    <a href="{{ route('backoffice.suppliers.show', $supplier) }}" class="hover:text-indigo-700 hover:underline">{{ $supplier->name }}</a>
                                </td>
                                <td class="px-5 py-4 text-slate-700">
                                    {{ $supplier->contact_person ?? '-' }}
                                </td>
                                <td class="px-5 py-4 font-mono text-slate-600">
                                    {{ $supplier->phone ?? '-' }}
                                </td>
                                <td class="px-5 py-4 text-slate-500 max-w-xs truncate">
                                    {{ $supplier->address ?? '-' }}
                                </td>
                                @if ($isMaster)
                                    <td class="px-5 py-4 text-xs font-medium text-slate-600">
                                        <span class="rounded bg-slate-100 px-2 py-1">{{ $supplier->branch->name ?? '-' }}</span>
                                    </td>
                                @endif
                                <td class="px-5 py-4 text-center">
                                    @if ($supplier->is_active)
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200">
                                            Aktif
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 border border-slate-200">
                                            Nonaktif
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('backoffice.suppliers.show', $supplier) }}" class="rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">
                                            Buka Buku
                                        </a>
                                        <a href="{{ route('backoffice.suppliers.edit', $supplier) }}" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 hover:border-indigo-300">
                                            Edit
                                        </a>
                                        <form method="POST" action="{{ route('backoffice.suppliers.destroy', $supplier) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus supplier {{ $supplier->name }}?');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 hover:border-rose-300">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isMaster ? 7 : 6 }}" class="px-6 py-12 text-center text-slate-400">
                                    <svg class="mx-auto h-10 w-10 text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    Belum ada data supplier yang ditemukan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($suppliers->hasPages())
                <div class="border-t border-slate-200 px-6 py-4">
                    {{ $suppliers->links() }}
                </div>
            @endif
        </div>
    </main>
</x-app-layout>
