<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Kategori Biaya | Maju Bersama ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <!-- Header Utama -->
    <header class="border-b border-slate-800 bg-slate-950 text-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
            <div class="flex items-center gap-4">
                <a href="/backoffice" class="block">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-400">Maju Bersama ERP</p>
                    <h1 class="text-xl font-bold tracking-tight">Keuangan &amp; Akuntansi</h1>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <div class="rounded-full border border-sky-400/30 bg-sky-400/10 px-3 py-1 text-xs font-medium text-sky-200">
                    {{ $currentUser->branch?->name ?? 'Pusat' }} ({{ $currentUser->role }})
                </div>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-3xl space-y-6 px-6 py-8 lg:px-8">
        <!-- Error Banner -->
        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900 shadow-xs">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <div>
                        <p class="text-sm font-bold">Harap periksa kesalahan berikut:</p>
                        <ul class="mt-1 list-inside list-disc text-xs text-rose-800 space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-amber-600">Master Data Biaya</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">Edit Kategori: {{ $category->name }}</h2>
            </div>
            <a href="{{ route('backoffice.expense-categories.index') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                &larr; Kembali
            </a>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <form method="POST" action="{{ route('backoffice.expense-categories.update', $category->id) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <!-- Nama Kategori -->
                <div>
                    <label for="name" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                        Nama Kategori Biaya <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name', $category->name) }}"
                        required
                        autofocus
                        class="w-full rounded-xl border border-slate-300 bg-slate-50/50 px-4 py-3 text-sm font-semibold text-slate-900 outline-none focus:border-amber-500 focus:bg-white focus:ring-2 focus:ring-amber-400/20 transition"
                    >
                </div>

                <!-- Pemetaan Akun Beban (COA) -->
                <div>
                    <label for="chart_of_account_id" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                        Akun Buku Besar / COA Beban <span class="text-rose-500">*</span>
                    </label>
                    <select
                        id="chart_of_account_id"
                        name="chart_of_account_id"
                        required
                        class="w-full rounded-xl border border-slate-300 bg-slate-50/50 px-4 py-3 text-sm font-semibold text-slate-900 outline-none focus:border-amber-500 focus:bg-white focus:ring-2 focus:ring-amber-400/20 transition"
                    >
                        @foreach ($expenseAccounts as $acc)
                            <option value="{{ $acc->id }}" {{ old('chart_of_account_id', $category->chart_of_account_id) == $acc->id ? 'selected' : '' }}>
                                [{{ $acc->code }}] {{ $acc->name }} ({{ strtoupper($acc->type) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Status Aktif -->
                <div class="flex items-center gap-3 pt-2">
                    <input
                        type="checkbox"
                        id="is_active"
                        name="is_active"
                        value="1"
                        {{ old('is_active', $category->is_active) ? 'checked' : '' }}
                        class="h-4 w-4 rounded border-slate-300 text-amber-500 focus:ring-amber-400"
                    >
                    <label for="is_active" class="text-xs font-bold text-slate-800">
                        Kategori Aktif (Dapat dipilih pada formulir kas keluar)
                    </label>
                </div>

                <!-- Tombol Aksi -->
                <div class="border-t border-slate-100 pt-5 flex items-center justify-end gap-3">
                    <a href="{{ route('backoffice.expense-categories.index') }}" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50">
                        Batal
                    </a>
                    <button
                        type="submit"
                        class="rounded-xl bg-amber-500 px-6 py-2.5 text-xs font-bold text-slate-950 shadow-md hover:bg-amber-400 transition"
                    >
                        Perbarui Kategori Biaya →
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
