@php
    $initialProducts = old('products', $opportunity->exists
        ? $opportunity->products->map(fn ($p) => [
            'name' => $p['name'], 'quantity' => $p['quantity'], 'price' => $p['price'], 'cost' => $p['cost'],
          ])->values()->all()
        : []);
    $contactOptions = $contacts->map(fn ($c) => [
        'id' => $c->id,
        'name' => $c->full_name,
        'account_id' => $c->account_id,
    ])->values();
@endphp
<form method="POST" action="{{ $action }}"
      x-data="opportunityForm({{ \Illuminate\Support\Js::from([
          'products' => $initialProducts,
          'currency' => old('amount_currency', $opportunity->amount_currency ?: 'IDR'),
          'contacts' => $contactOptions,
          'accountId' => old('account_id', $opportunity->account_id),
          'contactId' => old('contact_id', $opportunity->contact_id),
      ]) }})">
    @csrf
    @if (($method ?? 'POST') === 'PUT')@method('PUT')@endif

    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        {{-- Kolom utama --}}
        <div class="space-y-5 lg:col-span-2">
            <x-card>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label class="crm-label">Company <span class="text-red-500">*</span></label>
                        <select name="company" required class="select2 w-full" data-placeholder="— Select —">
                            <option value="">— Select —</option>
                            @foreach ($companies as $co)
                                <option value="{{ $co }}" @selected(old('company', $opportunity->company) === $co)>{{ $co }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="crm-label">Type <span class="text-red-500">*</span></label>
                        <select name="type" required class="select2 w-full" data-placeholder="— Select —">
                            <option value="">— Select —</option>
                            @foreach ($types as $t)
                                <option value="{{ $t }}" @selected(old('type', $opportunity->type) === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="crm-label">Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $opportunity->name) }}" required
                               class="crm-field">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="crm-label">Account</label>
                        <select name="account_id" class="select2 w-full" data-placeholder="— Select —">
                            <option value="">— Select —</option>
                            @foreach ($accounts as $acc)
                                <option value="{{ $acc->id }}" @selected(old('account_id', $opportunity->account_id) === $acc->id)>{{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="crm-label">Contact</label>
                        <select name="contact_id" class="select2 w-full" data-placeholder="Select account first" :disabled="!accountId">
                            <option value="">— No contact —</option>
                        </select>
                        <p class="mt-1 text-xs text-slate-400" x-show="accountId">Auto-filled from account. You can clear or pick another contact.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="crm-label">Lead Source</label>
                        <select name="lead_source" class="select2 w-full" data-placeholder="— Select —">
                            <option value="">— Select —</option>
                            @foreach ($leadSources as $src)
                                <option value="{{ $src }}" @selected(old('lead_source', $opportunity->lead_source) === $src)>{{ $src }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="crm-label">Stage</label>
                        <select name="stage" class="select2 w-full">
                            @foreach ($stages as $st)
                                <option value="{{ $st }}" @selected(old('stage', $opportunity->stage) === $st)>{{ $st }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="crm-label">Amount <span class="text-red-500">*</span></label>
                        <div class="flex gap-2">
                            <input type="number" step="0.01" min="0" name="amount" x-model.number="amount" required
                                   class="crm-field min-w-[200px] flex-1" :readonly="products.length > 0">
                            <select name="amount_currency" class="select2 select2-compact w-28 shrink-0">
                                @foreach (['IDR', 'USD', 'EUR', 'SGD'] as $cur)
                                    <option value="{{ $cur }}" @selected(old('amount_currency', $opportunity->amount_currency ?: 'IDR') === $cur)>{{ $cur }}</option>
                                @endforeach
                            </select>
                        </div>
                        <p class="mt-1 text-xs text-slate-400" x-show="products.length > 0">Calculated automatically from line items.</p>
                    </div>
                    <div>
                        <label class="crm-label">Probability, % <span class="text-red-500">*</span></label>
                        <input type="number" min="0" max="100" name="probability" value="{{ old('probability', $opportunity->probability ?? 10) }}" required
                               class="crm-field">
                    </div>
                    <div>
                        <label class="crm-label">Close Date <span class="text-red-500">*</span></label>
                        <input type="date" name="close_date" value="{{ old('close_date', $opportunity->close_date ? \Illuminate\Support\Carbon::parse($opportunity->close_date)->format('Y-m-d') : '') }}" required
                               class="crm-field">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="crm-label">Description</label>
                        <textarea name="description" rows="4" class="crm-field">{{ old('description', $opportunity->description) }}</textarea>
                    </div>
                </div>
            </x-card>

            {{-- Line items --}}
            <x-card>
                <div class="mb-3 hidden grid-cols-12 gap-2 px-1 text-xs font-medium uppercase tracking-wider text-slate-400 sm:grid">
                    <div class="col-span-5">Item</div>
                    <div class="col-span-2 text-right">Qty</div>
                    <div class="col-span-2 text-right">Sell Price (Incl)</div>
                    <div class="col-span-2 text-right">Cost (Include)</div>
                    <div class="col-span-1"></div>
                </div>
                <div class="space-y-2">
                    <template x-for="(p, i) in products" :key="i">
                        <div class="grid grid-cols-12 gap-2">
                            <input type="text" :name="`products[${i}][name]`" x-model="p.name" placeholder="Item"
                                   class="crm-field col-span-12 sm:col-span-5">
                            <input type="number" step="0.01" min="0" :name="`products[${i}][quantity]`" x-model.number="p.quantity" placeholder="Qty"
                                   class="crm-field col-span-4 text-right sm:col-span-2">
                            <input type="number" step="0.01" min="0" :name="`products[${i}][price]`" x-model.number="p.price" placeholder="Sell Price"
                                   class="crm-field col-span-4 text-right sm:col-span-2">
                            <input type="number" step="0.01" min="0" :name="`products[${i}][cost]`" x-model.number="p.cost" placeholder="Cost"
                                   class="crm-field col-span-3 text-right sm:col-span-2">
                            <button type="button" @click="removeProduct(i)" class="col-span-1 rounded-lg p-2 text-red-500 hover:bg-red-50"><i class="bi bi-trash"></i></button>
                        </div>
                    </template>
                    <p x-show="products.length === 0" class="rounded-lg border border-dashed border-slate-200 py-4 text-center text-sm text-slate-400">No items yet. Click the + button below.</p>
                </div>
                <div class="mt-3 flex items-center justify-between">
                    <x-btn type="button" variant="secondary" icon="bi-plus-lg" @click="addProduct()">Add Item</x-btn>
                    <div class="text-sm">
                        <span class="text-slate-500">Total:&nbsp;</span>
                        <span class="font-semibold text-slate-800" x-text="formatMoney(productsTotal)"></span>
                    </div>
                </div>
                <div class="mt-5 border-t border-slate-100 pt-5">
                    <label class="crm-label">Vendor</label>
                    <input type="text" name="vendor" value="{{ old('vendor', $opportunity->vendor) }}"
                           class="crm-field">
                </div>
            </x-card>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-5">
            <x-card title="Assigned User">
                @if (auth()->user()->isAdmin())
                    <select name="assigned_user_id" class="select2 w-full" data-placeholder="— Select —">
                        <option value="">— Select —</option>
                        @foreach ($salesUsers as $u)
                            <option value="{{ $u->id }}" @selected(old('assigned_user_id', $opportunity->assigned_user_id) === $u->id)>{{ $u->display_name }}</option>
                        @endforeach
                    </select>
                @else
                    <p class="text-sm text-slate-700">{{ auth()->user()->display_name }}</p>
                    <input type="hidden" name="assigned_user_id" value="{{ old('assigned_user_id', $opportunity->assigned_user_id ?: auth()->id()) }}">
                @endif
            </x-card>

            <x-card title="Teams">
                <select name="team_ids[]" multiple class="select2 w-full" data-placeholder="— Select teams —">
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}" @selected(in_array($team->id, $selectedTeamIds))>{{ $team->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1.5 text-xs text-slate-400">Type to search, select one or more teams.</p>
            </x-card>

    <div class="flex flex-col gap-2">
                <x-btn type="submit" class="w-full justify-center" icon="bi-save">Save</x-btn>
                <x-btn href="{{ $cancelUrl }}" variant="secondary" class="w-full justify-center">Cancel</x-btn>
            </div>
        </div>
    </div>
</form>

<script>
    function opportunityForm(config) {
        return {
            products: (config.products || []).map(p => ({
                name: p.name ?? '',
                quantity: Number(p.quantity) || 0,
                price: Number(p.price) || 0,
                cost: Number(p.cost) || 0,
            })),
            contacts: config.contacts || [],
            accountId: config.accountId || '',
            contactId: config.contactId || '',
            currency: config.currency || 'IDR',
            amount: {{ (float) old('amount', $opportunity->amount ?? 0) }},
            get filteredContacts() {
                if (!this.accountId) return [];
                return this.contacts.filter(c => c.account_id === this.accountId);
            },
            get productsTotal() {
                return this.products.reduce((s, p) => s + (Number(p.quantity) || 0) * (Number(p.price) || 0), 0);
            },
            onAccountChange() {
                const list = this.filteredContacts;
                const stillValid = list.some(c => String(c.id) === String(this.contactId));

                if (!this.accountId) {
                    this.contactId = '';
                } else if (!stillValid) {
                    this.contactId = list.length > 0 ? String(list[0].id) : '';
                }

                this.refreshContactSelect();
            },
            refreshContactSelect() {
                const el = this.$root.querySelector('[name="contact_id"]');
                if (!el || !window.CrmSelect2) return;

                const placeholder = this.accountId ? '— No contact —' : 'Select account first';
                el.disabled = !this.accountId;

                CrmSelect2.setOptions(
                    el,
                    this.filteredContacts.map(c => ({ id: c.id, name: c.name })),
                    this.contactId,
                    placeholder
                );
                CrmSelect2.bindAlpine(el, this, 'contactId');
            },
            addProduct() { this.products.push({ name: '', quantity: 1, price: 0, cost: 0 }); },
            removeProduct(i) { this.products.splice(i, 1); },
            init() {
                this.$watch('products', () => { if (this.products.length > 0) this.amount = this.productsTotal; });
                if (this.products.length > 0) this.amount = this.productsTotal;

                this.$nextTick(() => {
                    if (!window.CrmSelect2) return;
                    CrmSelect2.init(this.$root);

                    const accountEl = this.$root.querySelector('[name="account_id"]');
                    const currencyEl = this.$root.querySelector('[name="amount_currency"]');

                    if (accountEl) {
                        CrmSelect2.bindAlpine(accountEl, this, 'accountId', () => this.onAccountChange());
                    }
                    if (currencyEl) {
                        CrmSelect2.bindAlpine(currencyEl, this, 'currency');
                    }

                    if (this.accountId && !this.contactId) {
                        const list = this.filteredContacts;
                        if (list.length > 0) {
                            this.contactId = String(list[0].id);
                        }
                    }

                    this.refreshContactSelect();
                });
            },
            formatMoney(value) {
                value = Number(value) || 0;
                if (this.currency === 'IDR') return 'Rp ' + value.toLocaleString('id-ID', { maximumFractionDigits: 0 });
                return this.currency + ' ' + value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
        };
    }
</script>
