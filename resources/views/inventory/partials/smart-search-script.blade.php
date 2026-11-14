@once
<script>
    window.ProductSmartSearch = (() => {
        const indexCache = new WeakMap();
        const anchors = new WeakMap();

        const textOf = (value) => {
            if (value && typeof value === 'object') {
                return value.name ?? value.label ?? '';
            }
            return value ?? '';
        };

        const normalize = (value) => String(value ?? '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, ' ')
            .trim();

        const compact = (value) => normalize(value).replace(/ /g, '');

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');

        const indexOf = (product) => {
            let index = indexCache.get(product);
            if (!index) {
                const name = normalize(textOf(product.name));
                const sku = normalize(textOf(product.sku));
                const category = normalize(textOf(product.category));
                const all = [name, sku, category, normalize(textOf(product.unit))].join(' ');
                index = {
                    name,
                    skuCompact: sku.replace(/ /g, ''),
                    all,
                    allCompact: all.replace(/ /g, ''),
                    words: all.split(' ').filter(Boolean),
                };
                indexCache.set(product, index);
            }
            return index;
        };

        const distance = (a, b, max) => {
            if (Math.abs(a.length - b.length) > max) return max + 1;
            let previous = Array.from({ length: b.length + 1 }, (_, i) => i);
            for (let i = 1; i <= a.length; i++) {
                const current = [i];
                let rowMin = i;
                for (let j = 1; j <= b.length; j++) {
                    const cost = a[i - 1] === b[j - 1] ? 0 : 1;
                    current[j] = Math.min(previous[j] + 1, current[j - 1] + 1, previous[j - 1] + cost);
                    rowMin = Math.min(rowMin, current[j]);
                }
                if (rowMin > max) return max + 1;
                previous = current;
            }
            return previous[b.length];
        };

        const tokenScore = (token, index) => {
            if (index.words.some((word) => word.startsWith(token))) return 40;
            if (index.all.includes(token) || index.allCompact.includes(token)) return 25;
            if (token.length < 3) return 0;

            const allowed = token.length <= 4 ? 1 : 2;
            let best = allowed + 1;
            for (const word of index.words) {
                best = Math.min(best, distance(token, word, allowed), distance(token, word.slice(0, token.length), allowed));
                if (best === 0) break;
            }
            return best <= allowed ? 15 - best * 4 : 0;
        };

        const score = (product, query) => {
            const normalizedQuery = normalize(query);
            if (!normalizedQuery) return 1;

            const index = indexOf(product);
            const compactQuery = normalizedQuery.replace(/ /g, '');
            let total = 0;

            if (index.skuCompact === compactQuery) total += 1000;
            else if (index.skuCompact.startsWith(compactQuery)) total += 600;
            else if (index.name.startsWith(normalizedQuery)) total += 500;
            else if (index.name.includes(normalizedQuery)) total += 200;

            for (const token of normalizedQuery.split(' ')) {
                const value = tokenScore(token, index);
                if (value === 0 && total < 600) return 0;
                total += value;
            }

            return total + Math.max(0, 10 - index.name.length / 10);
        };

        return {
            normalize,

            score,

            matches(product, query) {
                return score(product, query) > 0;
            },

            search(products, query, limit = 20) {
                if (!normalize(query)) return products.slice(0, limit);

                return products
                    .map((product) => ({ product, value: score(product, query) }))
                    .filter((entry) => entry.value > 0)
                    .sort((a, b) => b.value - a.value || String(a.product.name).localeCompare(String(b.product.name)))
                    .slice(0, limit)
                    .map((entry) => entry.product);
            },

            exactSku(products, query) {
                const compactQuery = compact(query);
                if (!compactQuery) return null;
                return products.find((product) => indexOf(product).skuCompact === compactQuery) ?? null;
            },

            textOf,

            results(products, query, limit = 12) {
                return this.search(products || [], query || '', limit);
            },

            label(product) {
                const sku = String(textOf(product?.sku)).trim();
                const name = String(textOf(product?.name)).trim();
                if (sku && name) return sku + ' — ' + name;
                return name || sku;
            },

            ensureLine(item) {
                return Object.assign({
                    product_id: '',
                    query: '',
                    open: false,
                    active: 0,
                    menuTop: 0,
                    menuLeft: 0,
                    menuWidth: 280,
                }, item || {});
            },

            apply(item, product) {
                item.product_id = product.id ?? product.product_id ?? '';
                item.query = this.label(product);
                item.open = false;
                item.active = 0;
            },

            anchor(item, element) {
                if (!element) return;
                anchors.set(item, element);
                const rect = element.getBoundingClientRect();
                const menuHeight = 240;
                const spaceBelow = window.innerHeight - rect.bottom;
                const openUp = spaceBelow < menuHeight && rect.top > spaceBelow;
                item.menuTop = openUp ? Math.max(8, rect.top - menuHeight - 4) : rect.bottom + 4;
                item.menuLeft = Math.max(8, rect.left);
                item.menuWidth = Math.max(rect.width, 260);
            },

            reposition(item) {
                const element = anchors.get(item);
                if (element) this.anchor(item, element);
            },

            // Harus objek: :style berupa string menimpa seluruh atribut style, termasuk display:none dari x-show.
            menuStyle(item) {
                return {
                    top: (item.menuTop || 0) + 'px',
                    left: (item.menuLeft || 0) + 'px',
                    width: (item.menuWidth || 280) + 'px',
                };
            },

            // Enter di kolom isian dokumen tidak boleh mengirim form setengah jadi.
            blockImplicitSubmit(event) {
                const target = event.target;
                if (!(target instanceof HTMLInputElement)) return;
                if (['submit', 'button', 'image', 'reset'].includes(target.type)) return;
                event.preventDefault();
            },

            move(active, total, step) {
                if (!total) return 0;
                return (Number(active || 0) + step + total) % total;
            },

            highlight(text, query) {
                const raw = String(text ?? '');
                const tokens = normalize(query).split(' ').filter((token) => token.length > 0);
                if (tokens.length === 0) return escapeHtml(raw);

                const lower = raw.toLowerCase();
                const ranges = [];
                for (const token of tokens) {
                    let from = lower.indexOf(token);
                    while (from !== -1) {
                        ranges.push([from, from + token.length]);
                        from = lower.indexOf(token, from + token.length);
                    }
                }
                if (ranges.length === 0) return escapeHtml(raw);

                ranges.sort((a, b) => a[0] - b[0]);
                const merged = [ranges[0]];
                for (const [start, end] of ranges.slice(1)) {
                    const last = merged[merged.length - 1];
                    if (start <= last[1]) last[1] = Math.max(last[1], end);
                    else merged.push([start, end]);
                }

                let html = '';
                let cursor = 0;
                for (const [start, end] of merged) {
                    html += escapeHtml(raw.slice(cursor, start));
                    html += '<mark class="rounded bg-amber-200 px-0.5 text-slate-900">' + escapeHtml(raw.slice(start, end)) + '</mark>';
                    cursor = end;
                }
                return html + escapeHtml(raw.slice(cursor));
            },
        };
    })();

    window.withProductPicker = function (form) {
        const baseInit = form.init;

        form.init = function () {
            this.items = (this.items || []).map((item) => ProductSmartSearch.ensureLine(item));
            this.hydrateProductQueries();
            if (typeof baseInit === 'function') {
                return baseInit.call(this);
            }
        };

        form.hydrateProductQueries = function () {
            this.items.forEach((item) => {
                if (item.query || !item.product_id) return;
                const product = (this.products || []).find((row) => row.id == item.product_id);
                item.query = product
                    ? ProductSmartSearch.label(product)
                    : ProductSmartSearch.label({ sku: item.sku, name: item.product_name });
            });
        };

        form.blankProductLine = function (extra) {
            return ProductSmartSearch.ensureLine(extra || {});
        };

        form.productResults = function (item) {
            return ProductSmartSearch.results(this.products, item.query, 12);
        };

        form.onProductQuery = function (item) {
            item.product_id = '';
            item.open = true;
            item.active = 0;
            ProductSmartSearch.reposition(item);
        };

        form.onProductFocus = function (item, event) {
            item.open = true;
            ProductSmartSearch.anchor(item, event.target);
        };

        form.moveProduct = function (item, step) {
            const total = this.productResults(item).length;
            item.open = true;
            item.active = ProductSmartSearch.move(item.active || 0, total, step);
            ProductSmartSearch.reposition(item);
        };

        form.chooseProduct = function (index, product) {
            ProductSmartSearch.apply(this.items[index], product);
            if (typeof this.onProductSelect === 'function') {
                this.onProductSelect(index);
            }
        };

        form.confirmProduct = function (index) {
            const item = this.items[index];
            const chosen = ProductSmartSearch.exactSku(this.products, item.query)
                || this.productResults(item)[item.active || 0]
                || null;
            if (chosen) this.chooseProduct(index, chosen);
        };

        return form;
    };
</script>
@endonce
