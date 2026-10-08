<x-app-layout title="Tambah Staf & Kasir Baru" :breadcrumbs="[
    ['label' => 'Pengaturan'],
    ['label' => 'Staf & Kasir', 'url' => route('backoffice.users.index')],
    ['label' => 'Tambah Staf'],
]">
    <main class="mx-auto w-full px-6 py-6 lg:px-10 max-w-2xl">
        <div class="mb-6">
            <p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Pengaturan</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Tambah Staf &amp; Kasir Baru</h2>
        </div>

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
            <div class="border-b border-slate-100 pb-5 mb-6">
                <h2 class="text-lg font-bold text-slate-900">Daftarkan Akun Pengguna Baru</h2>
                <p class="text-xs text-slate-500 mt-1">Buat akun untuk kasir toko POS atau administrator cabang operasional.</p>
            </div>

            <form method="POST" action="{{ route('backoffice.users.store') }}" class="space-y-6">
                @csrf

                <!-- Nama Lengkap -->
                <div>
                    <label for="name" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                        Nama Lengkap <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required placeholder="Contoh: Budi Santoso" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                </div>

                <!-- Email Login -->
                <div>
                    <label for="email" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                        Alamat Email (Digunakan untuk Login) <span class="text-rose-500">*</span>
                    </label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required placeholder="Contoh: budi.kasir@majubersama.online" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                        Kata Sandi (Password) <span class="text-rose-500">*</span>
                    </label>
                    <input type="password" id="password" name="password" required minlength="6" placeholder="Minimal 6 karakter" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                    <p class="text-[11px] text-slate-400 mt-1">Kata sandi akan otomatis dienkripsi secara aman menggunakan algoritma Bcrypt.</p>
                </div>

                <!-- Role / Hak Akses -->
                <div>
                    <label for="role" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                        Role / Hak Akses <span class="text-rose-500">*</span>
                    </label>
                    <select id="role" name="role" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                        <option value="cashier" {{ old('role') === 'cashier' ? 'selected' : '' }}>Kasir POS (Hanya akses kasir POS toko)</option>
                        <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Admin Cabang (Akses Backoffice cabang &amp; POS)</option>
                        @if ($isMaster)
                            <option value="master" {{ old('role') === 'master' ? 'selected' : '' }}>Master / Superadmin (Akses penuh seluruh cabang)</option>
                        @endif
                    </select>
                </div>

                <!-- Cabang Penempatan -->
                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                        Cabang Penempatan <span class="text-rose-500">*</span>
                    </label>
                    @if ($isMaster)
                        <select id="branch_id" name="branch_id" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                            <option value="">Kantor Pusat (Tanpa cabang khusus)</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }} ({{ $branch->code }})
                                </option>
                            @endforeach
                        </select>
                    @else
                        <input type="text" disabled value="{{ $currentUser->branch?->name }} ({{ $currentUser->branch?->code }})" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-2.5 text-sm text-slate-600">
                        <input type="hidden" name="branch_id" value="{{ $currentUser->branch_id }}">
                        <p class="text-[11px] text-slate-400 mt-1">Admin cabang hanya dapat mendaftarkan staf untuk cabangnya sendiri.</p>
                    @endif
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
                    <a href="{{ route('backoffice.users.index') }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                        Batal
                    </a>
                    <button type="submit" class="rounded-xl bg-sky-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-500 transition-colors">
                        Simpan Pengguna
                    </button>
                </div>
            </form>
        </div>
    </main>
</x-app-layout>
