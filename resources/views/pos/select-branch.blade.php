<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Cabang Kasir | Maju Bersama ERP</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <main class="mx-auto flex min-h-screen w-full max-w-3xl flex-col justify-center px-6 py-10">
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-600">Maju Bersama ERP</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Kasir POS</h1>

        @if ($needsBranchAssignment)
            <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-6 text-amber-950">
                <p class="text-lg font-bold">Akun ini belum terhubung ke cabang.</p>
                <p class="mt-2 text-sm leading-relaxed">
                    Kasir hanya bisa menjual stok cabangnya sendiri. Minta admin pusat menautkan akun
                    <strong>{{ $currentUser->name }}</strong> ke salah satu cabang, lalu buka halaman ini lagi.
                </p>
                <a href="{{ route('dashboard') }}" class="mt-5 inline-flex rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white">Kembali ke beranda</a>
            </div>
        @else
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-lg font-bold text-slate-950">Pilih cabang yang akan dilayani.</p>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">
                    Akun pusat tidak punya cabang tetap, jadi mesin kasir tidak bisa dibuka sebelum cabang dipilih.
                    Stok, harga, dan pelanggan baru mengikuti cabang yang dipilih.
                </p>
                @if ($invalidBranch)
                    <p class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-800">Cabang yang dipilih tidak ditemukan.</p>
                @endif
                @if ($branches->isEmpty())
                    <p class="mt-4 text-sm font-semibold text-rose-700">Belum ada data cabang. Buat cabang terlebih dahulu.</p>
                @else
                    <ul class="mt-5 grid gap-3 sm:grid-cols-2">
                        @foreach ($branches as $branch)
                            <li>
                                <a href="{{ route('pos', ['branch_id' => $branch->id]) }}" class="block rounded-2xl border border-slate-200 px-4 py-4 transition hover:border-sky-400 hover:bg-sky-50">
                                    <span class="block text-sm font-bold text-slate-950">{{ $branch->name }}</span>
                                    <span class="mt-1 block text-xs font-mono text-slate-500">{{ $branch->code }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </main>
</body>
</html>
