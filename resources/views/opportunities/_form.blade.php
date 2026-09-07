@php
    $initialProducts = old('products', $opportunity->exists
        ? $opportunity->products->map(fn ($p) => [
            'name' => $p['name'],
            'quantity' => $p['quantity'],
            'sell_exclude' => $p['sell_exclude'],
            'cost_exclude' => $p['cost_exclude'],
            'discount_exclude' => $p['discount_exclude'] ?? 0,
            'shipping_exclude' => $p['shipping_exclude'] ?? 0,
            'vendor' => $p['vendor'],
            'brand' => $p['brand'] ?? '',
            'sku' => $p['sku'] ?? '',
            'category' => $p['category'] ?? '',
            'tax_category' => $p['tax_category'],
            'item_kind' => $p['item_kind'],
            'has_royalty' => ! empty($p['has_royalty']),
            'royalty_type' => (string) ($p['royalty_type'] ?? (! empty($p['has_royalty']) ? 'luar' : '')),
            'image' => (string) ($p['image'] ?? ''),
            'image_url' => $p['image_url'] ?? (\App\Models\Espo\Opportunity::productImageUrl($p['image'] ?? null)),
            'cost_foreign' => ! empty($p['cost_foreign']) || ! empty($p['cost_in_usd']),
            'cost_fx_code' => $p['cost_fx_code'] ?? (($p['cost_in_usd'] ?? false) ? 'USD' : ''),
            'cost_fx' => $p['cost_fx'] ?? $p['cost_usd'] ?? 0,
            'fx_rate' => $p['fx_rate'] ?? $p['usd_rate'] ?? 0,
          ])->values()->all()
        : []);
    $contactOptions = $contacts->map(fn ($c) => [
        'id' => $c->id,
        'name' => $c->full_name,
        'account_id' => $c->account_id,
    ])->values();
    $ppnPercent = \App\Support\OpportunityProductPricing::ppnPercent();
    $pphNonWapuJasa = \App\Support\OpportunityProductPricing::pphNonWapuJasaPercent();
    $pphWapuBarang = \App\Support\OpportunityProductPricing::pphWapuBarangPercent();
    $pphWapuJasa = \App\Support\OpportunityProductPricing::pphWapuJasaPercent();
    $pnbpPercent = \App\Support\OpportunityProductPricing::pnbpPercent();
    $pnbpTiers = \App\Support\OpportunityProductPricing::pnbpTiers();
    $pph29Percent = \App\Support\OpportunityProductPricing::pph29Percent();
    $zinitTiers = \App\Support\OpportunityProductPricing::zinitTiers();
    $royaltyDalamPercent = \App\Support\OpportunityProductPricing::royaltyDalamPercent();
    $royaltyLuarPercent = \App\Support\OpportunityProductPricing::royaltyLuarPercent();
    $purchasingMode = $purchasingMode ?? false;
    $catalogUser = auth()->user();
    $catalogCanAdd = [
        'brands' => (bool) ($catalogUser?->canManageBrands()),
        'categories' => (bool) ($catalogUser?->canManageCategories()),
        'vendors' => (bool) ($catalogUser?->canManageVendors()),
    ];
    $catalogQuickUrls = [
        'brands' => route('brands.quick-store'),
        'categories' => route('categories.quick-store'),
        'vendors' => route('vendors.quick-store'),
    ];
