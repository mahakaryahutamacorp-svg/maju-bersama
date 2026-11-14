{{-- Kolom pilih produk dengan smart search. Butuh withProductPicker() di Alpine parent. --}}
<div
    class="relative"
    @click.away="item.open = false"
    @scroll.window="item.open && ProductSmartSearch.reposition(item)"
    x-effect="const field = $el.querySelector('[data-product-query]'); if (field) field.setCustomValidity(item.product_id ? '' : 'Pilih produk dari hasil pencarian.')"
>
    <input type="hidden" :name="'items[' + index + '][product_id]'" :value="item.product_id">
    <input
        type="text"
        data-product-query
        x-model="item.query"
        @input="onProductQuery(item)"
        @focus="onProductFocus(item, $event)"
        @keydown.arrow-down.prevent="moveProduct(item, 1)"
        @keydown.arrow-up.prevent="moveProduct(item, -1)"
        @keydown.enter.prevent="confirmProduct(index)"
        @keypress.enter.prevent
        @keydown.escape="item.open = false"
        :required="!item.product_id"
        autocomplete="off"
        placeholder="{{ $placeholder ?? 'Ketik nama, SKU, atau kategori...' }}"
        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
    >
    <div
        x-show="item.open && (item.query || '').trim() !== '' && productResults(item).length > 0"
        x-cloak
        class="fixed z-50 max-h-60 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-xl"
        :style="ProductSmartSearch.menuStyle(item)"
    >
        <template x-for="(prod, resultIndex) in productResults(item)" :key="prod.id">
            <button
                type="button"
                @mouseenter="item.active = resultIndex"
                @click="chooseProduct(index, prod)"
                :class="resultIndex === item.active ? 'bg-sky-50' : ''"
                class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-sky-50"
            >
                <span class="min-w-0">
                    <span class="block truncate font-semibold text-slate-900" x-html="ProductSmartSearch.highlight(prod.name, item.query)"></span>
                    <span class="block text-xs text-slate-500">
                        SKU: <span class="font-mono" x-html="ProductSmartSearch.highlight(prod.sku, item.query)"></span>
                        <template x-if="ProductSmartSearch.textOf(prod.category)">
                            <span> · <span x-html="ProductSmartSearch.highlight(ProductSmartSearch.textOf(prod.category), item.query)"></span></span>
                        </template>
                    </span>
                </span>
                <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600" x-show="prod.stock !== undefined && prod.stock !== null" x-text="'Stok ' + prod.stock"></span>
            </button>
        </template>
    </div>
    <p
        x-show="item.open && (item.query || '').trim() !== '' && productResults(item).length === 0"
        x-cloak
        class="fixed z-50 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-500 shadow-xl"
        :style="ProductSmartSearch.menuStyle(item)"
    >
        Produk tidak ditemukan. Coba nama, SKU, atau kategori.
    </p>
</div>
