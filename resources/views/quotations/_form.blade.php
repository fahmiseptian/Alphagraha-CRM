@php
    $isCreate = ! $quotation->exists;
    $initialItems = old('items', $quotation->exists
        ? $quotation->items->map(fn ($i) => [
            'name' => $i->name, 'description' => $i->description,
            'quantity' => (float) $i->quantity, 'unit' => $i->unit,
            // Form menampilkan harga yang ditagihkan (setelah diskon item bila ada).
            'unit_price' => (float) $i->unit_price,
            'sell_exclude' => (float) $i->listSellExclude(),
            'discount_exclude' => (float) ($i->discount_exclude ?? 0),
            'cost_exclude' => (float) ($i->cost_exclude ?? 0),
            'tax_category' => $i->tax_category,
            'item_kind' => $i->item_kind,
            'vendor' => $i->vendor,
          ])->values()->all()
        : ($seedItems ?? []));
    if (empty($initialItems)) {
        $initialItems = [['name' => '', 'description' => '', 'quantity' => 1, 'unit' => '', 'unit_price' => 0, 'sell_exclude' => 0, 'discount_exclude' => 0]];
    }
    $accountMap = $accounts->mapWithKeys(fn ($a) => [$a->id => [
        'name' => $a->name,
        'address' => collect([$a->billing_address_street, $a->billing_address_city, $a->billing_address_state, $a->billing_address_postal_code, $a->billing_address_country])->filter()->implode(', '),
    ]]);
    $config = [
        'items' => $initialItems,
        'discount' => (float) old('discount', $quotation->discount ?? 0),
        'taxPercent' => (float) old('tax_percent', $quotation->tax_percent ?? \App\Support\OpportunityProductPricing::ppnPercent()),
        'currency' => old('currency', $quotation->currency ?? 'IDR'),
        'accounts' => $accountMap,
        'accountId' => (string) old('account_id', $quotation->account_id),
        'customerName' => old('customer_name', $quotation->customer_name) ?? '',
        'companyName' => old('company_name', $quotation->company_name) ?? '',
        'customerAddress' => old('customer_address', $quotation->customer_address) ?? '',
    ];
    $ppnPercent = $config['taxPercent'];
@endphp

