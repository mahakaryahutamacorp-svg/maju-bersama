<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="pos-token" content="{{ $previewToken }}">
    <title>POS | Maju Bersama ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <div x-data="posCart({{ $products->toJson() }})" class="min-h-screen">
        <header class="border-b border-slate-800 bg-slate-950 text-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5 lg:px-8">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-400">Maju Bersama ERP</p>
                        {{ auth()->user()->branch->name }}
                </div>
                    <form method="POST" action="/logout" class="hidden sm:block">
                        @csrf
                        <button type="submit" class="text-sm text-slate-300 hover:text-white">Logout</button>
                    </form>
                <div class="flex items-center gap-4">
                    <nav class="hidden items-center gap-4 text-sm text-slate-300 md:flex">
                        <a href="/pos" class="font-semibold text-white">POS</a>
                        <a href="/inventory" class="hover:text-white">Inventory</a>
                        <a href="/reports/journal" class="hover:text-white">Ledger</a>
                    </nav>
                    <div class="rounded-full border border-sky-400/30 bg-sky-400/10 px-3 py-1 text-xs font-medium text-sky-200">
                        Pusat branch
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto grid max-w-7xl gap-6 px-6 py-8 lg:grid-cols-[minmax(0,1fr)_380px] lg:px-8">
            <section>
                <div class="mb-5 flex items-end justify-between">
                    <div>
                        <p class="text-sm font-medium text-amber-600">Catalog</p>
                        <h2 class="mt-1 text-2xl font-bold text-slate-950">Choose products</h2>
                    </div>
                    <span class="text-sm text-slate-500" x-text="products.length + ' items'"></span>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <template x-for="product in products" :key="product.id">
                        <button type="button" @click="addToCart(product)" class="group flex min-h-48 flex-col justify-between rounded-2xl border border-slate-200 bg-white p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-sky-300 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50" :disabled="product.stock < 1">
                            <div>
                                <div class="flex items-start justify-between gap-3">
                                    <span class="font-mono text-xs font-semibold text-sky-700" x-text="product.sku"></span>
                                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700" x-text="product.category.name"></span>
                                </div>
                                <h3 class="mt-5 font-semibold leading-6 text-slate-900" x-text="product.name"></h3>
                            </div>
                            <div class="mt-6 flex items-end justify-between gap-3">
                                <div>
                                    <div class="text-lg font-bold text-slate-950" x-text="formatCurrency(product.selling_price)"></div>
                                    <div class="mt-1 text-xs text-slate-500" x-text="product.stock + ' in stock'"></div>
                                </div>
                                <span class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition group-hover:bg-sky-600">Add</span>
                            </div>
                        </button>
                    </template>
                </div>
            </section>

            <aside class="h-fit rounded-2xl border border-slate-200 bg-white shadow-sm lg:sticky lg:top-6">
                <div class="border-b border-slate-200 px-6 py-5">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-slate-950">Current cart</h2>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600" x-text="itemCount + ' items'"></span>
                    </div>
                </div>

                <div class="max-h-[min(48vh,460px)] overflow-y-auto px-6">
                    <template x-if="cart.length === 0">
                        <div class="py-14 text-center">
                            <p class="font-medium text-slate-700">Your cart is empty</p>
                            <p class="mt-1 text-sm text-slate-500">Choose a product to start a sale.</p>
                        </div>
                    </template>
                    <div class="divide-y divide-slate-100">
                        <template x-for="item in cart" :key="item.product.id">
                            <div class="flex gap-3 py-4">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-slate-900" x-text="item.product.name"></p>
                                    <p class="mt-1 text-xs text-slate-500" x-text="formatCurrency(item.product.selling_price) + ' each'"></p>
                                    <div class="mt-3 flex items-center gap-2">
                                        <button type="button" @click="changeQuantity(item.product.id, -1)" class="h-7 w-7 rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50">-</button>
                                        <span class="w-5 text-center text-sm font-semibold" x-text="item.quantity"></span>
                                        <button type="button" @click="changeQuantity(item.product.id, 1)" class="h-7 w-7 rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50">+</button>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-bold text-slate-900" x-text="formatCurrency(item.product.selling_price * item.quantity)"></p>
                                    <button type="button" @click="removeFromCart(item.product.id)" class="mt-3 text-xs font-medium text-rose-600 hover:text-rose-700">Remove</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="border-t border-slate-200 px-6 py-5">
                    <div class="flex items-center justify-between text-base font-bold text-slate-950">
                        <span>Total</span>
                        <span x-text="formatCurrency(total)"></span>
                    </div>
                    <p x-show="message" x-text="message" class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700"></p>
                    <p x-show="error" x-text="error" class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700"></p>
                    <button type="button" @click="checkout" :disabled="cart.length === 0 || loading" class="mt-5 w-full rounded-xl bg-sky-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:bg-slate-300" x-text="loading ? 'Processing...' : 'Checkout'"></button>
                </div>
            </aside>
        </main>
    </div>

    <script>
        function posCart(products) {
            return {
                products,
                cart: [],
                loading: false,
                message: '',
                error: '',
                get itemCount() {
                    return this.cart.reduce((sum, item) => sum + item.quantity, 0);
                },
                get total() {
                    return this.cart.reduce((sum, item) => sum + (Number(item.product.selling_price) * item.quantity), 0);
                },
                formatCurrency(value) {
                    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
                },
                addToCart(product) {
                    const item = this.cart.find((entry) => entry.product.id === product.id);
                    if (item) {
                        if (item.quantity < product.stock) item.quantity++;
                        return;
                    }
                    this.cart.push({ product, quantity: 1 });
                },
                changeQuantity(productId, amount) {
                    const item = this.cart.find((entry) => entry.product.id === productId);
                    if (!item) return;
                    const product = this.products.find((entry) => entry.id === productId);
                    item.quantity = Math.max(1, Math.min(product.stock, item.quantity + amount));
                },
                removeFromCart(productId) {
                    this.cart = this.cart.filter((entry) => entry.product.id !== productId);
                },
                async checkout() {
                    this.loading = true;
                    this.message = '';
                    this.error = '';
                    try {
                        const response = await fetch('/api/checkout', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'Authorization': 'Bearer ' + document.querySelector('meta[name="pos-token"]').content,
                            },
                            body: JSON.stringify({
                                items: this.cart.map((item) => ({ product_id: item.product.id, quantity: item.quantity })),
                            }),
                        });
                        const payload = await response.json();
                        if (!response.ok) throw new Error(payload.message || 'Checkout failed.');
                        this.message = payload.message + ' Total: ' + this.formatCurrency(payload.total);
                        this.cart = [];
                        this.products = this.products.map((product) => {
                            const sold = payload.items.find((item) => item.product_id === product.id);
                            return sold ? { ...product, stock: product.stock - sold.quantity } : product;
                        });
                    } catch (checkoutError) {
                        this.error = checkoutError.message;
                    } finally {
                        this.loading = false;
                    }
                },
            };
        }
    </script>
</body>
</html>