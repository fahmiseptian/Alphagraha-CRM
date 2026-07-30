{{-- Purchase Orders — Purchasing / Superadmin, Closed Won only --}}
@php
    $canManagePo = auth()->user()->canManagePurchaseOrders()
        && $opportunity->stage === \App\Models\Espo\Opportunity::WON_STAGE;
    $poCurrency = $opportunity->amount_currency ?: 'IDR';
    $poList = $opportunity->purchaseOrders ?? collect();
    $ppnMultiplier = \App\Support\OpportunityProductPricing::ppnMultiplier();
    $poSurchargeCash = \App\Support\PurchaseOrderPricing::cashSurchargePercent();
    $poSurchargeTop = \App\Support\PurchaseOrderPricing::topSurchargePercent();

    $poFormInitial = [
        'mode' => null,
        'editId' => null,
        'number' => '',
        'paymentTerm' => 'top',
        'items' => [],
    ];

    if (old('number') !== null || old('items') !== null || old('payment_term') !== null) {
        $poFormInitial = [
            'mode' => old('_po_id') ? 'edit' : 'create',
            'editId' => old('_po_id') ? (int) old('_po_id') : null,
            'number' => old('number', ''),
            'paymentTerm' => old('payment_term', 'top'),
            'items' => collect(old('items', []))->map(fn ($i) => [
                'product_name' => $i['product_name'] ?? '',
                'quantity' => (float) ($i['quantity'] ?? 1),
                'description' => $i['description'] ?? '',
                'note' => $i['note'] ?? '',
                'unit_price' => (float) ($i['unit_price'] ?? 0),
            ])->values()->all(),
        ];
    }

    $poStoreUrl = route('opportunities.purchase-orders.store', $opportunity);
    $poUpdateBase = url('/opportunities/'.$opportunity->id.'/purchase-orders');
@endphp

