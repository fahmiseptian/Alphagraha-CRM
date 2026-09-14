/**
 * Inisialisasi Select2 untuk semua <select> di dalam scope.
 * Mendukung placeholder, multi-select, auto-submit filter, dan sinkron Alpine.js.
 */
(function ($) {
    'use strict';

    function optionsFor($el) {
        const placeholder = $el.data('placeholder')
            || $el.find('option[value=""]').first().text()
            || '— Select —';

        const opts = {
            width: '100%',
            placeholder: placeholder,
            allowClear: !$el.prop('required'),
            language: {
                noResults: () => 'No results found',
                searching: () => 'Searching...',
            },
        };

        if ($el.prop('multiple')) {
            opts.allowClear = true;
            opts.closeOnSelect = false;
        }

        if ($el.hasClass('select2-compact') || ($el.find('option').length <= 5 && !$el.hasClass('select2-search'))) {
            opts.minimumResultsForSearch = Infinity;
        }

        return opts;
    }

    function bindAutoSubmit($el) {
        // Support boolean HTML attribute (e.g. data-auto-submit)
        // which jQuery can read as empty string / falsey.
        if (!$el.is('[data-auto-submit]')) {
            return;
        }

        let ready = false;
        setTimeout(() => {
            ready = true;
        }, 100);

        const submit = () => {
            if (!ready) {
                return;
            }

            const form = $el.closest('form')[0];
            if (!form) {
                return;
            }

            const action = form.getAttribute('action') || window.location.pathname;
            const params = new URLSearchParams(new FormData(form));

            if (!params.get('assigned_user_id')) {
                params.delete('assigned_user_id');
            }

            const target = new URL(action, window.location.origin);
            target.search = params.toString();

            const current = `${window.location.pathname}${window.location.search}`;
            const next = `${target.pathname}${target.search}`;

            if (current !== next) {
                window.location.assign(target.href);
            }
        };

        $el.on('change.crmAutoSubmit select2:select.crmAutoSubmit select2:clear.crmAutoSubmit', submit);
    }

    window.CrmSelect2 = {
        init(root) {
            const $scope = root ? $(root) : $(document);
            $scope.find('select.select2').not('[data-brand-select], [data-category-select], [data-vendor-select], [data-po-vendor-select], [data-po-select2], [data-po-so-select]').each(function () {
                window.CrmSelect2.initElement(this);
            });
        },

        initElement(el) {
            const $el = $(el);
            if (!$el.length || $el.hasClass('select2-hidden-accessible')) {
                return $el;
            }

            $el.select2(optionsFor($el));
            bindAutoSubmit($el);

            return $el;
        },

        destroy(el) {
            const $el = $(el);
            if ($el.hasClass('select2-hidden-accessible')) {
                $el.off('change.crmAutoSubmit change.crmAlpine');
                $el.select2('destroy');
            }
            return $el;
        },

        refresh(el) {
            this.destroy(el);
            return this.initElement(el);
        },

        /**
         * Ganti opsi dropdown dinamis (mis. Contacts setelah Account berubah).
         */
        setOptions(el, items, selected, placeholder) {
            const $el = $(el);
            const ph = placeholder || $el.data('placeholder') || '— Select —';

            this.destroy(el);
            $el.empty().append(new Option(ph, '', false, false));

            items.forEach((item) => {
                const id = item.id ?? item.value ?? '';
                const text = item.text ?? item.name ?? String(id);
                $el.append(new Option(text, id, false, String(id) === String(selected)));
            });

            this.initElement(el);
            $el.val(selected ? String(selected) : '').trigger('change');

            return $el;
        },

        /**
         * Sinkronkan Select2 ↔ properti Alpine.js.
         */
        bindAlpine(el, alpine, property, callback) {
            const $el = $(el);
            let lastValue = Symbol('unset');

            const handler = function () {
                const value = $el.val() || '';
                if (value === lastValue) {
                    return;
                }
                lastValue = value;
                alpine[property] = value;
                if (typeof callback === 'function') {
                    callback(value);
                }
            };

            $el.off('.crmAlpine');
            $el.on('change.crmAlpine select2:select.crmAlpine select2:clear.crmAlpine', handler);

            if (alpine[property]) {
                $el.val(String(alpine[property])).trigger('change.select2');
            }

            handler();
        },
    };

    function initPoSelect2(el) {
        const $el = $(el);
        if (!$el.length || $el.hasClass('select2-hidden-accessible')) {
            return;
        }

        $el.select2({
            width: '100%',
            placeholder: $el.data('placeholder') || $el.find('option[value=""]').first().text() || '— Pilih —',
            allowClear: true,
            minimumResultsForSearch: 0,
            dropdownParent: $(document.body),
            language: {
                noResults: () => 'Tidak ditemukan',
                searching: () => 'Mencari...',
            },
        });

        $el.on('select2:select.crmPoDyn select2:clear.crmPoDyn', function () {
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    function scanPoSelect2() {
        document.querySelectorAll('select[data-po-select2]').forEach(initPoSelect2);
    }

    $(function () {
        const main = document.querySelector('main');
        if (main) {
            window.CrmSelect2.init(main);
        }

        let poSelect2Timer = null;
        const schedulePoSelect2 = function () {
            window.clearTimeout(poSelect2Timer);
            poSelect2Timer = window.setTimeout(scanPoSelect2, 40);
        };

        scanPoSelect2();
        if (document.body) {
            const observer = new MutationObserver(schedulePoSelect2);
            observer.observe(document.body, { childList: true, subtree: true });
        }
        document.addEventListener('alpine:initialized', schedulePoSelect2);
        document.addEventListener('alpine:init', schedulePoSelect2);
    });
})(jQuery);
