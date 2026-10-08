<x-app-layout title="Edit Pelanggan" :breadcrumbs="[
    ['label' => 'Jualan'],
    ['label' => 'Data Pelanggan', 'url' => route('backoffice.customers.index')],
    ['label' => 'Edit Pelanggan'],
]">
    <main class="mx-auto w-full px-6 py-6 lg:px-10 max-w-3xl space-y-6">
        <div class="mb-6">
            <p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Jualan</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Edit Data Pelanggan</h2>
        </div>

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-rose-900 shadow-xs">
                <p class="text-sm font-bold">Harap perbaiki kesalahan berikut:</p>
                <ul class="mt-1 list-disc list-inside text-xs text-rose-700 space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm">
            <div class="border-b border-slate-100 pb-5 mb-6">
                <h2 class="text-lg font-bold text-slate-900">Ubah Data Pelanggan: {{ $customer->name }}</h2>
                <p class="text-xs text-slate-500 mt-1">Perbarui biodata dan grup harga khusus (Pricing Tier) pelanggan ini.</p>
            </div>

            <form method="POST" action="{{ route('backoffice.customers.update', $customer->id) }}" class="space-y-5">
                @csrf
                @method('PUT')

                <!-- Nama Lengkap -->
                <div>
                    <label for="name" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">Nama Lengkap Pelanggan <span class="text-rose-500">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', $customer->name) }}" required class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 focus:outline-none">
                </div>

                <!-- Dropdown Pilihan CustomerGroup (Pricing Tier) -->
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4">
                    <label for="customer_group_id" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                        Grup Pelanggan (Pricing Tier Multi-Price)
                    </label>
                    <select id="customer_group_id" name="customer_group_id" class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-900 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 focus:outline-none">
                        <option value="">-- Tanpa Grup (Harga Dasar / Umum) --</option>
                        @foreach ($customerGroups as $cg)
                            <option value="{{ $cg->id }}" {{ (string)old('customer_group_id', $customer->customer_group_id) === (string)$cg->id ? 'selected' : '' }}>
                                {{ $cg->name }} {{ $cg->notes ? '— ' . $cg->notes : '' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-emerald-700 mt-1.5">
                        Saat pelanggan ini bertransaksi di kasir POS, sistem otomatis menerapkan harga khusus grup yang telah ditetapkan pada katalog produk.
                    </p>
                </div>

                <!-- Kontak: Telepon & Email -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="phone" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">No. Telepon / WhatsApp</label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone', $customer->phone) }}" class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="email" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">Alamat Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email', $customer->email) }}" class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 focus:outline-none">
                    </div>
                </div>

                <!-- Alamat Domisili -->
                <div>
                    <label for="address" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">Alamat Lengkap</label>
                    <textarea id="address" name="address" rows="3" class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 focus:outline-none">{{ old('address', $customer->address) }}</textarea>
                </div>

                <!-- Cabang (Jika Master) -->
                @if ($isMaster)
                    <div>
                        <label for="branch_id" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">Cabang Domisili</label>
                        <select id="branch_id" name="branch_id" class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 focus:outline-none">
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}" {{ (string)old('branch_id', $customer->branch_id) === (string)$b->id ? 'selected' : '' }}>
                                    {{ $b->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="flex items-center justify-end gap-3 pt-5 border-t border-slate-100">
                    <a href="{{ route('backoffice.customers.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                        Batal
                    </a>
                    <button type="submit" class="rounded-lg bg-sky-600 px-5 py-2.5 text-xs font-bold text-white shadow-xs hover:bg-sky-500 transition">
                        Perbarui Data Pelanggan
                    </button>
                </div>
            </form>
        </div>
    </main>
</x-app-layout>
