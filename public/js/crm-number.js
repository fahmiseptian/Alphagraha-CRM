/**
 * Format angka gaya Indonesia: ribuan dengan titik, desimal dengan koma.
 * Contoh: 1000000 → 1.000.000 | 12.5 → 12,5
 */
(function (window) {
    'use strict';

    function format(value, decimals) {
        decimals = decimals == null ? 0 : Number(decimals);
        if (!Number.isFinite(decimals) || decimals < 0) decimals = 0;

        const n = Number(value);
        if (!Number.isFinite(n)) {
            return (0).toLocaleString('id-ID', {
                minimumFractionDigits: 0,
                maximumFractionDigits: decimals,
            });
        }

        return n.toLocaleString('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: decimals,
        });
    }

    function parse(str) {
        if (str === null || str === undefined || str === '') return 0;
        if (typeof str === 'number') return Number.isFinite(str) ? str : 0;

        let s = String(str).trim();
        if (!s) return 0;

        s = s.replace(/[^\d.,\-]/g, '');
        const neg = s.startsWith('-');
        s = s.replace(/-/g, '');
        if (!s) return 0;

        if (s.includes(',')) {
            // 1.000.000,50 atau 12,5
            s = s.replace(/\./g, '').replace(',', '.');
        } else {
            const dots = s.match(/\./g) || [];
            if (dots.length > 1) {
                // 1.000.000
                s = s.replace(/\./g, '');
            } else if (dots.length === 1) {
                const parts = s.split('.');
                // 1.000 → ribuan; 1.5 → desimal EN
                if (parts[1].length === 3 && /^\d+$/.test(parts[1])) {
                    s = parts[0] + parts[1];
                }
            }
        }

        const n = parseFloat(s);
        if (!Number.isFinite(n)) return 0;
        return neg ? -Math.abs(n) : n;
    }

    function enhance(root) {
        root = root || document;
        root.querySelectorAll('[data-crm-number]').forEach(function (el) {
            if (el.dataset.crmNumberReady === '1') return;
            el.dataset.crmNumberReady = '1';

            const decimals = parseInt(el.dataset.decimals || '0', 10) || 0;
            if (el.value !== '' && el.value != null) {
                el.value = format(parse(el.value), decimals);
            }

            el.addEventListener('focus', function () {
                el.select();
            });

            el.addEventListener('blur', function () {
                el.value = format(parse(el.value), decimals);
            });

            const form = el.closest('form');
            if (form && form.dataset.crmNumberSubmit !== '1') {
                form.dataset.crmNumberSubmit = '1';
                form.addEventListener('submit', function () {
                    form.querySelectorAll('[data-crm-number]').forEach(function (input) {
                        const d = parseInt(input.dataset.decimals || '0', 10) || 0;
                        input.value = String(parse(input.value));
                        // jaga-jaga: biarkan kosong jadi 0 untuk required number
                        if (input.value === '' || input.value === 'NaN') input.value = '0';
                        void d;
                    });
                });
            }
        });
    }

    window.CrmNumber = { format: format, parse: parse, enhance: enhance };

    document.addEventListener('DOMContentLoaded', function () {
        enhance(document);
    });
})(window);
