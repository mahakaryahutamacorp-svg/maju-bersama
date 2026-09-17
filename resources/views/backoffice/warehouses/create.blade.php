<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Gudang | Maju Bersama ERP</title>
    <meta name="description" content="Form penambahan gudang baru pada cabang toko Maju Bersama ERP.">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">

    <!-- Header -->
    <header class="border-b border-slate-800 bg-slate-950 text-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
            <div>
                <a href="/backoffice">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-400">Maju Bersama ERP</p>
                    <h1 class="text-xl font-bold tracking-tight">Multi Gudang</h1>
                </a>
            </div>
            @if ($isMaster)
                <div class="rounded-full border border-amber-400/40 bg-amber-400/10 px-3 py-1 text-xs font-semibold text-amber-300">
                    👑 Akses Master Pusat
                </div>
            @else
                <div class="rounded-full border border-sky-400/40 bg-sky-400/10 px-3 py-1 text-xs font-semibold text-sky-300">
                    🏪 {{ $currentUser->branch->name }}
                </div>
            @endif
        </div>
    </header>

    <!-- Breadcrumb Bar -->
    <div class="border-b border-slate-200 bg-white shadow-sm">
        <div class="mx-auto flex max-w-7xl items-center px-6 py-2.5 lg:px-8">
            <div class="flex items-center gap-2 text-sm font-medium">
                <a href="{{ route('backoffice.warehouses.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    ← Daftar Gudang
                </a>
                <span class="text-slate-300">/</span>
                <span class="font-semibold text-slate-900">Tambah Gudang Baru</span>
            </div>
        </div>
    </div>

    <!-- Main Form -->
    <main class="mx-auto max-w-2xl px-6 py-8 lg:px-8">

        <!-- Validation Errors -->
        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800 shadow-sm">
                <div class="flex items-center gap-2 text-sm font-semibold">
                    <svg class="h-5 w-5 text-rose-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                    Terdapat kesalahan pada isian form:
                </div>
                <ul class="mt-2 list-inside list-disc space-y-1 text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" x-data="{ code: '{{ old('code') }}' }">
            <!-- Form Header -->
            <div class="mb-6 border-b border-slate-100 pb-5">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-50 text-xl">🏭</span>
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Tambah Gudang Baru</h2>
                        <p class="text-xs text-slate-500">
                            @if ($isMaster)
                                Tambahkan gudang baru ke cabang mana pun dalam jaringan Maju Bersama.
                            @else
                                Tambahkan gudang baru untuk cabang <strong>{{ $currentUser->branch->name }}</strong>.<br>
                                Contoh: <span class="font-mono">Etalase Depan</span>, <span class="font-mono">Gudang Belakang</span>, <span class="font-mono">Laci Kasir</span>.
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            <form id="form-tambah-gudang" method="POST" action="{{ route('backoffice.warehouses.store') }}" class="space-y-6">
                @csrf

                <!-- Pilih Cabang (Master only) -->
                @if ($isMaster)
                    <div>
                        <label for="branch_id" class="block text-sm font-semibold text-slate-800">
                            Cabang <span class="text-rose-500">*</span>
                        </label>
                        <select id="branch_id" name="branch_id" required
                            class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500">
                            <option value="">-- Pilih Cabang --</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }} ({{ $branch->code }})
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[11px] text-slate-400">Gudang ini akan terdaftar di bawah cabang yang dipilih.</p>
                    </div>
                @else
                    {{-- Branch Admin: branch_id sudah terkunci di server --}}
                    <input type="hidden" name="branch_id" value="{{ $currentUser->branch_id }}">
                @endif

                <!-- Kode & Nama Gudang -->
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="code" class="block text-sm font-semibold text-slate-800">
                            Kode Gudang <span class="text-rose-500">*</span>
                        </label>
                        <input id="code" type="text" name="code" x-model="code"
                            @input="code = code.toUpperCase()"
                            value="{{ old('code') }}"
                            required placeholder="GDG-01"
                            maxlength="20"
                            class="mt-1.5 w-full font-mono uppercase rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500">
                        <p class="mt-1 text-[11px] text-slate-400">Maks. 20 karakter, unik per cabang.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="name" class="block text-sm font-semibold text-slate-800">
                            Nama Gudang <span class="text-rose-500">*</span>
                        </label>
                        <input id="name" type="text" name="name" value="{{ old('name') }}"
                            required placeholder="Contoh: Gudang Belakang, Etalase Depan"
                            maxlength="100"
                            class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500">
                    </div>
                </div>

                <!-- Info Box -->
                <div class="rounded-xl border border-teal-100 bg-teal-50 p-4">
                    <div class="flex items-start gap-3">
                        <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-teal-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>
                        <div>
                            <p class="text-xs font-semibold text-teal-800">Tentang Multi Gudang</p>
                            <p class="mt-1 text-xs text-teal-700">Gudang adalah lokasi fisik penyimpanan stok di dalam satu cabang. Pada fase berikutnya, setiap item inventaris akan dikaitkan ke gudang tertentu sehingga transfer stok antar gudang dapat dilacak secara akurat.</p>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
                    <a href="{{ route('backoffice.warehouses.index') }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                        Batal
                    </a>
                    <button id="btn-simpan-gudang" type="submit"
                        class="rounded-xl bg-teal-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-teal-500 transition-colors">
                        Simpan Gudang
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