@endphp
<form method="POST" action="{{ $action }}" enctype="multipart/form-data"
      x-data="opportunityForm({{ \Illuminate\Support\Js::from([
          'products' => $initialProducts,
          'currency' => old('amount_currency', $opportunity->amount_currency ?: 'IDR'),
          'contacts' => $contactOptions,
          'accountId' => old('account_id', $opportunity->account_id),
          'contactId' => old('contact_id', $opportunity->contact_id),
          'crmTop' => old('crm_top', $opportunity->crm_top ?: ($opportunity->account?->crm_top ?? \App\Support\CustomerTop::DEFAULT)),
          'topMargins' => $topMargins ?? \App\Support\CustomerTop::allMinMargins(),
          'initialTaxCategory' => old('products.0.tax_category', count($initialProducts) > 0 ? ($initialProducts[0]['tax_category'] ?? null) : null),
          'ppnPercent' => $ppnPercent,
          'pphNonWapuJasa' => $pphNonWapuJasa,
          'pphWapuBarang' => $pphWapuBarang,
          'pphWapuJasa' => $pphWapuJasa,
          'pnbpPercent' => $pnbpPercent,
          'pnbpTiers' => $pnbpTiers,
          'pph29Percent' => $pph29Percent,
          'zinitTiers' => $zinitTiers,
          'royaltyDalamPercent' => $royaltyDalamPercent,
          'royaltyLuarPercent' => $royaltyLuarPercent,
          'hasDiscount' => (bool) old('has_discount', $opportunity->crm_has_discount),
          'discountAmount' => (float) old('discount_amount', $opportunity->crm_discount_amount ?? 0),
          'hasShippingCharge' => (bool) old('has_shipping_charge', $opportunity->crm_has_shipping_charge),
          'shippingSell' => (float) old('shipping_sell', $opportunity->crm_shipping_sell ?? 0),
          'accountMarginMeta' => $accountMarginMeta ?? [],
          'marginNominalUmum' => (float) ($marginNominalUmum ?? 0),
          'marginNominalOngkirPribadi' => (float) ($marginNominalOngkirPribadi ?? 0),
          'marginMaxPercent' => (float) ($marginMaxPercent ?? \App\Support\PaymentLevel::maxMarginPercent()),
          'purchasingMode' => $purchasingMode,
          'productTemplateUrl' => route('opportunities.products.template'),
          'stage' => old('stage', $opportunity->stage ?: 'Prospecting'),
          'initialStage' => $opportunity->stage ?: 'Prospecting',
          'noApprovalStages' => \App\Models\Espo\Opportunity::NO_APPROVAL_STAGES,
          'catalogCanAdd' => $catalogCanAdd,
          'catalogQuickUrls' => $catalogQuickUrls,
      ]) }})">
    @csrf
    @if (($method ?? 'POST') === 'PUT')@method('PUT')@endif

    @if ($purchasingMode)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Mode Purchasing: Anda hanya dapat mengubah <strong>harga modal (beli exclude)</strong> dan <strong>vendor</strong> pada deal Closed Won.
        </div>
    @endif

    @if ($errors->any())        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-5">
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        {{-- Kolom kiri: data opportunity --}}
        <div class="space-y-5 lg:col-span-2">
            <x-card>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2" @if($purchasingMode) aria-disabled="true"@endif>
                    <div>
                        <label class="crm-label">Company <span class="text-red-500">*</span></label>
                        <select name="company" required class="select2 w-full" data-placeholder="— Select —" @disabled($purchasingMode)>
                            <option value="">— Select —</option>
                            @foreach ($companies as $co)
                                <option value="{{ $co }}" @selected(old('company', $opportunity->company) === $co)>{{ $co }}</option>
                            @endforeach
                        </select>
                        @if ($purchasingMode)<input type="hidden" name="company" value="{{ $opportunity->company }}">@endif
                    </div>
                    <div>
                        <label class="crm-label">Type <span class="text-red-500">*</span></label>
                        <select name="type" required class="select2 w-full" data-placeholder="— Select —" @disabled($purchasingMode)>
                            <option value="">— Select —</option>
                            @foreach ($types as $t)
                                <option value="{{ $t }}" @selected(old('type', $opportunity->type) === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                        @if ($purchasingMode)<input type="hidden" name="type" value="{{ $opportunity->type }}">@endif
                    </div>
                    <div class="sm:col-span-2">
                        <label class="crm-label">Opportunity Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $opportunity->name) }}" required
                               class="crm-field" @readonly($purchasingMode)>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="crm-label">Customer Name</label>
                        <select name="account_id" class="select2 select2-search w-full" data-placeholder="Cari customer..." @disabled($purchasingMode)>
                            <option value="">— Select —</option>
                            @foreach ($accounts as $acc)
                                <option value="{{ $acc->id }}" @selected(old('account_id', $opportunity->account_id) === $acc->id)>{{ $acc->name }}</option>
                            @endforeach
                        </select>
                        @if ($purchasingMode)<input type="hidden" name="account_id" value="{{ $opportunity->account_id }}">@endif
                    </div>
                    <div>
                        <label class="crm-label">TOP <span class="text-red-500">*</span></label>
                        <select name="crm_top" required class="select2 w-full" data-placeholder="— Pilih TOP —" @disabled($purchasingMode)>
                            @foreach ($topOptions ?? \App\Support\CustomerTop::LABELS as $value => $label)
                                <option value="{{ $value }}" @selected(old('crm_top', $opportunity->crm_top ?: ($opportunity->account?->crm_top ?? 'cash')) === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @if ($purchasingMode)<input type="hidden" name="crm_top" value="{{ $opportunity->crm_top ?: ($opportunity->account?->crm_top ?? 'cash') }}">@endif
                        <p class="mt-1 text-xs text-slate-400">Default dari customer. Bisa diubah untuk deal ini.</p>
                    </div>
                    <div>
                        <label class="crm-label">Contact</label>
                        <select name="contact_id" class="select2 select2-search w-full" data-placeholder="Select customer first" :disabled="!accountId || purchasingMode">
                            <option value="">— No contact —</option>
                        </select>
                        @if ($purchasingMode)<input type="hidden" name="contact_id" value="{{ $opportunity->contact_id }}">@endif
                        <p class="mt-1 text-xs text-slate-400" x-show="accountId && !purchasingMode">Auto-filled from customer. You can clear or pick another contact.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="crm-label">Lead Source</label>
                        <select name="lead_source" class="select2 w-full" data-placeholder="— Select —" @disabled($purchasingMode)>
                            <option value="">— Select —</option>
                            @foreach ($leadSources as $src)
                                <option value="{{ $src }}" @selected(old('lead_source', $opportunity->lead_source) === $src)>{{ $src }}</option>
                            @endforeach
                        </select>
                        @if ($purchasingMode)<input type="hidden" name="lead_source" value="{{ $opportunity->lead_source }}">@endif
                    </div>
                    <div>
                        <label class="crm-label">Stage</label>
                        <select name="stage" class="select2 w-full"
                                @change="stage = $event.target.value"
                                @disabled($purchasingMode)>
                            @foreach ($stages as $st)
                                <option value="{{ $st }}" @selected(old('stage', $opportunity->stage) === $st)>{{ $st }}</option>
                            @endforeach
                        </select>
                        @if ($purchasingMode)<input type="hidden" name="stage" value="{{ $opportunity->stage }}">@endif
                        <p class="mt-1 text-xs text-slate-400"
                           x-show="skipsApproval" x-cloak>
                            Stage awal — approval margin &amp; diskon belum diperlukan.
                        </p>
                    </div>
                    <div class="sm:col-span-2" x-show="stage === 'Closed Lost'" x-cloak>
                        <label class="crm-label">
                            Catatan kekalahan
                            <span class="text-red-500" x-show="initialStage !== 'Closed Lost'">*</span>
                        </label>
                        <textarea
                            name="lost_reason"
                            rows="3"
                            class="crm-field"
                            :required="stage === 'Closed Lost' && initialStage !== 'Closed Lost'"
                            placeholder="Jelaskan alasan kekalahan deal ini..."
                            @readonly($purchasingMode)
                        >{{ old('lost_reason', $opportunity->crm_lost_reason) }}</textarea>
                        <p class="mt-1 text-xs text-slate-400">
                            Wajib diisi saat menutup deal sebagai Closed Lost (minimal 10 karakter).
                        </p>
                    </div>
                    <div>
                        <label class="crm-label">Amount <span class="text-red-500">*</span></label>
                        <div class="flex gap-2">
                            <input type="text" inputmode="decimal" required
                                   x-effect="if (editingField !== 'amount') $el.value = formatId(amount)"
                                   @focus="editingField = 'amount'"
                                   @blur="editingField = null; $el.value = formatId(amount)"
                                   @input="amount = parseId($event.target.value)"
                                   class="crm-field min-w-[200px] flex-1 tabular-nums" :readonly="products.length > 0 || purchasingMode">
                            <input type="hidden" name="amount" :value="amount">
                            <select name="amount_currency" class="select2 select2-compact w-28 shrink-0" @disabled($purchasingMode)>
                                @foreach (['IDR', 'USD', 'EUR', 'SGD'] as $cur)
                                    <option value="{{ $cur }}" @selected(old('amount_currency', $opportunity->amount_currency ?: 'IDR') === $cur)>{{ $cur }}</option>
                                @endforeach
                            </select>
                            @if ($purchasingMode)
                                <input type="hidden" name="amount_currency" value="{{ $opportunity->amount_currency ?: 'IDR' }}">
                            @endif
                        </div>
                        <p class="mt-1 text-xs text-slate-400" x-show="products.length > 0">Calculated automatically from line items.</p>
                    </div>
                    <div>
                        <label class="crm-label">Probability, % <span class="text-red-500">*</span></label>
                        <input type="text" inputmode="decimal" name="probability" required data-crm-number data-decimals="0"
                               value="{{ old('probability', $opportunity->probability ?? 10) }}"
                               class="crm-field tabular-nums" @readonly($purchasingMode)>
                    </div>
                    <div>
                        <label class="crm-label">Close Date <span class="text-red-500">*</span></label>
                        <input type="date" name="close_date" value="{{ old('close_date', $opportunity->close_date ? \Illuminate\Support\Carbon::parse($opportunity->close_date)->format('Y-m-d') : '') }}" required
                               class="crm-field" @readonly($purchasingMode)>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="crm-label">Description</label>
                        <textarea name="description" rows="4" class="crm-field" @readonly($purchasingMode)>{{ old('description', $opportunity->description) }}</textarea>
                    </div>
                </div>
            </x-card>
        </div>

        {{-- Sidebar kanan --}}
        <div class="space-y-5">
            <x-card title="Assigned User">
                @if (auth()->user()->isAdmin() && ! $purchasingMode)
                    <select name="assigned_user_id" class="select2 w-full" data-placeholder="— Select —">
                        <option value="">— Select —</option>
                        @foreach ($salesUsers as $u)
                            <option value="{{ $u->id }}" @selected(old('assigned_user_id', $opportunity->assigned_user_id) === $u->id)>{{ $u->display_name }}</option>
                        @endforeach
                    </select>
                @else
                    <p class="text-sm text-slate-700">{{ optional($opportunity->assignedUser)->display_name ?: auth()->user()->display_name }}</p>
                    <input type="hidden" name="assigned_user_id" value="{{ old('assigned_user_id', $opportunity->assigned_user_id ?: auth()->id()) }}">
                @endif
            </x-card>

            @unless ($purchasingMode)
            <x-card title="Teams">
                <select name="team_ids[]" multiple class="select2 w-full" data-placeholder="— Select teams —">
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}" @selected(in_array($team->id, $selectedTeamIds))>{{ $team->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1.5 text-xs text-slate-400">Type to search, select one or more teams.</p>
            </x-card>
            @endunless

            <div class="flex flex-col gap-2">
                <x-btn type="submit" class="w-full justify-center" icon="bi-save">Save</x-btn>
                <x-btn href="{{ $cancelUrl }}" variant="secondary" class="w-full justify-center">Cancel</x-btn>
            </div>
        </div>
        </div>

            {{-- Line items: full width --}}
            <x-card>
                {{-- Langkah 1: pilih kategori saja --}}
                <div x-show="!selectedTaxCategory && !purchasingMode" class="rounded-lg border border-dashed border-slate-200 p-6 text-center">
                    <p class="mb-4 text-sm font-medium text-slate-700">Pilih kategori pajak terlebih dahulu</p>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <button type="button" @click="selectTaxCategory('non_wapu')"
                                class="rounded-lg border-2 border-slate-200 bg-white px-4 py-4 text-left transition hover:border-brand-500 hover:bg-brand-50">
                            <span class="block text-sm font-semibold text-slate-800">Non Wapu</span>
                            @if (auth()->user()?->isSuperAdmin())
                            <span class="mt-1 block text-xs text-slate-500">Barang: PPN saja<br>Jasa: PPN + PPH</span>
                            @endif
                        </button>
                        <button type="button" @click="selectTaxCategory('wapu')"
                                class="rounded-lg border-2 border-slate-200 bg-white px-4 py-4 text-left transition hover:border-brand-500 hover:bg-brand-50">
                            <span class="block text-sm font-semibold text-slate-800">Wapu</span>
                            @if (auth()->user()?->isSuperAdmin())
                            <span class="mt-1 block text-xs text-slate-500">Barang: PPN + 1.5%<br>Jasa: PPN + 2%</span>
                            @endif
                        </button>
                        <button type="button" @click="selectTaxCategory('inaproc')"
                                class="rounded-lg border-2 border-slate-200 bg-white px-4 py-4 text-left transition hover:border-brand-500 hover:bg-brand-50">
                            <span class="block text-sm font-semibold text-slate-800">Inaproc</span>
                            @if (auth()->user()?->isSuperAdmin())
                            <span class="mt-1 block text-xs text-slate-500">Seperti Wapu + PNBP berjenjang + PPH 29</span>
                            @endif
                        </button>
                        <button type="button" @click="selectTaxCategory('zinit')"
                                class="rounded-lg border-2 border-slate-200 bg-white px-4 py-4 text-left transition hover:border-brand-500 hover:bg-brand-50">
                            <span class="block text-sm font-semibold text-slate-800">Zinit</span>
                            @if (auth()->user()?->isSuperAdmin())
                            <span class="mt-1 block text-xs text-slate-500">Fee Zinit + PPH 2% jika Jasa</span>
                            @endif
                        </button>
                    </div>
                </div>

                {{-- Langkah 2: form item setelah kategori dipilih --}}
                <div x-show="selectedTaxCategory || purchasingMode" x-cloak class="relative">
                    {{-- Overlay loading import Excel --}}
                    <div x-show="productImportBusy" x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="absolute inset-0 z-20 flex items-center justify-center rounded-lg bg-white/80 backdrop-blur-[1px]">
                        <div class="flex flex-col items-center gap-3 rounded-xl border border-slate-200 bg-white px-6 py-5 shadow-lg">
                            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                                <i class="bi bi-arrow-repeat animate-spin text-2xl"></i>
                            </span>
                            <div class="text-center">
                                <p class="text-sm font-semibold text-slate-800">Mengimpor produk…</p>
                                <p class="mt-0.5 text-xs text-slate-500">Membaca &amp; memproses file Excel</p>
                            </div>
                        </div>
                    </div>
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm text-slate-500">Kategori:</span>
                            <template x-if="!purchasingMode">
                                <div class="inline-flex flex-wrap gap-1 rounded-lg border border-slate-200 bg-white p-1">
                                    <button type="button" @click="changeTaxCategory('non_wapu')"
                                            :class="selectedTaxCategory === 'non_wapu' ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-50'"
                                            class="rounded-md px-3 py-1.5 text-xs font-semibold transition">Non Wapu</button>
                                    <button type="button" @click="changeTaxCategory('wapu')"
                                            :class="selectedTaxCategory === 'wapu' ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-50'"
                                            class="rounded-md px-3 py-1.5 text-xs font-semibold transition">Wapu</button>
                                    <button type="button" @click="changeTaxCategory('inaproc')"
                                            :class="selectedTaxCategory === 'inaproc' ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-50'"
                                            class="rounded-md px-3 py-1.5 text-xs font-semibold transition">Inaproc</button>
                                    <button type="button" @click="changeTaxCategory('zinit')"
                                            :class="selectedTaxCategory === 'zinit' ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-50'"
                                            class="rounded-md px-3 py-1.5 text-xs font-semibold transition">Zinit</button>
                                </div>
                            </template>
                            <span x-show="purchasingMode" class="rounded-full bg-brand-100 px-3 py-1 text-sm font-semibold text-brand-700" x-text="taxCategoryLabel(selectedTaxCategory)"></span>
                        </div>
                        @if (auth()->user()?->isSuperAdmin())
                        <p class="w-full text-xs text-slate-500 sm:w-auto">Kategori bisa diganti kapan saja — PPH/PNBP/margin item dihitung ulang. Exclude atau Include bisa diisi (saling hitung via PPN); modal ongkir per satuan mengurangi margin; % margin (putih) bisa diubah; potongan pajak &amp; nilai margin (kuning) otomatis.</p>
                        @endif
                    </div>

                    <div class="space-y-4">
                        <template x-for="(p, i) in products" :key="i">
                            <div class="flex w-full gap-3 rounded-lg border border-slate-200 p-4">
                                <div class="flex w-8 shrink-0 flex-col items-center pt-6">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold tabular-nums text-slate-700"
                                          x-text="i + 1"
                                          :title="'Item ' + (i + 1)"></span>
                                </div>
                                <div class="min-w-0 w-full flex-1">
                                <input type="hidden" :name="`products[${i}][tax_category]`" :value="p.tax_category">
                                <input type="hidden" :name="`products[${i}][sku]`" :value="p.sku || ''">
                                <div class="mb-3 grid w-full grid-cols-1 gap-2 sm:grid-cols-12">
                                    <div class="sm:col-span-2">
                                        <label class="crm-label text-xs">Barang / Jasa</label>
                                        <select :name="`products[${i}][item_kind]`" x-model="p.item_kind" @change="onItemKindChange(p)" class="crm-field w-full text-sm" :disabled="purchasingMode">
                                            <option value="barang">Barang</option>
                                            <option value="jasa">Jasa</option>
                                        </select>
                                    </div>
                                    <div class="sm:col-span-3">
                                        <label class="crm-label text-xs">Item</label>
                                        <input type="text" :name="`products[${i}][name]`" x-model="p.name" placeholder="Nama item" class="crm-field w-full" :readonly="purchasingMode">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="crm-label text-xs">Brand</label>
                                        <select :name="`products[${i}][brand]`"
                                                class="select2 select2-search w-full text-sm"
                                                data-placeholder="— Brand —"
                                                data-brand-select
                                                :disabled="purchasingMode"
                                                x-init="$nextTick(() => initBrandSelect($el, i))">
                                            <option value="">— Brand —</option>
                                            @foreach ($brandOptions ?? [] as $brand)
                                                <option value="{{ $brand['name'] }}">{{ $brand['name'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="crm-label text-xs">Category</label>
                                        <select :name="`products[${i}][category]`"
                                                class="select2 select2-search w-full text-sm"
                                                data-placeholder="— Category —"
                                                data-category-select
                                                :disabled="purchasingMode"
                                                x-init="$nextTick(() => initCategorySelect($el, i))">
                                            <option value="">— Category —</option>
                                            @foreach ($categoryOptions ?? [] as $category)
                                                <option value="{{ $category['name'] }}">{{ $category['name'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="sm:col-span-1">
                                        <label class="crm-label text-xs">Qty</label>
                                        <input type="text" inputmode="decimal"
                                               x-effect="if (editingField !== `qty-${i}`) $el.value = formatId(p.quantity, 2)"
                                               @focus="editingField = `qty-${i}`"
                                               @blur="editingField = null; $el.value = formatId(p.quantity, 2)"
                                               @input="p.quantity = parseId($event.target.value); refreshDiscountFromMargin()"
                                               class="crm-field w-full min-w-[4.5rem] text-right tabular-nums" :readonly="purchasingMode">
                                        <input type="hidden" :name="`products[${i}][quantity]`" :value="p.quantity">
                                    </div>
                                    <div class="sm:col-span-1">
                                        <label class="crm-label text-xs">Vendor</label>
                                        <select :name="`products[${i}][vendor]`"
                                                class="select2 select2-search w-full text-sm"
                                                data-placeholder="— Vendor —"
                                                data-vendor-select
                                                x-init="$nextTick(() => initVendorSelect($el, i))">
                                            <option value="">— Vendor —</option>
                                            @foreach ($vendorOptions ?? [] as $vendor)
                                                <option value="{{ $vendor['name'] }}">{{ $vendor['name'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="flex items-end justify-end sm:col-span-1">
                                        <button type="button" x-show="!purchasingMode" @click="removeProduct(i)" class="rounded-lg p-2 text-red-500 hover:bg-red-50" title="Hapus item"><i class="bi bi-trash"></i></button>
                                    </div>
                                </div>

                                <div class="mb-3 grid grid-cols-1 gap-3 sm:grid-cols-12">
                                    <div class="sm:col-span-6">
                                        <label class="crm-label text-xs">Gambar <span class="font-normal text-slate-400">(opsional)</span></label>
                                        <div class="mt-1.5 flex flex-wrap items-center gap-3">
                                            <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                                <template x-if="p.image_preview || p.image_url">
                                                    <img :src="p.image_preview || p.image_url" alt="" class="h-full w-full object-cover">
                                                </template>
                                                <template x-if="!(p.image_preview || p.image_url)">
                                                    <i class="bi bi-image text-lg text-slate-300"></i>
                                                </template>
                                            </div>
                                            <div class="min-w-0 flex-1 space-y-1">
                                                <input type="file"
                                                       :name="`products[${i}][image_file]`"
                                                       accept="image/png,image/jpeg,image/webp,image/gif"
                                                       class="crm-field w-full max-w-xs text-xs file:mr-2 file:rounded file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs file:font-medium file:text-slate-700"
                                                       :disabled="purchasingMode"
                                                       @change="onProductImageChange(p, $event)">
                                                <div class="flex items-center gap-2" x-show="p.image_preview || p.image_url || p.image" x-cloak>
                                                    <button type="button"
                                                            x-show="!purchasingMode"
                                                            @click="clearProductImage(p, i)"
                                                            class="text-xs font-medium text-red-600 hover:text-red-700">
                                                        Hapus gambar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <input type="hidden" :name="`products[${i}][image]`" :value="p.remove_image ? '' : (p.image || '')">
                                        <input type="hidden" :name="`products[${i}][remove_image]`" :value="p.remove_image ? 1 : 0">
                                    </div>
                                    <div class="sm:col-span-4">
                                        <label class="crm-label text-xs">Royalti</label>
                                        <div class="mt-1.5 flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 text-xs text-slate-700"
                                             :class="p.royalty_type ? 'border-brand-300 bg-brand-50' : ''">
                                            <label class="inline-flex items-center gap-1.5">
                                                <input type="checkbox"
                                                       :checked="p.royalty_type === 'dalam'"
                                                       @change="toggleRoyaltyType(p, 'dalam')"
                                                       class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                                       :disabled="purchasingMode">
                                                <span x-text="royaltyDalamPercent + '%'"></span>
                                            </label>
                                            <label class="inline-flex items-center gap-1.5">
                                                <input type="checkbox"
                                                       :checked="p.royalty_type === 'luar'"
                                                       @change="toggleRoyaltyType(p, 'luar')"
                                                       class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                                       :disabled="purchasingMode">
                                                <span x-text="royaltyLuarPercent + '%'"></span>
                                            </label>
                                        </div>
                                        <input type="hidden" :name="`products[${i}][royalty_type]`" :value="p.royalty_type || ''">
                                        <input type="hidden" :name="`products[${i}][has_royalty]`" :value="p.royalty_type ? 1 : 0">
                                    </div>
                                </div>

                            <div class="w-full overflow-x-auto">
                                <table class="w-full table-fixed text-sm">
                                    <colgroup>
                                        <col class="w-[28%]">
                                        <col class="w-[36%]">
                                        <col class="w-[36%]">
                                    </colgroup>
                                    <thead>
                                        <tr class="text-left text-xs uppercase tracking-wider text-slate-400">
                                            <th class="pb-2 pr-3"></th>
                                            <th class="pb-2 pr-3">Exclude</th>
                                            <th class="pb-2 pr-3">Include</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr>
                                            <td class="py-2 pr-3 font-medium text-slate-600">Harga Jual</td>
                                            <td class="py-2 pr-3">
                                                <input type="text" inputmode="decimal"
                                                       x-effect="if (editingField !== `sell-${i}`) $el.value = formatId(p.sell_exclude)"
                                                       @focus="editingField = `sell-${i}`"
                                                       @blur="editingField = null; $el.value = formatId(p.sell_exclude)"
                                                       @input="p.sell_exclude = parseId($event.target.value); onSellChange(p)"
                                                       class="crm-field w-full min-w-[8rem] bg-white text-right tabular-nums" :readonly="purchasingMode">
                                                <input type="hidden" :name="`products[${i}][sell_exclude]`" :value="p.sell_exclude">
                                            </td>
                                            <td class="py-2 pr-3">
                                                <input type="text" inputmode="decimal"
                                                       x-effect="if (editingField !== `sell-inc-${i}`) $el.value = formatId(sellInclude(p))"
                                                       @focus="editingField = `sell-inc-${i}`"
                                                       @blur="editingField = null; $el.value = formatId(sellInclude(p))"
                                                       @input="onSellIncludeChange(p, parseId($event.target.value))"
                                                       class="crm-field w-full min-w-[8rem] border-amber-200 bg-amber-50 text-right tabular-nums"
                                                       :readonly="purchasingMode"
                                                       title="Isi Include → Exclude dihitung otomatis (÷ PPN)">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="py-2 pr-3 font-medium text-slate-600">
                                                Diskon Item
                                                <span class="block text-[10px] font-normal normal-case tracking-normal text-slate-400">Harga net (0 = pakai harga jual)</span>
                                            </td>
                                            <td class="py-2 pr-3">
                                                <input type="text" inputmode="decimal" placeholder="0"
                                                       x-effect="if (editingField !== `disc-${i}`) $el.value = formatId(p.discount_exclude)"
                                                       @focus="editingField = `disc-${i}`"
                                                       @blur="editingField = null; $el.value = formatId(p.discount_exclude)"
                                                       @input="p.discount_exclude = parseId($event.target.value); onDiscountItemChange(p)"
                                                       class="crm-field w-full min-w-[8rem] bg-white text-right tabular-nums" :readonly="purchasingMode">
                                                <input type="hidden" :name="`products[${i}][discount_exclude]`" :value="p.discount_exclude">
                                            </td>
                                            <td class="py-2 pr-3">
                                                <input type="text" inputmode="decimal" placeholder="0"
                                                       x-effect="if (editingField !== `disc-inc-${i}`) $el.value = formatId(discountInclude(p))"
                                                       @focus="editingField = `disc-inc-${i}`"
                                                       @blur="editingField = null; $el.value = formatId(discountInclude(p))"
                                                       @input="onDiscountIncludeChange(p, parseId($event.target.value))"
                                                       class="crm-field w-full min-w-[8rem] border-amber-200 bg-amber-50 text-right tabular-nums"
                                                       :readonly="purchasingMode"
                                                       title="Isi Include → Exclude dihitung otomatis (÷ PPN)">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="py-2 pr-3 font-medium text-slate-600">
                                                <div class="flex flex-col gap-1.5">
                                                    <span>Harga Beli / Modal</span>
                                                    <div class="inline-flex w-fit rounded-md border border-slate-200 bg-slate-50 p-0.5">
                                                        <button type="button"
                                                                @click="setCostMode(p, false)"
                                                                :class="!p.cost_foreign
                                                                    ? 'rounded bg-white px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-800 shadow-sm'
                                                                    : 'rounded px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500 transition hover:text-slate-700'">
                                                            Rupiah
                                                        </button>
                                                        <button type="button"
                                                                @click="setCostMode(p, true)"
                                                                :class="p.cost_foreign
                                                                    ? 'rounded bg-white px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-800 shadow-sm'
                                                                    : 'rounded px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500 transition hover:text-slate-700'">
                                                            Asing
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-2 pr-3">
                                                <div x-show="!p.cost_foreign">
                                                    <input type="text" inputmode="decimal"
                                                           x-effect="if (editingField !== `cost-${i}`) $el.value = formatId(p.cost_exclude)"
                                                           @focus="editingField = `cost-${i}`"
                                                           @blur="editingField = null; $el.value = formatId(p.cost_exclude)"
                                                           @input="p.cost_exclude = parseId($event.target.value); onCostChange(p)"
                                                           class="crm-field w-full min-w-[8rem] bg-white text-right tabular-nums">
                                                </div>
                                                <div x-show="p.cost_foreign" class="space-y-1.5" x-cloak>
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="w-12 shrink-0 text-[10px] font-semibold uppercase text-slate-500">Mata uang</span>
                                                        <input type="text"
                                                               x-model="p.cost_fx_code"
                                                               @input="p.cost_fx_code = String($event.target.value || '').toUpperCase().replace(/[^A-Z]/g, '').slice(0, 6)"
                                                               placeholder="USD / SGD"
                                                               maxlength="6"
                                                               class="crm-field w-full min-w-[6rem] bg-white uppercase tracking-wide"
                                                               title="Kode mata uang asing, mis. USD, SGD, EUR">
                                                    </div>
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="w-12 shrink-0 text-[10px] font-semibold uppercase text-slate-500" x-text="p.cost_fx_code || 'FX'"></span>
                                                        <input type="text" inputmode="decimal" placeholder="0"
                                                               x-effect="if (editingField !== `cost-fx-${i}`) $el.value = formatId(p.cost_fx, 4)"
                                                               @focus="editingField = `cost-fx-${i}`"
                                                               @blur="editingField = null; $el.value = formatId(p.cost_fx, 4)"
                                                               @input="p.cost_fx = parseId($event.target.value); recalcCostFromFx(p)"
                                                               class="crm-field w-full min-w-[7rem] bg-white text-right tabular-nums"
                                                               title="Harga modal dalam mata uang asing">
                                                    </div>
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="w-12 shrink-0 text-[10px] font-semibold uppercase text-slate-500">Rate</span>
                                                        <input type="text" inputmode="decimal" placeholder="0"
                                                               x-effect="if (editingField !== `fx-rate-${i}`) $el.value = formatId(p.fx_rate)"
                                                               @focus="editingField = `fx-rate-${i}`"
                                                               @blur="editingField = null; $el.value = formatId(p.fx_rate)"
                                                               @input="p.fx_rate = parseId($event.target.value); recalcCostFromFx(p)"
                                                               class="crm-field w-full min-w-[7rem] bg-white text-right tabular-nums"
                                                               title="Kurs Rp per 1 unit mata uang asing (diisi sales)">
                                                    </div>
                                                    <p class="text-[11px] text-slate-500">
                                                        = <span class="font-semibold tabular-nums text-slate-700" x-text="formatMoney(p.cost_exclude)"></span>
                                                        <span class="text-slate-400"> exclude</span>
                                                    </p>
                                                </div>
                                                <input type="hidden" :name="`products[${i}][cost_exclude]`" :value="p.cost_exclude">
                                                <input type="hidden" :name="`products[${i}][cost_foreign]`" :value="p.cost_foreign ? 1 : 0">
                                                <input type="hidden" :name="`products[${i}][cost_in_usd]`" :value="p.cost_foreign ? 1 : 0">
                                                <input type="hidden" :name="`products[${i}][cost_fx_code]`" :value="p.cost_fx_code">
                                                <input type="hidden" :name="`products[${i}][cost_fx]`" :value="p.cost_fx">
                                                <input type="hidden" :name="`products[${i}][cost_usd]`" :value="p.cost_fx">
                                                <input type="hidden" :name="`products[${i}][fx_rate]`" :value="p.fx_rate">
                                                <input type="hidden" :name="`products[${i}][usd_rate]`" :value="p.fx_rate">
                                            </td>
                                            <td class="py-2 pr-3">
                                                <input type="text" inputmode="decimal"
                                                       x-effect="if (editingField !== `cost-inc-${i}`) $el.value = formatId(costInclude(p))"
                                                       @focus="editingField = `cost-inc-${i}`"
                                                       @blur="editingField = null; $el.value = formatId(costInclude(p))"
                                                       @input="onCostIncludeChange(p, parseId($event.target.value))"
                                                       class="crm-field w-full min-w-[8rem] border-amber-200 bg-amber-50 text-right tabular-nums"
                                                       :readonly="p.cost_foreign"
                                                       :title="p.cost_foreign ? 'Dihitung dari modal asing × rate' : 'Isi Include → Exclude dihitung otomatis (÷ PPN)'">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="py-2 pr-3 font-medium text-slate-600">
                                                Modal ongkir
                                                <span class="block text-[10px] font-normal normal-case tracking-normal text-slate-400">Per satuan, mengurangi margin</span>
                                            </td>
                                            <td class="py-2 pr-3">
                                                <input type="text" inputmode="decimal" placeholder="0"
                                                       x-effect="if (editingField !== `ship-${i}`) $el.value = formatId(p.shipping_exclude)"
                                                       @focus="editingField = `ship-${i}`"
                                                       @blur="editingField = null; $el.value = formatId(p.shipping_exclude)"
                                                       @input="p.shipping_exclude = parseId($event.target.value); onShippingChange(p)"
                                                       class="crm-field w-full min-w-[8rem] bg-white text-right tabular-nums" :readonly="purchasingMode">
                                                <input type="hidden" :name="`products[${i}][shipping_exclude]`" :value="p.shipping_exclude">
                                            </td>
                                            <td class="py-2 pr-3">
                                                <input type="text" inputmode="decimal" placeholder="0"
                                                       x-effect="if (editingField !== `ship-inc-${i}`) $el.value = formatId(shippingInclude(p))"
                                                       @focus="editingField = `ship-inc-${i}`"
                                                       @blur="editingField = null; $el.value = formatId(shippingInclude(p))"
                                                       @input="onShippingIncludeChange(p, parseId($event.target.value))"
                                                       class="crm-field w-full min-w-[8rem] border-amber-200 bg-amber-50 text-right tabular-nums"
                                                       :readonly="purchasingMode"
                                                       title="Isi Include → Exclude dihitung otomatis (÷ PPN)">
                                            </td>
                                        </tr>
                                        <tr x-show="appliesPph(p)">
                                            <td class="py-2 pr-3 font-medium text-slate-600" x-text="'PPH ' + pphPercentFor(p) + '%'"></td>
                                            <td class="py-2 pr-3 text-xs text-slate-400" x-text="'Basis × ' + pphPercentFor(p) + '%'"></td>
                                            <td class="py-2 pr-3">
                                                <input type="text" readonly :value="formatId(pphAmount(p))"
                                                       class="crm-field w-full min-w-[8rem] cursor-default border-amber-200 bg-amber-50 text-right tabular-nums text-slate-700">
                                            </td>
                                        </tr>
                                        <tr x-show="appliesPnbp(p)">
                                            <td class="py-2 pr-3 font-medium text-slate-600">PNBP</td>
                                            <td class="py-2 pr-3 text-xs text-slate-400">MIN(include × rate, cap)</td>
                                            <td class="py-2 pr-3">
                                                <input type="text" readonly :value="formatId(pnbpAmount(p))"
                                                       class="crm-field w-full min-w-[8rem] cursor-default border-amber-200 bg-amber-50 text-right tabular-nums text-slate-700">
                                            </td>
                                        </tr>
                                        <tr x-show="appliesPph29(p)">
                                            <td class="py-2 pr-3 font-medium text-slate-600" x-text="'PPH 29 ' + pph29Percent + '%'"></td>
                                            <td class="py-2 pr-3 text-xs text-slate-400">(Jual − Modal) exclude</td>
                                            <td class="py-2 pr-3">
                                                <input type="text" readonly :value="formatId(pph29Amount(p))"
                                                       class="crm-field w-full min-w-[8rem] cursor-default border-amber-200 bg-amber-50 text-right tabular-nums text-slate-700">
                                            </td>
                                        </tr>
                                        <tr x-show="appliesRoyalty(p)">
                                            <td class="py-2 pr-3 font-medium text-slate-600" x-text="'Royalti ' + royaltyPercentFor(p) + '%'"></td>
                                            <td class="py-2 pr-3 text-xs text-slate-400" x-text="royaltyTypeLabel(p)"></td>
                                            <td class="py-2 pr-3">
                                                <input type="text" readonly :value="formatId(royaltyAmount(p))"
                                                       class="crm-field w-full min-w-[8rem] cursor-default border-amber-200 bg-amber-50 text-right tabular-nums text-slate-700">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="py-2 pr-3 font-medium text-slate-600">Margin</td>
                                            <td class="py-2 pr-3 text-xs text-slate-400" x-text="marginLabel(p)"></td>
                                            <td class="py-2 pr-3">
                                                <div class="flex items-center gap-2">
                                                    <input type="text" readonly :value="formatId(marginAmount(p))"
                                                           class="crm-field min-w-[8rem] flex-1 cursor-default border-amber-200 bg-amber-50 text-right tabular-nums text-slate-700">
                                                    <div class="flex shrink-0 items-center gap-1">
                                                        <input type="text" inputmode="decimal"
                                                               x-effect="if (editingField !== `mgn-${i}`) $el.value = formatId(p.margin_percent, 2)"
                                                               @focus="editingField = `mgn-${i}`"
                                                               @blur="editingField = null; $el.value = formatId(p.margin_percent, 2)"
                                                               @input="p.margin_percent = parseId($event.target.value); onMarginPercentChange(p)"
                                                               class="crm-field w-24 bg-white text-right text-sm tabular-nums"
                                                               title="Ubah % margin untuk hitung ulang harga jual / diskon"
                                                               :readonly="purchasingMode || appliesZinit(p)">
                                                        <span class="text-xs font-semibold text-slate-600">%</span>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                                </div>
                            </div>
                        </template>
                        <p x-show="products.length === 0" class="rounded-lg border border-dashed border-slate-200 py-4 text-center text-sm text-slate-400">Belum ada item. Klik Add Item atau Import Excel.</p>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2" x-show="!purchasingMode">
                            <x-btn type="button" variant="secondary" icon="bi-plus-lg" @click="addProduct()" x-bind:disabled="productImportBusy">Add Item</x-btn>
                            <x-btn variant="secondary" icon="bi-download" :href="route('opportunities.products.template')" x-bind:class="productImportBusy ? 'pointer-events-none opacity-50' : ''">Download Template</x-btn>
                            <button type="button"
                                    @click="$refs.productImportInput.click()"
                                    x-bind:disabled="!selectedTaxCategory || productImportBusy"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">
                                <i class="bi" :class="productImportBusy ? 'bi-arrow-repeat animate-spin' : 'bi-file-earmark-excel'"></i>
                                <span x-text="productImportBusy ? 'Mengimpor…' : 'Import Excel'"></span>
                            </button>
                            <input type="file" x-ref="productImportInput" class="hidden"
                                   accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv"
                                   @change="importProductsFromFile($event)">
                        </div>
                        <div class="text-sm" :class="purchasingMode ? 'ml-auto' : ''">
                            <span class="text-slate-500">Total (Include):&nbsp;</span>
                            <span class="font-semibold text-slate-800" x-text="formatMoney(productsTotal)"></span>
                            <span class="mx-2 text-slate-300" x-show="products.some(p => appliesZinit(p))" x-cloak>·</span>
                            <span class="text-slate-500" x-show="products.some(p => appliesZinit(p))" x-cloak>Fee Zinit:&nbsp;</span>
                            <span class="font-semibold text-amber-700" x-show="products.some(p => appliesZinit(p))" x-cloak x-text="formatMoney(zinitDealFees().success_fee)"></span>
                            <span class="mx-2 text-slate-300">·</span>
                            <span class="text-slate-500">Total Margin:&nbsp;</span>
                            <span class="font-semibold text-green-700" x-text="formatMoney(productsMarginTotal)"></span>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-slate-400" x-show="!purchasingMode">
                        Bulk import: unduh template, isi (atau ganti) baris produk, lalu Import Excel (.xlsx / .csv). Kategori pajak mengikuti pilihan di atas.
                    </p>
                </div>
            </x-card>

            @unless ($purchasingMode)
            <x-card title="Ongkir jual">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="has_shipping_charge" value="0">
                    <input type="checkbox" name="has_shipping_charge" value="1" x-model="hasShippingCharge"
                           class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                           @checked(old('has_shipping_charge', $opportunity->crm_has_shipping_charge))>
                    Ada ongkir (dijual ke customer)
                </label>
                <div x-show="hasShippingCharge" x-cloak class="mt-3 grid max-w-md gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Exclude</label>
                        <input type="text" inputmode="decimal" placeholder="0"
                               x-effect="if (editingField !== 'ship-sell-ex') $el.value = formatId(shippingSell)"
                               @focus="editingField = 'ship-sell-ex'"
                               @blur="editingField = null; $el.value = formatId(shippingSell)"
                               @input="onShippingSellExcludeChange(parseId($event.target.value))"
                               class="crm-field w-full text-right tabular-nums">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Include</label>
                        <input type="text" inputmode="decimal" placeholder="0"
                               x-effect="if (editingField !== 'ship-sell-inc') $el.value = formatId(shippingSellInclude())"
                               @focus="editingField = 'ship-sell-inc'"
                               @blur="editingField = null; $el.value = formatId(shippingSellInclude())"
                               @input="onShippingSellIncludeChange(parseId($event.target.value))"
                               class="crm-field w-full border-amber-200 bg-amber-50 text-right tabular-nums"
                               title="Isi Include → Exclude dihitung otomatis (÷ PPN)">
                    </div>
                    <input type="hidden" name="shipping_sell" :value="shippingSell">
                    @if (auth()->user()?->isSuperAdmin())
                    <p class="mt-1 text-xs text-slate-400">
                        Jika dicentang, threshold margin nominal hanya memakai <em>Nominal Umum</em>
                        (tanpa Nominal Ongkir Pribadi).
                    </p>
                    @endif
                </div>
                <p class="mt-2 text-xs" :class="isFreeShippingCity ? 'text-green-700' : 'text-slate-500'" x-show="accountId">
                    <span x-show="isFreeShippingCity"><i class="bi bi-check-circle mr-1"></i>Kota customer termasuk kawasan free ongkir.</span>
                    <span x-show="!isFreeShippingCity"><i class="bi bi-info-circle mr-1"></i>Kota customer bukan free ongkir — threshold nominal memakai Umum + Ongkir Pribadi (kecuali checkbox di atas dicentang).</span>
                </p>
            </x-card>

            <x-card title="Diskon tambahan">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="has_discount" value="0">
                    <input type="checkbox" name="has_discount" value="1" x-model="hasDiscount"
                           class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                           @checked(old('has_discount', $opportunity->crm_has_discount))>
                    Aktifkan diskon tambahan
                </label>
                <div x-show="hasDiscount" x-cloak class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Persentase dari margin</label>
                        <div class="flex items-center gap-2">
                            <input type="text" inputmode="decimal" placeholder="0"
                                   x-effect="if (editingField !== 'discPct') $el.value = formatId(discountPercent, 2)"
                                   @focus="editingField = 'discPct'"
                                   @blur="editingField = null; $el.value = formatId(discountPercent, 2)"
                                   @input="discountPercent = parseId($event.target.value); onDiscountPercentChange()"
                                   class="crm-field w-full tabular-nums">
                            <span class="shrink-0 text-sm font-semibold text-slate-600">%</span>
                        </div>
                        @if (auth()->user()?->isSuperAdmin())
                        <p class="mt-1 text-xs text-slate-400">Isi % → nominal terisi otomatis dari basis margin.</p>
                        @endif
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Nominal diskon</label>
                        <input type="text" inputmode="decimal" placeholder="0"
                               x-effect="if (editingField !== 'discAmt') $el.value = formatId(discountAmount)"
                               @focus="editingField = 'discAmt'"
                               @blur="editingField = null; $el.value = formatId(discountAmount)"
                               @input="discountAmount = parseId($event.target.value); onDiscountAmountChange()"
                               class="crm-field w-full tabular-nums">
                        <input type="hidden" name="discount_amount" :value="discountAmount">
                        <p class="mt-1 text-xs text-slate-400" x-show="!skipsApproval">
                            Setiap diskon &gt; 0 wajib approval Superadmin (mulai stage Proposal).
                        </p>
                        <p class="mt-1 text-xs text-slate-400" x-show="skipsApproval" x-cloak>
                            Di Prospecting/Qualification, diskon tambahan tidak perlu approval.
                        </p>
                    </div>
                </div>
                <p class="mt-2 text-xs text-slate-500" x-show="hasDiscount && discountBasisMarginTotal > 0">
                    Basis:
                    <span x-text="discountBasisIsGross ? 'margin kotor (sebelum PNBP & PPH 29)' : 'total margin'"></span>
                    <span class="font-medium" x-text="formatMoney(discountBasisMarginTotal)"></span>
                </p>
                <p class="mt-2 text-xs text-amber-600" x-show="hasDiscount && discountBasisMarginTotal <= 0">
                    Isi harga jual &amp; modal produk dulu agar % diskon bisa dihitung dari margin.
                </p>
                @if ($opportunity->exists && $opportunity->hasActiveDiscount())
                    <p class="mt-2 text-xs text-slate-500">
                        Status: <span class="font-medium">{{ $opportunity->discountStatusLabel() }}</span>
                    </p>
                @endif
            </x-card>

            <div x-show="marginNeedsApprovalHint" x-cloak
                 class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">
                    <i class="bi bi-exclamation-triangle mr-1"></i>
                    <span x-text="marginAbovePercent ? 'Margin di atas batas atas' : 'Margin di bawah minimal'"></span>
                </p>
                <p class="mt-1 text-xs">
                    Margin saat ini
                    <strong x-text="(overallMarginPercent ?? '—') + '%'"></strong>
                    / <strong x-text="formatMoney(productsMarginTotal)"></strong>
                    · Minimal
                    <strong x-text="(accountMinMarginPct ?? '—') + '%'"></strong>
                    / <strong x-text="formatMoney(requiredNominalThreshold)"></strong>
                    · Maksimal
                    <strong x-text="marginMaxPercent + '%'"></strong>.
                    Opportunity tetap bisa disimpan, tetapi memerlukan approval Superadmin
                    (kecuali stage Prospecting/Qualification).
                </p>
            </div>
            @endunless
    </div>
</form>

<script>
    function opportunityForm(config) {
        const PPN_PERCENT = Number(config.ppnPercent) || 11;
        const TAX_MULTIPLIER = 1 + (PPN_PERCENT / 100);
        const PPH_NON_WAPU_JASA = Number(config.pphNonWapuJasa) || 2;
        const PPH_WAPU_BARANG = Number(config.pphWapuBarang) || 1.5;
        const PPH_WAPU_JASA = Number(config.pphWapuJasa) || 2;
        const PNBP_PERCENT = Number(config.pnbpPercent) || 0.4;
        const PNBP_TIERS = (config.pnbpTiers || []).map(t => ({
            max: t.max === null || t.max === undefined || t.max === '' ? null : Number(t.max),
            rate_percent: Number(t.rate_percent) || 0,
            cap: Number(t.cap) || 0,
        }));
        const PPH29_PERCENT = Number(config.pph29Percent) || 22;
        const ROYALTY_DALAM_PERCENT = Number(config.royaltyDalamPercent) || 15;
        const ROYALTY_LUAR_PERCENT = Number(config.royaltyLuarPercent) || 20;
        const ZINIT_TIERS = (config.zinitTiers || []).map(t => ({
            max: t.max === null || t.max === undefined || t.max === '' ? null : Number(t.max),
            platform_fee: Number(t.platform_fee) || 0,
            rate_percent: Number(t.rate_percent) || 0,
            cap: t.cap === null || t.cap === undefined || t.cap === '' ? null : Number(t.cap),
        }));

        function pphPercentLookup(taxCategory, itemKind) {
            if (taxCategory === 'zinit' && itemKind === 'jasa') return PPH_NON_WAPU_JASA;
            if (taxCategory === 'zinit') return 0;
            if (taxCategory === 'non_wapu' && itemKind === 'barang') return 0;
            if (taxCategory === 'non_wapu' && itemKind === 'jasa') return PPH_NON_WAPU_JASA;
            if ((taxCategory === 'wapu' || taxCategory === 'inaproc') && itemKind === 'barang') return PPH_WAPU_BARANG;
            if ((taxCategory === 'wapu' || taxCategory === 'inaproc') && itemKind === 'jasa') return PPH_WAPU_JASA;
            return PPH_NON_WAPU_JASA;
        }

        function matchPnbpTier(sellInclude) {
            const tiers = [...PNBP_TIERS].sort((a, b) => {
                if (a.max === null && b.max === null) return 0;
                if (a.max === null) return 1;
                if (b.max === null) return -1;
                return a.max - b.max;
            });
            let fallback = tiers[tiers.length - 1] || { max: null, rate_percent: PNBP_PERCENT, cap: 0 };
            for (const tier of tiers) {
                if (tier.max === null) return tier;
                if (sellInclude <= tier.max) return tier;
            }
            return fallback;
        }

        function calcPnbpFromInclude(sellInclude) {
            if (sellInclude <= 0) return 0;
            const tier = matchPnbpTier(sellInclude);
            let amount = sellInclude * ((Number(tier.rate_percent) || 0) / 100);
            const cap = Number(tier.cap) || 0;
            if (cap > 0) amount = Math.min(amount, cap);
            return Math.round(amount * 100) / 100;
        }

        function matchZinitTier(volume) {
            const tiers = [...ZINIT_TIERS].sort((a, b) => {
                if (a.max === null && b.max === null) return 0;
                if (a.max === null) return 1;
                if (b.max === null) return -1;
                return a.max - b.max;
            });
            let fallback = tiers[tiers.length - 1] || { max: null, platform_fee: 0, rate_percent: 0, cap: null };
            // Samakan Excel IFS: tier pertama < max, sisanya <= max.
            for (let i = 0; i < tiers.length; i++) {
                const tier = tiers[i];
                if (tier.max === null) return tier;
                if (i === 0) {
                    if (volume < tier.max) return tier;
                    continue;
                }
                if (volume <= tier.max) return tier;
            }
            return fallback;
        }

        function calcZinitFees(volume) {
            volume = Math.max(0, Math.round((Number(volume) || 0) * 100) / 100);
            if (volume <= 0) {
                return { volume: 0, platform_fee: 0, service_fee: 0, success_fee: 0, rate_percent: 0, cap: null };
            }
            const tier = matchZinitTier(volume);
            const platform = Math.round((Number(tier.platform_fee) || 0) * 100) / 100;
            const rate = Number(tier.rate_percent) || 0;
            let service = Math.round(volume * (rate / 100) * 100) / 100;
            const cap = tier.cap === null || tier.cap === undefined ? null : Number(tier.cap);
            if (cap !== null && cap > 0) service = Math.min(service, cap);
            return {
                volume,
                platform_fee: platform,
                service_fee: service,
                success_fee: Math.round((platform + service) * 100) / 100,
                rate_percent: rate,
                cap,
            };
        }

        function calcZinitFeesFromSellExclude(sellExclude, quantity = 1) {
            const qty = quantity > 0 ? quantity : 1;
            const includeVolume = Math.round((Number(sellExclude) || 0) * TAX_MULTIPLIER * qty * 100) / 100;
            return calcZinitFees(includeVolume);
        }

        function royaltyPercentLookup(royaltyType) {
            if (royaltyType === 'dalam') return ROYALTY_DALAM_PERCENT;
            if (royaltyType === 'luar') return ROYALTY_LUAR_PERCENT;
            return 0;
        }

        function calcNetMargin(base, costExclude, taxCategory, itemKind, quantity = 1, royaltyType = '', shippingExclude = 0) {
            let net;
            if (taxCategory === 'zinit') {
                const pphPct = pphPercentLookup(taxCategory, itemKind);
                const pph = pphPct > 0 ? Math.round(base * (pphPct / 100) * 100) / 100 : 0;
                // GP per baris tanpa fee; Fee Zinit dipotong sekali di Total Margin.
                net = Math.round((base - pph - costExclude) * 100) / 100;
            } else {
                const pphPct = pphPercentLookup(taxCategory, itemKind);
                const pph = pphPct > 0 ? Math.round(base * (pphPct / 100) * 100) / 100 : 0;
                if (taxCategory === 'wapu' || taxCategory === 'inaproc') {
                    const costInclude = Math.round(costExclude * TAX_MULTIPLIER * 100) / 100;
                    const gross = Math.round((base - pph - costInclude) * 100) / 100;
                    const pnbp = taxCategory === 'inaproc'
                        ? calcPnbpFromInclude(Math.round(base * TAX_MULTIPLIER * 100) / 100)
                        : 0;
                    const spread = base - costExclude;
                    const pph29 = (taxCategory === 'inaproc' && spread > 0)
                        ? Math.round(spread * (PPH29_PERCENT / 100) * 100) / 100
                        : 0;
                    net = Math.round((gross - pnbp - pph29) * 100) / 100;
                } else {
                    net = Math.round((base - pph - costExclude) * 100) / 100;
                }
            }
            const rate = royaltyPercentLookup(royaltyType);
            if (rate > 0) {
                const royalty = Math.round(costExclude * (rate / 100) * 100) / 100;
                net = Math.round((net - royalty) * 100) / 100;
            }
            const shipping = Math.max(0, Number(shippingExclude) || 0);
            if (shipping > 0) {
                net = Math.round((net - shipping) * 100) / 100;
            }
            return net;
        }

        const initialProducts = (config.products || []).map(p => {
            const taxCategory = p.tax_category ?? 'non_wapu';
            const itemKind = p.item_kind ?? 'barang';
            const sell = Number(p.sell_exclude) || 0;
            const cost = Number(p.cost_exclude) || 0;
            const discount = Number(p.discount_exclude) || 0;
            const shipping = Number(p.shipping_exclude) || 0;
            const qty = Number(p.quantity) || 1;
            let royaltyType = (p.royalty_type || '').toString();
            if (!royaltyType && p.has_royalty) royaltyType = 'luar';
            if (royaltyType !== 'dalam' && royaltyType !== 'luar') royaltyType = '';
            const base = discount > 0 ? discount : sell;
            const margin = calcNetMargin(base, cost, taxCategory, itemKind, qty, royaltyType, shipping);
            const pphPct = pphPercentLookup(taxCategory, itemKind);
            const pph = pphPct > 0 ? Math.round(base * (pphPct / 100) * 100) / 100 : 0;
            let denom = base;
            if (taxCategory === 'wapu' || taxCategory === 'inaproc') {
                denom = base - pph;
            } else if (taxCategory === 'zinit') {
                const fees = calcZinitFeesFromSellExclude(base, qty > 0 ? qty : 1);
                const feePerUnit = fees.success_fee / (qty > 0 ? qty : 1);
                denom = base - feePerUnit;
            }
            const marginPercent = denom > 0 ? Math.round((margin / denom) * 10000) / 100 : 0;

            return {
                name: p.name ?? '',
                quantity: qty,
                sell_exclude: sell,
                cost_exclude: cost,
                discount_exclude: discount,
                shipping_exclude: shipping,
                vendor: p.vendor ?? '',
                brand: p.brand ?? '',
                sku: p.sku ?? '',
                category: p.category ?? '',
                tax_category: taxCategory,
                item_kind: itemKind,
                royalty_type: royaltyType,
                has_royalty: !!royaltyType,
                image: p.image ?? '',
                image_url: p.image_url ?? '',
                image_preview: '',
                remove_image: false,
                margin_percent: marginPercent,
                _lockMarginPercent: false,
                cost_foreign: !!(p.cost_foreign || p.cost_in_usd),
                cost_fx_code: p.cost_fx_code || ((p.cost_foreign || p.cost_in_usd) ? 'USD' : ''),
                cost_fx: Number(p.cost_fx ?? p.cost_usd) || 0,
                fx_rate: Number(p.fx_rate ?? p.usd_rate) || 0,
            };
        });

        return {
            products: initialProducts,
            editingField: null,
            selectedTaxCategory: config.initialTaxCategory || null,
            contacts: config.contacts || [],
            accountId: config.accountId || '',
            contactId: config.contactId || '',
            crmTop: config.crmTop || 'cash',
            topMargins: config.topMargins || {},
            _accountReady: false,
            currency: config.currency || 'IDR',
            ppnPercent: PPN_PERCENT,
            pphNonWapuJasa: PPH_NON_WAPU_JASA,
            pphWapuBarang: PPH_WAPU_BARANG,
            pphWapuJasa: PPH_WAPU_JASA,
            pnbpPercent: PNBP_PERCENT,
            pnbpTiers: PNBP_TIERS,
            pph29Percent: PPH29_PERCENT,
            royaltyDalamPercent: ROYALTY_DALAM_PERCENT,
            royaltyLuarPercent: ROYALTY_LUAR_PERCENT,
            amount: {{ (float) old('amount', $opportunity->amount ?? 0) }},
            hasDiscount: !!config.hasDiscount,
            discountAmount: Number(config.discountAmount) || 0,
            discountPercent: 0,
            _lockDiscountPercent: false,
            hasShippingCharge: !!config.hasShippingCharge,
            shippingSell: Number(config.shippingSell) || 0,
            accountMarginMeta: config.accountMarginMeta || {},
            marginNominalUmum: Number(config.marginNominalUmum) || 0,
            marginNominalOngkirPribadi: Number(config.marginNominalOngkirPribadi) || 0,
            marginMaxPercent: Number(config.marginMaxPercent) || 90,
            purchasingMode: !!config.purchasingMode,
            productTemplateUrl: config.productTemplateUrl || '',
            catalogCanAdd: config.catalogCanAdd || { brands: false, categories: false, vendors: false },
            catalogQuickUrls: config.catalogQuickUrls || {},
            productImportBusy: false,
            stage: config.stage || 'Prospecting',
            initialStage: config.initialStage || config.stage || 'Prospecting',
            noApprovalStages: config.noApprovalStages || ['Prospecting', 'Qualification'],
            get skipsApproval() {
                return this.noApprovalStages.includes(this.stage);
            },
            get filteredContacts() {
                if (!this.accountId) return [];
                return this.contacts.filter(c => c.account_id === this.accountId);
            },
            get accountMeta() {
                return this.accountMarginMeta[this.accountId] || null;
            },
            get isFreeShippingCity() {
                return !!(this.accountMeta && this.accountMeta.free_shipping);
            },
            get accountMinMarginPct() {
                if (!this.accountMeta) return null;
                if (this.accountMeta.suspended) return null;

                const fromLevel = this.accountMeta.payment_level_margin;
                if (fromLevel === null || fromLevel === undefined) {
                    const fallback = this.accountMeta.min_margin_pct;
                    return fallback === null || fallback === undefined ? null : Number(fallback);
                }

                const fromTop = Number(this.topMargins[this.crmTop] ?? 0) || 0;
                return Math.max(Number(fromLevel) || 0, fromTop);
            },
            get requiredNominalThreshold() {
                const umum = Number(this.marginNominalUmum) || 0;
                if (this.isFreeShippingCity || this.hasShippingCharge) {
                    return this.round(umum);
                }
                return this.round(umum + (Number(this.marginNominalOngkirPribadi) || 0));
            },
            get overallMarginPercent() {
                const denom = this.products.reduce((s, p) => {
                    const qty = Number(p.quantity) || 0;
                    const taxCategory = p.tax_category || 'non_wapu';

                    if (taxCategory === 'zinit') {
                        return s + qty * this.effectiveSellExclude(p);
                    }

                    const effSell = this.effectiveSellExclude(p);
                    if (taxCategory === 'wapu' || taxCategory === 'inaproc') {
                        const pph = this.pphAmount(p);
                        return s + qty * (effSell - pph);
                    }

                    return s + qty * effSell;
                }, 0);
                const denomAfterFee = this.products.some(p => this.appliesZinit(p))
                    ? this.round(denom - this.zinitDealFees().success_fee)
                    : denom;
                if (denomAfterFee <= 0) return null;
                return this.round((this.productsMarginTotal / denomAfterFee) * 100);
            },
            get marginBelowPercent() {
                const min = this.accountMinMarginPct;
                if (min === null) return false;
                const pct = this.overallMarginPercent;
                return pct === null || pct < min;
            },
            get marginAbovePercent() {
                const max = Number(this.marginMaxPercent) || 0;
                if (max <= 0) return false;
                const pct = this.overallMarginPercent;
                return pct !== null && pct > max;
            },
            get marginBelowNominal() {
                const thr = this.requiredNominalThreshold;
                return thr > 0 && this.productsMarginTotal < thr;
            },
            get marginNeedsApprovalHint() {
                if (this.skipsApproval) return false;
                return !!(this.accountId && (this.marginBelowPercent || this.marginAbovePercent || this.marginBelowNominal));
            },
            get productsTotal() {
                return this.round(this.products.reduce((s, p) => {
                    return s + (Number(p.quantity) || 0) * this.effectiveSellInclude(p);
                }, 0));
            },
            /** Grand total include Zinit (K52) — basis rumus Fee Zinit. */
            get zinitGrandTotalInclude() {
                return this.round(this.products.reduce((s, p) => {
                    if (!this.appliesZinit(p)) return s;
                    return s + (Number(p.quantity) || 0) * this.effectiveSellInclude(p);
                }, 0));
            },
            zinitDealFees() {
                return calcZinitFees(this.zinitGrandTotalInclude);
            },
            get productsMarginTotal() {
                const lines = this.round(this.products.reduce(
                    (s, p) => s + (Number(p.quantity) || 0) * this.marginAmount(p),
                    0
                ));
                // Fix GP = Total GP − Fee Zinit (fee dihitung sekali dari grand total include).
                if (this.products.some(p => this.appliesZinit(p))) {
                    return this.round(lines - this.zinitDealFees().success_fee);
                }
                return lines;
            },
            /** Basis % diskon: Inaproc = margin kotor; lainnya = margin bersih. */
            get discountBasisMarginTotal() {
                return this.round(this.products.reduce((s, p) => {
                    const qty = Number(p.quantity) || 0;
                    const unit = (p.tax_category || 'non_wapu') === 'inaproc'
                        ? this.grossMarginAmount(p)
                        : this.marginAmount(p);
                    return s + qty * unit;
                }, 0));
            },
            get discountBasisIsGross() {
                return this.products.some(p => (p.tax_category || 'non_wapu') === 'inaproc');
            },
            taxCategoryLabel(category) {
                if (category === 'wapu') return 'Wapu';
                if (category === 'inaproc') return 'Inaproc';
                if (category === 'zinit') return 'Zinit';
                return 'Non Wapu';
            },
            syncDiscountPercentFromAmount() {
                const margin = this.discountBasisMarginTotal;
                const disc = Number(this.discountAmount) || 0;
                if (margin > 0 && disc > 0) {
                    this.discountPercent = this.round((disc / margin) * 100);
                } else if (!this._lockDiscountPercent) {
                    this.discountPercent = disc > 0 ? this.discountPercent : 0;
                }
            },
            onDiscountPercentChange() {
                this._lockDiscountPercent = true;
                let pct = Number(this.discountPercent) || 0;
                if (pct < 0) pct = 0;
                this.discountPercent = pct;
                const margin = this.discountBasisMarginTotal;
                if (margin > 0) {
                    this.discountAmount = this.floorThousand(margin * pct / 100);
                }
            },
            onDiscountAmountChange() {
                this._lockDiscountPercent = false;
                this.syncDiscountPercentFromAmount();
            },
            refreshDiscountFromMargin() {
                if (!this.hasDiscount) return;
                if (this._lockDiscountPercent && (Number(this.discountPercent) || 0) > 0) {
                    const margin = this.discountBasisMarginTotal;
                    if (margin > 0) {
                        this.discountAmount = this.floorThousand(margin * (Number(this.discountPercent) || 0) / 100);
                    }
                } else {
                    this.syncDiscountPercentFromAmount();
                }
            },
            round(value) {
                return Math.round((Number(value) || 0) * 100) / 100;
            },
            sellInclude(p) {
                return this.round((Number(p.sell_exclude) || 0) * TAX_MULTIPLIER);
            },
            discountInclude(p) {
                return this.round((Number(p.discount_exclude) || 0) * TAX_MULTIPLIER);
            },
            shippingInclude(p) {
                return this.round((Number(p.shipping_exclude) || 0) * TAX_MULTIPLIER);
            },
            costInclude(p) {
                return this.round((Number(p.cost_exclude) || 0) * TAX_MULTIPLIER);
            },
            excludeFromInclude(include) {
                if (TAX_MULTIPLIER <= 0) return 0;
                return this.round((Number(include) || 0) / TAX_MULTIPLIER);
            },
            shippingSellInclude() {
                return this.round((Number(this.shippingSell) || 0) * TAX_MULTIPLIER);
            },
            onShippingSellExcludeChange(value) {
                this.shippingSell = Number(value) || 0;
            },
            onShippingSellIncludeChange(value) {
                this.shippingSell = this.excludeFromInclude(value);
            },
            /** Pembulatan ke atas per seribu (540541 → 541000). */
            ceilThousand(value) {
                const n = Number(value) || 0;
                if (n <= 0) return 0;
                return Math.ceil(n / 1000) * 1000;
            },
            /** Pembulatan ke bawah per seribu (706036 → 706000). */
            floorThousand(value) {
                const n = Number(value) || 0;
                if (n <= 0) return 0;
                return Math.floor(n / 1000) * 1000;
            },
            onSellIncludeChange(p, includeValue) {
                p.sell_exclude = this.ceilThousand(this.excludeFromInclude(includeValue));
                this.onSellChange(p);
            },
            onDiscountIncludeChange(p, includeValue) {
                p.discount_exclude = this.excludeFromInclude(includeValue);
                this.onDiscountItemChange(p);
            },
            onShippingIncludeChange(p, includeValue) {
                p.shipping_exclude = this.excludeFromInclude(includeValue);
                this.onShippingChange(p);
            },
            onCostIncludeChange(p, includeValue) {
                if (p.cost_foreign) return;
                p.cost_exclude = this.excludeFromInclude(includeValue);
                this.onCostChange(p);
            },
            setCostMode(p, useForeign) {
                p.cost_foreign = !!useForeign;
                if (p.cost_foreign) {
                    if (!p.cost_fx_code) p.cost_fx_code = 'USD';
                    this.recalcCostFromFx(p);
                } else {
                    p.cost_fx_code = '';
                    p.cost_fx = 0;
                    p.fx_rate = 0;
                    this.onCostChange(p);
                }
            },
            recalcCostFromFx(p) {
                const fx = Number(p.cost_fx) || 0;
                const rate = Number(p.fx_rate) || 0;
                p.cost_exclude = this.round(fx * rate);
                this.onCostChange(p);
            },
            /** Basis harga net: diskon item bila > 0, selain itu harga jual. */
            effectiveSellExclude(p) {
                const discount = Number(p.discount_exclude) || 0;
                return discount > 0 ? discount : (Number(p.sell_exclude) || 0);
            },
            effectiveSellInclude(p) {
                return this.round(this.effectiveSellExclude(p) * TAX_MULTIPLIER);
            },
            appliesPph(p) {
                return this.pphPercentFor(p) > 0;
            },
            appliesPnbp(p) {
                return (p.tax_category || 'non_wapu') === 'inaproc';
            },
            appliesPph29(p) {
                return (p.tax_category || 'non_wapu') === 'inaproc';
            },
            appliesZinit(p) {
                return (p.tax_category || 'non_wapu') === 'zinit';
            },
            appliesRoyalty(p) {
                return p.royalty_type === 'dalam' || p.royalty_type === 'luar';
            },
            royaltyPercentFor(p) {
                return royaltyPercentLookup(p.royalty_type || '');
            },
            royaltyTypeLabel(p) {
                if (p.royalty_type === 'dalam') return 'Dalam negeri · modal excl × rate';
                if (p.royalty_type === 'luar') return 'Luar negeri · modal excl × rate';
                return '';
            },
            royaltyAmount(p) {
                if (!this.appliesRoyalty(p)) return 0;
                return this.round((Number(p.cost_exclude) || 0) * (this.royaltyPercentFor(p) / 100));
            },
            toggleRoyaltyType(p, type) {
                p.royalty_type = p.royalty_type === type ? '' : type;
                p.has_royalty = !!p.royalty_type;
                this.onRoyaltyChange(p);
            },
            onRoyaltyChange(p) {
                p.has_royalty = !!p.royalty_type;
                p._lockMarginPercent = false;
                p.margin_percent = this.calcMarginPercent(p);
                this.refreshDiscountFromMargin();
            },
            onProductImageChange(p, event) {
                const file = event?.target?.files?.[0] || null;
                if (p._previewUrl) {
                    try { URL.revokeObjectURL(p._previewUrl); } catch (e) {}
                    p._previewUrl = '';
                }
                if (!file) {
                    p.image_preview = '';
                    return;
                }
                p.remove_image = false;
                p._previewUrl = URL.createObjectURL(file);
                p.image_preview = p._previewUrl;
            },
            clearProductImage(p, index) {
                if (p._previewUrl) {
                    try { URL.revokeObjectURL(p._previewUrl); } catch (e) {}
                    p._previewUrl = '';
                }
                p.image_preview = '';
                p.image_url = '';
                p.image = '';
                p.remove_image = true;
                const input = this.$root?.querySelector?.(`input[name="products[${index}][image_file]"]`);
                if (input) input.value = '';
            },
            pphPercentFor(p) {
                return pphPercentLookup(p.tax_category || 'non_wapu', p.item_kind || 'barang');
            },
            pphAmount(p) {
                const rate = this.pphPercentFor(p) / 100;
                if (rate <= 0) return 0;
                return this.round(this.effectiveSellExclude(p) * rate);
            },
            pnbpAmount(p) {
                if (!this.appliesPnbp(p)) return 0;
                return calcPnbpFromInclude(this.effectiveSellInclude(p));
            },
            grossMarginAmount(p) {
                if (this.appliesZinit(p)) {
                    return this.marginAmount(p);
                }
                const base = this.effectiveSellExclude(p);
                const costExclude = Number(p.cost_exclude) || 0;
                const taxCategory = p.tax_category || 'non_wapu';
                if (taxCategory === 'wapu' || taxCategory === 'inaproc') {
                    const costInclude = this.round(costExclude * TAX_MULTIPLIER);
                    return this.round(base - this.pphAmount(p) - costInclude);
                }
                return this.round(base - this.pphAmount(p) - costExclude);
            },
            pph29Amount(p) {
                if (!this.appliesPph29(p)) return 0;
                const spread = this.effectiveSellExclude(p) - (Number(p.cost_exclude) || 0);
                if (spread <= 0) return 0;
                return this.round(spread * (Number(this.pph29Percent) || 0) / 100);
            },
            marginAmount(p) {
                const shipping = Math.max(0, Number(p.shipping_exclude) || 0);
                // Zinit: GP per baris tanpa fee; Fee Zinit dipotong sekali di Total Margin (Fix GP).
                if (this.appliesZinit(p)) {
                    return this.round(this.effectiveSellExclude(p) - this.pphAmount(p) - this.royaltyAmount(p) - (Number(p.cost_exclude) || 0) - shipping);
                }
                return this.round(this.grossMarginAmount(p) - this.pnbpAmount(p) - this.pph29Amount(p) - this.royaltyAmount(p) - shipping);
            },
            calcMarginPercent(p) {
                const margin = this.marginAmount(p);
                if (this.appliesZinit(p)) {
                    const base = this.effectiveSellExclude(p);
                    return base > 0 ? this.round((margin / base) * 100) : 0;
                }
                const base = this.effectiveSellExclude(p);
                const taxCategory = p.tax_category || 'non_wapu';
                const denom = (taxCategory === 'wapu' || taxCategory === 'inaproc')
                    ? (base - this.pphAmount(p))
                    : base;
                return denom > 0 ? this.round((margin / denom) * 100) : 0;
            },
            marginPercent(p) {
                return Number(p.margin_percent) || 0;
            },
            marginLabel(p) {
                const shippingBit = (Number(p.shipping_exclude) || 0) > 0 ? ' − Modal ongkir' : '';
                if (this.appliesZinit(p)) {
                    let s = 'Jual';
                    if (this.appliesPph(p)) s += ' − PPH';
                    if (this.appliesRoyalty(p)) s += ' − Royalti';
                    s += ' − Modal';
                    return s + shippingBit;
                }
                const hasItemDiscount = (Number(p.discount_exclude) || 0) > 0;
                const head = hasItemDiscount ? 'Diskon' : 'Jual Exclude';
                const royaltyBit = this.appliesRoyalty(p) ? ' − Royalti' : '';
                if ((p.tax_category || 'non_wapu') === 'wapu') {
                    return head + ' − PPH − Modal Include' + royaltyBit + shippingBit;
                }
                if (this.appliesPph29(p)) {
                    return head + ' − PPH − PNBP − PPH29 − Modal Include' + royaltyBit + shippingBit;
                }
                if (this.appliesPph(p)) {
                    return head + ' − PPH − Modal' + royaltyBit + shippingBit;
                }
                return (hasItemDiscount ? 'Diskon − Modal' : 'Jual Exclude − Beli Exclude') + royaltyBit + shippingBit;
            },
            /**
             * Dari % margin target (net) + modal → hitung harga basis.
             * Wapu: base = costInclude / ((1-rPph) * (1 - pct/100))
             * Inaproc: % vs (jual−PPH); iterasi karena PNBP berjenjang + modal include.
             */
            sellFromMarginPercent(p) {
                const pct = Number(p.margin_percent) || 0;
                const cost = Number(p.cost_exclude) || 0;
                const shipping = Math.max(0, Number(p.shipping_exclude) || 0);
                if (cost <= 0 || pct <= 0) {
                    return this.effectiveSellExclude(p);
                }

                const rPph = this.pphPercentFor(p) / 100;
                const isWapu = (p.tax_category || 'non_wapu') === 'wapu';
                const costInclude = this.round(cost * TAX_MULTIPLIER);
                const pctDec = pct / 100;

                if (isWapu) {
                    const denom = (1 - rPph) * (1 - pctDec);
                    if (denom <= 0) {
                        return this.effectiveSellExclude(p);
                    }
                    return this.round((costInclude + shipping) / denom);
                }

                if (!this.appliesPph29(p)) {
                    const denom = 1 - pctDec;
                    if (denom <= 0) return this.effectiveSellExclude(p);
                    return this.round((cost + shipping) / denom);
                }

                const r29 = (Number(this.pph29Percent) || 0) / 100;
                let base = this.effectiveSellExclude(p) || cost;

                // net = (base−PPH−modalIncl) − PNBP − r29*(base−modalExcl) − ongkir
                // % terhadap (base − PPH); PNBP berjenjang → iterasi.
                for (let i = 0; i < 10; i++) {
                    const pnbp = calcPnbpFromInclude(this.round(base * TAX_MULTIPLIER));
                    const coeff = (1 - rPph) * (1 - pctDec) - r29;
                    if (coeff <= 0) {
                        return this.effectiveSellExclude(p);
                    }
                    const next = this.round((costInclude - r29 * cost + pnbp + shipping) / coeff);
                    if (Math.abs(next - base) < 0.5) {
                        return next;
                    }
                    base = next;
                }

                return this.round(base);
            },
            applyMarginPercentToPrice(p) {
                const next = this.sellFromMarginPercent(p);
                if ((Number(p.discount_exclude) || 0) > 0) {
                    p.discount_exclude = next;
                } else {
                    p.sell_exclude = this.ceilThousand(next);
                }
            },
            onSellChange(p) {
                p._lockMarginPercent = false;
                p.margin_percent = this.calcMarginPercent(p);
                this.refreshDiscountFromMargin();
            },
            onDiscountItemChange(p) {
                p._lockMarginPercent = false;
                p.margin_percent = this.calcMarginPercent(p);
                this.refreshDiscountFromMargin();
            },
            onShippingChange(p) {
                if ((Number(p.shipping_exclude) || 0) < 0) p.shipping_exclude = 0;
                if (p._lockMarginPercent && (Number(p.margin_percent) || 0) > 0) {
                    this.applyMarginPercentToPrice(p);
                } else {
                    p.margin_percent = this.calcMarginPercent(p);
                }
                this.refreshDiscountFromMargin();
            },
            onCostChange(p) {
                if (p.cost_foreign) {
                    const fx = Number(p.cost_fx) || 0;
                    const rate = Number(p.fx_rate) || 0;
                    if (fx > 0 && rate > 0) {
                        p.cost_exclude = this.round(fx * rate);
                    }
                } else {
                    p.cost_fx_code = '';
                    p.cost_fx = 0;
                    p.fx_rate = 0;
                }
                if (p._lockMarginPercent && (Number(p.margin_percent) || 0) > 0) {
                    this.applyMarginPercentToPrice(p);
                } else {
                    p.margin_percent = this.calcMarginPercent(p);
                }
                this.refreshDiscountFromMargin();
            },
            onMarginPercentChange(p) {
                let pct = Number(p.margin_percent) || 0;
                const isWapu = (p.tax_category || 'non_wapu') === 'wapu';
                const rPph = this.pphPercentFor(p) / 100;
                const r29 = this.appliesPph29(p) ? (Number(this.pph29Percent) || 0) / 100 : 0;
                // Wapu: % vs (jual−PPH). Inaproc: coeff (1−rPph)*(1−pct) − r29 > 0.
                let maxPct = Math.max(100 - 0.01, 0);
                if (this.appliesPph29(p) && (1 - rPph) > 0) {
                    maxPct = Math.max((1 - r29 / (1 - rPph)) * 100 - 0.01, 0);
                } else if (isWapu) {
                    maxPct = Math.max(100 - 0.01, 0);
                }
                if (pct < 0) pct = 0;
                if (pct > maxPct) pct = maxPct;
                p.margin_percent = pct;
                p._lockMarginPercent = true;

                if ((Number(p.cost_exclude) || 0) > 0 && pct > 0) {
                    this.applyMarginPercentToPrice(p);
                }
                this.refreshDiscountFromMargin();
            },
            onItemKindChange(p) {
                if (p._lockMarginPercent && (Number(p.margin_percent) || 0) > 0 && (Number(p.cost_exclude) || 0) > 0) {
                    this.applyMarginPercentToPrice(p);
                } else {
                    p.margin_percent = this.calcMarginPercent(p);
                }
                this.refreshDiscountFromMargin();
            },
            formatId(value, decimals = 0) {
                if (window.CrmNumber) return window.CrmNumber.format(value, decimals);
                const n = Number(value) || 0;
                return n.toLocaleString('id-ID', { maximumFractionDigits: decimals });
            },
            parseId(str) {
                if (window.CrmNumber) return window.CrmNumber.parse(str);
                return Number(String(str).replace(/\./g, '').replace(',', '.')) || 0;
            },
            formatNumber(value) {
                return this.formatId(value, this.currency === 'IDR' ? 0 : 2);
            },
            formatPercent(value) {
                return this.formatId(value, 2) + '%';
            },
            formatMoney(value) {
                value = Number(value) || 0;
                if (this.currency === 'IDR') {
                    return 'Rp ' + this.formatId(value, 0);
                }
                return this.currency + ' ' + value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
            onAccountChange() {
                const list = this.filteredContacts;
                const stillValid = list.some(c => String(c.id) === String(this.contactId));

                if (!this.accountId) {
                    this.contactId = '';
                } else if (!stillValid) {
                    this.contactId = list.length > 0 ? String(list[0].id) : '';
                }

                this.applyCustomerTopDefault();
                this.refreshContactSelect();
            },
            applyCustomerTopDefault() {
                if (this.purchasingMode) return;

                const nextTop = this.accountMeta?.top || 'cash';
                this.crmTop = nextTop;
                this.syncTopSelect();
            },
            syncTopSelect() {
                const el = this.$root.querySelector('[name="crm_top"]');
                if (!el || !window.CrmSelect2 || !window.jQuery) return;

                window.jQuery(el).val(this.crmTop || 'cash').trigger('change');
            },
            refreshContactSelect() {
                const el = this.$root.querySelector('[name="contact_id"]');
                if (!el || !window.CrmSelect2) return;

                const placeholder = this.accountId ? '— No contact —' : 'Select customer first';
                el.disabled = !this.accountId;

                CrmSelect2.setOptions(
                    el,
                    this.filteredContacts.map(c => ({ id: c.id, name: c.name })),
                    this.contactId,
                    placeholder
                );
                CrmSelect2.bindAlpine(el, this, 'contactId');
            },
            selectTaxCategory(category) {
                this.changeTaxCategory(category);
            },
            changeTaxCategory(category) {
                if (!category || this.purchasingMode) return;
                this.selectedTaxCategory = category;
                this.products.forEach((p) => {
                    p.tax_category = category;
                    if (p._lockMarginPercent && (Number(p.margin_percent) || 0) > 0 && (Number(p.cost_exclude) || 0) > 0) {
                        this.applyMarginPercentToPrice(p);
                    } else {
                        p.margin_percent = this.calcMarginPercent(p);
                    }
                });
                this.refreshDiscountFromMargin();
            },
            addProduct() {
                if (!this.selectedTaxCategory) return;
                this.products.push({
                    name: '',
                    quantity: 1,
                    sell_exclude: 0,
                    cost_exclude: 0,
                    discount_exclude: 0,
                    shipping_exclude: 0,
                    vendor: '',
                    brand: '',
                    sku: '',
                    category: '',
                    tax_category: this.selectedTaxCategory,
                    item_kind: 'barang',
                    royalty_type: '',
                    has_royalty: false,
                    image: '',
                    image_url: '',
                    image_preview: '',
                    remove_image: false,
                    margin_percent: 0,
                    _lockMarginPercent: false,
                    cost_foreign: false,
                    cost_fx_code: '',
                    cost_fx: 0,
                    fx_rate: 0,
                });
                this.refreshBrandSelects();
                this.refreshCategorySelects();
                this.refreshVendorSelects();
            },
            removeProduct(i) {
                const brandEl = this.$root.querySelector(`select[name="products[${i}][brand]"]`);
                if (brandEl && window.CrmSelect2) {
                    CrmSelect2.destroy(brandEl);
                }
                const catEl = this.$root.querySelector(`select[name="products[${i}][category]"]`);
                if (catEl && window.CrmSelect2) {
                    CrmSelect2.destroy(catEl);
                }
                const vendorEl = this.$root.querySelector(`select[name="products[${i}][vendor]"]`);
                if (vendorEl && window.CrmSelect2) {
                    CrmSelect2.destroy(vendorEl);
                }
                this.products.splice(i, 1);
                this.refreshDiscountFromMargin();
                this.refreshBrandSelects();
                this.refreshCategorySelects();
                this.refreshVendorSelects();
            },
            initBrandSelect(el, index) {
                if (!el || !window.CrmSelect2 || !window.jQuery) return;

                const $el = window.jQuery(el);
                CrmSelect2.destroy(el);

                const canAdd = !!this.catalogCanAdd?.brands && !this.purchasingMode;
                const selectOpts = {
                    width: '100%',
                    placeholder: $el.data('placeholder') || '— Brand —',
                    allowClear: true,
                    dropdownParent: window.jQuery(document.body),
                    language: {
                        noResults: () => canAdd ? 'Ketik nama brand baru' : 'Brand tidak ditemukan',
                        searching: () => 'Mencari...',
                    },
                };

                if (canAdd) {
                    selectOpts.tags = true;
                    selectOpts.createTag = (params) => {
                        const term = window.jQuery.trim(params.term);
                        if (term === '') return null;
                        return { id: term, text: term, newTag: true };
                    };
                }

                $el.select2(selectOpts);

                $el.off('.crmBrand');
                $el.on('change.crmBrand select2:clear.crmBrand', () => {
                    if (!this.products[index]) return;
                    this.products[index].brand = $el.val() || '';
                });
                $el.on('select2:select.crmBrand', async (event) => {
                    if (!this.products[index]) return;
                    const data = event.params?.data;
                    if (canAdd && data?.newTag) {
                        await this.persistCatalogQuick('brands', data.id, $el, index, 'brand');
                        return;
                    }
                    this.products[index].brand = $el.val() || '';
                });

                const brand = this.products[index]?.brand || '';
                if (brand && $el.find('option').filter(function () {
                    return String(window.jQuery(this).val()) === String(brand);
                }).length === 0) {
                    $el.append(new Option(brand, brand, true, true));
                }
                $el.val(brand || '').trigger('change.select2');
                $el.prop('disabled', !!this.purchasingMode).trigger('change.select2');
            },
            refreshBrandSelects() {
                this.$nextTick(() => {
                    if (!window.CrmSelect2) return;
                    this.$root.querySelectorAll('select[data-brand-select]').forEach((el) => {
                        const match = String(el.getAttribute('name') || '').match(/products\[(\d+)\]\[brand\]/);
                        const index = match ? Number(match[1]) : -1;
                        if (index < 0) return;
                        this.initBrandSelect(el, index);
                    });
                });
            },
            initCategorySelect(el, index) {
                if (!el || !window.CrmSelect2 || !window.jQuery) return;

                const $el = window.jQuery(el);
                CrmSelect2.destroy(el);

                const canAdd = !!this.catalogCanAdd?.categories && !this.purchasingMode;
                const selectOpts = {
                    width: '100%',
                    placeholder: $el.data('placeholder') || '— Category —',
                    allowClear: true,
                    dropdownParent: window.jQuery(document.body),
                    language: {
                        noResults: () => canAdd ? 'Ketik nama category baru' : 'Category tidak ditemukan',
                        searching: () => 'Mencari...',
                    },
                };

                if (canAdd) {
                    selectOpts.tags = true;
                    selectOpts.createTag = (params) => {
                        const term = window.jQuery.trim(params.term);
                        if (term === '') return null;
                        return { id: term, text: term, newTag: true };
                    };
                }

                $el.select2(selectOpts);

                $el.off('.crmCategory');
                $el.on('change.crmCategory select2:clear.crmCategory', () => {
                    if (!this.products[index]) return;
                    this.products[index].category = $el.val() || '';
                });
                $el.on('select2:select.crmCategory', async (event) => {
                    if (!this.products[index]) return;
                    const data = event.params?.data;
                    if (canAdd && data?.newTag) {
                        await this.persistCatalogQuick('categories', data.id, $el, index, 'category');
                        return;
                    }
                    this.products[index].category = $el.val() || '';
                });

                const category = this.products[index]?.category || '';
                if (category && $el.find('option').filter(function () {
                    return String(window.jQuery(this).val()) === String(category);
                }).length === 0) {
                    $el.append(new Option(category, category, true, true));
                }
                $el.val(category || '').trigger('change.select2');
                $el.prop('disabled', !!this.purchasingMode).trigger('change.select2');
            },
            refreshCategorySelects() {
                this.$nextTick(() => {
                    if (!window.CrmSelect2) return;
                    this.$root.querySelectorAll('select[data-category-select]').forEach((el) => {
                        const match = String(el.getAttribute('name') || '').match(/products\[(\d+)\]\[category\]/);
                        const index = match ? Number(match[1]) : -1;
                        if (index < 0) return;
                        this.initCategorySelect(el, index);
                    });
                });
            },
            initVendorSelect(el, index) {
                if (!el || !window.CrmSelect2 || !window.jQuery) return;

                const $el = window.jQuery(el);
                CrmSelect2.destroy(el);

                const canAdd = !!this.catalogCanAdd?.vendors;
                const selectOpts = {
                    width: '100%',
                    placeholder: $el.data('placeholder') || '— Vendor —',
                    allowClear: true,
                    dropdownParent: window.jQuery(document.body),
                    language: {
                        noResults: () => canAdd ? 'Ketik nama vendor baru' : 'Vendor tidak ditemukan',
                        searching: () => 'Mencari...',
                    },
                };

                if (canAdd) {
                    selectOpts.tags = true;
                    selectOpts.createTag = (params) => {
                        const term = window.jQuery.trim(params.term);
                        if (term === '') return null;
                        return { id: term, text: term, newTag: true };
                    };
                }

                $el.select2(selectOpts);

                $el.off('.crmVendor');
                $el.on('change.crmVendor select2:clear.crmVendor', () => {
                    if (!this.products[index]) return;
                    this.products[index].vendor = $el.val() || '';
                });
                $el.on('select2:select.crmVendor', async (event) => {
                    if (!this.products[index]) return;
                    const data = event.params?.data;
                    if (canAdd && data?.newTag) {
                        await this.persistCatalogQuick('vendors', data.id, $el, index, 'vendor');
                        return;
                    }
                    this.products[index].vendor = $el.val() || '';
                });

                const vendor = this.products[index]?.vendor || '';
                if (vendor && $el.find('option').filter(function () {
                    return String(window.jQuery(this).val()) === String(vendor);
                }).length === 0) {
                    $el.append(new Option(vendor, vendor, true, true));
                }
                $el.val(vendor || '').trigger('change.select2');
            },
            async persistCatalogQuick(type, name, $el, index, field) {
                const url = this.catalogQuickUrls?.[type];
                if (!url || !name) return;

                const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token,
                        },
                        body: JSON.stringify({ name }),
                    });

                    if (!response.ok) {
                        let message = 'Gagal menyimpan data.';
                        try {
                            const payload = await response.json();
                            message = payload.message || Object.values(payload.errors || {})[0]?.[0] || message;
                        } catch (e) {}
                        throw new Error(message);
                    }

                    const payload = await response.json();
                    const savedName = payload.name || name;

                    if (!$el.find('option').filter(function () {
                        return String(window.jQuery(this).val()) === String(savedName);
                    }).length) {
                        $el.append(new Option(savedName, savedName, true, true));
                    }

                    $el.val(savedName).trigger('change.select2');
                    if (this.products[index]) {
                        this.products[index][field] = savedName;
                    }
                } catch (error) {
                    window.alert(error.message || 'Gagal menambah data master.');
                    $el.val('').trigger('change.select2');
                    if (this.products[index]) {
                        this.products[index][field] = '';
                    }
                }
            },
            refreshVendorSelects() {
                this.$nextTick(() => {
                    if (!window.CrmSelect2) return;
                    this.$root.querySelectorAll('select[data-vendor-select]').forEach((el) => {
                        const match = String(el.getAttribute('name') || '').match(/products\[(\d+)\]\[vendor\]/);
                        const index = match ? Number(match[1]) : -1;
                        if (index < 0) return;
                        this.initVendorSelect(el, index);
                    });
                });
            },
            /** Prospecting + ada nama produk → Qualification (sinkron select Stage). */
            promoteStageFromProducts() {
                if (this.purchasingMode || this.stage !== 'Prospecting') return;
                const hasNamed = this.products.some(p => String(p.name || '').trim() !== '');
                if (!hasNamed) return;

                this.stage = 'Qualification';
                this.$nextTick(() => {
                    const el = this.$root.querySelector('select[name="stage"]');
                    if (!el) return;
                    el.value = 'Qualification';
                    if (window.jQuery) {
                        window.jQuery(el).val('Qualification').trigger('change');
                    }
                });
            },

            async importProductsFromFile(event) {
                const input = event.target;
                const file = input?.files?.[0];
                input.value = '';
                if (!file) return;

                if (!this.selectedTaxCategory) {
                    alert('Pilih kategori pajak dulu sebelum import.');
                    return;
                }
                if (this.purchasingMode || this.productImportBusy) return;

                this.productImportBusy = true;
                await this.$nextTick();
                await new Promise((resolve) => requestAnimationFrame(() => resolve()));

                try {
                    const rows = await this.parseProductSpreadsheet(file);
                    const imported = [];
                    for (const row of rows) {
                        const mapped = this.mapImportedProductRow(row);
                        if (!mapped) continue;
                        imported.push(mapped);
                    }

                    if (imported.length === 0) {
                        alert('Tidak ada baris produk valid di file. Pastikan baris header punya kolom "nama" dan isi nama item di baris berikutnya.');
                        return;
                    }

                    const append = this.products.length === 0
                        || confirm(`Ditemukan ${imported.length} produk. OK = tambahkan ke daftar, Cancel = ganti semua item.`);

                    if (!append) {
                        this.products = [];
                    }

                    imported.forEach((p) => {
                        p.tax_category = this.selectedTaxCategory;
                        p.margin_percent = this.calcMarginPercent(p);
                        p._lockMarginPercent = false;
                        this.products.push(p);
                    });
                    this.refreshDiscountFromMargin();
                    this.refreshBrandSelects();
                    this.refreshCategorySelects();
                    this.refreshVendorSelects();
                    alert(`${imported.length} produk berhasil diimpor.`);
                } catch (err) {
                    console.error(err);
                    alert(err?.message || 'Gagal membaca file Excel.');
                } finally {
                    this.productImportBusy = false;
                }
            },
            async parseProductSpreadsheet(file) {
                const name = (file.name || '').toLowerCase();
                if (name.endsWith('.csv')) {
                    const text = await file.text();
                    return this.parseCsvToObjects(text);
                }

                const XLSX = await this.loadSheetJs();
                const buffer = await file.arrayBuffer();
                const workbook = XLSX.read(buffer, { type: 'array' });
                const sheetName = workbook.SheetNames[0];
                if (!sheetName) return [];
                const sheet = workbook.Sheets[sheetName];
                const matrix = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '', raw: false, blankrows: false });
                return this.matrixToProductObjects(matrix);
            },
            matrixToProductObjects(matrix) {
                if (!Array.isArray(matrix) || matrix.length < 2) return [];

                let headerIndex = 0;
                for (let i = 0; i < Math.min(matrix.length, 15); i++) {
                    const cells = (matrix[i] || []).map((c) => this.normalizeImportHeader(c));
                    if (cells.includes('nama') || cells.includes('jenis')) {
                        headerIndex = i;
                        break;
                    }
                }

                const headers = (matrix[headerIndex] || []).map((c) => this.normalizeImportHeader(c));
                if (!headers.includes('nama') && !headers.includes('jenis')) {
                    return [];
                }

                const rows = [];
                for (let i = headerIndex + 1; i < matrix.length; i++) {
                    const line = matrix[i] || [];
                    const empty = line.every((c) => String(c ?? '').trim() === '');
                    if (empty) continue;
                    const obj = {};
                    headers.forEach((h, j) => {
                        if (!h) return;
                        obj[h] = line[j] ?? '';
                    });
                    rows.push(obj);
                }

                return rows;
            },
            loadSheetJs() {
                if (window.XLSX) return Promise.resolve(window.XLSX);
                return new Promise((resolve, reject) => {
                    const existing = document.querySelector('script[data-crm-xlsx]');
                    if (existing) {
                        existing.addEventListener('load', () => resolve(window.XLSX));
                        existing.addEventListener('error', () => reject(new Error('Gagal memuat parser Excel.')));
                        return;
                    }
                    const script = document.createElement('script');
                    script.src = 'https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js';
                    script.async = true;
                    script.dataset.crmXlsx = '1';
                    script.onload = () => resolve(window.XLSX);
                    script.onerror = () => reject(new Error('Gagal memuat parser Excel.'));
                    document.head.appendChild(script);
                });
            },
            parseCsvToObjects(text) {
                const lines = text.replace(/^\uFEFF/, '').split(/\r\n|\n|\r/).filter(l => l.trim() !== '');
                if (lines.length < 2) return [];
                const matrix = lines.map((line) => this.splitCsvLine(line));
                return this.matrixToProductObjects(matrix);
            },
            splitCsvLine(line) {
                const result = [];
                let cur = '';
                let inQuotes = false;
                for (let i = 0; i < line.length; i++) {
                    const ch = line[i];
                    if (ch === '"') {
                        if (inQuotes && line[i + 1] === '"') {
                            cur += '"';
                            i++;
                        } else {
                            inQuotes = !inQuotes;
                        }
                    } else if (ch === ',' && !inQuotes) {
                        result.push(cur);
                        cur = '';
                    } else {
                        cur += ch;
                    }
                }
                result.push(cur);
                return result;
            },
            normalizeImportHeader(header) {
                let h = String(header || '')
                    .replace(/\u00a0/g, ' ')
                    .replace(/^\uFEFF/, '')
                    .trim()
                    .toLowerCase()
                    .replace(/[\s-]+/g, '_');
                const aliases = {
                    barang_jasa: 'jenis', tipe: 'jenis', kind: 'jenis',
                    item: 'nama', product: 'nama', produk: 'nama', nama_item: 'nama', nama_produk: 'nama', name: 'nama',
                    quantity: 'qty', jumlah: 'qty',
                    harga_jual: 'harga_jual_exclude', sell: 'harga_jual_exclude', sell_exclude: 'harga_jual_exclude',
                    diskon: 'diskon_exclude', discount: 'diskon_exclude', discount_exclude: 'diskon_exclude',
                    ongkir: 'ongkir_exclude', shipping: 'ongkir_exclude', shipping_exclude: 'ongkir_exclude',
                    ongkir_item: 'ongkir_exclude', harga_ongkir: 'ongkir_exclude',
                    harga_beli: 'harga_beli_exclude', modal: 'harga_beli_exclude', cost: 'harga_beli_exclude', cost_exclude: 'harga_beli_exclude',
                    merek: 'brand', brand_name: 'brand',
                    kategori: 'category', category_name: 'category',
                };
                return aliases[h] || h;
            },
            mapImportedProductRow(row) {
                const map = {};
                Object.keys(row || {}).forEach((key) => {
                    map[this.normalizeImportHeader(key)] = row[key];
                });

                const name = String(map.nama ?? '').trim();
                if (!name) return null;

                const kindRaw = String(map.jenis ?? map.item_kind ?? 'barang').trim().toLowerCase();
                const itemKind = kindRaw.includes('jasa') ? 'jasa' : 'barang';

                return {
                    name,
                    quantity: this.parseId(map.qty ?? map.quantity ?? 1) || 1,
                    vendor: String(map.vendor ?? '').trim(),
                    brand: String(map.brand ?? map.merek ?? '').trim(),
                    sku: '',
                    category: String(map.category ?? map.kategori ?? '').trim(),
                    sell_exclude: this.parseId(map.harga_jual_exclude ?? 0),
                    discount_exclude: this.parseId(map.diskon_exclude ?? 0),
                    shipping_exclude: this.parseId(map.ongkir_exclude ?? 0),
                    cost_exclude: this.parseId(map.harga_beli_exclude ?? 0),
                    tax_category: this.selectedTaxCategory,
                    item_kind: itemKind,
                    royalty_type: '',
                    has_royalty: false,
                    image: '',
                    image_url: '',
                    image_preview: '',
                    remove_image: false,
                    margin_percent: 0,
                    _lockMarginPercent: false,
                    cost_foreign: false,
                    cost_fx_code: '',
                    cost_fx: 0,
                    fx_rate: 0,
                };
            },
            init() {
                this.$watch('products', () => {
                    if (this.products.length > 0) this.amount = this.productsTotal;
                    this.promoteStageFromProducts();
                }, { deep: true });
                if (this.products.length > 0) this.amount = this.productsTotal;
                this.promoteStageFromProducts();


                this.$nextTick(() => {
                    if (!window.CrmSelect2) return;
                    CrmSelect2.init(this.$root);

                    const accountEl = this.$root.querySelector('[name="account_id"]');
                    const currencyEl = this.$root.querySelector('[name="amount_currency"]');
                    const topEl = this.$root.querySelector('[name="crm_top"]');

                    if (accountEl) {
                        CrmSelect2.bindAlpine(accountEl, this, 'accountId', () => {
                            if (!this._accountReady) return;
                            this.onAccountChange();
                        });
                    }
                    if (currencyEl) {
                        CrmSelect2.bindAlpine(currencyEl, this, 'currency');
                    }
                    if (topEl && !this.purchasingMode) {
                        CrmSelect2.bindAlpine(topEl, this, 'crmTop');
                    }

                    if (this.accountId && !this.contactId) {
                        const list = this.filteredContacts;
                        if (list.length > 0) {
                            this.contactId = String(list[0].id);
                        }
                    }

                    this.refreshContactSelect();
                    this.syncDiscountPercentFromAmount();
                    this.refreshBrandSelects();
                    this.refreshCategorySelects();
                    this.refreshVendorSelects();
                    this._accountReady = true;
                });
            },
        };
    }
</script>
