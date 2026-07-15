@php
    $isCreate = ! $quotation->exists;
    $initialItems = old('items', $quotation->exists
        ? $quotation->items->map(fn ($i) => [
            'name' => $i->name, 'description' => $i->description,
            'quantity' => (float) $i->quantity, 'unit' => $i->unit, 'unit_price' => (float) $i->unit_price,
          ])->values()->all()
        : ($seedItems ?? []));
    if (empty($initialItems)) {
        $initialItems = [['name' => '', 'description' => '', 'quantity' => 1, 'unit' => '', 'unit_price' => 0]];
    }
    $accountMap = $accounts->mapWithKeys(fn ($a) => [$a->id => [
        'name' => $a->name,
        'address' => collect([$a->billing_address_street, $a->billing_address_city, $a->billing_address_state, $a->billing_address_postal_code, $a->billing_address_country])->filter()->implode(', '),
    ]]);
    $config = [
        'items' => $initialItems,
        'discount' => (float) old('discount', $quotation->discount ?? 0),
        'taxPercent' => 11,
        'currency' => old('currency', $quotation->currency ?? 'IDR'),
        'accounts' => $accountMap,
        'accountId' => (string) old('account_id', $quotation->account_id),
        'customerName' => old('customer_name', $quotation->customer_name) ?? '',
        'companyName' => old('company_name', $quotation->company_name) ?? '',
        'customerAddress' => old('customer_address', $quotation->customer_address) ?? '',
    ];
@endphp

<form method="POST" action="{{ $action }}"
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
                                    <input type="number" step="0.01" min="0" :name="`items[${index}][unit_price]`" x-model.number="item.unit_price" placeholder="Unit price"
                                           @if ($isCreate) required @endif
                                           class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                                </div>
                                <div class="col-span-12 flex items-center justify-between sm:col-span-1 sm:justify-center">
                                    <span class="text-sm font-medium text-slate-700 sm:hidden" x-text="formatMoney(item.quantity * item.unit_price)"></span>
                                    <button type="button" @click="removeItem(index)" class="rounded-lg p-2 text-red-500 hover:bg-red-50"><i class="bi bi-trash"></i></button>
                                </div>
                                <div class="col-span-12">
                                    <input type="text" :name="`items[${index}][description]`" x-model="item.description" placeholder="Specification (optional)"
                                           class="w-full rounded-lg border border-slate-200 py-1.5 px-3 text-xs text-slate-500 focus:border-brand-500 focus:ring-1 focus:ring-brand-200">
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
                        <textarea name="terms" rows="3" @if ($isCreate) required @endif class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">{{ old('terms', $quotation->terms ?? "1. Harga sudah termasuk PPN 11%\n2. Harga dan ketersediaan barang dapat berubah sewaktu-waktu tanpa pemberitahuan terlebih dahulu.") }}</textarea>
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
                            @if ($quotation->hasBeenSent())
                                <p class="mt-1 text-xs text-amber-600">Sudah pernah Sent. Perubahan isi akan menaikkan revisi dokumen (mis. -R1, -R2).</p>
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
                        <dd class="font-medium text-slate-700">11%</dd>
                    </div>
                    <input type="hidden" name="tax_percent" value="11">
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
        return {
            items: config.items,
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
                this.items.push({ name: '', description: '', quantity: 1, unit: '', unit_price: 0 });
            },
            removeItem(index) {
                this.items.splice(index, 1);
                if (this.items.length === 0) this.addItem();
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
