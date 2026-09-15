<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Transfer | Maju Bersama ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body
    x-data="stockTransfer({
        token: @js($apiToken),
        stock: @js($stockLevels),
        defaultSource: @js($sourceBranches->first()?->id),
        defaultDestination: @js($destinationBranches->first()?->id),
    })"
    class="min-h-screen bg-slate-100 text-slate-900 antialiased"
>
    <header class="border-b border-slate-800 bg-slate-950 text-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5 lg:px-8">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-400">Maju Bersama ERP</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight">Stock Transfer</h1>
            </div>
            <div class="flex items-center gap-4">
                <nav class="hidden items-center gap-4 text-sm text-slate-300 md:flex">
                    <a href="/pos" class="hover:text-white">POS</a>
                    <a href="/inventory" class="hover:text-white">Inventory</a>
                    <a href="/inventory/transfer" class="font-semibold text-white">Transfer</a>
                    <a href="/reports/journal" class="hover:text-white">Ledger</a>
                </nav>
                <form method="POST" action="/logout" class="hidden sm:block">
                    @csrf
                    <button type="submit" class="text-sm text-slate-300 hover:text-white">Logout</button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl space-y-8 px-6 py-10 lg:px-8">
        <div>
            <p class="text-sm font-medium text-amber-600">Distribution</p>
            <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Move stock between branches</h2>
            <p class="mt-2 text-slate-500">
                Stock leaves the source branch and arrives at the destination branch in a single
                transaction. If any line fails, nothing is committed.
            </p>
        </div>

        <template x-if="feedback">
            <div
                class="rounded-xl border px-4 py-3 text-sm"
                :class="feedback.type === 'success'
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                    : 'border-rose-200 bg-rose-50 text-rose-800'"
                x-text="feedback.message"
            ></div>
        </template>

        <section class="grid gap-6 lg:grid-cols-[1fr_1.4fr]">
            <div class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Transfer details</h3>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Source branch</span>
                    <select x-model.number="sourceBranchId" @change="resetLines()" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-sky-500 focus:ring-2">
                        @foreach ($sourceBranches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Destination branch</span>
                    <select x-model.number="destinationBranchId" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-sky-500 focus:ring-2">
                        @foreach ($destinationBranches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Notes</span>
                    <textarea x-model="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-sky-500 focus:ring-2" placeholder="Optional"></textarea>
                </label>

                <button
                    type="button"
                    @click="submit()"
                    :disabled="submitting || lines.length === 0 || sourceBranchId === destinationBranchId"
                    class="w-full rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-300"
                >
                    <span x-show="!submitting">Execute transfer</span>
                    <span x-show="submitting" x-cloak>Processing...</span>
                </button>

                <p x-show="sourceBranchId === destinationBranchId" x-cloak class="text-xs text-rose-600">
                    Source and destination branch must be different.
                </p>
            </div>

            <div class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-slate-900">Items</h3>
                    <span class="text-xs text-slate-500" x-text="`${lines.length} line(s)`"></span>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row">
                    <select x-model.number="picker.productId" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-sky-500 focus:ring-2">
                        <option value="">Select product...</option>
                        <template x-for="option in availableStock" :key="option.product_id">
                            <option :value="option.product_id" x-text="`${option.sku} - ${option.name} (${option.quantity} on hand)`"></option>
                        </template>
                    </select>
                    <input x-model.number="picker.quantity" type="number" min="1" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-sky-500 focus:ring-2 sm:w-28" placeholder="Qty">
                    <button type="button" @click="addLine()" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-600">Add</button>
                </div>

                <p x-show="lineError" x-cloak class="text-xs text-rose-600" x-text="lineError"></p>

                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wider text-slate-500">
                            <th class="py-2">Product</th>
                            <th class="py-2 text-right">Qty</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(line, index) in lines" :key="line.product_id">
                            <tr>
                                <td class="py-3">
                                    <div class="font-medium text-slate-900" x-text="line.name"></div>
                                    <div class="font-mono text-xs text-slate-500" x-text="line.sku"></div>
                                </td>
                                <td class="py-3 text-right font-semibold" x-text="line.quantity"></td>
                                <td class="py-3 text-right">
                                    <button type="button" @click="lines.splice(index, 1)" class="text-xs text-rose-600 hover:underline">Remove</button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="lines.length === 0">
                            <td colspan="3" class="py-8 text-center text-slate-500">No items added yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Recent transfers</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Reference</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">From</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">To</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Lines</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($transfers as $transfer)
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-4 font-mono text-sm font-semibold text-sky-700">{{ $transfer->reference_number }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $transfer->transfer_date->format('d M Y') }}</td>
                                <td class="px-6 py-4 text-sm text-slate-900">{{ $transfer->sourceBranch->name }}</td>
                                <td class="px-6 py-4 text-sm text-slate-900">{{ $transfer->destinationBranch->name }}</td>
                                <td class="px-6 py-4 text-right text-sm font-semibold text-slate-900">{{ $transfer->items->count() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-slate-500">No transfers recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        function stockTransfer({ token, stock, defaultSource, defaultDestination }) {
            return {
                token,
                stock,
                sourceBranchId: defaultSource ?? null,
                destinationBranchId: defaultDestination ?? null,
                notes: '',
                lines: [],
                picker: { productId: '', quantity: 1 },
                submitting: false,
                feedback: null,
                lineError: '',

                get availableStock() {
                    return this.stock.filter((row) => row.branch_id === this.sourceBranchId);
                },

                resetLines() {
                    this.lines = [];
                    this.picker = { productId: '', quantity: 1 };
                },

                addLine() {
                    this.lineError = '';

                    const productId = Number(this.picker.productId);
                    const quantity = Number(this.picker.quantity);

                    if (!productId || quantity < 1) {
                        this.lineError = 'Select a product and enter a quantity of at least 1.';
                        return;
                    }

                    const source = this.availableStock.find((row) => row.product_id === productId);

                    if (!source) {
                        this.lineError = 'That product is not stocked at the source branch.';
                        return;
                    }

                    const existing = this.lines.find((line) => line.product_id === productId);
                    const requested = (existing?.quantity ?? 0) + quantity;

                    if (requested > source.quantity) {
                        this.lineError = `Only ${source.quantity} unit(s) available for ${source.name}.`;
                        return;
                    }

                    if (existing) {
                        existing.quantity = requested;
                    } else {
                        this.lines.push({
                            product_id: productId,
                            sku: source.sku,
                            name: source.name,
                            quantity,
                        });
                    }

                    this.picker = { productId: '', quantity: 1 };
                },

                async submit() {
                    if (this.submitting || this.lines.length === 0) {
                        return;
                    }

                    this.submitting = true;
                    this.feedback = null;

                    try {
                        const response = await fetch('/api/stock-transfers', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                Authorization: `Bearer ${this.token}`,
                            },
                            body: JSON.stringify({
                                source_branch_id: this.sourceBranchId,
                                destination_branch_id: this.destinationBranchId,
                                notes: this.notes || null,
                                items: this.lines.map((line) => ({
                                    product_id: line.product_id,
                                    quantity: line.quantity,
                                })),
                            }),
                        });

                        const payload = await response.json();

                        if (!response.ok) {
                            const firstError = payload.errors
                                ? Object.values(payload.errors).flat()[0]
                                : payload.message;

                            this.feedback = { type: 'error', message: firstError ?? 'Transfer failed.' };
                            return;
                        }

                        this.feedback = {
                            type: 'success',
                            message: `Transfer ${payload.reference_number} completed. Reloading...`,
                        };

                        window.setTimeout(() => window.location.reload(), 1200);
                    } catch (error) {
                        this.feedback = { type: 'error', message: 'Network error. Please try again.' };
                    } finally {
                        this.submitting = false;
                    }
                },
            };
        }
    </script>
</body>
</html>
