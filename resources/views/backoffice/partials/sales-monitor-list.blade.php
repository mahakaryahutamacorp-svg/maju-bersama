<ul class="mt-5 space-y-2 sm:hidden">
    @forelse ($locations as $location)
        <li class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3">
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-slate-900">{{ $location['name'] }}</p>
                <p class="text-xs text-slate-500">{{ $location['receipt_count'] }} nota</p>
            </div>
            <p class="shrink-0 text-sm font-semibold text-slate-950">{{ $location['total_formatted'] }}</p>
        </li>
    @empty
        <li class="rounded-xl border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-400">Belum ada lokasi.</li>
    @endforelse
</ul>
<div class="mt-5 hidden overflow-x-auto sm:block">
    <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-900 text-xs font-semibold uppercase tracking-wider text-white">
            <tr>
                <th class="px-4 py-3 text-left">Lokasi</th>
                <th class="px-4 py-3 text-right">Total</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 [&>tr:nth-child(even)]:bg-slate-50/60">
            @forelse ($locations as $location)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <p class="text-sm font-semibold text-slate-900">{{ $location['name'] }}</p>
                        <p class="text-xs text-slate-500">{{ $location['receipt_count'] }} nota</p>
                    </td>
                    <td class="px-4 py-3 text-right text-sm font-semibold text-slate-950">{{ $location['total_formatted'] }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="px-4 py-8 text-center text-sm text-slate-400">Belum ada lokasi.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
