@php
    $initialProducts = old('products', $opportunity->exists
        ? $opportunity->products->map(fn ($p) => [
            'name' => $p['name'],
            'quantity' => $p['quantity'],
            'sell_exclude' => $p['sell_exclude'],
            'cost_exclude' => $p['cost_exclude'],
            'discount_exclude' => $p['discount_exclude'] ?? 0,
            'vendor' => $p['vendor'],
            'tax_category' => $p['tax_category'],
            'item_kind' => $p['item_kind'],
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
    $pph29Percent = \App\Support\OpportunityProductPricing::pph29Percent();
    $purchasingMode = $purchasingMode ?? false;
@endphp
<form method="POST" action="{{ $action }}"
      x-data="opportunityForm({{ \Illuminate\Support\Js::from([
          'products' => $initialProducts,
          'currency' => old('amount_currency', $opportunity->amount_currency ?: 'IDR'),
          'contacts' => $contactOptions,
          'accountId' => old('account_id', $opportunity->account_id),
          'contactId' => old('contact_id', $opportunity->contact_id),
          'initialTaxCategory' => old('products.0.tax_category', count($initialProducts) > 0 ? ($initialProducts[0]['tax_category'] ?? null) : null),
          'ppnPercent' => $ppnPercent,
          'pphNonWapuJasa' => $pphNonWapuJasa,
          'pphWapuBarang' => $pphWapuBarang,
          'pphWapuJasa' => $pphWapuJasa,
          'pnbpPercent' => $pnbpPercent,
          'pph29Percent' => $pph29Percent,
          'hasDiscount' => (bool) old('has_discount', $opportunity->crm_has_discount),
          'discountAmount' => (float) old('discount_amount', $opportunity->crm_discount_amount ?? 0),
          'hasShippingCharge' => (bool) old('has_shipping_charge', $opportunity->crm_has_shipping_charge),
          'shippingSell' => (float) old('shipping_sell', $opportunity->crm_shipping_sell ?? 0),
          'accountMarginMeta' => $accountMarginMeta ?? [],
          'marginNominalUmum' => (float) ($marginNominalUmum ?? 0),
          'marginNominalOngkirPribadi' => (float) ($marginNominalOngkirPribadi ?? 0),
          'purchasingMode' => $purchasingMode,
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

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        {{-- Kolom utama --}}
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
                        <label class="crm-label">Account</label>
                        <select name="account_id" class="select2 w-full" data-placeholder="— Select —" @disabled($purchasingMode)>
                            <option value="">— Select —</option>
                            @foreach ($accounts as $acc)
                                <option value="{{ $acc->id }}" @selected(old('account_id', $opportunity->account_id) === $acc->id)>{{ $acc->name }}</option>
                            @endforeach
                        </select>
                        @if ($purchasingMode)<input type="hidden" name="account_id" value="{{ $opportunity->account_id }}">@endif
                    </div>
                    <div class="sm:col-span-2">
                        <label class="crm-label">Contact</label>
                        <select name="contact_id" class="select2 w-full" data-placeholder="Select account first" :disabled="!accountId || purchasingMode">
                            <option value="">— No contact —</option>
                        </select>
                        @if ($purchasingMode)<input type="hidden" name="contact_id" value="{{ $opportunity->contact_id }}">@endif
                        <p class="mt-1 text-xs text-slate-400" x-show="accountId && !purchasingMode">Auto-filled from account. You can clear or pick another contact.</p>
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
                        <select name="stage" class="select2 w-full" @disabled($purchasingMode)>
                            @foreach ($stages as $st)
                                <option value="{{ $st }}" @selected(old('stage', $opportunity->stage) === $st)>{{ $st }}</option>
                            @endforeach
                        </select>
                        @if ($purchasingMode)<input type="hidden" name="stage" value="{{ $opportunity->stage }}">@endif
                    </div>
                    <div>
                        <label class="crm-label">Amount <span class="text-red-500">*</span></label>
                        <div class="flex gap-2">
                            <input type="number" step="0.01" min="0" name="amount" x-model.number="amount" required
                                   class="crm-field min-w-[200px] flex-1" :readonly="products.length > 0 || purchasingMode">
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
                        <input type="number" min="0" max="100" name="probability" value="{{ old('probability', $opportunity->probability ?? 10) }}" required
                               class="crm-field" @readonly($purchasingMode)>
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

            {{-- Line items --}}
            <x-card>
                {{-- Langkah 1: pilih kategori saja --}}
                <div x-show="!selectedTaxCategory && !purchasingMode" class="rounded-lg border border-dashed border-slate-200 p-6 text-center">
                    <p class="mb-4 text-sm font-medium text-slate-700">Pilih kategori pajak terlebih dahulu</p>
                    <div class="mx-auto grid max-w-3xl gap-3 sm:grid-cols-3">
                        <button type="button" @click="selectTaxCategory('non_wapu')"
                                class="rounded-lg border-2 border-slate-200 bg-white px-4 py-4 text-left transition hover:border-brand-500 hover:bg-brand-50">
                            <span class="block text-sm font-semibold text-slate-800">Non Wapu</span>
                            <span class="mt-1 block text-xs text-slate-500">Barang: PPN saja<br>Jasa: PPN + PPH</span>
                        </button>
                        <button type="button" @click="selectTaxCategory('wapu')"
                                class="rounded-lg border-2 border-slate-200 bg-white px-4 py-4 text-left transition hover:border-brand-500 hover:bg-brand-50">
                            <span class="block text-sm font-semibold text-slate-800">Wapu</span>
                            <span class="mt-1 block text-xs text-slate-500">Barang: PPN + 1.5%<br>Jasa: PPN + 2%</span>
                        </button>
                        <button type="button" @click="selectTaxCategory('inaproc')"
                                class="rounded-lg border-2 border-slate-200 bg-white px-4 py-4 text-left transition hover:border-brand-500 hover:bg-brand-50">
                            <span class="block text-sm font-semibold text-slate-800">Inaproc</span>
                            <span class="mt-1 block text-xs text-slate-500">Seperti Wapu + PNBP + PPH 29</span>
                        </button>
                    </div>
                </div>

                {{-- Langkah 2: form item setelah kategori dipilih --}}
                <div x-show="selectedTaxCategory || purchasingMode" x-cloak>
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
                                </div>
                            </template>
                            <span x-show="purchasingMode" class="rounded-full bg-brand-100 px-3 py-1 text-sm font-semibold text-brand-700" x-text="taxCategoryLabel(selectedTaxCategory)"></span>
                        </div>
                        <p class="w-full text-xs text-slate-500 sm:w-auto">Kategori bisa diganti kapan saja — PPH/PNBP/margin item dihitung ulang. Exclude &amp; % margin (putih) bisa diubah; Include / potongan / nilai margin (kuning) otomatis.</p>
                    </div>

                    <div class="space-y-4">
                        <template x-for="(p, i) in products" :key="i">
                            <div class="rounded-lg border border-slate-200 p-4">
                                <input type="hidden" :name="`products[${i}][tax_category]`" :value="p.tax_category">
                                <div class="mb-3 grid grid-cols-1 gap-2 sm:grid-cols-12">
                                    <div class="sm:col-span-2">
                                        <label class="crm-label text-xs">Barang / Jasa</label>
                                        <select :name="`products[${i}][item_kind]`" x-model="p.item_kind" @change="onItemKindChange(p)" class="crm-field w-full text-sm" :disabled="purchasingMode">
                                            <option value="barang">Barang</option>
                                            <option value="jasa">Jasa</option>
                                        </select>
                                    </div>
                                    <div class="sm:col-span-5">
                                        <label class="crm-label text-xs">Item</label>
                                        <input type="text" :name="`products[${i}][name]`" x-model="p.name" placeholder="Nama item" class="crm-field w-full" :readonly="purchasingMode">
                                    </div>
                                    <div class="sm:col-span-1">
                                        <label class="crm-label text-xs">Qty</label>
                                        <input type="number" step="0.01" min="0" :name="`products[${i}][quantity]`" x-model.number="p.quantity"
                                               @input="refreshDiscountFromMargin()"
                                               class="crm-field w-full text-right" :readonly="purchasingMode">
                                    </div>
                                    <div class="sm:col-span-3">
                                        <label class="crm-label text-xs">Vendor</label>
                                        <input type="text" :name="`products[${i}][vendor]`" x-model="p.vendor" placeholder="Vendor" class="crm-field w-full">
                                    </div>
                                    <div class="flex items-end justify-end sm:col-span-1">
                                        <button type="button" x-show="!purchasingMode" @click="removeProduct(i)" class="rounded-lg p-2 text-red-500 hover:bg-red-50" title="Hapus item"><i class="bi bi-trash"></i></button>
                                    </div>
                                </div>

                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[640px] text-sm">
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
                                                <input type="number" step="0.01" min="0" :name="`products[${i}][sell_exclude]`" x-model.number="p.sell_exclude"
                                                       @input="onSellChange(p)"
                                                       class="crm-field w-full min-w-[8rem] bg-white text-right" :readonly="purchasingMode">
                                            </td>
                                            <td class="py-2 pr-3">
                                                <input type="text" readonly :value="formatNumber(sellInclude(p))"
                                                       class="crm-field w-full min-w-[8rem] cursor-default border-amber-200 bg-amber-50 text-right text-slate-700">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="py-2 pr-3 font-medium text-slate-600">
                                                Diskon Item
                                                <span class="block text-[10px] font-normal normal-case tracking-normal text-slate-400">Harga net (0 = pakai harga jual)</span>
                                            </td>
                                            <td class="py-2 pr-3">
                                                <input type="number" step="0.01" min="0" :name="`products[${i}][discount_exclude]`" x-model.number="p.discount_exclude"
                                                       @input="onDiscountItemChange(p)"
                                                       class="crm-field w-full min-w-[8rem] bg-white text-right" :readonly="purchasingMode"
                                                       placeholder="0">
                                            </td>
                                            <td class="py-2 pr-3">
                                                <input type="text" readonly :value="formatNumber(discountInclude(p))"
                                                       class="crm-field w-full min-w-[8rem] cursor-default border-amber-200 bg-amber-50 text-right text-slate-700">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="py-2 pr-3 font-medium text-slate-600">Harga Beli / Modal</td>
                                            <td class="py-2 pr-3">
                                                <input type="number" step="0.01" min="0" :name="`products[${i}][cost_exclude]`" x-model.number="p.cost_exclude"
                                                       @input="onCostChange(p)"
                                                       class="crm-field w-full min-w-[8rem] bg-white text-right">
                                            </td>
                                            <td class="py-2 pr-3">
                                                <input type="text" readonly :value="formatNumber(costInclude(p))"
                                                       class="crm-field w-full min-w-[8rem] cursor-default border-amber-200 bg-amber-50 text-right text-slate-700">
                                            </td>
                                        </tr>
                                        <tr x-show="appliesPph(p)">
                                            <td class="py-2 pr-3 font-medium text-slate-600" x-text="'PPH ' + pphPercentFor(p) + '%'"></td>
                                            <td class="py-2 pr-3 text-xs text-slate-400" x-text="'Basis × ' + pphPercentFor(p) + '%'"></td>
                                            <td class="py-2 pr-3">
                                                <input type="text" readonly :value="formatNumber(pphAmount(p))"
                                                       class="crm-field w-full min-w-[8rem] cursor-default border-amber-200 bg-amber-50 text-right text-slate-700">
                                            </td>
                                        </tr>
                                        <tr x-show="appliesPnbp(p)">
                                            <td class="py-2 pr-3 font-medium text-slate-600" x-text="'PNBP ' + pnbpPercent + '%'"></td>
                                            <td class="py-2 pr-3 text-xs text-slate-400" x-text="'Basis × ' + pnbpPercent + '%'"></td>
                                            <td class="py-2 pr-3">
                                                <input type="text" readonly :value="formatNumber(pnbpAmount(p))"
                                                       class="crm-field w-full min-w-[8rem] cursor-default border-amber-200 bg-amber-50 text-right text-slate-700">
                                            </td>
                                        </tr>
                                        <tr x-show="appliesPph29(p)">
                                            <td class="py-2 pr-3 font-medium text-slate-600" x-text="'PPH 29 ' + pph29Percent + '%'"></td>
                                            <td class="py-2 pr-3 text-xs text-slate-400">Dari margin kotor</td>
                                            <td class="py-2 pr-3">
                                                <input type="text" readonly :value="formatNumber(pph29Amount(p))"
                                                       class="crm-field w-full min-w-[8rem] cursor-default border-amber-200 bg-amber-50 text-right text-slate-700">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="py-2 pr-3 font-medium text-slate-600">Margin</td>
                                            <td class="py-2 pr-3 text-xs text-slate-400" x-text="marginLabel(p)"></td>
                                            <td class="py-2 pr-3">
                                                <div class="flex items-center gap-2">
                                                    <input type="text" readonly :value="formatNumber(marginAmount(p))"
                                                           class="crm-field min-w-[8rem] flex-1 cursor-default border-amber-200 bg-amber-50 text-right text-slate-700">
                                                    <div class="flex shrink-0 items-center gap-1">
                                                        <input type="number" step="0.01" min="0" max="99.99"
                                                               x-model.number="p.margin_percent"
                                                               @input="onMarginPercentChange(p)"
                                                               class="crm-field w-20 bg-white text-right text-sm"
                                                               title="Ubah % margin untuk hitung ulang harga jual / diskon"
                                                               :readonly="purchasingMode">
                                                        <span class="text-xs font-semibold text-slate-600">%</span>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>
                        <p x-show="products.length === 0" class="rounded-lg border border-dashed border-slate-200 py-4 text-center text-sm text-slate-400">Belum ada item. Klik tombol + di bawah untuk menambahkan.</p>
                    </div>
                    <div class="mt-3 flex items-center justify-between">
                        <x-btn type="button" variant="secondary" icon="bi-plus-lg" @click="addProduct()" x-show="!purchasingMode">Add Item</x-btn>
                        <div class="text-sm" :class="purchasingMode ? 'ml-auto' : ''">
                            <span class="text-slate-500">Total (Include):&nbsp;</span>
                            <span class="font-semibold text-slate-800" x-text="formatMoney(productsTotal)"></span>
                            <span class="mx-2 text-slate-300">·</span>
                            <span class="text-slate-500">Total Margin:&nbsp;</span>
                            <span class="font-semibold text-green-700" x-text="formatMoney(productsMarginTotal)"></span>
                        </div>
                    </div>
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
                <div x-show="hasShippingCharge" x-cloak class="mt-3 max-w-sm">
                    <label class="mb-1 block text-xs font-medium text-slate-500">Ongkir jual</label>
                    <input type="number" step="0.01" min="0" name="shipping_sell" x-model.number="shippingSell"
                           class="crm-field w-full" placeholder="0">
                    <p class="mt-1 text-xs text-slate-400">
                        Jika dicentang, threshold margin nominal hanya memakai <em>Nominal Umum</em>
                        (tanpa Nominal Ongkir Pribadi).
                    </p>
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
                            <input type="number" step="0.01" min="0" x-model.number="discountPercent"
                                   @input="onDiscountPercentChange()"
                                   class="crm-field w-full" placeholder="0">
                            <span class="shrink-0 text-sm font-semibold text-slate-600">%</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-400">Isi % → nominal terisi otomatis dari total margin.</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Nominal diskon</label>
                        <input type="number" step="0.01" min="0" name="discount_amount" x-model.number="discountAmount"
                               @input="onDiscountAmountChange()"
                               class="crm-field w-full" placeholder="0">
                        <p class="mt-1 text-xs text-slate-400">Setiap diskon &gt; 0 wajib approval Superadmin.</p>
                    </div>
                </div>
                <p class="mt-2 text-xs text-slate-500" x-show="hasDiscount && productsMarginTotal > 0">
                    Basis: total margin <span class="font-medium" x-text="formatMoney(productsMarginTotal)"></span>
                </p>
                <p class="mt-2 text-xs text-amber-600" x-show="hasDiscount && productsMarginTotal <= 0">
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
                <p class="font-semibold"><i class="bi bi-exclamation-triangle mr-1"></i> Margin di bawah minimal</p>
                <p class="mt-1 text-xs">
                    Margin saat ini
                    <strong x-text="(overallMarginPercent ?? '—') + '%'"></strong>
                    / <strong x-text="formatMoney(productsMarginTotal)"></strong>
                    · Minimal
                    <strong x-text="(accountMinMarginPct ?? '—') + '%'"></strong>
                    / <strong x-text="formatMoney(requiredNominalThreshold)"></strong>.
                    Opportunity tetap bisa disimpan, tetapi memerlukan approval Superadmin.
                </p>
            </div>
            @endunless
        </div>

        {{-- Sidebar --}}
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
</form>

<script>
    function opportunityForm(config) {
        const PPN_PERCENT = Number(config.ppnPercent) || 11;
        const TAX_MULTIPLIER = 1 + (PPN_PERCENT / 100);
        const PPH_NON_WAPU_JASA = Number(config.pphNonWapuJasa) || 2;
        const PPH_WAPU_BARANG = Number(config.pphWapuBarang) || 1.5;
        const PPH_WAPU_JASA = Number(config.pphWapuJasa) || 2;
        const PNBP_PERCENT = Number(config.pnbpPercent) || 0.4;
        const PPH29_PERCENT = Number(config.pph29Percent) || 22;

        function pphPercentLookup(taxCategory, itemKind) {
            if (taxCategory === 'non_wapu' && itemKind === 'barang') return 0;
            if (taxCategory === 'non_wapu' && itemKind === 'jasa') return PPH_NON_WAPU_JASA;
            if ((taxCategory === 'wapu' || taxCategory === 'inaproc') && itemKind === 'barang') return PPH_WAPU_BARANG;
            if ((taxCategory === 'wapu' || taxCategory === 'inaproc') && itemKind === 'jasa') return PPH_WAPU_JASA;
            return PPH_NON_WAPU_JASA;
        }

        function calcNetMargin(base, cost, taxCategory, itemKind) {
            const pphPct = pphPercentLookup(taxCategory, itemKind);
            const pph = pphPct > 0 ? Math.round(base * (pphPct / 100) * 100) / 100 : 0;
            const pnbp = taxCategory === 'inaproc'
                ? Math.round(base * (PNBP_PERCENT / 100) * 100) / 100
                : 0;
            const gross = Math.round((base - pph - pnbp - cost) * 100) / 100;
            const pph29 = (taxCategory === 'inaproc' && gross > 0)
                ? Math.round(gross * (PPH29_PERCENT / 100) * 100) / 100
                : 0;
            return Math.round((gross - pph29) * 100) / 100;
        }

        const initialProducts = (config.products || []).map(p => {
            const taxCategory = p.tax_category ?? 'non_wapu';
            const itemKind = p.item_kind ?? 'barang';
            const sell = Number(p.sell_exclude) || 0;
            const cost = Number(p.cost_exclude) || 0;
            const discount = Number(p.discount_exclude) || 0;
            const base = discount > 0 ? discount : sell;
            const margin = calcNetMargin(base, cost, taxCategory, itemKind);
            const marginPercent = base > 0 ? Math.round((margin / base) * 10000) / 100 : 0;

            return {
                name: p.name ?? '',
                quantity: Number(p.quantity) || 0,
                sell_exclude: sell,
                cost_exclude: cost,
                discount_exclude: discount,
                vendor: p.vendor ?? '',
                tax_category: taxCategory,
                item_kind: itemKind,
                margin_percent: marginPercent,
                _lockMarginPercent: false,
            };
        });

        return {
            products: initialProducts,
            selectedTaxCategory: config.initialTaxCategory || null,
            contacts: config.contacts || [],
            accountId: config.accountId || '',
            contactId: config.contactId || '',
            currency: config.currency || 'IDR',
            ppnPercent: PPN_PERCENT,
            pphNonWapuJasa: PPH_NON_WAPU_JASA,
            pphWapuBarang: PPH_WAPU_BARANG,
            pphWapuJasa: PPH_WAPU_JASA,
            pnbpPercent: PNBP_PERCENT,
            pph29Percent: PPH29_PERCENT,
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
            purchasingMode: !!config.purchasingMode,
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
                const v = this.accountMeta ? this.accountMeta.min_margin_pct : null;
                return v === null || v === undefined ? null : Number(v);
            },
            get requiredNominalThreshold() {
                const umum = Number(this.marginNominalUmum) || 0;
                if (this.isFreeShippingCity || this.hasShippingCharge) {
                    return this.round(umum);
                }
                return this.round(umum + (Number(this.marginNominalOngkirPribadi) || 0));
            },
            get overallMarginPercent() {
                const sell = this.products.reduce((s, p) => {
                    const qty = Number(p.quantity) || 0;
                    const disc = Number(p.discount_exclude) || 0;
                    const sellEx = Number(p.sell_exclude) || 0;
                    const base = disc > 0 ? disc : sellEx;
                    return s + qty * base;
                }, 0);
                if (sell <= 0) return null;
                return this.round((this.productsMarginTotal / sell) * 100);
            },
            get marginBelowPercent() {
                const min = this.accountMinMarginPct;
                if (min === null) return false;
                const pct = this.overallMarginPercent;
                return pct === null || pct < min;
            },
            get marginBelowNominal() {
                const thr = this.requiredNominalThreshold;
                return thr > 0 && this.productsMarginTotal < thr;
            },
            get marginNeedsApprovalHint() {
                return !!(this.accountId && (this.marginBelowPercent || this.marginBelowNominal));
            },
            get productsTotal() {
                return this.products.reduce((s, p) => s + (Number(p.quantity) || 0) * this.effectiveSellInclude(p), 0);
            },
            get productsMarginTotal() {
                return this.round(this.products.reduce(
                    (s, p) => s + (Number(p.quantity) || 0) * this.marginAmount(p),
                    0
                ));
            },
            taxCategoryLabel(category) {
                if (category === 'wapu') return 'Wapu';
                if (category === 'inaproc') return 'Inaproc';
                return 'Non Wapu';
            },
            syncDiscountPercentFromAmount() {
                const margin = this.productsMarginTotal;
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
                const margin = this.productsMarginTotal;
                if (margin > 0) {
                    this.discountAmount = this.round(margin * pct / 100);
                }
            },
            onDiscountAmountChange() {
                this._lockDiscountPercent = false;
                this.syncDiscountPercentFromAmount();
            },
            refreshDiscountFromMargin() {
                if (!this.hasDiscount) return;
                if (this._lockDiscountPercent && (Number(this.discountPercent) || 0) > 0) {
                    const margin = this.productsMarginTotal;
                    if (margin > 0) {
                        this.discountAmount = this.round(margin * (Number(this.discountPercent) || 0) / 100);
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
            costInclude(p) {
                return this.round((Number(p.cost_exclude) || 0) * TAX_MULTIPLIER);
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
                return this.round(this.effectiveSellExclude(p) * (Number(this.pnbpPercent) || 0) / 100);
            },
            grossMarginAmount(p) {
                const base = this.effectiveSellExclude(p);
                const cost = Number(p.cost_exclude) || 0;
                return this.round(base - this.pphAmount(p) - this.pnbpAmount(p) - cost);
            },
            pph29Amount(p) {
                if (!this.appliesPph29(p)) return 0;
                const gross = this.grossMarginAmount(p);
                if (gross <= 0) return 0;
                return this.round(gross * (Number(this.pph29Percent) || 0) / 100);
            },
            marginAmount(p) {
                return this.round(this.grossMarginAmount(p) - this.pph29Amount(p));
            },
            calcMarginPercent(p) {
                const margin = this.marginAmount(p);
                const base = this.effectiveSellExclude(p);
                return base > 0 ? this.round((margin / base) * 100) : 0;
            },
            marginPercent(p) {
                return Number(p.margin_percent) || 0;
            },
            marginLabel(p) {
                const hasItemDiscount = (Number(p.discount_exclude) || 0) > 0;
                const head = hasItemDiscount ? 'Diskon' : 'Jual Exclude';
                if (this.appliesPph29(p)) {
                    return head + ' − PPH − PNBP − PPH29 − Modal';
                }
                if (this.appliesPph(p)) {
                    return head + ' − PPH − Modal';
                }
                return hasItemDiscount ? 'Diskon − Modal' : 'Jual Exclude − Beli Exclude';
            },
            /**
             * Dari % margin target (net) + modal → hitung harga basis.
             * Inaproc: base = cost*(1-r29) / ((1-rPph-rPnbp)*(1-r29) - pct/100)
             */
            sellFromMarginPercent(p) {
                const pct = Number(p.margin_percent) || 0;
                const cost = Number(p.cost_exclude) || 0;
                if (cost <= 0 || pct <= 0) {
                    return this.effectiveSellExclude(p);
                }

                const rPph = this.pphPercentFor(p) / 100;
                const rPnbp = this.appliesPnbp(p) ? (Number(this.pnbpPercent) || 0) / 100 : 0;
                const r29 = this.appliesPph29(p) ? (Number(this.pph29Percent) || 0) / 100 : 0;
                const keep = (1 - rPph - rPnbp) * (1 - r29);
                const denom = keep - (pct / 100);
                if (denom <= 0) {
                    return this.effectiveSellExclude(p);
                }

                return this.round(cost * (1 - r29) / denom);
            },
            applyMarginPercentToPrice(p) {
                const next = this.sellFromMarginPercent(p);
                if ((Number(p.discount_exclude) || 0) > 0) {
                    p.discount_exclude = next;
                } else {
                    p.sell_exclude = next;
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
            onCostChange(p) {
                if (p._lockMarginPercent && (Number(p.margin_percent) || 0) > 0) {
                    this.applyMarginPercentToPrice(p);
                } else {
                    p.margin_percent = this.calcMarginPercent(p);
                }
                this.refreshDiscountFromMargin();
            },
            onMarginPercentChange(p) {
                let pct = Number(p.margin_percent) || 0;
                const rPph = this.pphPercentFor(p) / 100;
                const rPnbp = this.appliesPnbp(p) ? (Number(this.pnbpPercent) || 0) / 100 : 0;
                const r29 = this.appliesPph29(p) ? (Number(this.pph29Percent) || 0) / 100 : 0;
                const maxPct = Math.max((1 - rPph - rPnbp) * (1 - r29) * 100 - 0.01, 0);
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
            formatNumber(value) {
                value = Number(value) || 0;
                if (this.currency === 'IDR') return value.toLocaleString('id-ID', { maximumFractionDigits: 0 });
                return value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
            formatPercent(value) {
                const n = Number(value) || 0;
                return n.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
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
                    vendor: '',
                    tax_category: this.selectedTaxCategory,
                    item_kind: 'barang',
                    margin_percent: 0,
                    _lockMarginPercent: false,
                });
            },
            removeProduct(i) {
                this.products.splice(i, 1);
                this.refreshDiscountFromMargin();
            },
            init() {
                this.$watch('products', () => { if (this.products.length > 0) this.amount = this.productsTotal; }, { deep: true });
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
                    this.syncDiscountPercentFromAmount();
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
