<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Retur Penjualan (Sales Return) | Maju Bersama ERP</title>
    <meta name="description" content="Formulir pengembalian barang dari pelanggan dan pengembalian dana (refund) dengan koreksi stok dan 4-baris jurnal akuntansi otomatis.">
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
                    <h1 class="text-xl font-bold tracking-tight">Penjualan &amp; Kasir</h1>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <nav class="hidden items-center gap-4 text-sm text-slate-300 md:flex">
                    <a href="/pos" class="hover:text-white">POS Kasir</a>
                    <a href="/inventory" class="hover:text-white">Inventory</a>
                    <a href="/backoffice" class="hover:text-white">Backoffice</a>
                </nav>
                @if ($isMaster)
                    <div class="rounded-full border border-amber-400/40 bg-amber-400/10 px-3 py-1 text-xs font-semibold text-amber-300">
                        👑 Master Pusat
                    </div>
                @else
                    <div class="rounded-full border border-sky-400/40 bg-sky-400/10 px-3 py-1 text-xs font-semibold text-sky-300">
                        🏪 {{ $currentUser->branch->name ?? 'Cabang' }}
                    </div>
                @endif
            </div>
        </div>
    </header>

    <!-- Sub-Navbar -->
    <div class="border-b border-slate-200 bg-white shadow-xs">
        <div class="mx-auto flex max-w-7xl items-center justify-between overflow-x-auto px-6 py-2.5 lg:px-8">
            <div class="flex items-center gap-2 text-sm font-medium">
                <a href="{{ route('backoffice.sales-returns.index') }}" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    &larr; Riwayat Retur Penjualan
                </a>
                <a href="{{ route('backoffice.sales-returns.create') }}" class="rounded-lg bg-rose-600 px-3.5 py-2 font-bold text-white shadow-xs">
                    + Input Retur Pelanggan
                </a>
                <a href="/pos" class="rounded-lg px-3.5 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Kasir POS
                </a>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <main class="mx-auto max-w-7xl px-6 py-8 lg:px-8">

        <!-- Error Alerts -->
        @if ($errors->any())
            <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900 shadow-xs">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <div>
                        <p class="text-sm font-bold">Harap perbaiki kesalahan input berikut:</p>
                        <ul class="mt-1 list-inside list-disc text-xs text-rose-800 space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        @if ($activeShift)
            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 shadow-xs flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="h-3 w-3 rounded-full bg-emerald-500 animate-pulse"></div>
                    <div>
                        <p class="text-xs font-bold text-emerald-950 uppercase tracking-wide">Shift Kasir Aktif (#{{ $activeShift->id }})</p>
                        <p class="text-xs text-emerald-800">
                            Refund tunai akan otomatis dipotongkan dari rekonsiliasi kas laci saat tutup shift.
                        </p>
                    </div>
                </div>
                <span class="inline-flex rounded-md bg-emerald-200 px-2.5 py-1 text-xs font-bold text-emerald-800 uppercase">
                    Laci Terhubung
                </span>
            </div>
        @endif

        <!-- Form Alpine.js Root -->
        <div x-data="salesReturnForm(@js($products), @js($accounts))" class="space-y-6">
            <form method="POST" action="{{ route('backoffice.sales-returns.store') }}" id="salesReturnFormElement" class="space-y-6">
                @csrf

                <!-- Judul Halaman -->
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-rose-600">Layanan Purna Jual &amp; Kasir</p>
                        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Input Retur Penjualan (Refund)</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Terima barang kembali dari konsumen, kembalikan stok fisik ke etalase/gudang, dan potong saldo kas/bank secara akurat.
                        </p>
                    </div>
                    <div>
                        <a href="{{ route('backoffice.sales-returns.index') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50">
                            Batal
                        </a>
                    </div>
                </div>

                <!-- Bagian 1: Header Form (Info Pelanggan & Metode Refund) -->
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                    <h3 class="text-base font-bold text-slate-900 mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-rose-100 text-xs font-bold text-rose-700">1</span>
                        Informasi Retur &amp; Pengembalian Dana
                    </h3>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        <!-- Tanggal Retur -->
                        <div>
                            <label for="return_date" class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                Tanggal Retur <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="date"
                                id="return_date"
                                name="return_date"
                                value="{{ old('return_date', $todayDate) }}"
                                required
                                class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-xs focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/20"
                            >
                        </div>

                        <!-- Info Pelanggan (Input Teks Bebas) -->
                        <div>
                            <label for="customer_name" class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                Info / Nama Pelanggan <span class="text-slate-400 font-normal">(Opsional)</span>
                            </label>
                            <input
                                type="text"
                                id="customer_name"
                                name="customer_name"
                                value="{{ old('customer_name') }}"
                                placeholder="Contoh: Bpk. Ahmad / Umum"
                                class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-xs focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/20"
                            >
                        </div>

                        <!-- Referensi Penjualan Asli / No Struk (Opsional) -->
                        <div>
                            <label for="sale_id" class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                Ref. Struk / Penjualan Asli <span class="text-slate-400 font-normal">(Opsional)</span>
                            </label>
                            <select
                                id="sale_id"
                                name="sale_id"
                                class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-xs focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/20"
                            >
                                <option value="">-- Tanpa Tautan Struk --</option>
                                @foreach ($recentSales as $sale)
                                    <option value="{{ $sale->id }}" {{ old('sale_id') == $sale->id ? 'selected' : '' }}>
                                        {{ $sale->receipt_number }} ({{ $sale->created_at->format('d/m/Y H:i') }} - Rp {{ number_format($sale->total_amount / 100, 0, ',', '.') }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Metode Refund (Dropdown) -->
                        <div>
                            <label for="refund_method" class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                Metode Refund <span class="text-rose-500">*</span>
                            </label>
                            <select
                                id="refund_method"
                                name="refund_method"
                                x-model="refundMethod"
                                @change="onRefundMethodChange()"
                                required
                                class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-xs focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/20"
                            >
                                <option value="cash">Kasir / Tunai (Laci Kas)</option>
                                <option value="transfer">Transfer Bank</option>
                            </select>
                        </div>

                        <!-- Akun Kas / Bank Pemotong Saldo -->
                        <div>
                            <label for="chart_of_account_id" class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                Akun Pemotong Saldo <span class="text-rose-500">*</span>
                            </label>
                            <select
                                id="chart_of_account_id"
                                name="chart_of_account_id"
                                x-model="selectedAccountId"
                                required
                                class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-xs focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/20"
                            >
                                <template x-for="acc in accounts" :key="acc.id">
                                    <option :value="acc.id" x-text="acc.code + ' - ' + acc.name"></option>
                                </template>
                            </select>
                        </div>

                        <!-- Alasan Retur -->
                        <div>
                            <label for="reason" class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                Alasan Retur / Catatan
                            </label>
                            <textarea
                                id="reason"
                                name="reason"
                                rows="1"
                                placeholder="Contoh: Salah beli ukuran, cacat fungsi, dll"
                                class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-xs focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/20"
                            >{{ old('reason') }}</textarea>
                        </div>
                    </div>
                </section>

                <!-- Bagian 2: Dynamic Items Table -->
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center mb-4 pb-2 border-b border-slate-100">
                        <div>
                            <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-rose-100 text-xs font-bold text-rose-700">2</span>
                                Daftar Barang Diretur Pelanggan
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">Barang yang dikembalikan akan langsung menambah kembali stok fisik di toko.</p>
                        </div>
                        <div>
                            <button
                                type="button"
                                @click="addItem()"
                                id="btn-add-item"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-rose-50 border border-rose-200 px-3.5 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100 transition shadow-2xs"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                + Tambah Barang
                            </button>
                        </div>
                    </div>

                    <!-- Tabel Item Dinamis -->
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600">
                                <tr>
                                    <th class="w-12 px-3 py-3 text-center">No</th>
                                    <th class="px-4 py-3 min-w-[280px]">Produk</th>
                                    <th class="w-32 px-3 py-3 text-right">Stok Saat Ini</th>
                                    <th class="w-36 px-3 py-3 text-right">Qty Retur</th>
                                    <th class="w-48 px-3 py-3 text-right">Harga Refund Satuan</th>
                                    <th class="w-48 px-4 py-3 text-right">Subtotal Refund</th>
                                    <th class="w-16 px-3 py-3 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <template x-for="(item, index) in items" :key="index">
                                    <tr class="hover:bg-slate-50/70 transition-colors">
                                        <!-- Nomor Urut -->
                                        <td class="px-3 py-3.5 text-center text-xs text-slate-400 font-mono" x-text="index + 1"></td>

                                        <!-- Dropdown Produk -->
                                        <td class="px-4 py-3.5">
                                            <select
                                                :name="'items[' + index + '][product_id]'"
                                                x-model="item.product_id"
                                                @change="onProductSelect(index)"
                                                required
                                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 shadow-2xs focus:border-rose-500 focus:outline-none focus:ring-1 focus:ring-rose-500"
                                            >
                                                <option value="">-- Pilih Produk --</option>
                                                <template x-for="prod in products" :key="prod.id">
                                                    <option :value="prod.id" x-text="prod.name + (prod.sku ? ' [' + prod.sku + ']' : '')"></option>
                                                </template>
                                            </select>
                                            <!-- Hidden Cost Input for COGS calc -->
                                            <input type="hidden" :name="'items[' + index + '][unit_cost]'" :value="item.unit_cost">
                                        </td>

                                        <!-- Info Stok Saat Ini -->
                                        <td class="px-3 py-3.5 text-right font-mono text-xs text-slate-500">
                                            <span x-text="getSelectedProductStock(item.product_id)"></span>
                                        </td>

                                        <!-- Input Qty Retur -->
                                        <td class="px-3 py-3.5">
                                            <input
                                                type="number"
                                                :name="'items[' + index + '][quantity]'"
                                                x-model.number="item.quantity"
                                                @input="calculateSubtotal(index)"
                                                min="1"
                                                required
                                                class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-right text-sm font-mono text-slate-900 shadow-2xs focus:border-rose-500 focus:outline-none focus:ring-1 focus:ring-rose-500"
                                            >
                                        </td>

                                        <!-- Input Harga Refund Satuan dengan Live Rupiah Formatter -->
                                        <td class="px-3 py-3.5">
                                            <div class="relative">
                                                <input
                                                    type="number"
                                                    step="any"
                                                    :name="'items[' + index + '][unit_price]'"
                                                    x-model.number="item.unit_price"
                                                    @input="calculateSubtotal(index)"
                                                    min="0"
                                                    required
                                                    class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-right text-sm font-mono text-slate-900 shadow-2xs focus:border-rose-500 focus:outline-none focus:ring-1 focus:ring-rose-500"
                                                >
                                                <div class="mt-0.5 text-[11px] text-right font-mono text-slate-500" x-text="formatRupiah(item.unit_price)"></div>
                                            </div>
                                        </td>

                                        <!-- Subtotal Otomatis -->
                                        <td class="px-4 py-3.5 text-right font-mono font-bold text-slate-900">
                                            <span x-text="formatRupiah(item.subtotal)"></span>
                                        </td>

                                        <!-- Hapus Baris -->
                                        <td class="px-3 py-3.5 text-center">
                                            <button
                                                type="button"
                                                @click="removeItem(index)"
                                                :disabled="items.length <= 1"
                                                :class="items.length <= 1 ? 'opacity-30 cursor-not-allowed text-slate-400' : 'text-rose-500 hover:text-rose-700 hover:bg-rose-50'"
                                                class="rounded-lg p-1.5 transition-colors"
                                                title="Hapus baris ini"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                            <!-- Footer: Grand Total Refund -->
                            <tfoot class="border-t-2 border-slate-300 bg-slate-50">
                                <tr>
                                    <td colspan="5" class="px-4 py-4 text-right font-bold uppercase tracking-wider text-slate-700 text-xs">
                                        Total Pengembalian Dana (Grand Total Refund):
                                    </td>
                                    <td class="px-4 py-4 text-right font-mono text-xl font-black text-rose-700">
                                        <span x-text="formatRupiah(grandTotal)"></span>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                <!-- Bagian 3: Live Journal Preview (4 Baris Berpasangan) -->
                <section class="rounded-2xl border border-rose-200 bg-gradient-to-br from-rose-50/60 to-white p-6 shadow-xs">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-rose-600 text-white text-xs font-bold">3</span>
                        <h4 class="text-sm font-bold text-slate-900 uppercase tracking-wide">Live Journal Preview (4 Baris Entri Akuntansi)</h4>
                    </div>
                    <p class="text-xs text-slate-600 mb-4">
                        Pencatatan pembukuan otomatis berpasangan: Mengurangi omset penjualan &amp; kas/bank, serta mengembalikan nilai buku persediaan &amp; memotong beban HPP:
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Pair 1: Sisi Pengembalian Dana Penjualan -->
                        <div class="rounded-xl border border-rose-200 bg-white p-4 space-y-3">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                                <span class="text-xs font-bold text-rose-900 uppercase">Sisi Kasir / Pendapatan</span>
                                <span class="text-xs font-bold font-mono text-rose-600" x-text="formatRupiah(grandTotal)"></span>
                            </div>
                            <div class="space-y-2 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-700"><strong>Debit:</strong> 4110 Pendapatan Penjualan</span>
                                    <span class="font-mono font-bold text-slate-900" x-text="formatRupiah(grandTotal)"></span>
                                </div>
                                <div class="flex items-center justify-between text-slate-600">
                                    <span><strong>Kredit:</strong> <span x-text="getSelectedAccountName()"></span></span>
                                    <span class="font-mono font-bold text-slate-900" x-text="formatRupiah(grandTotal)"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Pair 2: Sisi Persediaan & HPP -->
                        <div class="rounded-xl border border-emerald-200 bg-white p-4 space-y-3">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                                <span class="text-xs font-bold text-emerald-900 uppercase">Sisi Stok &amp; HPP Barang</span>
                                <span class="text-xs font-bold font-mono text-emerald-600" x-text="formatRupiah(grandTotalCost)"></span>
                            </div>
                            <div class="space-y-2 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-700"><strong>Debit:</strong> 1210 Persediaan Barang</span>
                                    <span class="font-mono font-bold text-slate-900" x-text="formatRupiah(grandTotalCost)"></span>
                                </div>
                                <div class="flex items-center justify-between text-slate-600">
                                    <span><strong>Kredit:</strong> 5100 Harga Pokok Penjualan (HPP)</span>
                                    <span class="font-mono font-bold text-slate-900" x-text="formatRupiah(grandTotalCost)"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Tombol Aksi -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="{{ route('backoffice.sales-returns.index') }}" class="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50">
                        Batal
                    </a>
                    <button
                        type="submit"
                        id="btn-submit-sales-return"
                        :disabled="grandTotal <= 0"
                        :class="grandTotal <= 0 ? 'opacity-50 cursor-not-allowed bg-slate-400' : 'bg-rose-600 hover:bg-rose-700 shadow-md'"
                        class="inline-flex items-center gap-2 rounded-xl px-8 py-3 text-sm font-bold text-white transition focus:ring-2 focus:ring-rose-500 focus:ring-offset-2"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Proses Retur Penjualan &amp; Refund
                    </button>
                </div>
            </form>
        </div>
    </main>

    <!-- Alpine.js Component Script -->
    <script>
        function salesReturnForm(productsList, accountsList) {
            return {
                products: productsList || [],
                accounts: accountsList || [],
                refundMethod: 'cash',
                selectedAccountId: '',
                items: [
                    { product_id: '', quantity: 1, unit_price: 0, unit_cost: 0, subtotal: 0, subtotal_cost: 0 }
                ],
                init() {
                    this.onRefundMethodChange();
                    this.recalculateAll();
                },
                addItem() {
                    this.items.push({
                        product_id: '',
                        quantity: 1,
                        unit_price: 0,
                        unit_cost: 0,
                        subtotal: 0,
                        subtotal_cost: 0
                    });
                },
                removeItem(index) {
                    if (this.items.length > 1) {
                        this.items.splice(index, 1);
                    }
                },
                onRefundMethodChange() {
                    if (this.refundMethod === 'cash') {
                        const cashAcc = this.accounts.find(a => a.code === '1110' || a.name.toLowerCase().includes('kas'));
                        if (cashAcc) {
                            this.selectedAccountId = cashAcc.id;
                        } else if (this.accounts.length > 0) {
                            this.selectedAccountId = this.accounts[0].id;
                        }
                    } else {
                        const bankAcc = this.accounts.find(a => a.code === '1120' || a.name.toLowerCase().includes('bank'));
                        if (bankAcc) {
                            this.selectedAccountId = bankAcc.id;
                        } else if (this.accounts.length > 0) {
                            this.selectedAccountId = this.accounts[0].id;
                        }
                    }
                },
                onProductSelect(index) {
                    const selectedId = this.items[index].product_id;
                    const product = this.products.find(p => p.id == selectedId);
                    if (product) {
                        this.items[index].unit_price = parseFloat(product.selling_price) || 0;
                        this.items[index].unit_cost = parseFloat(product.purchase_price) || 0;
                    }
                    this.calculateSubtotal(index);
                },
                calculateSubtotal(index) {
                    const item = this.items[index];
                    const qty = Math.max(0, parseInt(item.quantity) || 0);
                    const price = Math.max(0, parseFloat(item.unit_price) || 0);
                    const cost = Math.max(0, parseFloat(item.unit_cost) || 0);
                    item.subtotal = qty * price;
                    item.subtotal_cost = qty * cost;
                },
                recalculateAll() {
                    this.items.forEach((_, i) => this.calculateSubtotal(i));
                },
                get grandTotal() {
                    return this.items.reduce((sum, item) => sum + (parseFloat(item.subtotal) || 0), 0);
                },
                get grandTotalCost() {
                    return this.items.reduce((sum, item) => sum + (parseFloat(item.subtotal_cost) || 0), 0);
                },
                formatRupiah(amount) {
                    return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount || 0);
                },
                getSelectedAccountName() {
                    const acc = this.accounts.find(a => a.id == this.selectedAccountId);
                    return acc ? (acc.code + ' ' + acc.name) : 'Kas/Bank';
                },
                getSelectedProductStock(productId) {
                    if (!productId) return '-';
                    const product = this.products.find(p => p.id == productId);
                    return product ? (product.stock ?? 0) : '-';
                }
            };
        }
    </script>
</body>
</html>
