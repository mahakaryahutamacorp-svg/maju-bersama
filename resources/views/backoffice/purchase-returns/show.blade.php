<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Retur {{ $purchaseReturn->reference_number }} | Maju Bersama ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <!-- Header Utama -->
    <header class="border-b border-slate-800 bg-slate-950 text-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
            <div class="flex items-center gap-4">
                <a href="/backoffice" class="block">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-400">Maju Bersama ERP</p>
                    <h1 class="text-xl font-bold tracking-tight">Pengadaan &amp; Pembelian</h1>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <nav class="hidden items-center gap-4 text-sm text-slate-300 md:flex">
                    <a href="{{ route('backoffice.purchase-returns.index') }}" class="hover:text-white">Riwayat Retur</a>
                    <a href="/backoffice" class="hover:text-white">Backoffice</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-6 py-8 lg:px-8 space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <a href="{{ route('backoffice.purchase-returns.index') }}" class="text-xs font-semibold text-rose-600 hover:underline">
                    &larr; Kembali ke Riwayat Retur
                </a>
                <h2 class="mt-1 text-2xl font-bold text-slate-950 font-mono">{{ $purchaseReturn->reference_number }}</h2>
            </div>
            <a href="javascript:window.print()" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50">
                Cetak Dokumen
            </a>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
            @include('backoffice.transactions.partials.purchase-return', ['purchaseReturn' => $purchaseReturn])
        </div>
    </main>
</body>
</html>
