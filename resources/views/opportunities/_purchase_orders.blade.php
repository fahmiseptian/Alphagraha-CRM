{{-- Purchase Orders — Purchasing / Superadmin, Closed Won only --}}
@php
    $canManagePo = auth()->user()->canManagePurchaseOrders()
        && $opportunity->stage === \App\Models\Espo\Opportunity::WON_STAGE;
    $canViewPo = auth()->user()->canViewPurchaseOrders()
        && $opportunity->stage === \App\Models\Espo\Opportunity::WON_STAGE;
    $poCurrency = $opportunity->amount_currency ?: 'IDR';
    $poList = $opportunity->purchaseOrders ?? collect();
    $ppnMultiplier = \App\Support\OpportunityProductPricing::ppnMultiplier();
    $poSurchargeCash = \App\Support\PurchaseOrderPricing::cashSurchargePercent();
    $poSurchargeTop = \App\Support\PurchaseOrderPricing::topSurchargePercent();
    $poVendorOptions = app(\App\Services\VendorStockService::class)->vendorOptions();
    $poVendorStocks = app(\App\Services\VendorStockService::class)->snapshotForPoForm();
    $poVendorStockNames = collect($poVendorStocks)
        ->pluck('product_name')
        ->map(fn ($n) => trim((string) $n))
        ->filter()
        ->unique()
        ->values()
        ->all();
    $poBrandOptions = app(\App\Services\CatalogService::class)->brandOptions();
    $poOppProducts = $opportunity->products->map(fn ($p) => [
        'name' => (string) ($p['name'] ?? ''),
        'brand' => (string) ($p['brand'] ?? ''),
        'sku' => (string) ($p['sku'] ?? ''),
        'quantity' => (float) ($p['quantity'] ?? 1),
        'vendor' => (string) ($p['vendor'] ?? ''),
        'cost_exclude' => (float) ($p['cost_exclude'] ?? 0),
    ])->filter(fn ($p) => trim($p['name']) !== '')->values()->all();

    $mapPoVendors = function ($vendors) {
        return collect($vendors ?? [])->map(fn ($v) => [
            'vendor_id' => $v['vendor_id'] ?? ($v->vendor_id ?? ''),
            'vendor_stock_id' => $v['vendor_stock_id'] ?? ($v->vendor_stock_id ?? ''),
            'vendor_name' => $v['vendor_name'] ?? ($v->vendor_name ?? ''),
            'status' => $v['status'] ?? ($v->status ?? 'ready'),
            'top' => $v['top'] ?? ($v->top ?? \App\Support\CustomerTop::DAYS_30),
            'unit_price' => (float) ($v['unit_price'] ?? ($v->unit_price ?? 0)),
            'is_selected' => (bool) ($v['is_selected'] ?? ($v->is_selected ?? false)),
        ])->values()->all();
    };

    $mapPoItems = function ($items) use ($mapPoVendors) {
        return collect($items ?? [])->map(fn ($i) => [
            'opportunity_product_name' => $i['opportunity_product_name'] ?? '',
            'product_name' => $i['product_name'] ?? '',
            'brand' => $i['brand'] ?? '',
            'quantity' => (float) ($i['quantity'] ?? 1),
            'description' => $i['description'] ?? '',
            'note' => $i['note'] ?? '',
            'unit_price' => (float) ($i['unit_price'] ?? 0),
            'vendors' => $mapPoVendors($i['vendors'] ?? []),
        ])->values()->all();
    };

    $poFormInitial = [
        'mode' => null,
        'editId' => null,
        'number' => '',
        'vendorId' => '',
        'paymentTerm' => 'top',
        'items' => [],
    ];

    if (old('plan_mode') !== null || old('pos') !== null) {
        $poFormInitial = [
            'mode' => 'plan',
            'editId' => null,
            'number' => '',
            'vendorId' => '',
            'paymentTerm' => old('payment_term', 'top'),
            'items' => [],
        ];
    } elseif (old('number') !== null || old('items') !== null || old('payment_term') !== null || old('vendor_id') !== null) {
        $poFormInitial = [
            'mode' => old('_po_id') ? 'edit' : 'plan',
            'editId' => old('_po_id') ? (int) old('_po_id') : null,
            'number' => old('number', ''),
            'vendorId' => old('vendor_id', ''),
            'paymentTerm' => old('payment_term', 'top'),
            'items' => $mapPoItems(old('items', [])),
        ];
    }

    $poStoreUrl = route('opportunities.purchase-orders.store', $opportunity);
    $poUpdateBase = url('/opportunities/'.$opportunity->id.'/purchase-orders');
    $poItemErrors = collect($errors->keys())->filter(fn ($k) => str_starts_with($k, 'items') || str_starts_with($k, 'pos'))->map(fn ($k) => $errors->first($k))->unique()->values();

    // Tree tampilan: Produk → PO → Item → Vendor
    $poTreeByProduct = [];
    $poPayloadById = [];
    $oppProductOrder = collect($poOppProducts)->pluck('name')->all();
    foreach ($poList as $po) {
        $poEditPayload = [
            'id' => $po->id,
            'number' => $po->number,
            'vendor_id' => $po->vendor_id,
            'payment_term' => $po->payment_term ?: 'top',
            'items' => $po->items->map(fn ($i) => [
                'opportunity_product_name' => $i->opportunity_product_name ?? '',
                'product_name' => $i->product_name,
                'brand' => $i->brand ?? '',
                'quantity' => (float) $i->quantity,
                'description' => $i->description ?? '',
                'note' => $i->note ?? '',
                'unit_price' => (float) $i->unit_price,
                'vendors' => $i->vendorQuotes->map(fn ($q) => [
                    'vendor_id' => $q->vendor_id,
                    'vendor_stock_id' => $q->vendor_stock_id,
                    'vendor_name' => $q->displayVendorName(),
                    'status' => $q->status ?: 'ready',
                    'top' => $q->topValue(),
                    'unit_price' => (float) $q->unit_price,
                    'is_selected' => (bool) $q->is_selected,
                ])->values()->all(),
            ])->values()->all(),
        ];
        $poPayloadById[$po->id] = $poEditPayload;

        $itemsByProduct = $po->items->groupBy(function ($i) {
            $name = trim((string) ($i->opportunity_product_name ?? ''));

            return $name !== '' ? $name : '(Tanpa produk opportunity)';
        });

        foreach ($itemsByProduct as $productName => $items) {
            if (! isset($poTreeByProduct[$productName])) {
                $poTreeByProduct[$productName] = [
                    'product_name' => $productName,
                    'pos' => [],
                    'item_count' => 0,
                    'total_modal' => 0.0,
                ];
            }
            $lineModal = 0.0;
            foreach ($items as $item) {
                $selected = $item->selectedVendorQuote();
                $unit = $selected ? (float) $selected->unit_price : (float) $item->unit_price;
                $lineModal += round((float) $item->quantity * $unit, 2);
            }
            $poTreeByProduct[$productName]['pos'][] = [
                'po' => $po,
                'items' => $items,
                'edit_payload' => $poEditPayload,
                'modal_total' => $lineModal,
            ];
            $poTreeByProduct[$productName]['item_count'] += $items->count();
            $poTreeByProduct[$productName]['total_modal'] = round(
                $poTreeByProduct[$productName]['total_modal'] + $lineModal,
                2
            );
        }
    }

    uksort($poTreeByProduct, function ($a, $b) use ($oppProductOrder) {
        $ia = array_search($a, $oppProductOrder, true);
        $ib = array_search($b, $oppProductOrder, true);
        $ia = $ia === false ? PHP_INT_MAX : $ia;
        $ib = $ib === false ? PHP_INT_MAX : $ib;
        if ($ia === $ib) {
            return strcasecmp($a, $b);
        }

        return $ia <=> $ib;
    });
@endphp

