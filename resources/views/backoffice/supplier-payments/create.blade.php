<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entri Pembayaran Supplier | Maju Bersama ERP</title>
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
                    <h1 class="text-xl font-bold tracking-tight">Pengadaan &amp; Keuangan</h1>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <nav class="hidden items-center gap-4 text-sm text-slate-300 md:flex">
                    <a href="/pos" class="hover:text-white">POS Kasir</a>
                    <a href="/inventory" class="hover:text-white">Inventory</a>
                    <a href="{{ route('backoffice.purchase-orders.index') }}" class="hover:text-white">Purchase Order</a>
                    <a href="{{ route('backoffice.supplier-payments.index') }}" class="text-amber-400 font-semibold">Pembayaran Supplier</a>
                    <a href="/reports/accounting/ledger" class="hover:text-white">Akuntansi</a>
                    <a href="/backoffice" class="hover:text-white">Backoffice</a>
                </nav>
                <div class="rounded-full border border-sky-400/30 bg-sky-400/10 px-3 py-1 text-xs font-medium text-sky-200">
                    {{ $currentUser->branch?->name ?? 'Pusat' }} ({{ $currentUser->role }})
                </div>
                <form method="POST" action="/logout" class="hidden sm:block">
                    @csrf
                    <button type="submit" class="text-sm text-slate-300 hover:text-white">Logout</button>
                </form>
            </div>
        </div>
    </header>

    <!-- Sub-Navbar Modul Pembayaran -->
    <div class="border-b border-slate-200 bg-white shadow-xs">
        <div class="mx-auto flex max-w-7xl items-center justify-between overflow-x-auto px-6 py-2.5 lg:px-8">
            <div class="flex items-center gap-2 text-sm font-medium">
                <a href="{{ route('backoffice.supplier-payments.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    &larr; Riwayat Pembayaran
                </a>
                <a href="{{ route('backoffice.supplier-payments.create') }}" class="rounded-lg bg-amber-500 px-3.5 py-2 font-bold text-slate-950 shadow-xs">
                    + Entri Pembayaran Baru
                </a>
                <a href="{{ route('backoffice.purchase-orders.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Purchase Order (PO)
                </a>
                <a href="/purchases/goods-receipts" class="rounded-lg px-3.5 py-2 text-emerald-700 hover:bg-emerald-50">
                    Penerimaan Barang (GR)
                </a>
                <a href="{{ route('backoffice.suppliers.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Data Supplier
                </a>
            </div>
        </div>
    </div>

    <main class="mx-auto max-w-5xl space-y-6 px-6 py-8 lg:px-8">
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

        <!-- Judul Form -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-amber-600">Pelunasan Hutang Usaha</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Formulir Pembayaran Supplier</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Catat pengeluaran dana kas/bank untuk pelunasan hutang dagang kepada supplier beserta otomatisasi jurnal akuntansinya.
                </p>
            </div>
            <div>
                <a href="{{ route('backoffice.supplier-payments.index') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50">
                    Batal
                </a>
            </div>
        </div>

        <!-- Form Alpine.js Root -->
        <div x-data="paymentForm({{ old('amount', 0) }})" class="space-y-6">
            <form method="POST" action="{{ route('backoffice.supplier-payments.store') }}" id="paymentFormElement" class="space-y-6">
                @csrf

                <!-- Card Utama: Informasi Pembayaran -->
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs sm:p-8">
                    <h3 class="text-base font-bold text-slate-950 border-b border-slate-100 pb-3.5 flex items-center gap-2">
                        <span class="flex h-6 w-6 items-center justify-center rounded-md bg-slate-900 text-xs font-bold text-amber-400">1</span>
                        Rincian Transaksi Pengeluaran Dana
                    </h3>

                    <div class="mt-6 grid gap-6 sm:grid-cols-2">
                        <!-- Tanggal Pembayaran -->
                        <div>
                            <label for="payment_date" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Tanggal Pembayaran <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="date"
                                id="payment_date"
                                name="payment_date"
                                value="{{ old('payment_date', $todayDate) }}"
                                required
                                class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-xs outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                            >
                        </div>

                        <!-- Supplier / Vendor -->
                        <div>
                            <label for="supplier_id" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Supplier / Vendor Penerima <span class="text-rose-500">*</span>
                            </label>
                            @if ($suppliers->isEmpty())
                                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                                    Belum ada data supplier aktif. Silakan <a href="{{ route('backoffice.suppliers.create') }}" class="font-bold underline text-amber-900">tambah supplier baru</a> terlebih dahulu.
                                </div>
                            @else
                                <select
                                    id="supplier_id"
                                    name="supplier_id"
                                    x-model="selectedSupplierId"
                                    @change="onSupplierChange($event)"
                                    required
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-xs outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                >
                                    <option value="">-- Pilih Supplier --</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                            {{ $supplier->name }} {{ $supplier->phone ? '('.$supplier->phone.')' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                        </div>

                        <!-- Sumber Dana / Rekening -->
                        <div>
                            <label for="chart_of_account_id" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Sumber Dana / Rekening Pembayar <span class="text-rose-500">*</span>
                            </label>
                            <select
                                id="chart_of_account_id"
                                name="chart_of_account_id"
                                x-model="selectedAccountId"
                                required
                                class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-xs outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                            >
                                <option value="">-- Pilih Akun Kas / Bank --</option>
                                @foreach ($accounts as $acc)
                                    <option value="{{ $acc->id }}" {{ old('chart_of_account_id') == $acc->id ? 'selected' : '' }}>
                                        {{ $acc->code }} - {{ $acc->name }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="mt-1 block text-[11px] text-slate-400">Akun aset lancar yang akan dikreditkan (berkurang)</span>
                        </div>

                        <!-- Metode Pembayaran -->
                        <div>
                            <label for="payment_method" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Metode Pembayaran <span class="text-rose-500">*</span>
                            </label>
                            <select
                                id="payment_method"
                                name="payment_method"
                                x-model="paymentMethod"
                                required
                                class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-xs outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                            >
                                <option value="Transfer" {{ old('payment_method') == 'Transfer' ? 'selected' : '' }}>Transfer Bank</option>
                                <option value="Cash" {{ old('payment_method') == 'Cash' ? 'selected' : '' }}>Cash / Tunai Kas</option>
                                <option value="Giro" {{ old('payment_method') == 'Giro' ? 'selected' : '' }}>Giro / Cek</option>
                            </select>
                        </div>

                        <!-- Jumlah Bayar / Nominal (Live Rupiah Formatter) -->
                        <div class="sm:col-span-2">
                            <label for="display_amount" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Jumlah Bayar / Nominal (Rp) <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-base font-extrabold text-slate-400 font-mono">Rp</span>
                                <input
                                    type="text"
                                    id="display_amount"
                                    x-model="formattedAmount"
                                    @input="formatInput($event)"
                                    placeholder="0"
                                    required
                                    class="w-full rounded-xl border border-amber-300 bg-amber-50/20 pl-12 pr-4 py-3 text-lg font-mono font-extrabold text-slate-950 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-400 transition"
                                >
                            </div>
                            <!-- Hidden input untuk nilai numerik murni -->
                            <input type="hidden" name="amount" :value="rawAmount">

                            <div class="mt-1.5 flex items-center justify-between text-xs text-slate-500">
                                <span>Ketik angka tanpa titik atau koma, sistem otomatis memformat.</span>
                                <span x-show="rawAmount > 0" class="font-medium text-amber-800">
                                    Nominal terinput: <strong class="font-mono text-slate-900" x-text="formatRupiah(rawAmount)"></strong>
                                </span>
                            </div>
                        </div>

                        <!-- Nomor Referensi / Bukti Transfer -->
                        <div>
                            <label for="reference_number" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Nomor Referensi / Bukti Transfer
                            </label>
                            <input
                                type="text"
                                id="reference_number"
                                name="reference_number"
                                value="{{ old('reference_number') }}"
                                placeholder="Contoh: TRF-BCA-882910..."
                                class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-xs outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                            >
                            <span class="mt-1 block text-[11px] text-slate-400">Kosongkan untuk nomor referensi otomatis sistem</span>
                        </div>

                        <!-- Catatan Pembayaran -->
                        <div class="sm:col-span-2">
                            <label for="notes" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Catatan / Keterangan Pembayaran
                            </label>
                            <textarea
                                id="notes"
                                name="notes"
                                rows="3"
                                placeholder="Contoh: Pelunasan invoice PO-2026-001 pengiriman beras, transfer melalui rekening operasional..."
                                class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-xs outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                            >{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </section>

                <!-- Card 2: Preview Dampak Jurnal Akuntansi Berpasangan -->
                <section class="rounded-2xl border border-indigo-200 bg-indigo-50/40 p-6 shadow-xs">
                    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-xs">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-indigo-950">Otomatisasi Jurnal Pelunasan Hutang (Double-Entry)</h4>
                                <p class="text-xs text-indigo-800 mt-0.5">
                                    Saat pembayaran disimpan, sistem akan secara otomatis membukukan jurnal akuntansi berpasangan berikut:
                                </p>
                            </div>
                        </div>

                        <!-- Baris Jurnal Live Preview -->
                        <div class="flex flex-wrap items-center gap-3 text-xs font-mono">
                            <div class="rounded-xl border border-emerald-300 bg-white p-2.5 text-left shadow-xs">
                                <span class="block text-[10px] font-bold text-emerald-700 font-sans uppercase">Debit (Hutang Dagang 2110)</span>
                                <span class="font-bold text-emerald-950" x-text="formatRupiah(rawAmount)"></span>
                            </div>
                            <span class="text-slate-400 font-sans font-bold text-lg">=</span>
                            <div class="rounded-xl border border-rose-300 bg-white p-2.5 text-left shadow-xs">
                                <span class="block text-[10px] font-bold text-rose-700 font-sans uppercase">Kredit (Kas / Bank Sumber Dana)</span>
                                <span class="font-bold text-rose-950" x-text="formatRupiah(rawAmount)"></span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Tombol Aksi Form -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="{{ route('backoffice.supplier-payments.index') }}" class="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition-colors">
                        Batal
                    </a>
                    <button
                        type="submit"
                        id="btn-submit-payment"
                        class="inline-flex items-center gap-2 rounded-xl bg-amber-500 px-7 py-3 text-sm font-bold text-slate-950 shadow-md transition hover:bg-amber-400 focus:ring-2 focus:ring-amber-400 focus:ring-offset-2"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Simpan &amp; Bukukan Pembayaran
                    </button>
                </div>
            </form>
        </div>
    </main>

    <!-- Alpine.js Component Script -->
    <script>
        function paymentForm(initialAmount = 0) {
            return {
                rawAmount: initialAmount || 0,
                formattedAmount: initialAmount > 0 ? new Intl.NumberFormat('id-ID').format(initialAmount) : '',
                selectedSupplierId: '{{ old("supplier_id", "") }}',
                selectedAccountId: '{{ old("chart_of_account_id", "") }}',
                paymentMethod: '{{ old("payment_method", "Transfer") }}',

                formatInput(event) {
                    // Hanya izinkan angka
                    let value = event.target.value.replace(/[^\d]/g, '');
                    this.rawAmount = value ? parseInt(value, 10) : 0;
                    this.formattedAmount = value ? new Intl.NumberFormat('id-ID').format(this.rawAmount) : '';
                },

                formatRupiah(val) {
                    return 'Rp ' + new Intl.NumberFormat('id-ID').format(val || 0);
                },

                onSupplierChange(event) {
                    // Optional reactive behavior if needed
                }
            };
        }
    </script>
</body>
</html>
