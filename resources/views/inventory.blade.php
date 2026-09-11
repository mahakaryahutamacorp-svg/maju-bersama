<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory | Maju Bersama ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <header class="border-b border-slate-800 bg-slate-950 text-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5 lg:px-8">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-400">Maju Bersama ERP</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight">Inventory</h1>
            </div>
            <div class="rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1 text-xs font-medium text-emerald-300">
                {{ $products->count() }} products
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl space-y-8 px-6 py-10 lg:px-8">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-amber-600">Stock control</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Product inventory</h2>
                <p class="mt-2 text-slate-500">Current product availability across the Pusat branch.</p>
            </div>
            <div class="text-sm text-slate-500">Updated {{ now()->format('d M Y, H:i') }}</div>
        </div>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">SKU</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Product</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Category</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Selling price</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Stock</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($products as $product)
                            <tr class="transition-colors hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-5 font-mono text-sm font-semibold text-sky-700">{{ $product->sku }}</td>
                                <td class="px-6 py-5">
                                    <div class="font-semibold text-slate-900">{{ $product->name }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $product->branch->name }}</div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-5">
                                    <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">{{ $product->category->name }}</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-5 text-right font-medium text-slate-900">Rp {{ number_format((float) $product->selling_price, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-6 py-5 text-right">
                                    <span class="font-semibold {{ $product->stock < 10 ? 'text-rose-600' : 'text-emerald-600' }}">{{ number_format($product->stock) }}</span>
                                    <span class="ml-1 text-xs text-slate-400">units</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-slate-500">No products found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>