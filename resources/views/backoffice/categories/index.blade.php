<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategori Produk | Maju Bersama ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body x-data="categoryManager()" class="min-h-screen bg-slate-100 text-slate-900 antialiased">
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
                <a href="{{ route('backoffice.products.create') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    + Tambah Produk
                </a>
                <a href="{{ route('backoffice.categories.index') }}" class="rounded-lg bg-sky-600 px-3.5 py-2 font-semibold text-white shadow-sm">
                    Kategori
                </a>
                <a href="{{ route('backoffice.users.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Staf &amp; Kasir
                </a>
                @if ($isMaster)
                    <a href="{{ route('backoffice.branches.index') }}" class="rounded-lg px-3.5 py-2 text-amber-700 hover:bg-amber-50">
                        Manajemen Cabang
                    </a>
                @endif
            </div>
            <button type="button" @click="openCreateModal()" class="hidden sm:inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-500">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Tambah Kategori
            </button>
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

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800 shadow-sm">
                <ul class="list-inside list-disc text-xs space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Filter & Search Bar -->
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
            <form method="GET" action="{{ route('backoffice.categories.index') }}" class="flex items-center gap-3 w-full sm:w-auto">
                <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama kategori..." class="rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 w-full sm:w-72">
                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                    Cari
                </button>
                @if ($search)
                    <a href="{{ route('backoffice.categories.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                        Reset
                    </a>
                @endif
            </form>
            <button type="button" @click="openCreateModal()" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-500">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Tambah Kategori
            </button>
        </div>

        <!-- Categories Table -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 class="font-bold text-slate-900">Daftar Kategori Produk</h2>
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">Total: {{ $categories->total() }} kategori</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-6 py-3">No</th>
                            <th class="px-6 py-3">Nama Kategori</th>
                            <th class="px-6 py-3 text-center">Jumlah Produk Terkait</th>
                            <th class="px-6 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($categories as $index => $category)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-6 py-4 text-xs text-slate-400">
                                    {{ $categories->firstItem() + $index }}
                                </td>
                                <td class="px-6 py-4 font-semibold text-slate-900">
                                    {{ $category->name }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $category->products_count > 0 ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $category->products_count }} produk
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="inline-flex items-center gap-2">
                                        <button type="button" @click="openEditModal({{ $category->id }}, '{{ addslashes($category->name) }}')" class="rounded-lg bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 hover:bg-sky-100">
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('backoffice.categories.destroy', $category->id) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kategori \'{{ $category->name }}\'?');">
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
                                <td colspan="4" class="px-6 py-12 text-center text-slate-500">
                                    Tidak ada kategori produk yang terdaftar.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($categories->hasPages())
                <div class="border-t border-slate-100 px-6 py-4">
                    {{ $categories->links() }}
                </div>
            @endif
        </div>
    </main>

    <!-- Modal Create / Edit Kategori (Alpine.js) -->
    <div x-show="showModal" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div @click.away="showModal = false" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl border border-slate-200">
            <div class="border-b border-slate-100 pb-4 mb-4">
                <h3 class="text-base font-bold text-slate-900" x-text="isEdit ? 'Ubah Nama Kategori' : 'Tambah Kategori Baru'"></h3>
                <p class="text-xs text-slate-500 mt-0.5">Klasifikasikan produk agar rapi di katalog dan POS kasir.</p>
            </div>

            <form :action="formAction" method="POST">
                @csrf
                <template x-if="isEdit">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div class="mb-5">
                    <label for="modal-name" class="block text-sm font-semibold text-slate-800">
                        Nama Kategori <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="modal-name" name="name" x-model="categoryName" required placeholder="Contoh: Pupuk Organik, Obat Hama, Benih" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" @click="showModal = false" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Batal
                    </button>
                    <button type="submit" class="rounded-xl bg-sky-600 px-5 py-2 text-sm font-semibold text-white hover:bg-sky-500">
                        Simpan Kategori
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function categoryManager() {
            return {
                showModal: false,
                isEdit: false,
                categoryName: '',
                formAction: '{{ route('backoffice.categories.store') }}',

                openCreateModal() {
                    this.isEdit = false;
                    this.categoryName = '';
                    this.formAction = '{{ route('backoffice.categories.store') }}';
                    this.showModal = true;
                },

                openEditModal(id, name) {
                    this.isEdit = true;
                    this.categoryName = name;
                    this.formAction = '/backoffice/categories/' + id;
                    this.showModal = true;
                }
            };
        }
    </script>
</body>
</html>