<form id="quotation-form" method="POST" action="{{ $action }}"
      x-data="quotationForm({{ \Illuminate\Support\Js::from($config) }})">
    @csrf
    @if (($method ?? 'POST') === 'PUT')@method('PUT')@endif
    <input type="hidden" name="opportunity_id" value="{{ old('opportunity_id', $quotation->opportunity_id) }}">

    @if (old('opportunity_id', $quotation->opportunity_id))
        <div class="mb-4 flex items-center gap-2 rounded-lg border border-brand-200 bg-brand-50 px-4 py-2.5 text-sm text-brand-700">
            <i class="bi bi-link-45deg"></i>
            <span>This quotation is linked to an Opportunity/Deal.</span>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Data pelanggan --}}
            <x-card title="Customer Details">
                <div class="space-y-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Select Customer (EspoCRM)</label>
                        <select name="account_id" class="select2 w-full" data-placeholder="— Manual entry / no customer —">
                            <option value="">— Manual entry / no customer —</option>
                            @foreach ($accounts as $acc)
                                <option value="{{ $acc->id }}" @selected(old('account_id', $quotation->account_id) === $acc->id)>{{ $acc->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-400">Selecting a customer will auto-fill name & address. You can still edit them.</p>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">Customer Name <span class="text-red-500">*</span></label>
                            <input type="text" name="customer_name" x-model="customerName" required
                                   class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">Company Name</label>
                            <input type="text" name="company_name" x-model="companyName"
                                   class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">Email @if ($isCreate)<span class="text-red-500">*</span>@endif</label>
                            <input type="email" name="customer_email" value="{{ old('customer_email', $quotation->customer_email) }}"
                                   @if ($isCreate) required @endif
                                   class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">Phone @if ($isCreate)<span class="text-red-500">*</span>@endif</label>
                            <input type="text" name="customer_phone" value="{{ old('customer_phone', $quotation->customer_phone) }}"
                                   @if ($isCreate) required @endif
                                   class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Address @if ($isCreate)<span class="text-red-500">*</span>@endif</label>
                        <textarea name="customer_address" x-model="customerAddress" rows="2"
                                  @if ($isCreate) required @endif
                                  class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200"></textarea>
                    </div>
                </div>
            </x-card>

            {{-- Item penawaran --}}
            <x-card>
                <x-slot:title>Quotation Items @if ($isCreate)<span class="text-red-500">*</span>@endif</x-slot:title>
                <x-slot:action>
                    <button type="button" @click="addItem()" class="inline-flex items-center gap-1 rounded-lg bg-brand-50 px-3 py-1.5 text-sm font-medium text-brand-600 hover:bg-brand-100">
                        <i class="bi bi-plus-lg"></i> Add Item
                    </button>
                </x-slot:action>

                <div class="space-y-3">
                    <template x-for="(item, index) in items" :key="index">
                        <div class="rounded-lg border border-slate-200 p-3">
                            <input type="hidden" :name="`items[${index}][sell_exclude]`" x-model.number="item.sell_exclude">
                            <input type="hidden" :name="`items[${index}][discount_exclude]`" x-model.number="item.discount_exclude">
                            <input type="hidden" :name="`items[${index}][cost_exclude]`" x-model.number="item.cost_exclude">
                            <input type="hidden" :name="`items[${index}][tax_category]`" x-model="item.tax_category">
                            <input type="hidden" :name="`items[${index}][item_kind]`" x-model="item.item_kind">
                            <input type="hidden" :name="`items[${index}][vendor]`" x-model="item.vendor">
                            <div class="grid grid-cols-12 gap-2">
                                <div class="col-span-12 sm:col-span-5">
                                    <input type="text" :name="`items[${index}][name]`" x-model="item.name" placeholder="Product/service name" @if ($isCreate) required @endif
                                           class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                                </div>
                                <div class="col-span-4 sm:col-span-2">
                                    <input type="number" step="0.01" min="0" :name="`items[${index}][quantity]`" x-model.number="item.quantity" placeholder="Qty"
                                           @if ($isCreate) required @endif
                                           class="w-full rounded-lg border border-slate-300 py-2 px-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                                </div>
                                <div class="col-span-3 sm:col-span-1">
                                    <input type="text" :name="`items[${index}][unit]`" x-model="item.unit" placeholder="unit"
                                           class="w-full rounded-lg border border-slate-300 py-2 px-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                                </div>
                                <div class="col-span-5 sm:col-span-3">
                                    <input type="number" step="0.01" min="0" :name="`items[${index}][unit_price]`" x-model.number="item.unit_price" placeholder="Harga exclude"
                                           @input="onUnitPriceChange(item)"
                                           @if ($isCreate) required @endif
                                           class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
                                           title="Harga jual exclude (setelah diskon item bila ada)">
                                </div>
                                <div class="col-span-12 flex items-center justify-between sm:col-span-1 sm:justify-center">
                                    <span class="text-sm font-medium text-slate-700 sm:hidden" x-text="formatMoney(item.quantity * item.unit_price)"></span>
                                    <button type="button" @click="removeItem(index)" class="rounded-lg p-2 text-red-500 hover:bg-red-50"><i class="bi bi-trash"></i></button>
                                </div>
                                <div class="col-span-12">
                                    <input type="text" :name="`items[${index}][description]`" x-model="item.description" placeholder="Specification (optional)"
                                           class="w-full rounded-lg border border-slate-200 py-1.5 px-3 text-xs text-slate-500 focus:border-brand-500 focus:ring-1 focus:ring-brand-200">
                                    <p class="mt-1 text-[11px] text-amber-700" x-show="(Number(item.discount_exclude) || 0) > 0">
                                        Diskon item: list <span x-text="formatMoney(item.sell_exclude)"></span>
                                        → setelah diskon <span x-text="formatMoney(item.unit_price)"></span>
                                    </p>
                                </div>
                            </div>
                            <div class="mt-2 hidden text-right text-sm text-slate-500 sm:block">
                                Subtotal: <span class="font-semibold text-slate-700" x-text="formatMoney(item.quantity * item.unit_price)"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </x-card>

            {{-- Catatan --}}
            <x-card title="Notes & Terms">
                <div class="space-y-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Additional Notes</label>
                        <textarea name="notes" rows="2" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">{{ old('notes', $quotation->notes) }}</textarea>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Terms & Conditions @if ($isCreate)<span class="text-red-500">*</span>@endif</label>
                        <textarea id="quotation_terms" name="terms" rows="3" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">{{ old('terms', $quotation->terms ?? \App\Support\OpportunityProductPricing::defaultQuotationTerms($ppnPercent)) }}</textarea>
                    </div>
                </div>
            </x-card>
        </div>

        {{-- Sidebar ringkasan --}}
        <div class="space-y-4">
            <x-card title="Quotation Settings">
                <div class="space-y-4">
                    <div>
                        @if ($isCreate)
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">Quotation Number</label>
                            <input type="text" value="Otomatis — {{ $resolvedSalesCode ?? '…' }}/QO/…" disabled
                                   class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 px-3 text-sm text-slate-500">
                            <p class="mt-1 text-xs text-slate-400">
                                Nomor digenerate otomatis memakai Sales Code
                                <strong>{{ $resolvedSalesCode ?? '—' }}</strong>
                                @if (! empty($resolvedSalesOwner))
                                    ({{ $resolvedSalesOwner }})
                                @endif.
                                Revisi (-R1) hanya setelah status Sent dan ada perubahan.
                            </p>
                        @else
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">Quotation Number</label>
                            <input type="text" name="number" value="{{ old('number', $quotation->number) }}" readonly
                                   class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 px-3 text-sm text-slate-700">
                            @if ($quotation->willBumpDocumentRevisionOnEdit())
                                <p class="mt-1 text-xs text-amber-600">
                                    Status saat ini <strong>Sent</strong>. Perubahan isi akan otomatis menjadi revisi dokumen
                                    ({{ $quotation->base_number ?: $quotation->number }} → …-R{{ max(1, (int) $quotation->document_revision + 1) }}…)
                                    dan status dikembalikan ke <strong>Draft</strong> (perlu dikirim ulang).
                                </p>
                            @elseif ($quotation->hasBeenSent())
                                <p class="mt-1 text-xs text-slate-500">
                                    Sudah ada revisi dokumen
                                    @if ($quotation->document_revision > 0)
                                        <strong>R{{ $quotation->document_revision }}</strong>
                                    @endif
                                    ({{ $quotation->number }}). Selama status masih <strong>Draft</strong>, edit isi
                                    tidak menaikkan nomor R. Baru naik R lagi setelah di-<strong>Sent</strong> lalu diubah.
                                </p>
                            @else
                                <p class="mt-1 text-xs text-slate-400">Belum Sent — nomor tetap tanpa suffix revisi meski diedit.</p>
                            @endif
                        @endif
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Date <span class="text-red-500">*</span></label>
                        <input type="date" name="quotation_date" value="{{ old('quotation_date', optional($quotation->quotation_date)->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required
                               class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Valid Until @if ($isCreate)<span class="text-red-500">*</span>@endif</label>
                        <input type="date" name="valid_until" value="{{ old('valid_until', optional($quotation->valid_until)->format('Y-m-d')) }}"
                               @if ($isCreate) required @endif
                               class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">Currency</label>
                            <select name="currency" class="select2 select2-compact w-full">
                                <option value="IDR">IDR</option>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                                <option value="SGD">SGD</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">Status</label>
                            <select name="status" class="select2 w-full">
                                @foreach ($statuses as $key => $label)
                                    <option value="{{ $key }}" @selected(old('status', $quotation->status ?? 'draft') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Template</label>
                        <select name="template_id" class="select2 w-full" data-placeholder="— Select template —">
                            @foreach ($templates as $tpl)
                                <option value="{{ $tpl->id }}" @selected(old('template_id', $quotation->template_id ?? optional($templates->firstWhere('is_default', true))->id) === $tpl->id)>
                                    {{ $tpl->name }} @if($tpl->is_default) (default) @endif
                                </option>
                            @endforeach
                        </select>
                        @if (! empty($templateCompany))
                            <p class="mt-1 text-xs text-slate-400">Hanya template kategori <strong>{{ $templateCompany }}</strong>.</p>
                        @endif
                        @if ($templates->isEmpty())
                            <p class="mt-1 text-xs text-amber-600">Belum ada template aktif untuk kategori ini. Buat di Quotation Templates.</p>
                        @endif
                    </div>
                </div>
            </x-card>

            <x-card title="Cost Summary">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Subtotal</dt>
                        <dd class="font-medium text-slate-800" x-text="formatMoney(subtotal)"></dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Discount</dt>
                        <dd><input type="number" step="0.01" min="0" name="discount" x-model.number="discount" class="w-28 rounded-lg border border-slate-300 py-1.5 px-2 text-right text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200"></dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Tax (PPN)</dt>
                        <dd class="font-medium text-slate-700" x-text="taxPercent + '%'"></dd>
                    </div>
                    <input type="hidden" name="tax_percent" :value="taxPercent">
                    <div class="flex justify-between text-slate-500">
                        <dt>Tax Amount</dt>
                        <dd x-text="formatMoney(taxAmount)"></dd>
                    </div>
                    <div class="flex justify-between border-t border-slate-200 pt-3 text-base font-bold text-slate-900">
                        <dt>Total</dt>
                        <dd x-text="formatMoney(total)"></dd>
                    </div>
                </dl>
                <button type="submit" class="mt-5 w-full rounded-lg bg-brand-600 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                    <i class="bi bi-save"></i> Save Quotation
                </button>
                <a href="{{ url()->previous() }}" class="mt-2 block w-full rounded-lg border border-slate-300 py-2.5 text-center text-sm text-slate-600 hover:bg-slate-50">Cancel</a>
            </x-card>
        </div>
    </div>
</form>

<script>
    function quotationForm(config) {
        const items = (config.items || []).map(item => ({
            name: item.name ?? '',
            description: item.description ?? '',
            quantity: Number(item.quantity) || 0,
            unit: item.unit ?? '',
            unit_price: Number(item.unit_price) || 0,
            sell_exclude: Number(item.sell_exclude ?? item.unit_price) || 0,
            discount_exclude: Number(item.discount_exclude) || 0,
            cost_exclude: Number(item.cost_exclude) || 0,
            tax_category: item.tax_category ?? '',
            item_kind: item.item_kind ?? '',
            vendor: item.vendor ?? '',
        }));

        return {
            items,
            discount: config.discount,
            taxPercent: config.taxPercent,
            currency: config.currency,
            accounts: config.accounts,
            accountId: config.accountId || '',
            customerName: config.customerName || '',
            companyName: config.companyName || '',
            customerAddress: config.customerAddress || '',

            get subtotal() {
                return this.items.reduce((sum, i) => sum + (Number(i.quantity) || 0) * (Number(i.unit_price) || 0), 0);
            },
            get taxAmount() {
                const base = Math.max(this.subtotal - (Number(this.discount) || 0), 0);
                return base * ((Number(this.taxPercent) || 0) / 100);
            },
            get total() {
                return Math.max(this.subtotal - (Number(this.discount) || 0), 0) + this.taxAmount;
            },
            addItem() {
                this.items.push({
                    name: '', description: '', quantity: 1, unit: '', unit_price: 0,
                    sell_exclude: 0, discount_exclude: 0, cost_exclude: 0,
                    tax_category: '', item_kind: '', vendor: '',
                });
            },
            removeItem(index) {
                this.items.splice(index, 1);
                if (this.items.length === 0) this.addItem();
            },
            onUnitPriceChange(item) {
                const price = Number(item.unit_price) || 0;
                const discount = Number(item.discount_exclude) || 0;
                // Tanpa diskon item: list ikut harga yang diedit user.
                if (discount <= 0) {
                    item.sell_exclude = price;
                    return;
                }
                // Ada diskon: jika user menaikkan/turunkan tagihan, biarkan list tetap;
                // bila tagihan ≥ list, anggap tidak pakai diskon lagi.
                const list = Number(item.sell_exclude) || 0;
                if (list > 0 && price >= list) {
                    item.discount_exclude = 0;
                    item.sell_exclude = price;
                }
            },
            fillFromAccount() {
                const acc = this.accounts[this.accountId];
                if (acc) {
                    this.customerName = acc.name || this.customerName;
                    this.companyName = acc.name || this.companyName;
                    if (acc.address) this.customerAddress = acc.address;
                }
            },
            formatMoney(value) {
                value = Number(value) || 0;
                if (this.currency === 'IDR') {
                    return 'Rp ' + value.toLocaleString('id-ID', { maximumFractionDigits: 0 });
                }
                return this.currency + ' ' + value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
            init() {
                this.$nextTick(() => {
                    if (!window.CrmSelect2) return;
                    CrmSelect2.init(this.$root);
                    const accountEl = this.$root.querySelector('[name="account_id"]');
                    const currencyEl = this.$root.querySelector('[name="currency"]');
                    if (accountEl) {
                        CrmSelect2.bindAlpine(accountEl, this, 'accountId', () => this.fillFromAccount());
                    }
                    if (currencyEl) {
                        CrmSelect2.bindAlpine(currencyEl, this, 'currency');
                    }
                });
            },
        };
    }
</script>

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css">
<style>
    .note-editor.note-frame {
        border: 1px solid #cbd5e1;
        border-radius: 0.5rem;
        overflow: hidden;
    }
    .note-toolbar {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 6px 8px;
    }
    .note-editable {
        min-height: 140px;
        font-family: 'Inter', sans-serif;
        font-size: 13px;
        line-height: 1.6;
        background: #fff;
    }
    .note-statusbar { display: none; }
    .note-btn { border-radius: 0.375rem; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/lang/summernote-en-US.min.js"></script>
<script>
    $(function () {
        $('#quotation_terms').summernote({
            height: 160,
            lang: 'en-US',
            placeholder: 'Terms & Conditions...',
            toolbar: [
                ['font', ['bold', 'italic', 'underline', 'clear']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['insert', ['link', 'hr']],
                ['view', ['codeview']],
            ],
        });

        $('#quotation-form').on('submit', function () {
            $('#quotation_terms').val($('#quotation_terms').summernote('code'));
        });
    });
</script>
@endpush
