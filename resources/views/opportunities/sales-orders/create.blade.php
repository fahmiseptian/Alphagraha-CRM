@extends('layouts.app')
@section('title', 'Create Sales Order')

@section('content')
@php
    $cfg = [
        'form' => $form,
        'storeUrl' => route('opportunities.sales-orders.store', $opportunity),
        'showUrl' => route('opportunities.show', $opportunity),
        'currency' => $form['currency'] ?? 'IDR',
    ];
@endphp

<div class="mb-6">
    <a href="{{ route('opportunities.show', $opportunity) }}" class="crm-back"><i class="bi bi-arrow-left"></i> Kembali ke opportunity</a>
    <h2 class="crm-page-title">Create Sales Order</h2>
    <p class="crm-page-desc">{{ $opportunity->name }} · {{ $form['customerName'] ?: 'Customer' }}</p>
</div>

<div x-data="salesOrderForm({{ \Illuminate\Support\Js::from($cfg) }})" x-init="init()">
    <form @submit.prevent="submitSo" class="space-y-5">
        <x-card title="Customer & pembayaran">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="crm-label">No. SO</label>
                    <input type="text" class="crm-field bg-slate-50 font-mono" readonly
                           value="{{ $form['previewSoNumber'] ?: 'Otomatis saat disimpan' }}">
                    <p class="mt-1 text-xs text-slate-400">SO + tahun 2 digit + bulan + tanggal + 5 digit urutan.</p>
                </div>
                <div>
                    <label class="crm-label">No. PSO</label>
                    <input type="text" class="crm-field bg-slate-50 font-mono" readonly
                           value="{{ $form['previewPsoNumber'] ?: 'Otomatis saat disimpan' }}">
                    <p class="mt-1 text-xs text-slate-400">Otomatis ikut No. SO, hanya prefix PSO.</p>
                </div>
                <div>
                    <label class="crm-label">Nomor ref</label>
                    <input type="text" class="crm-field bg-slate-50 font-mono" readonly
                           value="{{ $form['previewSoRef'] ?: 'Otomatis saat disimpan' }}">
                    @if (! empty($form['salesCodeError']))
                        <p class="mt-1 text-xs text-red-600">{{ $form['salesCodeError'] }}</p>
                    @else
                        <p class="mt-1 text-xs text-slate-400">
                            Dipakai Sales Code
                            <strong>{{ $form['salesCode'] ?: '—' }}</strong>
                            @if (! empty($form['salesCodeOwner']))
                                ({{ $form['salesCodeOwner'] }})
                            @endif.
                            Format: 0001/{kode}/SO/VIII/26.
                        </p>
                    @endif
                </div>
                <div>
                    <label class="crm-label">Customer</label>
                    <input type="text" class="crm-field bg-slate-50" readonly value="{{ $form['customerName'] }}">
                </div>
                <div>
                    <label class="crm-label">Email</label>
                    <input type="email" x-model="email" class="crm-field" placeholder="Email customer">
                    <p class="mt-1 text-xs text-slate-400" x-show="!email">
                        Opsional.
                        @if (! empty($form['customerEditUrl']))
                            <a href="{{ $form['customerEditUrl'] }}" class="font-medium underline">Lengkapi di data customer</a>
                        @endif
                    </p>
                </div>
                <div>
                    <label class="crm-label">TOP <span class="text-red-500">*</span></label>
                    <select name="payment" x-ref="paymentSelect" class="select2 w-full" data-placeholder="— Pilih TOP —" required>
                        @foreach ($form['topOptions'] ?? [] as $value => $label)
                            <option value="{{ $value }}" @selected(($form['payment'] ?? '') === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-400">
                        Customer: {{ $form['customerTopLabel'] ?? 'Cash' }}.
                    </p>
                </div>
                <div>
                    <label class="crm-label">No. PO customer</label>
                    <input type="text" x-model="poNumber" class="crm-field" maxlength="100">
                </div>
                <div>
                    <label class="crm-label">File PO</label>
                    <input type="file"
                           accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                           class="crm-field file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-brand-700"
                           @change="onPoFile($event)">
                    <p class="mt-1 text-xs text-slate-400">Opsional. PDF, JPG, JPEG, atau PNG. Maks. 5MB.</p>
                    <p class="mt-1 text-xs text-slate-600" x-show="poFileName" x-cloak x-text="'Dipilih: ' + poFileName"></p>
                    <p class="mt-1 text-xs text-red-600" x-show="poFileError" x-cloak x-text="poFileError"></p>
                </div>
                <div>
                    <label class="crm-label">Required delivery</label>
                    <input type="date" x-model="requiredDelivery" class="crm-field">
                </div>
                <div>
                    <label class="crm-label">Metode pengiriman</label>
                    <input type="text" x-model="shippingMethod" class="crm-field" maxlength="150" placeholder="Contoh: JNE, ambil sendiri">
                </div>
                <div class="sm:col-span-2">
                    <label class="crm-label">Catatan</label>
                    <textarea x-model="note" rows="2" class="crm-field" maxlength="2000"></textarea>
                </div>
            </div>
        </x-card>

        <x-card title="Alamat billing & shipping">
            <p class="mb-4 text-sm text-slate-500">
                Pilih dari buku alamat customer.
                @if (! empty($form['customerShowUrl']))
                    <a href="{{ $form['customerShowUrl'] }}" class="font-medium text-brand-600 hover:underline">Kelola alamat</a>
                @endif
            </p>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2" x-show="addresses.length">
                <div>
                    <label class="crm-label">Billing address <span class="text-red-500">*</span></label>
                    <select x-ref="billingAddressSelect" class="select2 select2-search w-full" data-placeholder="— Pilih alamat billing —"></select>
                    <p class="mt-2 text-xs text-slate-500" x-show="billingPreview" x-cloak x-text="billingPreview"></p>
                </div>
                <div>
                    <label class="crm-label">Shipping address</label>
                    <label class="mb-2 flex items-center gap-2 text-xs text-slate-500">
                        <input type="checkbox" x-model="sameAsBilling">
                        Sama dengan billing
                    </label>
                    <select x-ref="shippingAddressSelect" class="select2 select2-search w-full" data-placeholder="— Pilih alamat shipping —" :disabled="sameAsBilling"></select>
                    <p class="mt-2 text-xs text-slate-500" x-show="shippingPreview" x-cloak x-text="shippingPreview"></p>
                </div>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" x-show="!addresses.length" x-cloak>
                Customer belum punya alamat.
                @if (! empty($form['customerShowUrl']))
                    <a href="{{ $form['customerShowUrl'] }}" class="font-medium underline">Tambah alamat di data customer</a>
                    dulu sebelum membuat Sales Order.
                @endif
            </div>
        </x-card>

        <x-card title="Produk untuk Sales Order ini">
            <p class="mb-4 text-sm text-slate-500">
                Item dan qty di bawah hanya untuk SO ini, tidak mengubah produk opportunity. Brand dan Category wajib terisi.
            </p>
            @if (! empty($form['brandCategoryError']))
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $form['brandCategoryError'] }}
                    @if (! empty($form['editOpportunityUrl']))
                        <a href="{{ $form['editOpportunityUrl'] }}" class="font-medium underline">Lengkapi di opportunity</a>
                    @endif
                </div>
            @endif
            <p class="mb-3" x-show="items.length < sourceItems.length" x-cloak>
                <button type="button" @click="restoreOpportunityItems()" class="text-sm font-medium text-brand-600 hover:underline">
                    Kembalikan semua item opportunity
                </button>
            </p>
            <div class="space-y-4">
                <template x-for="(item, i) in items" :key="item.index">
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="mb-2 flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-800" x-text="item.name || ('Produk ' + (i + 1))"></p>
                                <p class="mt-0.5 text-xs" :class="(item.brand && item.category) ? 'text-slate-400' : 'text-red-600'">
                                    <span x-text="'Brand: ' + (item.brand || '—')"></span>
                                    <span> · </span>
                                    <span x-text="'Category: ' + (item.category || '—')"></span>
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"
                                      x-text="'Qty opportunity ' + (item.qty_origin ?? item.qty)"></span>
                                <button type="button"
                                        @click="removeItem(i)"
                                        :disabled="items.length <= 1"
                                        class="rounded-lg p-2 text-red-500 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"
                                        title="Hapus dari SO ini">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-12">
                            <div class="sm:col-span-4">
                                <label class="crm-label text-xs">SKU</label>
                                <input type="text" x-model="item.sku" class="crm-field" placeholder="Opsional">
                            </div>
                            <div class="sm:col-span-3">
                                <label class="crm-label text-xs">Qty SO ini</label>
                                <input type="number" min="1" step="1" x-model.number="item.qty" class="crm-field">
                            </div>
                            <div class="sm:col-span-5">
                                <label class="crm-label text-xs">Harga jual (exclude)</label>
                                <input type="text" class="crm-field bg-slate-50" readonly :value="formatMoney(item.sell_exclude)">
                                <p class="mt-1 text-[11px] text-slate-400" x-show="item.has_item_discount" x-cloak>
                                    Pakai Diskon Item (list <span x-text="formatMoney(item.list_sell_exclude)"></span>)
                                </p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </x-card>

        <div class="flex justify-end gap-2">
            <x-btn href="{{ route('opportunities.show', $opportunity) }}" variant="secondary">Batal</x-btn>
            <x-btn type="submit" icon="bi-check-lg" ::disabled="saving || !canSubmitItems">
                <span x-text="saving ? 'Menyimpan…' : 'Buat Sales Order'"></span>
            </x-btn>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function salesOrderForm(cfg) {
    const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
    const todayYmd = () => {
        const d = new Date();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + m + '-' + day;
    };

    return {
        email: cfg.form.email || '',
        payment: cfg.form.payment || 'cash',
        poNumber: cfg.form.poNumber || '',
        requiredDelivery: cfg.form.requiredDelivery || todayYmd(),
        note: cfg.form.note || '',
        shippingMethod: cfg.form.shippingMethod || '',
        addresses: cfg.form.addresses || [],
        billingAddressId: cfg.form.billingAddressId ? String(cfg.form.billingAddressId) : '',
        shippingAddressId: cfg.form.shippingAddressId ? String(cfg.form.shippingAddressId) : '',
        sameAsBilling: cfg.form.sameAsBilling !== false,
        poFile: null,
        poFileName: '',
        poFileError: '',
        sourceItems: JSON.parse(JSON.stringify(cfg.form.items || [])),
        items: JSON.parse(JSON.stringify(cfg.form.items || [])),
        saving: false,
        cfg,

        init() {
            this.$nextTick(() => {
                if (window.CrmSelect2) {
                    CrmSelect2.init(this.$root);
                    const payEl = this.$refs.paymentSelect;
                    if (payEl) {
                        CrmSelect2.bindAlpine(payEl, this, 'payment');
                    }
                    this.refreshAddressSelects();
                    this.$watch('sameAsBilling', () => this.syncShippingDisabled());
                    this.syncShippingDisabled();
                }
            });
        },

        addressById(id) {
            return (this.addresses || []).find((a) => String(a.id) === String(id)) || null;
        },

        addressLabel(row) {
            if (!row) return '';
            const bits = [row.label, row.line].filter(Boolean);
            return bits.join(' — ');
        },

        get billingPreview() {
            const row = this.addressById(this.billingAddressId);
            if (!row) return '';
            const extra = [row.contact, row.phone].filter(Boolean).join(' · ');
            return extra ? (row.line + (extra ? ' · ' + extra : '')) : row.line;
        },

        get shippingPreview() {
            const id = this.sameAsBilling ? this.billingAddressId : this.shippingAddressId;
            const row = this.addressById(id);
            if (!row) return '';
            const extra = [row.contact, row.phone].filter(Boolean).join(' · ');
            return extra ? (row.line + (extra ? ' · ' + extra : '')) : row.line;
        },

        refreshAddressSelects() {
            const items = (this.addresses || []).map((a) => ({
                id: String(a.id),
                text: this.addressLabel(a),
            }));
            const bind = (ref, key) => {
                const el = this.$refs[ref];
                if (!el || !window.CrmSelect2) return;
                CrmSelect2.setOptions(el, items, this[key] || '', el.getAttribute('data-placeholder'));
                CrmSelect2.bindAlpine(el, this, key);
            };
            bind('billingAddressSelect', 'billingAddressId');
            bind('shippingAddressSelect', 'shippingAddressId');
            this.syncShippingDisabled();
        },

        syncShippingDisabled() {
            const el = this.$refs.shippingAddressSelect;
            if (!el || !window.jQuery) return;
            window.jQuery(el).prop('disabled', !!this.sameAsBilling).trigger('change.select2');
        },

        formatMoney(n) {
            const v = Number(n || 0);
            return 'Rp ' + Math.round(v).toLocaleString('id-ID');
        },

        get canSubmitItems() {
            if (this.cfg.form.salesCodeError) return false;
            return (this.items || []).length > 0
                && (this.items || []).every((it) => String(it.brand || '').trim() !== '' && String(it.category || '').trim() !== '');
        },

        removeItem(i) {
            if (this.items.length <= 1) {
                alert('Sales Order wajib punya minimal 1 produk.');
                return;
            }
            this.items.splice(i, 1);
        },

        restoreOpportunityItems() {
            this.items = JSON.parse(JSON.stringify(this.sourceItems || []));
        },

        onPoFile(event) {
            const input = event.target;
            const file = input.files && input.files[0];
            this.poFile = null;
            this.poFileName = '';
            this.poFileError = '';
            if (!file) return;
            const okType = /^(application\/pdf|image\/jpeg|image\/png)$/i.test(file.type)
                || /\.(pdf|jpe?g|png)$/i.test(file.name);
            if (!okType) {
                this.poFileError = 'File PO harus PDF, JPG, JPEG, atau PNG.';
                input.value = '';
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                this.poFileError = 'Ukuran file PO maksimal 5MB.';
                input.value = '';
                return;
            }
            this.poFile = file;
            this.poFileName = file.name;
        },

        async submitSo() {
            if (this.cfg.form.salesCodeError) {
                alert(this.cfg.form.salesCodeError);
                return;
            }
            if (!this.items.length) {
                alert('Sales Order wajib punya minimal 1 produk.');
                return;
            }
            const incomplete = this.items.find((it) => !String(it.brand || '').trim() || !String(it.category || '').trim());
            if (incomplete) {
                alert('Setiap produk Sales Order wajib punya Brand dan Category. Lengkapi di opportunity.');
                return;
            }
            if (this.poFileError) {
                alert(this.poFileError);
                return;
            }
            if (!this.billingAddressId) {
                alert('Pilih alamat billing. Tambah alamat di data customer jika belum ada.');
                return;
            }
            if (!this.sameAsBilling && !this.shippingAddressId) {
                alert('Pilih alamat shipping, atau centang sama dengan billing.');
                return;
            }
            this.saving = true;
            try {
                const fd = new FormData();
                fd.append('email', this.email || '');
                fd.append('payment', this.payment || '');
                fd.append('billing_address_id', this.billingAddressId || '');
                fd.append('same_as_billing', this.sameAsBilling ? '1' : '0');
                fd.append('shipping_address_id', this.sameAsBilling ? (this.billingAddressId || '') : (this.shippingAddressId || ''));
                if (this.shippingMethod) fd.append('shipping_method', this.shippingMethod);
                if (this.poNumber) fd.append('po_number', this.poNumber);
                if (this.requiredDelivery) fd.append('required_delivery', this.requiredDelivery);
                if (this.note) fd.append('note', this.note);
                this.items.forEach((it, i) => {
                    fd.append('items[' + i + '][index]', it.index);
                    fd.append('items[' + i + '][sku]', it.sku || '');
                    fd.append('items[' + i + '][qty]', it.qty);
                });
                if (this.poFile) fd.append('po_file', this.poFile);
                const res = await fetch(this.cfg.storeUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf(),
                    },
                    body: fd,
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok || !json.success) {
                    const firstErr = json.errors ? Object.values(json.errors).flat()[0] : null;
                    alert(json.message || firstErr || 'Gagal membuat Sales Order.');
                    return;
                }
                window.location.href = json.redirect || this.cfg.showUrl;
            } catch (e) {
                alert(e?.message || 'Gagal membuat Sales Order.');
            } finally {
                this.saving = false;
            }
        },
    };
}
</script>
@endpush