@if ($canViewPo)
    <div id="purchase-orders" class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm"
         x-data="{
            mode: @js($poFormInitial['mode']),
            editId: @js($poFormInitial['editId']),
            number: @js($poFormInitial['number']),
            vendorId: @js((string) ($poFormInitial['vendorId'] ?? '')),
            paymentTerm: @js($poFormInitial['paymentTerm']),
            groups: [],
            planProducts: [],
            poDrafts: [],
            items: @js($poFormInitial['items'] ?: []),
            editingField: null,
            openId: null,
            openProduct: null,
            previewPoId: null,
            uid: 1,
            _catalogCache: null,
            storeUrl: @js($poStoreUrl),
            updateBase: @js($poUpdateBase),
            ppnMultiplier: @js($ppnMultiplier),
            surchargeCash: @js($poSurchargeCash),
            surchargeTop: @js($poSurchargeTop),
            vendorOptions: @js($poVendorOptions),
            vendorStocks: @js($poVendorStocks),
            brandOptions: @js($poBrandOptions),
            opportunityProducts: @js($poOppProducts),
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
            nextUid() {
                return this.uid++;
            },
            emptyVendor(selected = false) {
                return {
                    _uid: this.nextUid(),
                    vendor_id: '',
                    vendor_stock_id: '',
                    vendor_name: '',
                    status: 'ready',
                    top: '30',
                    unit_price: 0,
                    is_selected: !!selected,
                };
            },
            emptyItem(brand = '') {
                return {
                    _uid: this.nextUid(),
                    product_name: '',
                    brand: brand || '',
                    quantity: 1,
                    description: '',
                    note: '',
                    unit_price: 0,
                    vendors: [this.emptyVendor(true)],
                    _searchOpen: false,
                    _searchHighlight: -1,
                    _lastPickedName: '',
                };
            },
            emptyGroup(oppProduct) {
                const p = oppProduct || {};
                return {
                    _uid: this.nextUid(),
                    opportunity_product_name: p.name || '',
                    opportunity_quantity: p.quantity || 1,
                    opportunity_brand: p.brand || '',
                    items: [this.emptyItem(p.brand || '')],
                };
            },
            emptyPlanProduct(oppProduct) {
                const p = oppProduct || {};
                return {
                    _uid: this.nextUid(),
                    name: p.name || '',
                    brand: p.brand || '',
                    quantity: p.quantity || 1,
                    items: [],
                };
            },
            hydrateItem(row) {
                const item = Object.assign(this.emptyItem(), {
                    product_name: row.product_name || '',
                    brand: row.brand || '',
                    quantity: row.quantity ?? 1,
                    description: row.description || '',
                    note: row.note || '',
                    unit_price: row.unit_price || 0,
                    _lastPickedName: row.product_name || '',
                    _searchOpen: false,
                    _searchHighlight: -1,
                }, { _uid: this.nextUid() });
                const vendors = Array.isArray(row.vendors) ? row.vendors : [];
                item.vendors = vendors.length
                    ? vendors.map((v, idx) => Object.assign(this.emptyVendor(false), v, {
                        _uid: this.nextUid(),
                        is_selected: v.is_selected === true || v.is_selected === 1 || v.is_selected === '1' || (!vendors.some((x) => x.is_selected) && idx === 0),
                    }))
                    : [this.emptyVendor(true)];
                this.applySelectedPrice(item);
                return item;
            },
            groupsFromProductList() {
                if (!this.opportunityProducts.length) {
                    return [];
                }
                return this.opportunityProducts.map((p) => this.emptyGroup(p));
            },
            availableOppProducts() {
                const used = new Set(
                    (this.groups || [])
                        .map((g) => this.normalizeName(g.opportunity_product_name))
                        .filter(Boolean)
                );
                return (this.opportunityProducts || []).filter(
                    (p) => !used.has(this.normalizeName(p.name))
                );
            },
            addProductGroup(product) {
                if (!product) return;
                const key = this.normalizeName(product.name);
                const exists = (this.groups || []).some(
                    (g) => this.normalizeName(g.opportunity_product_name) === key
                );
                if (exists) return;
                this.groups.push(this.emptyGroup(product));
                this.refreshPoVendorSelects();
            },
            removeProductGroup(index) {
                if (!this.groups || !this.groups.length) return;
                this.destroyPoVendorSelects();
                this.groups.splice(index, 1);
                this.syncPoVendorFromSelected();
                this.refreshPoVendorSelects();
            },
            hydrateGroupsFromFlatItems(rows) {
                const list = rows || [];
                if (!list.length) {
                    return [];
                }
                const hasOppName = list.some((r) => String(r.opportunity_product_name || '').trim() !== '');
                const map = new Map();
                const order = [];
                for (const row of list) {
                    const opp = String(row.opportunity_product_name || '').trim();
                    const name = String(row.product_name || '').trim();
                    const key = hasOppName ? (opp || name || '__empty__') : (name || '__empty__');
                    if (!map.has(key)) {
                        map.set(key, []);
                        order.push(key);
                    }
                    map.get(key).push(row);
                }
                return order.map((key) => {
                    const rowsInGroup = map.get(key) || [];
                    const first = rowsInGroup[0] || {};
                    const oppName = hasOppName
                        ? (String(first.opportunity_product_name || '').trim() || (key === '__empty__' ? '' : key))
                        : (key === '__empty__' ? '' : key);
                    const oppMatch = this.opportunityProducts.find((p) => this.normalizeName(p.name) === this.normalizeName(oppName));
                    const group = this.emptyGroup(oppMatch || { name: oppName, brand: first.brand || '', quantity: 1 });
                    group.opportunity_product_name = oppName;
                    group.items = rowsInGroup.map((row) => this.hydrateItem(row));
                    if (!group.items.length) {
                        group.items = [this.emptyItem(group.opportunity_brand || '')];
                    }
                    return group;
                });
            },
            flatIndex(gIndex, iIndex) {
                let n = 0;
                for (let g = 0; g < gIndex; g++) {
                    n += ((this.groups[g] && this.groups[g].items) || []).length;
                }
                return n + iIndex;
            },
            formAction() {
                if (this.mode === 'edit' && this.editId) {
                    return this.updateBase + '/' + this.editId;
                }
                return this.storeUrl;
            },
            startCreate() {
                this.destroyPoVendorSelects();
                this.mode = 'plan';
                this.editId = null;
                this.number = '';
                this.vendorId = '';
                this.paymentTerm = 'top';
                this.groups = [];
                this.items = [];
                this.planProducts = (this.opportunityProducts || []).map((p) => this.emptyPlanProduct(p));
                this.poDrafts = [];
                this.refreshPoVendorSelects();
            },
            startEdit(po) {
                this.destroyPoVendorSelects();
                this.mode = 'edit';
                this.editId = po.id;
                this.number = po.number || '';
                this.vendorId = po.vendor_id ? String(po.vendor_id) : '';
                this.paymentTerm = po.payment_term || 'top';
                this.groups = this.hydrateGroupsFromFlatItems(po.items && po.items.length ? po.items : []);
                this.items = [];
                this.planProducts = [];
                this.poDrafts = [];
                this.syncPoVendorFromSelected({ preservePaymentTerm: true });
                this.refreshPoVendorSelects();
            },
            cancelForm() {
                this.destroyPoVendorSelects();
                this.mode = null;
                this.editId = null;
                this.number = '';
                this.vendorId = '';
                this.paymentTerm = 'top';
                this.groups = [];
                this.items = [];
                this.planProducts = [];
                this.poDrafts = [];
            },
            addPlanItem(product) {
                if (!product) return;
                product.items.push(this.emptyItem(product.brand || ''));
                this.refreshPoVendorSelects();
                this.touchPlanDrafts();
            },
            removePlanItem(product, index) {
                if (!product || !(product.items || []).length) return;
                this.destroyPoVendorSelects();
                product.items.splice(index, 1);
                this.refreshPoVendorSelects();
                this.touchPlanDrafts();
            },
            selectedQuote(item) {
                return (item.vendors || []).find((v) => v.is_selected) || null;
            },
            productModalTotal(product) {
                return (product.items || []).reduce((sum, item) => {
                    const sel = this.selectedQuote(item);
                    if (!sel || !sel.vendor_id) return sum;
                    const qty = Number(item.quantity) || 0;
                    const price = Number(sel.unit_price) || Number(item.unit_price) || 0;
                    return sum + (qty * price);
                }, 0);
            },
            suggestPoNumber(vendorName, vendorId) {
                const d = new Date();
                const ymd = String(d.getFullYear())
                    + String(d.getMonth() + 1).padStart(2, '0')
                    + String(d.getDate()).padStart(2, '0');
                let slug = String(vendorName || '')
                    .replace(/[^a-zA-Z0-9]+/g, '')
                    .substring(0, 12)
                    .toUpperCase();
                if (!slug) slug = 'VENDOR';
                const suffix = vendorId != null && vendorId !== '' ? String(vendorId) : '';
                return suffix ? ('PO-' + ymd + '-' + slug + '-' + suffix) : ('PO-' + ymd + '-' + slug);
            },
            paymentTermFromVendorTop(top) {
                return String(top || '') === 'cash' ? 'cash' : 'top';
            },
            vendorTopLabel(top) {
                const t = String(top || '');
                const labels = {
                    cash: 'Cash',
                    '7': 'TOP 7 hari',
                    '14': 'TOP 14 hari',
                    '30': 'TOP 30 hari',
                    '45': 'TOP 45 hari',
                    '60': 'TOP 60 hari',
                };
                return labels[t] || (t ? ('TOP ' + t) : 'TOP 30 hari');
            },
            buildPosByVendor() {
                const map = new Map();
                for (const product of (this.planProducts || [])) {
                    for (const item of (product.items || [])) {
                        const name = String(item.product_name || '').trim();
                        if (!name) continue;
                        const sel = this.selectedQuote(item);
                        if (!sel || !sel.vendor_id) continue;
                        const vid = String(sel.vendor_id);
                        if (!map.has(vid)) {
                            const opt = this.vendorOptions.find((v) => String(v.id) === vid);
                            const vendorName = sel.vendor_name || (opt ? opt.name : '') || '';
                            const vendorTop = (opt && opt.top) ? opt.top : (sel.top || '30');
                            map.set(vid, {
                                vendor_id: sel.vendor_id,
                                vendor_name: vendorName,
                                vendor_top: vendorTop,
                                payment_term: this.paymentTermFromVendorTop(vendorTop),
                                number: this.suggestPoNumber(vendorName, sel.vendor_id),
                                items: [],
                            });
                        }
                        map.get(vid).items.push({
                            opportunity_product_name: product.name || '',
                            product_name: name,
                            brand: item.brand || '',
                            quantity: item.quantity,
                            description: item.description || '',
                            note: item.note || '',
                            unit_price: Number(sel.unit_price) || Number(item.unit_price) || 0,
                            vendors: (item.vendors || []).map((v) => ({
                                vendor_id: v.vendor_id,
                                vendor_stock_id: v.vendor_stock_id,
                                vendor_name: v.vendor_name,
                                status: v.status || 'ready',
                                top: v.top || '30',
                                unit_price: v.unit_price,
                                is_selected: !!v.is_selected,
                            })),
                        });
                    }
                }
                return Array.from(map.values());
            },
            refreshPoDrafts() {
                const built = this.buildPosByVendor();
                const prevByVendor = {};
                for (const d of (this.poDrafts || [])) {
                    if (d.vendor_id != null && d.vendor_id !== '') {
                        prevByVendor[String(d.vendor_id)] = {
                            number: d.number,
                            payment_term: d.payment_term,
                        };
                    }
                }
                this.poDrafts = built.map((po) => {
                    const prev = prevByVendor[String(po.vendor_id)] || {};
                    const number = (prev.number != null && String(prev.number).trim() !== '')
                        ? prev.number
                        : po.number;
                    // Pertahankan pilihan user; jika draft baru, default dari TOP vendor.
                    const payment_term = (prev.payment_term === 'cash' || prev.payment_term === 'top')
                        ? prev.payment_term
                        : po.payment_term;
                    return Object.assign({}, po, { number, payment_term });
                });
            },
            touchPlanDrafts() {
                if (this.mode === 'plan') this.refreshPoDrafts();
            },
            draftPoTotal(draft) {
                const isCash = String(draft.payment_term || '') === 'cash';
                const pct = isCash ? Number(this.surchargeCash) || 0 : Number(this.surchargeTop) || 0;
                const rate = pct / 100;
                return (draft.items || []).reduce((sum, item) => {
                    const qty = Number(item.quantity) || 0;
                    const price = Number(item.unit_price) || 0;
                    const line = price + (rate > 0 ? Math.round(price * rate * 100) / 100 : 0);
                    return sum + (qty * line);
                }, 0);
            },
            draftSurchargeLabel(draft) {
                const isCash = String(draft.payment_term || '') === 'cash';
                const p = isCash ? Number(this.surchargeCash) || 0 : Number(this.surchargeTop) || 0;
                if (p <= 0) return '';
                const n = Number.isInteger(p) ? String(p) : String(p);
                return n.replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1') + '%';
            },
            planGrandTotal() {
                return (this.poDrafts || []).reduce((sum, d) => sum + this.draftPoTotal(d), 0);
            },
            canSubmitPlan() {
                const drafts = this.poDrafts || [];
                if (!drafts.length) return false;
                return drafts.every((d) =>
                    d.vendor_id
                    && String(d.number || '').trim() !== ''
                    && (d.payment_term === 'top' || d.payment_term === 'cash')
                );
            },
            poVendorName() {
                if (!this.vendorId) return '';
                const vendor = this.vendorOptions.find((v) => String(v.id) === String(this.vendorId));
                if (vendor) return vendor.name;
                for (const group of (this.groups || [])) {
                    for (const item of (group.items || [])) {
                        const selected = (item.vendors || []).find((v) => v.is_selected);
                        if (selected && String(selected.vendor_id) === String(this.vendorId) && selected.vendor_name) {
                            return selected.vendor_name;
                        }
                    }
                }
                return '';
            },
            syncPoVendorFromSelected(opts = {}) {
                for (const group of (this.groups || [])) {
                    for (const item of (group.items || [])) {
                        const selected = (item.vendors || []).find((v) => v.is_selected);
                        if (selected && selected.vendor_id) {
                            this.setPoVendorId(selected.vendor_id, opts);
                            return;
                        }
                    }
                }
            },
            setPoVendorId(vendorId, opts = {}) {
                const next = vendorId ? String(vendorId) : '';
                const changed = next !== String(this.vendorId || '');
                this.vendorId = next;
                // Saat ganti vendor (edit), default kondisi mengikuti TOP master vendor.
                if (!opts.preservePaymentTerm && changed && next) {
                    const vendor = this.vendorOptions.find((v) => String(v.id) === next);
                    if (vendor) {
                        this.paymentTerm = this.paymentTermFromVendorTop(vendor.top);
                    }
                }
            },
            addItemToGroup(group) {
                group.items.push(this.emptyItem(group.opportunity_brand || ''));
                this.refreshPoVendorSelects();
            },
            removeItemFromGroup(group, index) {
                if ((group.items || []).length <= 1) return;
                this.destroyPoVendorSelects();
                group.items.splice(index, 1);
                this.syncPoVendorFromSelected();
                this.refreshPoVendorSelects();
            },
            normalizeName(name) {
                return String(name || '').trim().toLowerCase();
            },
            getProductCatalog() {
                if (this._catalogCache) return this._catalogCache;
                const map = new Map();
                for (const row of (this.vendorStocks || [])) {
                    const key = this.normalizeName(row.product_name);
                    if (!key) continue;
                    if (!map.has(key)) {
                        map.set(key, {
                            key,
                            name: row.product_name,
                            sku: row.sku || '',
                            minPrice: Number.isFinite(Number(row.price)) ? Number(row.price) : Infinity,
                            maxPrice: Number(row.price) || 0,
                            vendorCount: 0,
                            readyCount: 0,
                        });
                    }
                    const entry = map.get(key);
                    entry.vendorCount += 1;
                    if (String(row.status || '') === 'ready') entry.readyCount += 1;
                    const price = Number(row.price) || 0;
                    if (price < entry.minPrice) entry.minPrice = price;
                    if (price > entry.maxPrice) entry.maxPrice = price;
                    if (!entry.sku && row.sku) entry.sku = row.sku;
                }
                this._catalogCache = Array.from(map.values())
                    .map((p) => ({
                        ...p,
                        minPrice: Number.isFinite(p.minPrice) ? p.minPrice : 0,
                    }))
                    .sort((a, b) => a.name.localeCompare(b.name, 'id'));
                return this._catalogCache;
            },
            searchCatalog(query, limit = 10) {
                const q = this.normalizeName(query);
                const all = this.getProductCatalog();
                if (!q) return all.slice(0, limit);
                const scored = [];
                for (const product of all) {
                    const name = this.normalizeName(product.name);
                    const sku = String(product.sku || '').toLowerCase();
                    let score = 0;
                    if (name === q) score = 100;
                    else if (name.startsWith(q)) score = 80;
                    else if (name.includes(q)) score = 60;
                    else if (sku && sku.includes(q)) score = 40;
                    else continue;
                    scored.push({ product, score });
                }
                scored.sort((a, b) => b.score - a.score || a.product.name.localeCompare(b.product.name, 'id'));
                return scored.slice(0, limit).map((row) => row.product);
            },
            openItemSearch(item) {
                item._searchOpen = true;
                item._searchHighlight = 0;
            },
            closeItemSearch(item) {
                item._searchOpen = false;
                item._searchHighlight = -1;
            },
            moveItemSearchHighlight(item, delta) {
                const results = this.searchCatalog(item.product_name);
                if (!results.length) {
                    item._searchHighlight = -1;
                    return;
                }
                const cur = Number(item._searchHighlight);
                const next = Number.isFinite(cur) && cur >= 0 ? cur + delta : (delta > 0 ? 0 : results.length - 1);
                item._searchHighlight = (next + results.length) % results.length;
            },
            selectHighlightedCatalog(item) {
                const results = this.searchCatalog(item.product_name);
                const idx = Number(item._searchHighlight);
                if (idx >= 0 && results[idx]) {
                    this.selectCatalogProduct(item, results[idx]);
                    return;
                }
                this.confirmNewItemName(item);
            },
            selectCatalogProduct(item, product) {
                item.product_name = product.name;
                item._lastPickedName = '';
                this.closeItemSearch(item);
                this.onItemNamePick(item);
            },
            confirmNewItemName(item) {
                this.closeItemSearch(item);
                this.onItemNamePick(item);
            },
            stocksForProduct(name, sku) {
                const n = this.normalizeName(name);
                const s = String(sku || '').trim().toLowerCase();
                if (!n && !s) return [];
                const exact = this.vendorStocks.filter((row) => this.normalizeName(row.product_name) === n);
                if (exact.length) return exact;
                if (!s) return [];
                return this.vendorStocks.filter((row) => String(row.sku || '').trim().toLowerCase() === s);
            },
            quoteFromStock(row, selected) {
                const vendor = this.vendorOptions.find((v) => String(v.id) === String(row.vendor_id));
                return {
                    _uid: this.nextUid(),
                    vendor_id: row.vendor_id,
                    vendor_stock_id: row.id,
                    vendor_name: row.vendor_name,
                    status: row.status || 'ready',
                    top: vendor ? (vendor.top || '30') : '30',
                    unit_price: Number(row.price) || 0,
                    is_selected: !!selected,
                };
            },
            onItemNamePick(item) {
                const name = String(item.product_name || '').trim();
                const prev = String(item._lastPickedName || '');
                // Jangan overwrite vendor/harga yang sudah diedit jika nama item sama.
                if (name !== '' && this.normalizeName(name) === this.normalizeName(prev)) {
                    return;
                }
                item._lastPickedName = name;
                if (!name) {
                    if (!item.vendors || !item.vendors.length) {
                        item.vendors = [this.emptyVendor(true)];
                    }
                    this.applySelectedPrice(item);
                    this.touchPlanDrafts();
                    return;
                }
                if (!String(item.brand || '').trim()) {
                    const oppMatch = this.opportunityProducts.find((p) => this.normalizeName(p.name) === this.normalizeName(name));
                    if (oppMatch && oppMatch.brand) {
                        item.brand = oppMatch.brand;
                    }
                }
                const stocks = this.stocksForProduct(name);
                if (stocks.length) {
                    item.vendors = stocks.map((row, idx) => this.quoteFromStock(row, idx === 0));
                    this.applySelectedPrice(item);
                    if (this.mode === 'edit') this.syncPoVendorFromSelected();
                    this.refreshPoVendorSelects();
                    this.touchPlanDrafts();
                    return;
                }
                // Nama baru / tidak ada di VendorStock — biarkan free text.
                const hasReal = (item.vendors || []).some((v) => v.vendor_id || String(v.vendor_name || '').trim() || Number(v.unit_price) > 0);
                if (!hasReal) {
                    item.vendors = [this.emptyVendor(true)];
                    this.applySelectedPrice(item);
                    this.refreshPoVendorSelects();
                }
                this.touchPlanDrafts();
            },
            addVendor(item) {
                item.vendors.push(this.emptyVendor(item.vendors.length === 0));
                this.refreshPoVendorSelects();
                this.touchPlanDrafts();
            },
            removeVendor(item, index) {
                if (item.vendors.length <= 1) return;
                this.destroyPoVendorSelects();
                const wasSelected = item.vendors[index].is_selected;
                item.vendors.splice(index, 1);
                if (wasSelected || !item.vendors.some((v) => v.is_selected)) {
                    item.vendors[0].is_selected = true;
                }
                this.applySelectedPrice(item);
                if (this.mode === 'edit') this.syncPoVendorFromSelected();
                this.refreshPoVendorSelects();
                this.touchPlanDrafts();
            },
            selectVendor(item, index) {
                item.vendors.forEach((v, i) => { v.is_selected = i === index; });
                this.applySelectedPrice(item);
                const selected = item.vendors[index];
                if (this.mode === 'edit' && selected && selected.vendor_id) {
                    this.setPoVendorId(selected.vendor_id);
                }
                this.touchPlanDrafts();
            },
            applySelectedPrice(item) {
                const selected = (item.vendors || []).find((v) => v.is_selected) || (item.vendors || [])[0];
                item.unit_price = selected ? (Number(selected.unit_price) || 0) : (Number(item.unit_price) || 0);
            },
            onVendorChange(item, quote) {
                const vendor = this.vendorOptions.find((v) => String(v.id) === String(quote.vendor_id));
                quote.vendor_name = vendor ? vendor.name : '';
                if (vendor) {
                    quote.top = vendor.top || '30';
                }
                const stock = this.vendorStocks.find((row) =>
                    String(row.vendor_id) === String(quote.vendor_id)
                    && this.normalizeName(row.product_name) === this.normalizeName(item.product_name)
                );
                if (stock) {
                    quote.vendor_stock_id = stock.id;
                    quote.status = stock.status || quote.status;
                    quote.unit_price = Number(stock.price) || 0;
                } else {
                    quote.vendor_stock_id = '';
                }
                if (quote.is_selected) {
                    this.applySelectedPrice(item);
                    if (this.mode === 'edit' && quote.vendor_id) this.setPoVendorId(quote.vendor_id);
                }
                this.touchPlanDrafts();
            },
            onQuotePrice(item, quote) {
                if (quote.is_selected) this.applySelectedPrice(item);
                this.touchPlanDrafts();
            },
            findItemByUid(uid) {
                for (const group of (this.groups || [])) {
                    const found = (group.items || []).find((row) => String(row._uid) === String(uid));
                    if (found) return found;
                }
                for (const product of (this.planProducts || [])) {
                    const found = (product.items || []).find((row) => String(row._uid) === String(uid));
                    if (found) return found;
                }
                return null;
            },
            initPoVendorSelect(el, item, quote) {
                const jq = window.jQuery;
                if (!el || !item || !quote || !jq || typeof jq.fn.select2 !== 'function') return;

                el._poItem = item;
                el._poQuote = quote;

                const $el = jq(el);
                if ($el.hasClass('select2-hidden-accessible')) {
                    if (String($el.val() || '') !== String(quote.vendor_id || '')) {
                        $el.val(quote.vendor_id ? String(quote.vendor_id) : '').trigger('change.select2');
                    }
                    return;
                }

                if (window.CrmSelect2) {
                    CrmSelect2.destroy(el);
                }

                $el.select2({
                    width: '100%',
                    placeholder: $el.attr('data-placeholder') || '— Pilih vendor —',
                    allowClear: false,
                    minimumResultsForSearch: 0,
                    dropdownParent: jq(document.body),
                    language: {
                        noResults: () => 'Vendor tidak ditemukan',
                        searching: () => 'Mencari...',
                    },
                });

                $el.off('.crmPoVendor');
                $el.val(quote.vendor_id ? String(quote.vendor_id) : '').trigger('change.select2');
                $el.on('select2:select.crmPoVendor select2:clear.crmPoVendor', () => {
                    quote.vendor_id = $el.val() || '';
                    this.onVendorChange(item, quote);
                });
            },
            destroyPoVendorSelects() {
                const jq = window.jQuery;
                this.$root.querySelectorAll('select[data-po-select2]').forEach((el) => {
                    if (window.CrmSelect2) {
                        CrmSelect2.destroy(el);
                    } else if (jq && jq(el).hasClass('select2-hidden-accessible')) {
                        jq(el).select2('destroy');
                    }
                });
            },
            refreshPoVendorSelects() {
                this.$nextTick(() => {
                    window.setTimeout(() => {
                        this.$root.querySelectorAll('select[data-po-vendor-select]').forEach((el) => {
                            const item = this.findItemByUid(el.getAttribute('data-item-uid'));
                            const quote = item
                                ? (item.vendors || []).find((row) => String(row._uid) === String(el.getAttribute('data-quote-uid')))
                                : null;
                            this.initPoVendorSelect(el, item || el._poItem, quote || el._poQuote);
                        });
                    }, 30);
                });
            },
            selectedVendor(item) {
                return (item.vendors || []).find((v) => v.is_selected) || null;
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
            productTotal(group) {
                return (group.items || []).reduce((sum, item) => sum + this.lineTotal(item), 0);
            },
            grandTotal() {
                return (this.groups || []).reduce((sum, group) => sum + this.productTotal(group), 0);
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
         }"
         x-init="
            if (mode === 'plan') {
                planProducts = (opportunityProducts || []).map((p) => emptyPlanProduct(p));
                poDrafts = [];
            }
            if ((items || []).length) {
                groups = hydrateGroupsFromFlatItems(items);
                syncPoVendorFromSelected();
                refreshPoVendorSelects();
            }
            items = [];
         ">
        <div class="flex items-center justify-between gap-3 px-5 pt-4">
            @unless ($poStandalone ?? false)
                <h3 class="text-sm font-semibold text-slate-800">Purchase Orders</h3>
            @else
                <p class="text-[11px] font-medium uppercase tracking-wider text-slate-400">
                    Hierarki: Produk → PO → Item → Vendor
                </p>
            @endunless
            <div class="flex items-center gap-1">
                @if ($canManagePo)
                <a href="{{ route('vendor-stocks.index') }}"
                   class="inline-flex items-center justify-center rounded-lg bg-slate-50 px-2.5 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-brand-600"
                   title="Ketersediaan vendor">
                    <i class="bi bi-boxes"></i>
                </a>
                @endif
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
                @if ($canManagePo)
                    <button type="button"
                            @click="startCreate()"
                            class="inline-flex items-center gap-1 rounded-lg bg-brand-50 px-3 py-1.5 text-sm font-medium text-brand-600 hover:bg-brand-100"
                            title="Rencana pembelian — item per produk, PO digroup per vendor">
                        <i class="bi bi-plus-lg"></i>
                        <span class="hidden sm:inline">PO</span>
                    </button>
                @endif
            </div>
        </div>

        {{-- Ongkir: 1 opportunity = 1 ongkir --}}
        <div class="mx-5 mt-3 rounded-lg border border-slate-100 bg-slate-50/80 px-3 py-2.5"
             @if ($canManagePo)
             x-data="{
                editing: @js(old('crm_shipping_cost') !== null),
             }"
             @endif>
            @if ($canManagePo)
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
                               class="crm-field w-full max-w-[200px] text-right tabular-nums"
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
            @else
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <span class="shrink-0 text-xs font-semibold uppercase tracking-wider text-slate-400">Ongkir</span>
                <span class="text-sm font-semibold tabular-nums text-slate-800">
                    @if ($opportunity->crm_shipping_cost !== null && (float) $opportunity->crm_shipping_cost > 0)
                        {{ money($opportunity->crm_shipping_cost, $poCurrency) }}
                    @else
                        <span class="font-normal text-slate-400">Belum diisi</span>
                    @endif
                </span>
            </div>
            @endif
            @error('crm_shipping_cost')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        @if ($canManagePo)
        @error('number')
            <div class="mx-5 mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</div>
        @enderror
        @error('vendor_id')
            <div class="mx-5 mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</div>
        @enderror
        @error('payment_term')
            <div class="mx-5 mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</div>
        @enderror
        @error('items')
            <div class="mx-5 mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</div>
        @enderror
        @error('pos')
            <div class="mx-5 mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</div>
        @enderror
        @if ($poItemErrors->isNotEmpty() && ! $errors->has('items') && ! $errors->has('pos'))
            <div class="mx-5 mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ $poItemErrors->first() }}
            </div>
        @endif

        {{-- Form plan (create) / edit --}}
        <div x-show="mode !== null" x-cloak class="border-b border-slate-100 px-5 py-4">
            <form method="POST" :action="formAction()" class="space-y-4"
                  @submit="if (mode === 'plan') { refreshPoDrafts(); if (!canSubmitPlan()) { $event.preventDefault(); } }">
                @csrf
                <input type="hidden" name="_method" value="PUT" :disabled="mode !== 'edit'">
                <input type="hidden" name="_po_id" :value="editId || ''" :disabled="mode !== 'edit'">

                {{-- ========== PLAN MODE ========== --}}
                <div x-show="mode === 'plan'" class="space-y-4">
                    <div class="flex flex-wrap items-center gap-2 text-[11px] font-medium uppercase tracking-wider text-slate-400">
                        <span class="text-brand-600">1. Produk</span>
                        <i class="bi bi-chevron-right text-[10px]"></i>
                        <span :class="planProducts.some(p => (p.items || []).length) ? 'text-brand-600' : ''">2. Item</span>
                        <i class="bi bi-chevron-right text-[10px]"></i>
                        <span :class="poDrafts.length ? 'text-brand-600' : ''">3. Vendor</span>
                        <i class="bi bi-chevron-right text-[10px]"></i>
                        <span :class="poDrafts.length ? 'text-brand-600' : (planProducts.some(p => (p.items || []).length) ? 'text-slate-500' : '')">4. PO per Vendor</span>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Produk opportunity</p>
                        <p class="mt-0.5 text-[11px] text-slate-400">
                            Tambah item di bawah tiap produk, pilih vendor Dipilih. PO digroup otomatis per vendor.
                        </p>
                    </div>

                    <p x-show="!vendorOptions.length" class="text-xs text-amber-700">
                        Belum ada vendor. Superadmin perlu menambah vendor di menu Administration → Vendors sebelum PO bisa dibandingkan.
                    </p>
                    <p x-show="!getProductCatalog().length" class="text-xs text-slate-500">
                        Belum ada produk di ketersediaan vendor — ketik nama item baru, lalu isi vendor & harga. Data akan tersimpan saat Buat semua PO.
                    </p>
                    <p x-show="!opportunityProducts.length" class="rounded-xl border border-dashed border-amber-200 bg-amber-50/50 px-4 py-4 text-sm text-amber-800">
                        Opportunity belum punya produk. Tambahkan produk di deal terlebih dahulu.
                    </p>

                    <template x-for="product in planProducts" :key="product._uid">
                        <div class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/40 p-3 sm:p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 pb-2">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-800">
                                        Produk:
                                        <span x-text="product.name || '—'"></span>
                                    </p>
                                    <p class="mt-0.5 text-xs text-slate-400">
                                        <span x-show="product.brand" x-text="product.brand"></span>
                                        <span x-show="product.brand && product.quantity"> · </span>
                                        <span x-show="product.quantity"
                                              x-text="'Qty opp: ' + formatId(product.quantity, 2)"></span>
                                    </p>
                                </div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <p class="text-sm text-slate-600">
                                        Total Modal:
                                        <span class="font-semibold text-slate-900" x-text="formatMoney(productModalTotal(product))"></span>
                                    </p>
                                    <button type="button" @click="addPlanItem(product)"
                                            class="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700">
                                        <i class="bi bi-plus-lg"></i> Tambah item
                                    </button>
                                </div>
                            </div>

                            <p x-show="!(product.items || []).length" class="text-xs text-slate-400">
                                Belum ada item. Klik “Tambah item” untuk mencari dari ketersediaan vendor.
                            </p>

                            <template x-for="(item, i) in product.items" :key="item._uid">
                                <div class="space-y-2 rounded-lg border border-slate-200 bg-white p-3">
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-12">
                                        <div class="sm:col-span-5">
                                            <label class="crm-label text-xs">Item name</label>
                                            <div class="relative"
                                                 @keydown.escape.stop="closeItemSearch(item)"
                                                 @click.outside="closeItemSearch(item)">
                                                <div class="crm-search">
                                                    <i class="bi bi-search"></i>
                                                    <input type="text"
                                                           x-model="item.product_name"
                                                           autocomplete="off"
                                                           placeholder="Cari produk di ketersediaan vendor..."
                                                           class="crm-field w-full"
                                                           @focus="openItemSearch(item)"
                                                           @input="openItemSearch(item)"
                                                           @blur="touchPlanDrafts()"
                                                           @keydown.arrow-down.prevent="moveItemSearchHighlight(item, 1)"
                                                           @keydown.arrow-up.prevent="moveItemSearchHighlight(item, -1)"
                                                           @keydown.enter.prevent="selectHighlightedCatalog(item)">
                                                </div>

                                                <div x-show="item._searchOpen"
                                                     x-cloak
                                                     class="absolute left-0 right-0 z-40 mt-1 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                                                    <div class="border-b border-slate-100 px-3 py-2 text-[11px] font-medium uppercase tracking-wider text-slate-400">
                                                        <span x-show="String(item.product_name || '').trim()">Hasil pencarian</span>
                                                        <span x-show="!String(item.product_name || '').trim()">Produk di ketersediaan</span>
                                                        <span class="ml-1 font-normal normal-case tracking-normal text-slate-300"
                                                              x-text="'· ' + getProductCatalog().length + ' produk'"></span>
                                                    </div>

                                                    <div class="max-h-64 overflow-y-auto py-1">
                                                        <template x-for="(catalogProduct, pi) in searchCatalog(item.product_name)" :key="catalogProduct.key">
                                                            <button type="button"
                                                                    class="flex w-full items-start gap-3 px-3 py-2.5 text-left transition"
                                                                    :class="Number(item._searchHighlight) === pi ? 'bg-brand-50' : 'hover:bg-slate-50'"
                                                                    @mouseenter="item._searchHighlight = pi"
                                                                    @mousedown.prevent="selectCatalogProduct(item, catalogProduct)">
                                                                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                                                                    <i class="bi bi-box-seam text-sm"></i>
                                                                </span>
                                                                <span class="min-w-0 flex-1">
                                                                    <span class="block truncate text-sm font-medium text-slate-800" x-text="catalogProduct.name"></span>
                                                                    <span class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-slate-500">
                                                                        <span x-text="catalogProduct.vendorCount + ' vendor'"></span>
                                                                        <span class="text-slate-300">·</span>
                                                                        <span class="tabular-nums text-slate-600"
                                                                              x-text="catalogProduct.minPrice === catalogProduct.maxPrice
                                                                                ? formatMoney(catalogProduct.minPrice)
                                                                                : (formatMoney(catalogProduct.minPrice) + ' – ' + formatMoney(catalogProduct.maxPrice))"></span>
                                                                        <span x-show="catalogProduct.readyCount > 0"
                                                                              class="rounded bg-green-50 px-1.5 py-0.5 text-[10px] font-medium text-green-700"
                                                                              x-text="catalogProduct.readyCount + ' ready'"></span>
                                                                        <span x-show="catalogProduct.sku" class="truncate text-slate-400" x-text="'SKU: ' + catalogProduct.sku"></span>
                                                                    </span>
                                                                </span>
                                                                <i class="bi bi-chevron-right mt-1 text-xs text-slate-300"></i>
                                                            </button>
                                                        </template>

                                                        <div x-show="!searchCatalog(item.product_name).length"
                                                             class="px-3 py-4 text-center text-sm text-slate-500">
                                                            <template x-if="String(item.product_name || '').trim()">
                                                                <div class="space-y-2">
                                                                    <p>Tidak ada produk cocok di ketersediaan.</p>
                                                                    <button type="button"
                                                                            class="inline-flex items-center gap-1 rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-700"
                                                                            @mousedown.prevent="confirmNewItemName(item)">
                                                                        <i class="bi bi-plus-lg"></i>
                                                                        Pakai “<span class="max-w-[10rem] truncate" x-text="item.product_name"></span>” sebagai item baru
                                                                    </button>
                                                                </div>
                                                            </template>
                                                            <template x-if="!String(item.product_name || '').trim()">
                                                                <p>Belum ada data ketersediaan vendor.</p>
                                                            </template>
                                                        </div>
                                                    </div>

                                                    <div x-show="String(item.product_name || '').trim() && searchCatalog(item.product_name).length"
                                                         class="border-t border-slate-100 px-3 py-2">
                                                        <button type="button"
                                                                class="text-xs font-medium text-brand-600 hover:text-brand-700"
                                                                @mousedown.prevent="confirmNewItemName(item)">
                                                            <i class="bi bi-plus-lg"></i>
                                                            Tetap pakai nama ini sebagai item baru
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="mt-1 text-[11px] leading-snug text-slate-400">
                                                Ketik untuk mencari produk yang sudah ada. Belum ada? Boleh buat nama baru — tersimpan ke ketersediaan saat Buat semua PO.
                                            </p>
                                        </div>
                                        <div class="sm:col-span-3">
                                            <label class="crm-label text-xs">Brand</label>
                                            <select x-model="item.brand"
                                                    @change="touchPlanDrafts()"
                                                    class="select2 select2-search w-full text-sm"
                                                    data-placeholder="— Brand —"
                                                    data-po-select2>
                                                <option value="">— Brand —</option>
                                                @foreach ($poBrandOptions as $opt)
                                                    <option value="{{ $opt['name'] }}">{{ $opt['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="crm-label text-xs">Qty</label>
                                            <input type="text" inputmode="decimal"
                                                   x-effect="if (editingField !== `pq-${item._uid}`) $el.value = formatId(item.quantity, 2)"
                                                   @focus="editingField = `pq-${item._uid}`"
                                                   @blur="editingField = null; $el.value = formatId(item.quantity, 2); touchPlanDrafts()"
                                                   @input="item.quantity = parseId($event.target.value)"
                                                   class="crm-field w-full text-right tabular-nums">
                                        </div>
                                        <div class="flex items-end justify-between gap-2 sm:col-span-2">
                                            <div class="min-w-0 flex-1">
                                                <label class="crm-label text-xs">Harga modal (vendor terpilih)</label>
                                                <p class="truncate py-2 text-sm font-medium text-slate-700" x-text="formatMoney(modal(item))"></p>
                                            </div>
                                            <button type="button" @click="removePlanItem(product, i)"
                                                    class="mb-1 rounded p-1.5 text-red-500 hover:bg-red-50" title="Hapus item">
                                                <i class="bi bi-trash text-sm"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="rounded-md border border-slate-200 bg-slate-50/60">
                                        <div class="flex items-center justify-between gap-2 px-3 py-2">
                                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Perbandingan vendor</p>
                                            <button type="button" @click="addVendor(item)"
                                                    class="text-xs font-medium text-brand-600 hover:text-brand-700">
                                                <i class="bi bi-plus-lg"></i> Tambah vendor
                                            </button>
                                        </div>
                                        <div class="space-y-2 px-2 pb-2">
                                            <div class="hidden sm:grid sm:grid-cols-12 gap-2 px-1 text-[10px] font-medium uppercase tracking-wider text-slate-400">
                                                <div class="sm:col-span-1">Pakai</div>
                                                <div class="sm:col-span-4">Vendor</div>
                                                <div class="sm:col-span-2">TOP</div>
                                                <div class="sm:col-span-2">Status</div>
                                                <div class="sm:col-span-2 text-right">Harga</div>
                                                <div class="sm:col-span-1"></div>
                                            </div>
                                            <template x-for="(quote, vi) in item.vendors" :key="quote._uid">
                                                <div class="grid grid-cols-1 gap-2 rounded-md border border-slate-200 p-2 sm:grid-cols-12 sm:items-center"
                                                     :class="quote.is_selected ? 'bg-brand-50/80' : 'bg-white'">
                                                    <div class="sm:col-span-1">
                                                        <label class="inline-flex items-center gap-1.5 text-xs text-slate-600">
                                                            <input type="radio"
                                                                   :name="'plan_select_' + item._uid"
                                                                   :checked="quote.is_selected"
                                                                   @change="selectVendor(item, vi)"
                                                                   class="border-slate-300 text-brand-600 focus:ring-brand-500">
                                                            <span x-show="quote.is_selected">Dipilih</span>
                                                        </label>
                                                    </div>
                                                    <div class="sm:col-span-4">
                                                        <select x-model="quote.vendor_id"
                                                                @change="onVendorChange(item, quote)"
                                                                class="select2 select2-search w-full text-xs"
                                                                data-placeholder="— Pilih vendor —"
                                                                data-po-select2
                                                                data-po-vendor-select
                                                                :data-item-uid="item._uid"
                                                                :data-quote-uid="quote._uid">
                                                            <option value="">— Pilih vendor —</option>
                                                            @foreach ($poVendorOptions as $opt)
                                                                <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="sm:col-span-2">
                                                        <select x-model="quote.top"
                                                                class="crm-field w-full py-1 text-xs">
                                                            @foreach (\App\Support\CustomerTop::LABELS as $value => $label)
                                                                <option value="{{ $value }}">{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="sm:col-span-2">
                                                        <select x-model="quote.status"
                                                                class="crm-field w-full py-1 text-xs">
                                                            <option value="ready">Ready</option>
                                                            <option value="indent">Indent</option>
                                                        </select>
                                                    </div>
                                                    <div class="sm:col-span-2">
                                                        <input type="text" inputmode="decimal"
                                                               x-effect="if (editingField !== `pv-${quote._uid}`) $el.value = formatId(quote.unit_price)"
                                                               @focus="editingField = `pv-${quote._uid}`"
                                                               @blur="editingField = null; $el.value = formatId(quote.unit_price)"
                                                               @input="quote.unit_price = parseId($event.target.value); onQuotePrice(item, quote)"
                                                               class="crm-field w-full py-1 text-right text-xs tabular-nums">
                                                    </div>
                                                    <div class="flex justify-end sm:col-span-1">
                                                        <button type="button" @click="removeVendor(item, vi)" x-show="item.vendors.length > 1"
                                                                class="rounded p-1 text-red-500 hover:bg-red-50" title="Hapus vendor">
                                                            <i class="bi bi-trash text-sm"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="crm-label text-xs">Note</label>
                                        <textarea x-model="item.note" rows="2" @change="touchPlanDrafts()"
                                                  placeholder="Catatan" class="crm-field w-full text-sm"></textarea>
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
                                                    <th class="px-2 py-1.5 font-medium text-right">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr class="tabular-nums text-slate-600">
                                                    <td class="px-2 py-1.5 text-right" x-show="hasSurcharge()" x-text="formatMoney(extraExclude(item))"></td>
                                                    <td class="px-2 py-1.5 text-right font-medium text-slate-700" x-text="formatMoney(jumlahExclude(item))"></td>
                                                    <td class="px-2 py-1.5 text-right" x-text="formatMoney(hargaInclude(item))"></td>
                                                    <td class="px-2 py-1.5 text-right" x-show="hasSurcharge()" x-text="formatMoney(extraInclude(item))"></td>
                                                    <td class="px-2 py-1.5 text-right font-medium text-slate-700" x-text="formatMoney(jumlahInclude(item))"></td>
                                                    <td class="px-2 py-1.5 text-right font-medium text-slate-800" x-text="formatMoney(lineTotal(item))"></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Preview PO per vendor + submit fields --}}
                    <div x-show="poDrafts.length" x-cloak class="space-y-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">PO yang akan dibuat (per vendor)</p>
                            <p class="mt-0.5 text-[11px] text-slate-400">
                                Item dari produk berbeda dengan vendor sama digabung ke satu PO. Edit nomor PO bila perlu.
                            </p>
                        </div>

                        <input type="hidden" name="plan_mode" value="1" :disabled="mode !== 'plan'">

                        <template x-for="(draft, di) in poDrafts" :key="'draft-' + draft.vendor_id">
                            <div class="space-y-3 rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <div>
                                        <label class="crm-label">Nomor PO <span class="text-red-500">*</span></label>
                                        <input type="text"
                                               :name="'pos[' + di + '][number]'"
                                               x-model="draft.number"
                                               maxlength="100"
                                               :required="mode === 'plan'"
                                               :disabled="mode !== 'plan'"
                                               placeholder="PO-YYYYMMDD-VENDOR"
                                               class="crm-field w-full">
                                    </div>
                                    <div>
                                        <label class="crm-label">Vendor</label>
                                        <input type="hidden" :name="'pos[' + di + '][vendor_id]'" :value="draft.vendor_id" :disabled="mode !== 'plan'">
                                        <input type="hidden" :name="'pos[' + di + '][vendor_name]'" :value="draft.vendor_name" :disabled="mode !== 'plan'">
                                        <input type="text" readonly
                                               :value="draft.vendor_name"
                                               class="crm-field w-full bg-slate-50 text-slate-700">
                                        <p class="mt-1 text-[11px] text-slate-400"
                                           x-text="'Master TOP: ' + vendorTopLabel(draft.vendor_top)"></p>
                                    </div>
                                    <div>
                                        <label class="crm-label">Kondisi <span class="text-red-500">*</span></label>
                                        <div class="flex flex-wrap gap-3 pt-2">
                                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                                <input type="radio"
                                                       :name="'pos[' + di + '][payment_term]'"
                                                       value="top"
                                                       x-model="draft.payment_term"
                                                       :disabled="mode !== 'plan'"
                                                       class="border-slate-300 text-brand-600 focus:ring-brand-500">
                                                TOP <span class="text-xs text-slate-400" x-text="'(' + (Number(surchargeTop) || 0) + '%)'"></span>
                                            </label>
                                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                                <input type="radio"
                                                       :name="'pos[' + di + '][payment_term]'"
                                                       value="cash"
                                                       x-model="draft.payment_term"
                                                       :disabled="mode !== 'plan'"
                                                       class="border-slate-300 text-brand-600 focus:ring-brand-500">
                                                Cash <span class="text-xs text-slate-400" x-text="'(' + (Number(surchargeCash) || 0) + '%)'"></span>
                                            </label>
                                        </div>
                                        <p class="mt-1 text-[11px] text-slate-400">Default mengikuti TOP vendor; bisa diubah.</p>
                                    </div>
                                </div>

                                <div class="overflow-x-auto rounded-md border border-slate-100">
                                    <table class="w-full min-w-[520px] text-left text-xs">
                                        <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400">
                                            <tr>
                                                <th class="px-3 py-2 font-medium">Item</th>
                                                <th class="px-3 py-2 font-medium">Dari produk</th>
                                                <th class="px-3 py-2 font-medium text-right">Qty</th>
                                                <th class="px-3 py-2 font-medium text-right">Harga</th>
                                                <th class="px-3 py-2 font-medium text-right">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="(row, ri) in draft.items" :key="'ditem-' + di + '-' + ri">
                                                <tr class="border-t border-slate-100 text-slate-700">
                                                    <td class="px-3 py-2 font-medium" x-text="row.product_name"></td>
                                                    <td class="px-3 py-2 text-slate-500" x-text="row.opportunity_product_name"></td>
                                                    <td class="px-3 py-2 text-right tabular-nums" x-text="formatId(row.quantity, 2)"></td>
                                                    <td class="px-3 py-2 text-right tabular-nums" x-text="formatMoney(row.unit_price)"></td>
                                                    <td class="px-3 py-2 text-right tabular-nums font-medium"
                                                        x-text="formatMoney((Number(row.quantity) || 0) * (Number(row.unit_price) || 0))"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>

                                <template x-for="(row, ri) in draft.items" :key="'dfields-' + di + '-' + ri">
                                    <div class="hidden">
                                        <input type="hidden" :name="'pos[' + di + '][items][' + ri + '][opportunity_product_name]'" :value="row.opportunity_product_name" :disabled="mode !== 'plan'">
                                        <input type="hidden" :name="'pos[' + di + '][items][' + ri + '][product_name]'" :value="row.product_name" :disabled="mode !== 'plan'">
                                        <input type="hidden" :name="'pos[' + di + '][items][' + ri + '][brand]'" :value="row.brand || ''" :disabled="mode !== 'plan'">
                                        <input type="hidden" :name="'pos[' + di + '][items][' + ri + '][quantity]'" :value="row.quantity" :disabled="mode !== 'plan'">
                                        <input type="hidden" :name="'pos[' + di + '][items][' + ri + '][note]'" :value="row.note || ''" :disabled="mode !== 'plan'">
                                        <input type="hidden" :name="'pos[' + di + '][items][' + ri + '][description]'" :value="row.description || ''" :disabled="mode !== 'plan'">
                                        <input type="hidden" :name="'pos[' + di + '][items][' + ri + '][unit_price]'" :value="row.unit_price" :disabled="mode !== 'plan'">
                                        <template x-for="(qv, qi) in (row.vendors || [])" :key="'dq-' + di + '-' + ri + '-' + qi">
                                            <div>
                                                <input type="hidden" :name="'pos[' + di + '][items][' + ri + '][vendors][' + qi + '][vendor_id]'" :value="qv.vendor_id || ''" :disabled="mode !== 'plan'">
                                                <input type="hidden" :name="'pos[' + di + '][items][' + ri + '][vendors][' + qi + '][vendor_stock_id]'" :value="qv.vendor_stock_id || ''" :disabled="mode !== 'plan'">
                                                <input type="hidden" :name="'pos[' + di + '][items][' + ri + '][vendors][' + qi + '][vendor_name]'" :value="qv.vendor_name || ''" :disabled="mode !== 'plan'">
                                                <input type="hidden" :name="'pos[' + di + '][items][' + ri + '][vendors][' + qi + '][status]'" :value="qv.status || 'ready'" :disabled="mode !== 'plan'">
                                                <input type="hidden" :name="'pos[' + di + '][items][' + ri + '][vendors][' + qi + '][top]'" :value="qv.top || '30'" :disabled="mode !== 'plan'">
                                                <input type="hidden" :name="'pos[' + di + '][items][' + ri + '][vendors][' + qi + '][unit_price]'" :value="qv.unit_price" :disabled="mode !== 'plan'">
                                                <input type="hidden" :name="'pos[' + di + '][items][' + ri + '][vendors][' + qi + '][is_selected]'" :value="qv.is_selected ? 1 : 0" :disabled="mode !== 'plan'">
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <div class="flex justify-end">
                                    <p class="text-sm text-slate-600">
                                        Total PO:
                                        <span class="font-semibold text-slate-900" x-text="formatMoney(draftPoTotal(draft))"></span>
                                        <span class="ml-1 text-xs text-slate-400"
                                              x-show="draftSurchargeLabel(draft)"
                                              x-text="'(' + (draft.payment_term === 'cash' ? 'Cash' : 'TOP') + ' +' + draftSurchargeLabel(draft) + ')'"></span>
                                    </p>
                                </div>
                            </div>
                        </template>

                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
                            <p class="text-sm text-slate-600">
                                Grand total:
                                <span class="font-semibold text-slate-900" x-text="formatMoney(planGrandTotal())"></span>
                                <span class="ml-1 text-xs text-slate-400" x-text="poDrafts.length + ' PO'"></span>
                            </p>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="cancelForm()" class="text-sm text-slate-500 hover:text-slate-700">Cancel</button>
                                <button type="submit"
                                        :disabled="!canSubmitPlan()"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50">
                                    <i class="bi bi-check-lg"></i>
                                    Buat semua PO
                                </button>
                            </div>
                        </div>
                    </div>

                    <div x-show="!poDrafts.length" class="flex justify-end border-t border-slate-100 pt-3">
                        <button type="button" @click="cancelForm()" class="text-sm text-slate-500 hover:text-slate-700">Cancel</button>
                    </div>
                </div>

                {{-- ========== EDIT MODE ========== --}}
                <div x-show="mode === 'edit'" class="space-y-4">
                    <div class="flex flex-wrap items-center gap-2 text-[11px] font-medium uppercase tracking-wider text-slate-400">
                        <span class="text-brand-600">1. Produk</span>
                        <i class="bi bi-chevron-right text-[10px]"></i>
                        <span class="text-brand-600">2. Item</span>
                        <i class="bi bi-chevron-right text-[10px]"></i>
                        <span class="text-brand-600">3. Vendor</span>
                        <i class="bi bi-chevron-right text-[10px]"></i>
                        <span class="text-brand-600">4. Nomor PO & Kondisi</span>
                    </div>

                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Produk & item</p>
                            <p class="mt-0.5 text-[11px] text-slate-400">
                                Edit item & vendor, lalu simpan nomor PO.
                            </p>
                        </div>
                        <div x-show="availableOppProducts().length" class="relative" x-data="{ open: false }" @click.outside="open = false">
                            <button type="button" @click="open = !open"
                                    class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-brand-600 hover:bg-brand-50">
                                <i class="bi bi-plus-lg"></i> Tambah produk opportunity
                            </button>
                            <div x-show="open" x-cloak
                                 class="absolute right-0 z-30 mt-1 w-72 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                                <p class="border-b border-slate-100 px-3 py-2 text-[11px] font-medium uppercase tracking-wider text-slate-400">
                                    Produk opportunity
                                </p>
                                <div class="max-h-56 overflow-y-auto py-1">
                                    <template x-for="product in availableOppProducts()" :key="product.name">
                                        <button type="button"
                                                class="flex w-full flex-col px-3 py-2 text-left hover:bg-slate-50"
                                                @click="addProductGroup(product); open = false">
                                            <span class="text-sm font-medium text-slate-800" x-text="product.name"></span>
                                            <span class="text-[11px] text-slate-400"
                                                  x-text="(product.brand ? product.brand + ' · ' : '') + 'Qty opp: ' + formatId(product.quantity, 2)"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p x-show="!vendorOptions.length" class="text-xs text-amber-700">
                        Belum ada vendor. Superadmin perlu menambah vendor di menu Administration → Vendors sebelum PO bisa dibandingkan.
                    </p>

                    <template x-for="(group, gi) in groups" :key="group._uid">
                        <div class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/40 p-3 sm:p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 pb-2">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-800">
                                        Produk:
                                        <span x-text="group.opportunity_product_name || '—'"></span>
                                    </p>
                                    <p class="mt-0.5 text-xs text-slate-400"
                                       x-show="group.opportunity_brand || group.opportunity_quantity">
                                        <span x-show="group.opportunity_brand" x-text="group.opportunity_brand"></span>
                                        <span x-show="group.opportunity_brand && group.opportunity_quantity"> · </span>
                                        <span x-show="group.opportunity_quantity"
                                              x-text="'Qty opp: ' + formatId(group.opportunity_quantity, 2)"></span>
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="addItemToGroup(group)"
                                            class="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700">
                                        <i class="bi bi-plus-lg"></i> Tambah item
                                    </button>
                                    <button type="button" @click="removeProductGroup(gi)"
                                            class="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-500"
                                            title="Hapus produk dari PO ini">
                                        <i class="bi bi-trash text-sm"></i>
                                    </button>
                                </div>
                            </div>

                            <template x-for="(item, i) in group.items" :key="item._uid">
                                <div class="space-y-2 rounded-lg border border-slate-200 bg-white p-3">
                                    <input type="hidden"
                                           :name="'items[' + flatIndex(gi, i) + '][opportunity_product_name]'"
                                           :value="group.opportunity_product_name"
                                           :disabled="mode !== 'edit'">

                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-12">
                                        <div class="sm:col-span-5">
                                            <label class="crm-label text-xs">Item name</label>
                                            <div class="relative"
                                                 @keydown.escape.stop="closeItemSearch(item)"
                                                 @click.outside="closeItemSearch(item)">
                                                <div class="crm-search">
                                                    <i class="bi bi-search"></i>
                                                    <input type="text"
                                                           :name="'items[' + flatIndex(gi, i) + '][product_name]'"
                                                           x-model="item.product_name"
                                                           :required="mode === 'edit'"
                                                           :disabled="mode !== 'edit'"
                                                           autocomplete="off"
                                                           placeholder="Cari produk di ketersediaan vendor..."
                                                           class="crm-field w-full"
                                                           @focus="openItemSearch(item)"
                                                           @input="openItemSearch(item)"
                                                           @keydown.arrow-down.prevent="moveItemSearchHighlight(item, 1)"
                                                           @keydown.arrow-up.prevent="moveItemSearchHighlight(item, -1)"
                                                           @keydown.enter.prevent="selectHighlightedCatalog(item)">
                                                </div>

                                                <div x-show="item._searchOpen"
                                                     x-cloak
                                                     class="absolute left-0 right-0 z-40 mt-1 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                                                    <div class="border-b border-slate-100 px-3 py-2 text-[11px] font-medium uppercase tracking-wider text-slate-400">
                                                        <span x-show="String(item.product_name || '').trim()">Hasil pencarian</span>
                                                        <span x-show="!String(item.product_name || '').trim()">Produk di ketersediaan</span>
                                                        <span class="ml-1 font-normal normal-case tracking-normal text-slate-300"
                                                              x-text="'· ' + getProductCatalog().length + ' produk'"></span>
                                                    </div>

                                                    <div class="max-h-64 overflow-y-auto py-1">
                                                        <template x-for="(catalogProduct, pi) in searchCatalog(item.product_name)" :key="catalogProduct.key">
                                                            <button type="button"
                                                                    class="flex w-full items-start gap-3 px-3 py-2.5 text-left transition"
                                                                    :class="Number(item._searchHighlight) === pi ? 'bg-brand-50' : 'hover:bg-slate-50'"
                                                                    @mouseenter="item._searchHighlight = pi"
                                                                    @mousedown.prevent="selectCatalogProduct(item, catalogProduct)">
                                                                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                                                                    <i class="bi bi-box-seam text-sm"></i>
                                                                </span>
                                                                <span class="min-w-0 flex-1">
                                                                    <span class="block truncate text-sm font-medium text-slate-800" x-text="catalogProduct.name"></span>
                                                                    <span class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-slate-500">
                                                                        <span x-text="catalogProduct.vendorCount + ' vendor'"></span>
                                                                        <span class="text-slate-300">·</span>
                                                                        <span class="tabular-nums text-slate-600"
                                                                              x-text="catalogProduct.minPrice === catalogProduct.maxPrice
                                                                                ? formatMoney(catalogProduct.minPrice)
                                                                                : (formatMoney(catalogProduct.minPrice) + ' – ' + formatMoney(catalogProduct.maxPrice))"></span>
                                                                        <span x-show="catalogProduct.readyCount > 0"
                                                                              class="rounded bg-green-50 px-1.5 py-0.5 text-[10px] font-medium text-green-700"
                                                                              x-text="catalogProduct.readyCount + ' ready'"></span>
                                                                        <span x-show="catalogProduct.sku" class="truncate text-slate-400" x-text="'SKU: ' + catalogProduct.sku"></span>
                                                                    </span>
                                                                </span>
                                                                <i class="bi bi-chevron-right mt-1 text-xs text-slate-300"></i>
                                                            </button>
                                                        </template>

                                                        <div x-show="!searchCatalog(item.product_name).length"
                                                             class="px-3 py-4 text-center text-sm text-slate-500">
                                                            <template x-if="String(item.product_name || '').trim()">
                                                                <div class="space-y-2">
                                                                    <p>Tidak ada produk cocok di ketersediaan.</p>
                                                                    <button type="button"
                                                                            class="inline-flex items-center gap-1 rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-700"
                                                                            @mousedown.prevent="confirmNewItemName(item)">
                                                                        <i class="bi bi-plus-lg"></i>
                                                                        Pakai “<span class="max-w-[10rem] truncate" x-text="item.product_name"></span>” sebagai item baru
                                                                    </button>
                                                                </div>
                                                            </template>
                                                            <template x-if="!String(item.product_name || '').trim()">
                                                                <p>Belum ada data ketersediaan vendor.</p>
                                                            </template>
                                                        </div>
                                                    </div>

                                                    <div x-show="String(item.product_name || '').trim() && searchCatalog(item.product_name).length"
                                                         class="border-t border-slate-100 px-3 py-2">
                                                        <button type="button"
                                                                class="text-xs font-medium text-brand-600 hover:text-brand-700"
                                                                @mousedown.prevent="confirmNewItemName(item)">
                                                            <i class="bi bi-plus-lg"></i>
                                                            Tetap pakai nama ini sebagai item baru
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="sm:col-span-3">
                                            <label class="crm-label text-xs">Brand</label>
                                            <select :name="'items[' + flatIndex(gi, i) + '][brand]'"
                                                    x-model="item.brand"
                                                    :disabled="mode !== 'edit'"
                                                    class="select2 select2-search w-full text-sm"
                                                    data-placeholder="— Brand —"
                                                    data-po-select2>
                                                <option value="">— Brand —</option>
                                                @foreach ($poBrandOptions as $opt)
                                                    <option value="{{ $opt['name'] }}">{{ $opt['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="crm-label text-xs">Qty</label>
                                            <input type="text" inputmode="decimal" :required="mode === 'edit'"
                                                   x-effect="if (editingField !== `pq-${item._uid}`) $el.value = formatId(item.quantity, 2)"
                                                   @focus="editingField = `pq-${item._uid}`"
                                                   @blur="editingField = null; $el.value = formatId(item.quantity, 2)"
                                                   @input="item.quantity = parseId($event.target.value)"
                                                   class="crm-field w-full text-right tabular-nums">
                                            <input type="hidden" :name="'items[' + flatIndex(gi, i) + '][quantity]'" :value="item.quantity" :disabled="mode !== 'edit'">
                                        </div>
                                        <div class="flex items-end justify-between gap-2 sm:col-span-2">
                                            <div class="min-w-0 flex-1">
                                                <label class="crm-label text-xs">Harga modal (vendor terpilih)</label>
                                                <p class="truncate py-2 text-sm font-medium text-slate-700" x-text="formatMoney(modal(item))"></p>
                                                <input type="hidden" :name="'items[' + flatIndex(gi, i) + '][unit_price]'" :value="item.unit_price" :disabled="mode !== 'edit'">
                                            </div>
                                            <button type="button" @click="removeItemFromGroup(group, i)" x-show="group.items.length > 1"
                                                    class="mb-1 rounded p-1.5 text-red-500 hover:bg-red-50" title="Hapus item">
                                                <i class="bi bi-trash text-sm"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="rounded-md border border-slate-200 bg-slate-50/60">
                                        <div class="flex items-center justify-between gap-2 px-3 py-2">
                                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Perbandingan vendor</p>
                                            <button type="button" @click="addVendor(item)"
                                                    class="text-xs font-medium text-brand-600 hover:text-brand-700">
                                                <i class="bi bi-plus-lg"></i> Tambah vendor
                                            </button>
                                        </div>
                                        <div class="space-y-2 px-2 pb-2">
                                            <div class="hidden sm:grid sm:grid-cols-12 gap-2 px-1 text-[10px] font-medium uppercase tracking-wider text-slate-400">
                                                <div class="sm:col-span-1">Pakai</div>
                                                <div class="sm:col-span-4">Vendor</div>
                                                <div class="sm:col-span-2">TOP</div>
                                                <div class="sm:col-span-2">Status</div>
                                                <div class="sm:col-span-2 text-right">Harga</div>
                                                <div class="sm:col-span-1"></div>
                                            </div>
                                            <template x-for="(quote, vi) in item.vendors" :key="quote._uid">
                                                <div class="grid grid-cols-1 gap-2 rounded-md border border-slate-200 p-2 sm:grid-cols-12 sm:items-center"
                                                     :class="quote.is_selected ? 'bg-brand-50/80' : 'bg-white'">
                                                    <div class="sm:col-span-1">
                                                        <label class="inline-flex items-center gap-1.5 text-xs text-slate-600">
                                                            <input type="radio"
                                                                   :name="'po_select_' + item._uid"
                                                                   :checked="quote.is_selected"
                                                                   @change="selectVendor(item, vi)"
                                                                   class="border-slate-300 text-brand-600 focus:ring-brand-500">
                                                            <span x-show="quote.is_selected">Dipilih</span>
                                                        </label>
                                                        <input type="hidden" :name="'items[' + flatIndex(gi, i) + '][vendors][' + vi + '][is_selected]'" :value="quote.is_selected ? 1 : 0" :disabled="mode !== 'edit'">
                                                        <input type="hidden" :name="'items[' + flatIndex(gi, i) + '][vendors][' + vi + '][vendor_stock_id]'" :value="quote.vendor_stock_id || ''" :disabled="mode !== 'edit'">
                                                        <input type="hidden" :name="'items[' + flatIndex(gi, i) + '][vendors][' + vi + '][vendor_name]'" :value="quote.vendor_name" :disabled="mode !== 'edit'">
                                                    </div>
                                                    <div class="sm:col-span-4">
                                                        <select :name="'items[' + flatIndex(gi, i) + '][vendors][' + vi + '][vendor_id]'"
                                                                x-model="quote.vendor_id"
                                                                @change="onVendorChange(item, quote)"
                                                                :disabled="mode !== 'edit'"
                                                                class="select2 select2-search w-full text-xs"
                                                                data-placeholder="— Pilih vendor —"
                                                                data-po-select2
                                                                data-po-vendor-select
                                                                :data-item-uid="item._uid"
                                                                :data-quote-uid="quote._uid">
                                                            <option value="">— Pilih vendor —</option>
                                                            @foreach ($poVendorOptions as $opt)
                                                                <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="sm:col-span-2">
                                                        <select :name="'items[' + flatIndex(gi, i) + '][vendors][' + vi + '][top]'"
                                                                x-model="quote.top"
                                                                :disabled="mode !== 'edit'"
                                                                class="crm-field w-full py-1 text-xs">
                                                            @foreach (\App\Support\CustomerTop::LABELS as $value => $label)
                                                                <option value="{{ $value }}">{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="sm:col-span-2">
                                                        <select :name="'items[' + flatIndex(gi, i) + '][vendors][' + vi + '][status]'"
                                                                x-model="quote.status"
                                                                :disabled="mode !== 'edit'"
                                                                class="crm-field w-full py-1 text-xs">
                                                            <option value="ready">Ready</option>
                                                            <option value="indent">Indent</option>
                                                        </select>
                                                    </div>
                                                    <div class="sm:col-span-2">
                                                        <input type="text" inputmode="decimal" :required="mode === 'edit'"
                                                               x-effect="if (editingField !== `pv-${quote._uid}`) $el.value = formatId(quote.unit_price)"
                                                               @focus="editingField = `pv-${quote._uid}`"
                                                               @blur="editingField = null; $el.value = formatId(quote.unit_price)"
                                                               @input="quote.unit_price = parseId($event.target.value); onQuotePrice(item, quote)"
                                                               class="crm-field w-full py-1 text-right text-xs tabular-nums">
                                                        <input type="hidden" :name="'items[' + flatIndex(gi, i) + '][vendors][' + vi + '][unit_price]'" :value="quote.unit_price" :disabled="mode !== 'edit'">
                                                    </div>
                                                    <div class="flex justify-end sm:col-span-1">
                                                        <button type="button" @click="removeVendor(item, vi)" x-show="item.vendors.length > 1"
                                                                class="rounded p-1 text-red-500 hover:bg-red-50" title="Hapus vendor">
                                                            <i class="bi bi-trash text-sm"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="crm-label text-xs">Note</label>
                                        <textarea :name="'items[' + flatIndex(gi, i) + '][note]'" x-model="item.note" rows="2"
                                                  :disabled="mode !== 'edit'"
                                                  placeholder="Catatan" class="crm-field w-full text-sm"></textarea>
                                        <input type="hidden" :name="'items[' + flatIndex(gi, i) + '][description]'" value="" :disabled="mode !== 'edit'">
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
                                                    <th class="px-2 py-1.5 font-medium text-right">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr class="tabular-nums text-slate-600">
                                                    <td class="px-2 py-1.5 text-right" x-show="hasSurcharge()" x-text="formatMoney(extraExclude(item))"></td>
                                                    <td class="px-2 py-1.5 text-right font-medium text-slate-700" x-text="formatMoney(jumlahExclude(item))"></td>
                                                    <td class="px-2 py-1.5 text-right" x-text="formatMoney(hargaInclude(item))"></td>
                                                    <td class="px-2 py-1.5 text-right" x-show="hasSurcharge()" x-text="formatMoney(extraInclude(item))"></td>
                                                    <td class="px-2 py-1.5 text-right font-medium text-slate-700" x-text="formatMoney(jumlahInclude(item))"></td>
                                                    <td class="px-2 py-1.5 text-right font-medium text-slate-800" x-text="formatMoney(lineTotal(item))"></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </template>

                            <div class="flex items-center justify-end border-t border-slate-200/80 pt-2">
                                <p class="text-sm text-slate-600">
                                    Total produk:
                                    <span class="font-semibold text-slate-900" x-text="formatMoney(productTotal(group))"></span>
                                </p>
                            </div>
                        </div>
                    </template>

                    <div class="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">4. Nomor PO & Kondisi</p>
                            <p class="mt-0.5 text-[11px] text-slate-400">
                                Isi nomor PO dan kondisi bayar. Vendor terisi otomatis dari yang ditandai Dipilih.
                            </p>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div>
                                <label class="crm-label">Nomor PO <span class="text-red-500">*</span></label>
                                <input type="text" name="number" x-model="number" maxlength="100"
                                       :required="mode === 'edit'"
                                       :disabled="mode !== 'edit'"
                                       placeholder="Contoh: PO-2026-001"
                                       class="crm-field w-full">
                            </div>
                            <div>
                                <label class="crm-label">Vendor PO <span class="text-red-500">*</span></label>
                                <input type="hidden" name="vendor_id" :value="vendorId" :disabled="mode !== 'edit'">
                                <input type="text" readonly
                                       :value="poVendorName()"
                                       placeholder="Otomatis dari vendor yang dipilih"
                                       class="crm-field w-full bg-slate-50 text-slate-700">
                                <p class="mt-1 text-xs text-slate-400">Dari vendor yang ditandai Dipilih pada item.</p>
                            </div>
                            <div>
                                <label class="crm-label">Kondisi</label>
                                <div class="flex gap-4 pt-2">
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                        <input type="radio" name="payment_term" value="top" x-model="paymentTerm"
                                               :disabled="mode !== 'edit'"
                                               class="border-slate-300 text-brand-600 focus:ring-brand-500">
                                        TOP <span class="text-xs text-slate-400" x-text="'(' + (Number(surchargeTop) || 0) + '%)'"></span>
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                        <input type="radio" name="payment_term" value="cash" x-model="paymentTerm"
                                               :disabled="mode !== 'edit'"
                                               class="border-slate-300 text-brand-600 focus:ring-brand-500">
                                        Cash <span class="text-xs text-slate-400" x-text="'(' + (Number(surchargeCash) || 0) + '%)'"></span>
                                    </label>
                                </div>
                                <p class="mt-1 text-[11px] text-slate-400">Default mengikuti TOP vendor; bisa diubah.</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3"
                         x-show="groups.length">
                        <p class="text-sm text-slate-600">
                            Total PO:
                            <span class="font-semibold text-slate-900" x-text="formatMoney(grandTotal())"></span>
                            <span class="ml-1 text-xs text-slate-400" x-show="hasSurcharge()" x-text="'(' + (isCash() ? 'Cash' : 'TOP') + ' +' + surchargeLabel() + ')'"></span>
                        </p>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="cancelForm()" class="text-sm text-slate-500 hover:text-slate-700">Cancel</button>
                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
                                <i class="bi bi-check-lg"></i>
                                Update PO
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        @endif

        @if ($poList->count())
            <div class="mt-2 space-y-3 px-5 pb-4">
                @unless ($poStandalone ?? false)
                <p class="text-[11px] font-medium uppercase tracking-wider text-slate-400">
                    Hierarki: Produk → PO → Item → Vendor
                </p>
                @endunless

                @foreach ($poTreeByProduct as $productName => $productNode)
                    @php
                        $productKey = 'prod-'.md5((string) $productName);
                        $productPoCount = count($productNode['pos']);
                    @endphp
                    <div class="overflow-hidden rounded-xl border border-slate-200/80">
                        <button type="button"
                                @click="openProduct = openProduct === @js($productKey) ? null : @js($productKey)"
                                class="flex w-full items-start gap-2 bg-slate-50/80 px-4 py-3 text-left hover:bg-slate-50">
                            <span class="mt-0.5 shrink-0 rounded p-0.5 text-slate-400">
                                <i class="bi" :class="openProduct === @js($productKey) ? 'bi-chevron-down' : 'bi-chevron-right'"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                    <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Produk</span>
                                    <span class="text-sm font-semibold text-slate-800">{{ $productNode['product_name'] }}</span>
                                </div>
                                <p class="mt-0.5 text-xs text-slate-400">
                                    {{ $productPoCount }} PO
                                    &middot;
                                    {{ (int) $productNode['item_count'] }} item
                                    &middot;
                                    Modal {{ money($productNode['total_modal'], $poCurrency) }}
                                </p>
                            </div>
                        </button>

                        <div x-show="openProduct === @js($productKey)" x-cloak class="space-y-2 border-t border-slate-100 bg-white p-3">
                            @foreach ($productNode['pos'] as $poNode)
                                @php
                                    $po = $poNode['po'];
                                    $poItems = $poNode['items'];
                                    $poEditPayload = $poNode['edit_payload'];
                                    $poKey = 'po-'.$po->id.'-'.md5((string) $productName);
                                    $poIsCash = $po->isCash();
                                    $poCurrencyCode = $po->currency ?: $poCurrency;
                                @endphp
                                <div class="overflow-hidden rounded-lg border border-slate-200"
                                     x-show="!(mode === 'edit' && Number(editId) === {{ (int) $po->id }})">
                                    <div class="flex items-start gap-2 px-3 py-2.5">
                                        <button type="button"
                                                @click="openId = openId === @js($poKey) ? null : @js($poKey)"
                                                class="mt-0.5 shrink-0 rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                                            <i class="bi" :class="openId === @js($poKey) ? 'bi-chevron-down' : 'bi-chevron-right'"></i>
                                        </button>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                                <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">PO</span>
                                                <span class="text-sm font-semibold text-slate-800">{{ $po->number }}</span>
                                                <span class="text-sm font-medium text-slate-700">{{ $po->displayVendorName() }}</span>
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide
                                                    {{ $poIsCash ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600' }}">
                                                    {{ $po->paymentTermLabel() }}
                                                </span>
                                                <span class="text-sm text-slate-500">{{ money($poNode['modal_total'], $poCurrencyCode) }}</span>
                                                <span class="text-xs text-slate-400">{{ $poItems->count() }} item</span>
                                            </div>
                                            <p class="mt-0.5 text-xs text-slate-400">
                                                {{ optional($po->creator)->display_name ?: '—' }}
                                                &middot;
                                                {{ $po->created_at?->translatedFormat('d M Y H:i') }}
                                            </p>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-1">
                                            <button type="button"
                                                    @click="previewPoId = {{ (int) $po->id }}"
                                                    class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-brand-600"
                                                    title="Preview perbandingan vendor">
                                                <i class="bi bi-eye text-sm"></i>
                                            </button>
                                            @if ($canManagePo)
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
                                            @endif
                                        </div>
                                    </div>

                                    <div x-show="openId === @js($poKey)" x-cloak class="space-y-2 border-t border-slate-100 bg-slate-50/50 p-3">
                                        @foreach ($poItems as $item)
                                            @php
                                                $selectedQuote = $item->selectedVendorQuote();
                                                $itemModal = $selectedQuote
                                                    ? (float) $selectedQuote->unit_price
                                                    : (float) $item->unit_price;
                                            @endphp
                                            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                                                <div class="border-b border-slate-100 px-3 py-2.5">
                                                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                                        <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Item</span>
                                                        <span class="text-sm font-medium text-slate-800">{{ $item->product_name }}</span>
                                                    </div>
                                                    @if ($item->brand)
                                                        <p class="mt-0.5 text-xs text-slate-500">{{ $item->brand }}</p>
                                                    @endif
                                                    @if ($item->description)
                                                        <p class="mt-0.5 whitespace-pre-line text-xs text-slate-500">{{ $item->description }}</p>
                                                    @endif
                                                    @if ($item->note)
                                                        <p class="mt-0.5 whitespace-pre-line text-xs italic text-slate-400">{{ $item->note }}</p>
                                                    @endif
                                                    <p class="mt-1 text-xs text-slate-400">
                                                        Qty {{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }}
                                                        · Modal {{ money($itemModal, $poCurrencyCode) }}
                                                        · Subtotal {{ money($item->line_total, $poCurrencyCode) }}
                                                    </p>
                                                </div>
                                                <div class="overflow-x-auto">
                                                    <table class="w-full min-w-[480px] text-left text-sm">
                                                        <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-400">
                                                            <tr>
                                                                <th class="px-3 py-2 font-medium">Vendor</th>
                                                                <th class="px-3 py-2 font-medium">TOP</th>
                                                                <th class="px-3 py-2 font-medium">Status</th>
                                                                <th class="px-3 py-2 font-medium text-right">Harga</th>
                                                                <th class="px-3 py-2 font-medium text-center">Pakai</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-slate-50">
                                                            @forelse ($item->vendorQuotes as $quote)
                                                                <tr class="{{ $quote->is_selected ? 'bg-brand-50/60' : '' }}">
                                                                    <td class="px-3 py-2 {{ $quote->is_selected ? 'font-medium text-slate-800' : 'text-slate-500' }}">
                                                                        {{ $quote->displayVendorName() }}
                                                                    </td>
                                                                    <td class="px-3 py-2 {{ $quote->is_selected ? 'text-slate-700' : 'text-slate-500' }}">
                                                                        {{ $quote->topLabel() }}
                                                                    </td>
                                                                    <td class="px-3 py-2">
                                                                        <x-badge :color="$quote->isReady() ? 'green' : 'amber'">{{ $quote->statusLabel() }}</x-badge>
                                                                    </td>
                                                                    <td class="px-3 py-2 text-right tabular-nums {{ $quote->is_selected ? 'font-medium text-slate-800' : 'text-slate-500' }}">
                                                                        {{ money($quote->unit_price, $poCurrencyCode) }}
                                                                    </td>
                                                                    <td class="px-3 py-2 text-center">
                                                                        @if ($quote->is_selected)
                                                                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-brand-700">
                                                                                <i class="bi bi-check-circle-fill"></i> Dipilih
                                                                            </span>
                                                                        @else
                                                                            <span class="text-xs text-slate-400">—</span>
                                                                        @endif
                                                                    </td>
                                                                </tr>
                                                            @empty
                                                                <tr>
                                                                    <td colspan="5" class="px-3 py-3 text-center text-sm text-slate-400">Belum ada perbandingan vendor</td>
                                                                </tr>
                                                            @endforelse
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Daftar flat: satu baris per PO (per vendor) --}}
            <div class="mt-4 border-t border-slate-100 px-5 pb-5 pt-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-[11px] font-medium uppercase tracking-wider text-slate-400">
                        Semua PO · {{ $poList->count() }} vendor
                    </p>
                    <p class="text-sm font-semibold tabular-nums text-slate-800">
                        Total {{ money((float) $poList->sum('total'), $poCurrency) }}
                    </p>
                </div>
                <div class="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200/80 bg-white">
                    @foreach ($poList->sortBy('number') as $po)
                        @php
                            $poFlatPayload = $poPayloadById[$po->id] ?? null;
                            $poFlatIsCash = $po->isCash();
                            $poFlatCurrency = $po->currency ?: $poCurrency;
                            $poFlatItemCount = $po->items->count();
                        @endphp
                        <div class="flex items-start gap-2 px-4 py-3 hover:bg-slate-50/60"
                             x-show="!(mode === 'edit' && Number(editId) === {{ (int) $po->id }})">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                    <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">PO</span>
                                    <span class="text-sm font-semibold text-slate-800">{{ $po->number }}</span>
                                    <span class="text-sm font-medium text-slate-700">{{ $po->displayVendorName() }}</span>
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                                        'bg-amber-50 text-amber-700' => $poFlatIsCash,
                                        'bg-slate-100 text-slate-600' => ! $poFlatIsCash,
                                    ])>{{ $po->paymentTermLabel() }}</span>
                                    <span class="text-sm font-semibold tabular-nums text-slate-800">{{ money((float) $po->total, $poFlatCurrency) }}</span>
                                    <span class="text-xs text-slate-400">{{ $poFlatItemCount }} item</span>
                                </div>
                                <p class="mt-0.5 text-xs text-slate-400">
                                    {{ optional($po->creator)->display_name ?: '—' }}
                                    &middot;
                                    {{ $po->created_at?->translatedFormat('d M Y H:i') }}
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <button type="button"
                                        @click="previewPoId = {{ (int) $po->id }}"
                                        class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-brand-600"
                                        title="Preview perbandingan vendor">
                                    <i class="bi bi-eye text-sm"></i>
                                </button>
                                @if ($canManagePo && $poFlatPayload)
                                    <button type="button"
                                            @click="startEdit(@js($poFlatPayload))"
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
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            @foreach ($poList as $po)
                <div x-show="previewPoId === {{ (int) $po->id }}"
                     x-cloak
                     class="fixed inset-0 z-[70] flex items-center justify-center p-4"
                     role="dialog"
                     aria-modal="true"
                     @keydown.escape.window="if (previewPoId === {{ (int) $po->id }}) previewPoId = null">
                    <div class="absolute inset-0 bg-slate-900/50" @click="previewPoId = null"></div>
                    <div class="relative z-10 flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl bg-white shadow-xl">
                        <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                            <div>
                                <h3 class="text-base font-semibold text-slate-800">Perbandingan vendor</h3>
                                <p class="mt-0.5 text-sm text-slate-500">
                                    {{ $po->number }}
                                    · {{ $po->displayVendorName() }}
                                    · {{ $po->paymentTermLabel() }}
                                </p>
                            </div>
                            <button type="button" @click="previewPoId = null"
                                    class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                                    title="Tutup">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                            @php
                                $previewGrouped = $po->items->groupBy(fn ($i) => trim((string) ($i->opportunity_product_name ?? '')) !== ''
                                    ? (string) $i->opportunity_product_name
                                    : '__flat__');
                                $previewHasOppGroups = $previewGrouped->keys()->contains(fn ($k) => $k !== '__flat__');
                            @endphp
                            @forelse ($previewGrouped as $groupName => $groupItems)
                                @if ($previewHasOppGroups && $groupName !== '__flat__')
                                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">
                                        Produk: {{ $groupName }}
                                    </p>
                                @endif
                                @foreach ($groupItems as $item)
                                    @php
                                        $selectedQuote = $item->selectedVendorQuote();
                                    @endphp
                                    <div class="rounded-lg border border-slate-200">
                                        <div class="border-b border-slate-100 bg-slate-50 px-4 py-2.5">
                                            <p class="font-medium text-slate-800">{{ $item->product_name }}</p>
                                            @if ($item->brand)
                                                <p class="text-xs text-slate-500">{{ $item->brand }}</p>
                                            @endif
                                            <p class="mt-0.5 text-xs text-slate-400">
                                                Qty {{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }}
                                                · Modal terpilih {{ money((float) ($selectedQuote?->unit_price ?? $item->unit_price), $po->currency ?: $poCurrency) }}
                                            </p>
                                        </div>
                                        <div class="overflow-x-auto">
                                            <table class="w-full min-w-[520px] text-left text-sm">
                                                <thead class="text-xs uppercase tracking-wider text-slate-400">
                                                    <tr>
                                                        <th class="px-4 py-2 font-medium">Vendor</th>
                                                        <th class="px-4 py-2 font-medium">TOP</th>
                                                        <th class="px-4 py-2 font-medium">Status</th>
                                                        <th class="px-4 py-2 font-medium text-right">Harga</th>
                                                        <th class="px-4 py-2 font-medium text-center">Pakai</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-50">
                                                    @forelse ($item->vendorQuotes as $quote)
                                                        <tr class="{{ $quote->is_selected ? 'bg-brand-50/60' : '' }}">
                                                            <td class="px-4 py-2.5 {{ $quote->is_selected ? 'font-medium text-slate-800' : 'text-slate-500' }}">
                                                                {{ $quote->displayVendorName() }}
                                                            </td>
                                                            <td class="px-4 py-2.5 {{ $quote->is_selected ? 'text-slate-700' : 'text-slate-500' }}">
                                                                {{ $quote->topLabel() }}
                                                            </td>
                                                            <td class="px-4 py-2.5">
                                                                <x-badge :color="$quote->isReady() ? 'green' : 'amber'">{{ $quote->statusLabel() }}</x-badge>
                                                            </td>
                                                            <td class="px-4 py-2.5 text-right tabular-nums {{ $quote->is_selected ? 'font-medium text-slate-800' : 'text-slate-500' }}">
                                                                {{ money($quote->unit_price, $po->currency ?: $poCurrency) }}
                                                            </td>
                                                            <td class="px-4 py-2.5 text-center">
                                                                @if ($quote->is_selected)
                                                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-brand-700">
                                                                        <i class="bi bi-check-circle-fill"></i> Dipilih
                                                                    </span>
                                                                @else
                                                                    <span class="text-xs text-slate-400">—</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="5" class="px-4 py-3 text-center text-sm text-slate-400">Belum ada perbandingan vendor</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endforeach
                            @empty
                                <p class="py-8 text-center text-sm text-slate-400">Tidak ada item pada PO ini.</p>
                            @endforelse
                        </div>
                        <div class="flex justify-end border-t border-slate-100 px-5 py-3">
                            <button type="button" @click="previewPoId = null"
                                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        @else
            <div x-show="mode === null" class="px-5 py-8 text-center text-sm text-slate-400">
                @if ($canManagePo)
                    Belum ada Purchase Order. Klik <i class="bi bi-plus-lg"></i> untuk membuat rencana pembelian (PO per vendor).
                @else
                    Belum ada Purchase Order.
                @endif
            </div>
        @endif
    </div>
@endif
