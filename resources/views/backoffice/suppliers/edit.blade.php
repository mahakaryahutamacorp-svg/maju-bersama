<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Supplier: {{ $supplier->name }} | Maju Bersama ERP</title>
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
                    <h1 class="text-xl font-bold tracking-tight">Manajemen Rekanan</h1>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <div class="rounded-full border border-sky-400/30 bg-sky-400/10 px-3 py-1 text-xs font-medium text-sky-200">
                    {{ $currentUser->branch?->name ?? 'Pusat' }} ({{ $currentUser->role }})
                </div>
            </div>
        </div>
    </header>

    <!-- Sub-Navbar -->
    <div class="border-b border-slate-200 bg-white shadow-xs">
        <div class="mx-auto flex max-w-7xl items-center justify-between overflow-x-auto px-6 py-2.5 lg:px-8">
            <div class="flex items-center gap-2 text-sm font-medium">
                <a href="{{ route('backoffice.suppliers.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    &larr; Kembali ke Daftar Supplier
                </a>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <main class="mx-auto max-w-3xl px-6 py-8 lg:px-8">
        <!-- Error Alerts -->
        @if ($errors->any())
            <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900 shadow-xs">
                <p class="text-sm font-bold">Harap perbaiki kesalahan berikut:</p>
                <ul class="mt-1 list-inside list-disc text-xs text-rose-800 space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-2xl border border-slate-200 bg-white shadow-xs">
            <div class="border-b border-slate-200 px-6 py-5">
                <h2 class="text-lg font-bold text-slate-900">Edit Data Supplier</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Perbarui informasi kontak, alamat, atau status aktif supplier.
                </p>
            </div>

            <form method="POST" action="{{ route('backoffice.suppliers.update', $supplier) }}" class="p-6 space-y-6">
                @csrf
                @method('PUT')

                @if ($isMaster)
                    <div>
                        <label for="branch_id" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Cabang Terdaftar <span class="text-rose-500">*</span>
                        </label>
                        <select id="branch_id" name="branch_id" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" {{ old('branch_id', $supplier->branch_id) == $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }} ({{ $branch->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div>
                    <label for="name" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                        Nama Perusahaan / Supplier <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="name" name="name" value="{{ old('name', $supplier->name) }}" required placeholder="Contoh: PT Sumber Pangan Makmur" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="contact_person" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Nama Kontak (PIC)
                        </label>
                        <input type="text" id="contact_person" name="contact_person" value="{{ old('contact_person', $supplier->contact_person) }}" placeholder="Contoh: Bpk. Bambang" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="phone" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Nomor Telepon / WhatsApp
                        </label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone', $supplier->phone) }}" placeholder="Contoh: 081234567890" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm font-mono focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label for="address" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                        Alamat Lengkap
                    </label>
                    <textarea id="address" name="address" rows="3" placeholder="Alamat gudang / kantor supplier..." class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">{{ old('address', $supplier->address) }}</textarea>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $supplier->is_active) ? 'checked' : '' }} class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    <label for="is_active" class="text-sm font-medium text-slate-700 cursor-pointer">
                        Status Aktif (Supplier dapat dipilih saat pembuatan Purchase Order)
                    </label>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-6">
                    <a href="{{ route('backoffice.suppliers.index') }}" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Batal
                    </a>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-bold text-white shadow-xs hover:bg-indigo-700 transition-colors">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
