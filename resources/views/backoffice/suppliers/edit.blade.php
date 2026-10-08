<x-app-layout :title="'Edit Supplier: '.($supplier->name)" :breadcrumbs="[
    ['label' => 'Belanja Barang'],
    ['label' => 'Buku Pemasok', 'url' => route('backoffice.suppliers.index')],
    ['label' => 'Edit Pemasok'],
]">
    <main class="mx-auto w-full px-6 py-6 lg:px-10 max-w-3xl">
        <div class="mb-6">
            <p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Belanja Barang</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Edit Supplier: {{ $supplier->name }}</h2>
        </div>

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
                        <label for="branch_id" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                            Cabang Terdaftar <span class="text-rose-500">*</span>
                        </label>
                        <select id="branch_id" name="branch_id" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" {{ old('branch_id', $supplier->branch_id) == $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }} ({{ $branch->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div>
                    <label for="name" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                        Nama Perusahaan / Supplier <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="name" name="name" value="{{ old('name', $supplier->name) }}" required placeholder="Contoh: PT Sumber Pangan Makmur" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="contact_person" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                            Nama Kontak (PIC)
                        </label>
                        <input type="text" id="contact_person" name="contact_person" value="{{ old('contact_person', $supplier->contact_person) }}" placeholder="Contoh: Bpk. Bambang" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                    </div>
                    <div>
                        <label for="phone" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                            Nomor Telepon / WhatsApp
                        </label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone', $supplier->phone) }}" placeholder="Contoh: 081234567890" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm font-mono focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                    </div>
                </div>

                <div>
                    <label for="address" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                        Alamat Lengkap
                    </label>
                    <textarea id="address" name="address" rows="3" placeholder="Alamat gudang / kantor supplier..." class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-sky-500 focus:ring-1 focus:ring-sky-500">{{ old('address', $supplier->address) }}</textarea>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $supplier->is_active) ? 'checked' : '' }} class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-sky-500">
                    <label for="is_active" class="text-sm font-medium text-slate-700 cursor-pointer">
                        Status Aktif (Supplier dapat dipilih saat pembuatan Purchase Order)
                    </label>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-6">
                    <a href="{{ route('backoffice.suppliers.index') }}" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Batal
                    </a>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-sky-600 px-6 py-2.5 text-sm font-bold text-white shadow-xs hover:bg-sky-700 transition-colors">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </main>
</x-app-layout>
