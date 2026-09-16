<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Cabang - {{ $branch->name }} | Maju Bersama ERP</title>
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
                <div class="rounded-full border border-amber-400/40 bg-amber-400/10 px-3 py-1 text-xs font-semibold text-amber-300">
                    👑 Akses Master Pusat
                </div>
            </div>
        </div>
    </header>

    <!-- Sub-Navbar Master Data -->
    <div class="border-b border-slate-200 bg-white shadow-sm">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-2.5 lg:px-8">
            <div class="flex items-center gap-2 text-sm font-medium">
                <a href="{{ route('backoffice.branches.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    ← Kembali ke Daftar Cabang
                </a>
                <span class="text-slate-300">/</span>
                <span class="font-semibold text-slate-900">Edit Cabang: {{ $branch->name }}</span>
            </div>
        </div>
    </div>

    <!-- Main Form Container -->
    <main class="mx-auto max-w-2xl px-6 py-8 lg:px-8">
        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800 shadow-sm">
                <div class="flex items-center gap-2 font-semibold text-sm">
                    <svg class="h-5 w-5 text-rose-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                    <span>Terdapat kesalahan pada isian form:</span>
                </div>
                <ul class="mt-2 list-inside list-disc text-xs space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="border-b border-slate-100 pb-5 mb-6 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Edit Data Cabang Toko</h2>
                    <p class="text-xs text-slate-500 mt-1">Perbarui profil operasional cabang toko.</p>
                </div>
                <span class="font-mono text-xs font-bold bg-amber-100 text-amber-900 px-3 py-1 rounded-full">
                    {{ $branch->code }}
                </span>
            </div>

            <form method="POST" action="{{ route('backoffice.branches.update', $branch->id) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <!-- Kode & Nama Cabang -->
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="code" class="block text-sm font-semibold text-slate-800">
                            Kode Cabang <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="code" name="code" value="{{ old('code', $branch->code) }}" required class="mt-1.5 w-full uppercase font-mono rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                    </div>

                    <div class="sm:col-span-2">
                        <label for="name" class="block text-sm font-semibold text-slate-800">
                            Nama Cabang Toko <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="name" name="name" value="{{ old('name', $branch->name) }}" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                    </div>
                </div>

                <!-- Induk Cabang -->
                <div>
                    <label for="parent_id" class="block text-sm font-semibold text-slate-800">
                        Induk / Cabang Utama
                    </label>
                    <select id="parent_id" name="parent_id" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                        <option value="">Kantor Pusat / Root (Tanpa Induk)</option>
                        @foreach ($parentBranches as $parent)
                            <option value="{{ $parent->id }}" {{ old('parent_id', $branch->parent_id) == $parent->id ? 'selected' : '' }}>
                                {{ $parent->name }} ({{ $parent->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Telepon & Alamat -->
                <div class="space-y-4">
                    <div>
                        <label for="phone" class="block text-sm font-semibold text-slate-800">
                            Nomor Telepon Cabang
                        </label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone', $branch->phone) }}" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                    </div>

                    <div>
                        <label for="address" class="block text-sm font-semibold text-slate-800">
                            Alamat Lokasi Toko
                        </label>
                        <textarea id="address" name="address" rows="3" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">{{ old('address', $branch->address) }}</textarea>
                    </div>
                </div>

                <!-- Status Aktif Checkbox -->
                <div class="flex items-center gap-3 rounded-xl bg-slate-50 p-4 border border-slate-200">
                    <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $branch->is_active) ? 'checked' : '' }} class="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                    <label for="is_active" class="text-sm font-medium text-slate-700 select-none">
                        Cabang Aktif Beroperasi
                    </label>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
                    <a href="{{ route('backoffice.branches.index') }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                        Batal
                    </a>
                    <button type="submit" class="rounded-xl bg-amber-500 px-6 py-2.5 text-sm font-bold text-slate-950 shadow-sm hover:bg-amber-400 transition-colors">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