@if ($canManagePo)
    <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm"
         x-data="{
            mode: @js($poFormInitial['mode']),
            editId: @js($poFormInitial['editId']),
            number: @js($poFormInitial['number']),
            paymentTerm: @js($poFormInitial['paymentTerm']),
            items: @js($poFormInitial['items'] ?: []),
            editingField: null,
            openId: null,
            storeUrl: @js($poStoreUrl),
            updateBase: @js($poUpdateBase),
            ppnMultiplier: @js($ppnMultiplier),
            surchargeCash: @js($poSurchargeCash),
            surchargeTop: @js($poSurchargeTop),
            isCash() {
                return this.paymentTerm === 'cash';
            },
            surchargePercent() {
                return this.isCash() ? Number(this.surchargeCash) || 0 : Number(this.surchargeTop) || 0;
            },
            hasSurcharge() {
                return this.surchargePercent() > 0;
            },
            surchargeLabel() {
                const p = this.surchargePercent();
                const n = Number.isInteger(p) ? String(p) : String(p);
                return n.replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1') + '%';
            },
            emptyItem() {
                return { product_name: '', quantity: 1, description: '', note: '', unit_price: 0 };
            },
            formAction() {
                if (this.mode === 'edit' && this.editId) {
                    return this.updateBase + '/' + this.editId;
                }
                return this.storeUrl;
            },
            startCreate() {
                this.mode = 'create';
                this.editId = null;
                this.number = '';
                this.paymentTerm = 'top';
                this.items = [this.emptyItem()];
            },
            startEdit(po) {
                this.mode = 'edit';
                this.editId = po.id;
                this.number = po.number || '';
                this.paymentTerm = po.payment_term || 'top';
                this.items = (po.items && po.items.length)
                    ? po.items.map((i) => Object.assign(this.emptyItem(), i))
                    : [this.emptyItem()];
            },
            cancelForm() {
                this.mode = null;
                this.editId = null;
                this.number = '';
                this.paymentTerm = 'top';
                this.items = [];
            },
            addItem() {
                this.items.push(this.emptyItem());
            },
            removeItem(index) {
                if (this.items.length <= 1) return;
                this.items.splice(index, 1);
            },
            modal(item) {
                return Number(item.unit_price) || 0;
            },
            extraExclude(item) {
                const rate = this.surchargePercent() / 100;
                if (rate <= 0) return 0;
                return Math.round(this.modal(item) * rate * 100) / 100;
            },
            jumlahExclude(item) {
                return Math.round((this.modal(item) + this.extraExclude(item)) * 100) / 100;
            },
            hargaInclude(item) {
                return Math.round(this.modal(item) * this.ppnMultiplier * 100) / 100;
            },
            extraInclude(item) {
                const rate = this.surchargePercent() / 100;
                if (rate <= 0) return 0;
                return Math.round(this.hargaInclude(item) * rate * 100) / 100;
            },
            jumlahInclude(item) {
                return Math.round((this.hargaInclude(item) + this.extraInclude(item)) * 100) / 100;
            },
            lineTotal(item) {
                return (Number(item.quantity) || 0) * this.jumlahExclude(item);
            },
            grandTotal() {
                return this.items.reduce((sum, item) => sum + this.lineTotal(item), 0);
            },
            formatId(value, decimals = 0) {
                if (window.CrmNumber) return window.CrmNumber.format(value, decimals);
                return (Number(value) || 0).toLocaleString('id-ID', { maximumFractionDigits: decimals });
            },
            parseId(str) {
                if (window.CrmNumber) return window.CrmNumber.parse(str);
                return Number(String(str).replace(/\./g, '').replace(',', '.')) || 0;
            },
            formatMoney(amount) {
                const n = Math.round(Number(amount) || 0);
                return 'Rp ' + this.formatId(n, 0);
            },
         }">
        <div class="flex items-center justify-between gap-3 px-5 pt-4">
            <h3 class="text-sm font-semibold text-slate-800">Purchase Orders</h3>
            <div class="flex items-center gap-1">
                <a href="{{ route('opportunities.purchase-orders.preview', $opportunity) }}"
                   target="_blank"
                   class="inline-flex items-center justify-center rounded-lg bg-slate-50 px-2.5 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-brand-600"
                   title="Preview laporan">
                    <i class="bi bi-eye"></i>
                </a>
                <a href="{{ route('opportunities.purchase-orders.pdf', $opportunity) }}"
                   class="inline-flex items-center justify-center rounded-lg bg-slate-50 px-2.5 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-red-600"
                   title="Download PDF">
                    <i class="bi bi-file-earmark-pdf"></i>
                </a>
                <button type="button"
                        @click="startCreate()"
                        class="inline-flex items-center gap-1 rounded-lg bg-brand-50 px-3 py-1.5 text-sm font-medium text-brand-600 hover:bg-brand-100"
                        title="Tambah PO">
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>
        </div>

        {{-- Ongkir: 1 opportunity = 1 ongkir --}}
        <div class="mx-5 mt-3 rounded-lg border border-slate-100 bg-slate-50/80 px-3 py-2.5"
             x-data="{
                editing: @js(old('crm_shipping_cost') !== null),
             }">
            <form method="POST" action="{{ route('opportunities.shipping-cost.update', $opportunity) }}"
                  class="flex flex-wrap items-center gap-x-3 gap-y-2">
                @csrf
                @method('PUT')
                <div class="flex min-w-0 flex-1 items-center gap-2">
                    <span class="shrink-0 text-xs font-semibold uppercase tracking-wider text-slate-400">Ongkir</span>
                    <template x-if="!editing">
                        <span class="text-sm font-semibold tabular-nums text-slate-800">
                            @if ($opportunity->crm_shipping_cost !== null && (float) $opportunity->crm_shipping_cost > 0)
                                {{ money($opportunity->crm_shipping_cost, $poCurrency) }}
                            @else
                                <span class="font-normal text-slate-400">Belum diisi</span>
                            @endif
                        </span>
                    </template>
                    <template x-if="editing">
                        <input type="text" inputmode="decimal" name="crm_shipping_cost" data-crm-number data-decimals="0"
                               value="{{ old('crm_shipping_cost', $opportunity->crm_shipping_cost ?? 0) }}"
                               placeholder="0"
                               class="crm-field w-full max-w-[200px] text-right text-sm tabular-nums"
                               autofocus
                               x-init="$nextTick(() => window.CrmNumber && CrmNumber.enhance($el.parentElement))">
                    </template>
                </div>
                <div class="flex items-center gap-1.5">
                    <template x-if="!editing">
                        <button type="button" @click="editing = true"
                                class="rounded p-1.5 text-slate-500 hover:bg-white hover:text-brand-600"
                                title="Edit ongkir">
                            <i class="bi bi-pencil text-sm"></i>
                        </button>
                    </template>
                    <template x-if="editing">
                        <div class="flex items-center gap-1.5">
                            <button type="button" @click="editing = false"
                                    class="text-xs text-slate-500 hover:text-slate-700">Batal</button>
                            <button type="submit"
                                    class="inline-flex items-center gap-1 rounded-lg bg-brand-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-brand-700">
                                <i class="bi bi-check-lg"></i> Simpan
                            </button>
                        </div>
                    </template>
                </div>
            </form>
            @error('crm_shipping_cost')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        @error('number')
            <div class="mx-5 mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</div>
        @enderror
        @error('payment_term')
            <div class="mx-5 mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</div>
        @enderror
        @error('items')
            <div class="mx-5 mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</div>
        @enderror

        {{-- Form create / edit --}}
        <div x-show="mode !== null" x-cloak class="border-b border-slate-100 px-5 py-4">
            <form method="POST" :action="formAction()" class="space-y-4">
                @csrf
                <input type="hidden" name="_method" value="PUT" :disabled="mode !== 'edit'">
                <input type="hidden" name="_po_id" :value="editId || ''">

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:max-w-xl">
                    <div>
                        <label class="crm-label">Nomor PO</label>
                        <input type="text" name="number" x-model="number" required maxlength="100"
                               placeholder="Contoh: PO-2026-001"
                               class="crm-field w-full">
                    </div>
                    <div>
                        <label class="crm-label">Kondisi</label>
                        <div class="flex gap-4 pt-2">
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input type="radio" name="payment_term" value="top" x-model="paymentTerm"
                                       class="border-slate-300 text-brand-600 focus:ring-brand-500">
                                TOP <span class="text-xs text-slate-400" x-text="'(' + (Number(surchargeTop) || 0) + '%)'"></span>
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input type="radio" name="payment_term" value="cash" x-model="paymentTerm"
                                       class="border-slate-300 text-brand-600 focus:ring-brand-500">
                                Cash <span class="text-xs text-slate-400" x-text="'(' + (Number(surchargeCash) || 0) + '%)'"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Items</p>
                        <button type="button" @click="addItem()"
                                class="text-xs font-medium text-brand-600 hover:text-brand-700">
                            <i class="bi bi-plus-lg"></i> Tambah item
                        </button>
                    </div>

                    <template x-for="(item, i) in items" :key="i">
                        <div class="space-y-2 rounded-lg border border-slate-200 p-3">
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-12">
                                <div class="sm:col-span-5">
                                    <label class="crm-label text-xs">Nama produk</label>
                                    <input type="text" :name="'items[' + i + '][product_name]'" x-model="item.product_name"
                                           required placeholder="Nama produk" class="crm-field w-full">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="crm-label text-xs">Qty</label>
                                    <input type="text" inputmode="decimal" required
                                           x-effect="if (editingField !== `pq-${i}`) $el.value = formatId(item.quantity, 2)"
                                           @focus="editingField = `pq-${i}`"
                                           @blur="editingField = null; $el.value = formatId(item.quantity, 2)"
                                           @input="item.quantity = parseId($event.target.value)"
                                           class="crm-field w-full text-right tabular-nums">
                                    <input type="hidden" :name="'items[' + i + '][quantity]'" :value="item.quantity">
                                </div>
                                <div class="sm:col-span-3">
                                    <label class="crm-label text-xs">Harga modal</label>
                                    <input type="text" inputmode="decimal" required
                                           x-effect="if (editingField !== `pp-${i}`) $el.value = formatId(item.unit_price)"
                                           @focus="editingField = `pp-${i}`"
                                           @blur="editingField = null; $el.value = formatId(item.unit_price)"
                                           @input="item.unit_price = parseId($event.target.value)"
                                           class="crm-field w-full text-right tabular-nums">
                                    <input type="hidden" :name="'items[' + i + '][unit_price]'" :value="item.unit_price">
                                </div>
                                <div class="flex items-end justify-between gap-2 sm:col-span-2">
                                    <div class="min-w-0 flex-1">
                                        <label class="crm-label text-xs">Subtotal</label>
                                        <p class="truncate py-2 text-sm font-medium text-slate-700" x-text="formatMoney(lineTotal(item))"></p>
                                    </div>
                                    <button type="button" @click="removeItem(i)" x-show="items.length > 1"
                                            class="mb-1 rounded p-1.5 text-red-500 hover:bg-red-50" title="Hapus item">
                                        <i class="bi bi-trash text-sm"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="overflow-x-auto rounded-md border border-slate-100 bg-slate-50/80">
                                <table class="w-full min-w-[640px] text-left text-xs">
                                    <thead class="text-[10px] uppercase tracking-wider text-slate-400">
                                        <tr>
                                            <th class="px-2 py-1.5 font-medium text-right" x-show="hasSurcharge()" x-text="surchargeLabel() + ' Exclude'"></th>
                                            <th class="px-2 py-1.5 font-medium text-right">Jumlah Exclude</th>
                                            <th class="px-2 py-1.5 font-medium text-right">Harga Include</th>
                                            <th class="px-2 py-1.5 font-medium text-right" x-show="hasSurcharge()" x-text="surchargeLabel() + ' Include'"></th>
                                            <th class="px-2 py-1.5 font-medium text-right">Jumlah Include</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="tabular-nums text-slate-600">
                                            <td class="px-2 py-1.5 text-right" x-show="hasSurcharge()" x-text="formatMoney(extraExclude(item))"></td>
                                            <td class="px-2 py-1.5 text-right font-medium text-slate-700" x-text="formatMoney(jumlahExclude(item))"></td>
                                            <td class="px-2 py-1.5 text-right" x-text="formatMoney(hargaInclude(item))"></td>
                                            <td class="px-2 py-1.5 text-right" x-show="hasSurcharge()" x-text="formatMoney(extraInclude(item))"></td>
                                            <td class="px-2 py-1.5 text-right font-medium text-slate-700" x-text="formatMoney(jumlahInclude(item))"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <div>
                                    <label class="crm-label text-xs">Deskripsi</label>
                                    <textarea :name="'items[' + i + '][description]'" x-model="item.description" rows="2"
                                              placeholder="Deskripsi" class="crm-field w-full text-sm"></textarea>
                                </div>
                                <div>
                                    <label class="crm-label text-xs">Note</label>
                                    <textarea :name="'items[' + i + '][note]'" x-model="item.note" rows="2"
                                              placeholder="Catatan" class="crm-field w-full text-sm"></textarea>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
                    <p class="text-sm text-slate-600">
                        Total PO:
                        <span class="font-semibold text-slate-900" x-text="formatMoney(grandTotal())"></span>
                        <span class="ml-1 text-xs text-slate-400" x-show="hasSurcharge()" x-text="'(' + (isCash() ? 'Cash' : 'TOP') + ' +' + surchargeLabel() + ')'"></span>
                    </p>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="cancelForm()" class="text-sm text-slate-500 hover:text-slate-700">Cancel</button>
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
                            <i class="bi bi-check-lg"></i>
                            <span x-text="mode === 'edit' ? 'Update PO' : 'Simpan PO'"></span>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        @if ($poList->count())
            <ul class="mt-2 divide-y divide-slate-50">
                @foreach ($poList as $po)
                    @php
                        $poIsCash = $po->isCash();
                        $poSurchargePct = \App\Support\PurchaseOrderPricing::surchargePercent($po->payment_term);
                        $poHasSurcharge = $poSurchargePct > 0;
                        $poSurchargeLabel = rtrim(rtrim(number_format($poSurchargePct, 2, ',', '.'), '0'), ',').'%';
                        $poEditPayload = [
                            'id' => $po->id,
                            'number' => $po->number,
                            'payment_term' => $po->payment_term ?: 'top',
                            'items' => $po->items->map(fn ($i) => [
                                'product_name' => $i->product_name,
                                'quantity' => (float) $i->quantity,
                                'description' => $i->description ?? '',
                                'note' => $i->note ?? '',
                                'unit_price' => (float) $i->unit_price,
                            ])->values()->all(),
                        ];
                        $poColspan = $poHasSurcharge ? 8 : 6;
                    @endphp
                    <li class="px-5 py-3" x-show="!(mode === 'edit' && Number(editId) === {{ (int) $po->id }})">
                        <div class="flex items-start gap-2">
                            <button type="button" @click="openId = openId === {{ $po->id }} ? null : {{ $po->id }}"
                                    class="mt-0.5 shrink-0 rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                                <i class="bi" :class="openId === {{ $po->id }} ? 'bi-chevron-down' : 'bi-chevron-right'"></i>
                            </button>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                    <span class="text-sm font-semibold text-slate-800">{{ $po->number }}</span>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide
                                        {{ $poIsCash ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600' }}">
                                        {{ $po->paymentTermLabel() }}
                                    </span>
                                    <span class="text-sm text-slate-500">{{ money($po->total, $po->currency ?: $poCurrency) }}</span>
                                    <span class="text-xs text-slate-400">{{ $po->items->count() }} item</span>
                                </div>
                                <p class="mt-0.5 text-xs text-slate-400">
                                    {{ optional($po->creator)->display_name ?: '—' }}
                                    &middot;
                                    {{ $po->created_at?->translatedFormat('d M Y H:i') }}
                                </p>

                                <div x-show="openId === {{ $po->id }}" x-cloak class="mt-3 overflow-x-auto rounded-lg border border-slate-100">
                                    <table class="w-full min-w-[720px] text-left text-sm">
                                        <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-400">
                                            <tr>
                                                <th class="px-3 py-2 font-medium">Produk</th>
                                                <th class="px-3 py-2 font-medium text-right">Qty</th>
                                                <th class="px-3 py-2 font-medium text-right">Harga modal</th>
                                                @if ($poHasSurcharge)
                                                    <th class="px-3 py-2 font-medium text-right">{{ $poSurchargeLabel }} Exclude</th>
                                                @endif
                                                <th class="px-3 py-2 font-medium text-right">Jumlah Exclude</th>
                                                <th class="px-3 py-2 font-medium text-right">Harga Include</th>
                                                @if ($poHasSurcharge)
                                                    <th class="px-3 py-2 font-medium text-right">{{ $poSurchargeLabel }} Include</th>
                                                @endif
                                                <th class="px-3 py-2 font-medium text-right">Jumlah Include</th>
                                                <th class="px-3 py-2 font-medium text-right">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-50">
                                            @foreach ($po->items as $item)
                                                @php $b = $item->pricingBreakdown(null, $po->payment_term); @endphp
                                                <tr>
                                                    <td class="px-3 py-2 align-top">
                                                        <p class="font-medium text-slate-700">{{ $item->product_name }}</p>
                                                        @if ($item->description)
                                                            <p class="mt-0.5 whitespace-pre-line text-xs text-slate-500">{{ $item->description }}</p>
                                                        @endif
                                                        @if ($item->note)
                                                            <p class="mt-0.5 whitespace-pre-line text-xs italic text-slate-400">{{ $item->note }}</p>
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2 text-right tabular-nums text-slate-600">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }}</td>
                                                    <td class="px-3 py-2 text-right tabular-nums text-slate-600">{{ money($b['modal'], $po->currency ?: $poCurrency) }}</td>
                                                    @if ($poHasSurcharge)
                                                        <td class="px-3 py-2 text-right tabular-nums text-slate-600">{{ money($b['extra_exclude'], $po->currency ?: $poCurrency) }}</td>
                                                    @endif
                                                    <td class="px-3 py-2 text-right tabular-nums text-slate-600">{{ money($b['jumlah_exclude'], $po->currency ?: $poCurrency) }}</td>
                                                    <td class="px-3 py-2 text-right tabular-nums text-slate-600">{{ money($b['harga_include'], $po->currency ?: $poCurrency) }}</td>
                                                    @if ($poHasSurcharge)
                                                        <td class="px-3 py-2 text-right tabular-nums text-slate-600">{{ money($b['extra_include'], $po->currency ?: $poCurrency) }}</td>
                                                    @endif
                                                    <td class="px-3 py-2 text-right tabular-nums text-slate-600">{{ money($b['jumlah_include'], $po->currency ?: $poCurrency) }}</td>
                                                    <td class="px-3 py-2 text-right tabular-nums font-medium text-slate-800">{{ money($item->line_total, $po->currency ?: $poCurrency) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr class="border-t border-slate-200 bg-slate-50">
                                                <td colspan="{{ $poColspan }}" class="px-3 py-2 text-right text-xs font-semibold uppercase text-slate-500">Total PO</td>
                                                <td class="px-3 py-2 text-right font-semibold text-slate-900">{{ money($po->total, $po->currency ?: $poCurrency) }}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <button type="button"
                                        @click="startEdit(@js($poEditPayload))"
                                        class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-brand-600"
                                        title="Edit">
                                    <i class="bi bi-pencil text-sm"></i>
                                </button>
                                <form method="POST"
                                      action="{{ route('opportunities.purchase-orders.destroy', [$opportunity, $po]) }}"
                                      onsubmit="return confirm(@js('Hapus Purchase Order '.$po->number.'?'))"
                                      class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="rounded p-1.5 text-red-500 hover:bg-red-50" title="Hapus">
                                        <i class="bi bi-trash text-sm"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <div x-show="mode === null" class="px-5 py-8 text-center text-sm text-slate-400">
                Belum ada Purchase Order. Klik <i class="bi bi-plus-lg"></i> untuk menambah.
            </div>
        @endif
    </div>
@endif
