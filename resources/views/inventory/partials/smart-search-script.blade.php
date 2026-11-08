@once
<script>
    window.ProductSmartSearch = (() => {
        const indexCache = new WeakMap();

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
                const name = normalize(product.name);
                const sku = normalize(product.sku);
                const category = normalize(product.category);
                const all = [name, sku, category, normalize(product.unit)].join(' ');
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
</script>
@endonce
