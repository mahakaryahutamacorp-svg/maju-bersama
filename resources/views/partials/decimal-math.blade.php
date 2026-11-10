<script>
    window.DecimalMath = window.DecimalMath || {
        qty(value) {
            const number = Number(String(value ?? '').replace(',', '.'));
            if (!Number.isFinite(number)) return 0;
            return Math.round(number * 1000) / 1000;
        },
        money(value) {
            const number = Number(value);
            if (!Number.isFinite(number)) return 0;
            return Math.round((number + Number.EPSILON) * 100) / 100;
        },
        mul(quantity, price) {
            const qty = Math.round(this.qty(quantity) * 1000);
            const unit = Math.round(this.money(price) * 100);
            return Math.round((qty * unit) / 1000) / 100;
        },
        rupiah(amount) {
            return 'Rp ' + this.money(amount).toLocaleString('id-ID', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },
        idMoney(value) {
            if (typeof value === 'number' && Number.isFinite(value)) {
                return this.money(value);
            }
            let raw = String(value ?? '').trim().replace(/[^\d,.\-]/g, '');
            if (!raw || raw === '-') return 0;
            const negative = raw.startsWith('-');
            raw = raw.replace(/-/g, '');
            const hasComma = raw.includes(',');
            const dots = (raw.match(/\./g) || []).length;
            if (hasComma && dots > 0) {
                if (raw.lastIndexOf(',') > raw.lastIndexOf('.')) {
                    raw = raw.replace(/\./g, '').replace(',', '.');
                } else {
                    raw = raw.replace(/,/g, '');
                }
            } else if (hasComma) {
                raw = raw.replace(',', '.');
            } else if (dots > 1) {
                raw = raw.replace(/\./g, '');
            } else if (dots === 1 && (raw.split('.')[1] || '').length === 3) {
                raw = raw.replace('.', '');
            }
            const number = Number(raw);
            if (!Number.isFinite(number)) return 0;
            const rounded = this.money(number);
            return negative ? -rounded : rounded;
        },
        formatIdTyping(text) {
            const original = String(text ?? '');
            const group = (digits) => {
                const clean = String(digits || '').replace(/^0+(?=\d)/, '');
                return clean.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            };
            if (original.includes(',')) {
                const cleaned = original.replace(/[^\d,]/g, '');
                const commaAt = cleaned.indexOf(',');
                const whole = cleaned.slice(0, commaAt);
                const fraction = cleaned.slice(commaAt + 1).replace(/[^\d]/g, '').slice(0, 2);
                return group(whole) + ',' + fraction;
            }
            const dotDecimal = original.replace(/[^\d.]/g, '').match(/^(\d+)\.(\d{1,2})$/);
            if (dotDecimal && (original.match(/\./g) || []).length === 1) {
                return group(dotDecimal[1]) + ',' + dotDecimal[2];
            }
            return group(original.replace(/[^\d]/g, ''));
        },
    };
</script>
