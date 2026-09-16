<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Akun Pengguna - {{ $user->name }} | Maju Bersama ERP</title>
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
                <div class="rounded-full border border-sky-400/30 bg-sky-400/10 px-3 py-1 text-xs font-medium text-sky-200">
                    {{ $currentUser->branch?->name ?? 'Pusat' }} ({{ $currentUser->role }})
                </div>
            </div>
        </div>
    </header>

    <!-- Sub-Navbar Master Data -->
    <div class="border-b border-slate-200 bg-white shadow-sm">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-2.5 lg:px-8">
            <div class="flex items-center gap-2 text-sm font-medium">
                <a href="{{ route('backoffice.users.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    ← Kembali ke Daftar Staf
                </a>
                <span class="text-slate-300">/</span>
                <span class="font-semibold text-slate-900">Edit Pengguna: {{ $user->name }}</span>
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
                    <h2 class="text-lg font-bold text-slate-900">Perbarui Informasi Akun</h2>
                    <p class="text-xs text-slate-500 mt-1">Ubah nama, email, role, atau atur ulang password pengguna.</p>
                </div>
                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $user->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                    {{ $user->is_active ? 'Status Aktif' : 'Non-aktif' }}
                </span>
            </div>

            <form method="POST" action="{{ route('backoffice.users.update', $user->id) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <!-- Nama Lengkap -->
                <div>
                    <label for="name" class="block text-sm font-semibold text-slate-800">
                        Nama Lengkap <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                </div>

                <!-- Email Login -->
                <div>
                    <label for="email" class="block text-sm font-semibold text-slate-800">
                        Alamat Email <span class="text-rose-500">*</span>
                    </label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                </div>

                <!-- Password Baru (Opsional) -->
                <div>
                    <label for="password" class="block text-sm font-semibold text-slate-800">
                        Kata Sandi Baru (Opsional)
                    </label>
                    <input type="password" id="password" name="password" minlength="6" placeholder="Biarkan kosong jika tidak ingin mengubah password" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                    <p class="text-[11px] text-slate-400 mt-1">Kosongkan jika password saat ini tetap ingin digunakan.</p>
                </div>

                <!-- Role / Hak Akses -->
                <div>
                    <label for="role" class="block text-sm font-semibold text-slate-800">
                        Role / Hak Akses <span class="text-rose-500">*</span>
                    </label>
                    <select id="role" name="role" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                        <option value="cashier" {{ old('role', $user->role) === 'cashier' ? 'selected' : '' }}>Kasir POS</option>
                        <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Admin Cabang</option>
                        @if ($isMaster)
                            <option value="master" {{ old('role', $user->role) === 'master' ? 'selected' : '' }}>Master / Superadmin</option>
                        @endif
                    </select>
                </div>

                <!-- Cabang Penempatan -->
                <div>
                    <label class="block text-sm font-semibold text-slate-800">
                        Cabang Penempatan
                    </label>
                    @if ($isMaster)
                        <select id="branch_id" name="branch_id" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                            <option value="">Kantor Pusat (Tanpa cabang khusus)</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" {{ old('branch_id', $user->branch_id) == $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }} ({{ $branch->code }})
                                </option>
                            @endforeach
                        </select>
                    @else
                        <input type="text" disabled value="{{ $user->branch?->name }} ({{ $user->branch?->code }})" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-2.5 text-sm text-slate-600">
                    @endif
                </div>

                <!-- Status Aktif Checkbox -->
                <div class="flex items-center gap-3 rounded-xl bg-slate-50 p-4 border border-slate-200">
                    <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $user->is_active) ? 'checked' : '' }} class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                    <label for="is_active" class="text-sm font-medium text-slate-700 select-none">
                        Akun Pengguna Aktif (Bisa login ke sistem POS / Backoffice)
                    </label>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
                    <a href="{{ route('backoffice.users.index') }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                        Batal
                    </a>
                    <button type="submit" class="rounded-xl bg-sky-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-500 transition-colors">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
