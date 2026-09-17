<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk | Maju Bersama POS &amp; Akuntansi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-900 antialiased">
    <main class="flex min-h-screen items-center justify-center px-6 py-12">
        <div class="grid w-full max-w-5xl overflow-hidden rounded-3xl bg-white shadow-2xl lg:grid-cols-[1.05fr_0.95fr]">
            <section class="hidden bg-sky-700 p-12 text-white lg:flex lg:flex-col lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-sky-200">Maju Bersama ERP</p>
                    <h1 class="mt-8 max-w-md text-5xl font-bold leading-tight">Satu ruang kerja untuk pusat dan seluruh cabang.</h1>
                </div>
                <div class="border-t border-sky-500 pt-6 text-sm text-sky-100">
                    <p>Masuk menggunakan akun cabang untuk membuka Kasir (POS), Persediaan, dan Buku Besar sesuai hak akses.</p>
                </div>
            </section>

            <section class="p-8 sm:p-12">
                <a href="/" class="text-sm font-semibold text-sky-700 hover:text-sky-800">&larr; Beranda Maju Bersama ERP</a>
                <div class="mt-12">
                    <p class="text-sm font-medium text-amber-600">Akses Sistem</p>
                    <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Masuk ke aplikasi</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-500">Gunakan akun pusat atau akun cabang untuk melanjutkan.</p>
                </div>

                @if ($errors->any())
                    <div class="mt-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="/login" class="mt-8 space-y-5">
                    @csrf
                    <div>
                        <label for="email" class="text-sm font-semibold text-slate-700">Alamat Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none ring-sky-500 focus:ring-2" placeholder="nama@majubersama.test">
                    </div>
                    <div>
                        <label for="password" class="text-sm font-semibold text-slate-700">Kata Sandi</label>
                        <input id="password" name="password" type="password" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none ring-sky-500 focus:ring-2" placeholder="Masukkan kata sandi">
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-500">
                        <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 text-sky-600">
                        Ingat saya
                    </label>
                    <button type="submit" class="w-full rounded-xl bg-sky-600 px-4 py-3 font-bold text-white transition hover:bg-sky-700">Masuk</button>
                </form>
            </section>
        </div>
    </main>
</body>
</html>