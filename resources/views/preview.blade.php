<x-app-layout title="Pratinjau Sistem" :breadcrumbs="[
    ['label' => 'Pengaturan'],
    ['label' => 'Preview Data Sistem'],
]">
    <main class="mx-auto w-full px-6 py-6 lg:px-10 max-w-7xl space-y-8">
        <div>
            <p class="text-sm font-medium text-amber-600">Ringkasan Sistem</p>
            <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Pratinjau Data Awal Sistem</h2>
            <p class="mt-2 text-slate-500">Pantau data cabang, pengguna administratif, dan bagan akun akuntansi.</p>
        </div>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                <div>
                    <h3 class="text-lg font-semibold text-slate-950">Daftar Cabang &amp; Pengguna Admin</h3>
                    <p class="mt-1 text-sm text-slate-500">Lokasi cabang terdaftar dan staf penanggung jawab.</p>
                </div>
                <span class="rounded-full bg-amber-50 px-3 py-1 text-sm font-semibold text-amber-700">{{ $branches->count() }} cabang</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-900 text-xs font-semibold uppercase tracking-wider text-white">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Cabang</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Kode Cabang</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Pengguna Admin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white [&>tr:nth-child(even)]:bg-slate-50/60">
                        @forelse ($branches as $branch)
                            <tr class="transition-colors hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-semibold text-slate-900">{{ $branch->name }}</td>
                                <td class="whitespace-nowrap px-6 py-4 font-mono text-sm text-slate-500">{{ $branch->code }}</td>
                                <td class="px-6 py-4">
                                    @forelse ($branch->users as $user)
                                        <div class="mb-2 last:mb-0">
                                            <p class="text-sm font-medium text-slate-800">{{ $user->name }}</p>
                                            <p class="text-xs text-slate-500">{{ $user->email }} &middot; {{ str_replace('_', ' ', $user->role) }}</p>
                                        </div>
                                    @empty
                                        <span class="text-sm text-slate-400">Belum ada pengguna ditugaskan</span>
                                    @endforelse
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-6 py-8 text-center text-sm text-slate-400">Tidak ada cabang ditemukan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                <div>
                    <h3 class="text-lg font-semibold text-slate-950">Bagan Akun (Chart of Accounts)</h3>
                    <p class="mt-1 text-sm text-slate-500">Daftar akun buku besar untuk pembukuan keuangan berpasangan.</p>
                </div>
                <span class="rounded-full bg-sky-50 px-3 py-1 text-sm font-semibold text-sky-700">{{ $chartOfAccounts->count() }} akun</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-900 text-xs font-semibold uppercase tracking-wider text-white">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Kode Akun</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Nama Akun</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Klasifikasi Akun</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white [&>tr:nth-child(even)]:bg-slate-50/60">
                        @forelse ($chartOfAccounts as $account)
                            <tr class="transition-colors hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-4 font-mono text-sm font-semibold text-sky-700">{{ $account->code }}</td>
                                <td class="px-6 py-4 text-sm text-slate-800">{{ $account->name }}</td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium capitalize text-slate-600">
                                        @switch(strtolower($account->type))
                                            @case('asset') Aset @break
                                            @case('liability') Kewajiban @break
                                            @case('equity') Ekuitas @break
                                            @case('revenue') Pendapatan @break
                                            @case('expense') Beban @break
                                            @default {{ $account->type }}
                                        @endswitch
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-6 py-8 text-center text-sm text-slate-400">Tidak ada bagan akun ditemukan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</x-app-layout>
