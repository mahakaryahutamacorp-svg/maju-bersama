{{-- Kolom pilih produk dengan smart search. Butuh withProductPicker() di Alpine parent. --}}
<div
    class="relative z-20"
    @mousedown.outside="closeProductMenu(item, $event)"
    @scroll.window="item.open && ProductSmartSearch.reposition(item)"
    @resize.window="item.open && ProductSmartSearch.reposition(item)"
>
    <input type="hidden" :name="'items[' + index + '][product_id]'" :value="item.product_id">
    <input
        type="text"
        data-product-query
        x-model="item.query"
        @input="onProductQuery(item, $event)"
        @focus="onProductFocus(item, $event)"
        @click="onProductFocus(item, $event)"
        @keydown.arrow-down.prevent="moveProduct(item, 1)"
        @keydown.arrow-up.prevent="moveProduct(item, -1)"
        @keydown.enter.prevent="confirmProduct(index, $event)"
        @keypress.enter.prevent
        @keydown.escape="item.open = false"
        autocomplete="off"
        placeholder="{{ $placeholder ?? 'Ketik nama, SKU, atau kategori...' }}"
        style="z-index: 90; color: #0f172a; caret-color: #0284c7;"
        class="relative w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
    >
    <template x-teleport="body">
        <div
            data-product-menu
            x-init="ProductSmartSearch.registerMenu(item, $el)"
            x-show="item.open"
            x-cloak
            class="fixed z-[80] overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-xl"
        >
            <p class="border-b border-slate-100 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                <span x-text="productResults(item).length"></span> hasil
                <span x-show="!(item.query || '').trim()">· ketik nama/SKU untuk menyaring</span>
            </p>
            <template x-for="(prod, resultIndex) in productResults(item)" :key="prod.id">
                <button
                    type="button"
                    @mouseenter="item.active = resultIndex"
                    @mousedown.prevent="chooseProduct(index, prod)"
                    :class="resultIndex === item.active ? 'bg-sky-50' : ''"
                    class="flex w-full items-start justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-sky-50"
                >
                    <span class="min-w-0">
                        <span class="block whitespace-normal break-words font-semibold text-slate-900" x-html="ProductSmartSearch.highlight(prod.name, item.query)"></span>
                        <span class="mt-0.5 block text-xs text-slate-500">
                            SKU: <span class="font-mono" x-html="ProductSmartSearch.highlight(prod.sku, item.query)"></span>
                            <template x-if="ProductSmartSearch.textOf(prod.category)">
                                <span> · <span x-html="ProductSmartSearch.highlight(ProductSmartSearch.textOf(prod.category), item.query)"></span></span>
                            </template>
                        </span>
                    </span>
                    <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600" x-show="prod.stock !== undefined && prod.stock !== null" x-text="'Stok ' + prod.stock"></span>
                </button>
            </template>
            <p
                x-show="(item.query || '').trim() !== '' && productResults(item).length === 0"
                x-cloak
                class="px-3 py-2 text-sm text-slate-500"
            >
                Produk tidak ditemukan. Coba nama, SKU, atau kategori.
            </p>
        </div>
    </template>
</div>
