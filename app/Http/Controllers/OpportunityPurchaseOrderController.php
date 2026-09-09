<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Espo\Opportunity;
use App\Models\PurchaseOrder;
use App\Models\VendorStock;
use App\Support\CustomerTop;
use App\Support\OpportunityProductPricing;
use App\Services\PurchaseOrderReportService;
use App\Services\PurchaseOrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OpportunityPurchaseOrderController extends Controller
{
    use ScopesToUser;

    public function __construct(
        protected PurchaseOrderService $purchaseOrders,
        protected PurchaseOrderReportService $reports
    ) {}

    public function index(Request $request, Opportunity $opportunity)
    {
        $this->authorizeView($opportunity);

        $opportunity->load([
            'account',
            'purchaseOrders.creator',
            'purchaseOrders.vendor',
            'purchaseOrders.items.vendorQuotes.vendor',
        ]);

        $backUrl = null;
        $fromSo = $request->query('from_so');
        if ($fromSo !== null && $fromSo !== '' && ctype_digit((string) $fromSo)) {
            $salesOrder = $opportunity->salesOrders()->whereKey((int) $fromSo)->first();
            if ($salesOrder) {
                $backUrl = route('opportunities.sales-orders.show', [$opportunity, $salesOrder]);
            }
        }

        return view('opportunities.purchase-orders.index', [
            'opportunity' => $opportunity,
            'backUrl' => $backUrl,
            'poStandalone' => true,
        ]);
    }

    public function preview(Opportunity $opportunity)
    {
        $this->authorizeView($opportunity);

        $report = $this->reports->build($opportunity);

        return view('opportunities.purchase-orders.preview', compact('opportunity', 'report'));
    }

    public function pdf(Opportunity $opportunity)
    {
        $this->authorizeView($opportunity);

        $report = $this->reports->build($opportunity);
        $filename = 'Laporan-PO-'.str_replace(['/', '\\', ' '], '-', $opportunity->name ?: $opportunity->id).'.pdf';

        return Pdf::loadView('opportunities.purchase-orders.pdf', compact('opportunity', 'report'))
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    public function store(Request $request, Opportunity $opportunity)
    {
        $this->authorizeManage($opportunity);

        // Mode rencana: banyak PO digroup per vendor.
        if ($request->boolean('plan_mode') || $request->has('pos')) {
            $data = $this->validatePlanPayload($request);
            $created = $this->purchaseOrders->createMany(
                $opportunity,
                $data['pos'],
                auth()->id()
            );

            $count = count($created);
            $numbers = collect($created)->pluck('number')->implode(', ');

            return back()->with(
                'success',
                "Berhasil membuat {$count} PO per vendor ({$numbers}). Total modal per produk di-mirror ke opportunity."
            );
        }

        $data = $this->validatePayload($request);

        $this->purchaseOrders->create(
            $opportunity,
            $data['number'],
            $data['items'],
            $data['payment_term'],
            auth()->id(),
            $data['vendor_id'],
            $data['vendor_name'] ?? null
        );

        return back()->with('success', 'Purchase Order berhasil ditambahkan. Total per produk di-mirror ke modal opportunity & ketersediaan vendor diperbarui.');
    }

    public function update(Request $request, Opportunity $opportunity, PurchaseOrder $purchaseOrder)
    {
        $this->authorizeManage($opportunity);
        $this->ensureBelongsToOpportunity($opportunity, $purchaseOrder);

        $data = $this->validatePayload($request, $purchaseOrder);

        $this->purchaseOrders->update(
            $purchaseOrder,
            $data['number'],
            $data['items'],
            $data['payment_term'],
            auth()->id(),
            $data['vendor_id'],
            $data['vendor_name'] ?? null
        );

        return back()->with('success', 'Purchase Order berhasil diperbarui. Total per produk di-mirror ke modal opportunity & ketersediaan vendor diperbarui.');
    }

    public function destroy(Opportunity $opportunity, PurchaseOrder $purchaseOrder)
    {
        $this->authorizeManage($opportunity);
        $this->ensureBelongsToOpportunity($opportunity, $purchaseOrder);

        $purchaseOrder->delete();
        $this->purchaseOrders->syncOpportunityCostsFromPo($opportunity);

        return back()->with('success', 'Purchase Order berhasil dihapus. Modal opportunity dihitung ulang dari PO yang tersisa.');
    }

    /**
     * Simpan ongkir opportunity (1 opp = 1 ongkir), dari area PO.
     */
    public function updateShipping(Request $request, Opportunity $opportunity)
    {
        $this->authorizeManage($opportunity);

        $data = $request->validate([
            'crm_shipping_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $raw = $data['crm_shipping_cost'] ?? null;
        $opportunity->crm_shipping_cost = ($raw === null || $raw === '')
            ? null
            : round((float) $raw, 2);
        $opportunity->save();

        return back()->with('success', 'Ongkir berhasil disimpan.');
    }

    /**
     * Validasi rencana pembelian → banyak PO (1 vendor = 1 PO).
     * Kondisi TOP/Cash per PO (default dari master vendor).
     *
     * @return array{pos:list<array{number:string,vendor_id:int,vendor_name:?string,payment_term:string,items:list<array<string,mixed>>}>}
     */
    protected function validatePlanPayload(Request $request): array
    {
        $data = $request->validate([
            'pos' => ['required', 'array', 'min:1'],
            'pos.*.number' => [
                'required',
                'string',
                'max:100',
                'distinct',
                Rule::unique('crm_purchase_orders', 'number'),
                $this->poNumberSequenceRule(),
            ],
            'pos.*.vendor_id' => ['required', 'integer', 'distinct', 'exists:crm_vendors,id'],
            'pos.*.vendor_name' => ['nullable', 'string', 'max:255'],
            'pos.*.payment_term' => ['required', Rule::in([PurchaseOrder::PAYMENT_TOP, PurchaseOrder::PAYMENT_CASH])],
            'pos.*.items' => ['required', 'array', 'min:1'],
            'pos.*.items.*.product_name' => ['required', 'string', 'max:255'],
            'pos.*.items.*.opportunity_product_name' => ['required', 'string', 'max:255'],
            'pos.*.items.*.brand' => ['nullable', 'string', 'max:255'],
            'pos.*.items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'pos.*.items.*.description' => ['nullable', 'string', 'max:5000'],
            'pos.*.items.*.note' => ['nullable', 'string', 'max:5000'],
            'pos.*.items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'pos.*.items.*.vendors' => ['required', 'array', 'min:1'],
            'pos.*.items.*.vendors.*.vendor_id' => ['nullable', 'integer', 'exists:crm_vendors,id'],
            'pos.*.items.*.vendors.*.vendor_stock_id' => ['nullable', 'integer', 'exists:crm_vendor_stocks,id'],
            'pos.*.items.*.vendors.*.vendor_name' => ['nullable', 'string', 'max:255'],
            'pos.*.items.*.vendors.*.status' => ['required', Rule::in(array_keys(VendorStock::STATUSES))],
            'pos.*.items.*.vendors.*.top' => ['nullable', 'string', Rule::in(CustomerTop::OPTIONS)],
            'pos.*.items.*.vendors.*.unit_price' => ['required', 'numeric', 'min:0'],
            'pos.*.items.*.vendors.*.price_basis' => ['nullable', Rule::in(['exclude', 'include'])],
            'pos.*.items.*.vendors.*.is_pkp' => ['nullable'],
            'pos.*.items.*.vendors.*.quoted_at' => ['nullable', 'date', 'before_or_equal:today'],
            'pos.*.items.*.vendors.*.is_selected' => ['nullable'],
        ], [
            'pos.required' => 'Belum ada PO yang bisa dibuat. Lengkapi item dan pilih vendor Dipilih.',
            'pos.*.vendor_id.distinct' => 'Setiap vendor hanya boleh satu PO dalam satu kali buat.',
            'pos.*.number.distinct' => 'Nomor PO tidak boleh sama.',
            'pos.*.number.required' => 'Isi nomor urut PO. Contoh: '.PurchaseOrderService::numberExample().'.',
            'pos.*.payment_term.required' => 'Kondisi TOP/Cash wajib diisi per vendor.',
            'pos.*.items.*.vendors.*.quoted_at.before_or_equal' => 'Tanggal vendor tidak boleh lebih dari hari ini.',
        ]);

        $pos = [];
        foreach ($data['pos'] as $poIndex => $poRow) {
            $vendorId = (int) $poRow['vendor_id'];
            $vendor = \App\Models\Vendor::query()->whereKey($vendorId)->first();
            $vendorName = $vendor?->name ?: trim((string) ($poRow['vendor_name'] ?? ''));
            $paymentTerm = $poRow['payment_term'] ?? $this->purchaseOrders->defaultPaymentTermForVendor($vendorId);

            $items = [];
            foreach ($poRow['items'] as $itemIndex => $item) {
                $itemName = trim((string) $item['product_name']);
                $oppProductName = trim((string) ($item['opportunity_product_name'] ?? ''));
                if ($itemName === '' || $oppProductName === '') {
                    continue;
                }

                $quotes = $this->normalizeIncomingQuotes($item['vendors'] ?? [], $itemName);
                if ($quotes === []) {
                    throw ValidationException::withMessages([
                        "pos.$poIndex.items.$itemIndex.vendors" => 'Setiap item wajib punya vendor pembanding.',
                    ]);
                }

                $selected = collect($quotes)->first(
                    fn ($q) => in_array($q['is_selected'], [true, 1, '1', 'true', 'on'], true)
                ) ?: $quotes[0];
                $selectedVendorId = isset($selected['vendor_id']) ? (int) $selected['vendor_id'] : null;
                if (! $selectedVendorId || $selectedVendorId !== $vendorId) {
                    throw ValidationException::withMessages([
                        "pos.$poIndex.items.$itemIndex.vendors" => 'Vendor Dipilih pada item harus sama dengan vendor PO.',
                    ]);
                }

                foreach ($quotes as $qi => $q) {
                    if (in_array($q['is_selected'], [true, 1, '1', 'true', 'on'], true)) {
                        $quotes[$qi]['vendor_id'] = $vendorId;
                        $quotes[$qi]['vendor_name'] = $vendorName;
                        $quotes[$qi]['is_selected'] = true;
                    }
                }

                $items[] = [
                    'opportunity_product_name' => $oppProductName,
                    'product_name' => $itemName,
                    'brand' => trim((string) ($item['brand'] ?? '')) ?: null,
                    'quantity' => $item['quantity'],
                    'description' => $item['description'] ?? null,
                    'note' => $item['note'] ?? null,
                    'unit_price' => $item['unit_price'] ?? 0,
                    'vendors' => $quotes,
                ];
            }

            if ($items === []) {
                throw ValidationException::withMessages([
                    "pos.$poIndex.items" => 'Setiap PO wajib punya minimal satu item.',
                ]);
            }

            $pos[] = [
                'number' => trim((string) $poRow['number']),
                'vendor_id' => $vendorId,
                'vendor_name' => $vendorName,
                'payment_term' => $paymentTerm === PurchaseOrder::PAYMENT_CASH
                    ? PurchaseOrder::PAYMENT_CASH
                    : PurchaseOrder::PAYMENT_TOP,
                'items' => $items,
            ];
        }

        return [
            'pos' => $pos,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rawQuotes
     * @return list<array<string, mixed>>
     */
    protected function normalizeIncomingQuotes(array $rawQuotes, string $itemName): array
    {
        $quotes = [];
        foreach ($rawQuotes as $quote) {
            $vendorName = trim((string) ($quote['vendor_name'] ?? ''));
            $vendorId = isset($quote['vendor_id']) && $quote['vendor_id'] !== '' && $quote['vendor_id'] !== null
                ? (int) $quote['vendor_id']
                : null;
            if ($vendorName === '' && empty($vendorId)) {
                continue;
            }
            if (! $vendorId && $vendorName !== '') {
                $matched = \App\Models\Vendor::query()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($vendorName)])
                    ->value('id');
                if ($matched) {
                    $vendorId = (int) $matched;
                }
            }
            if ($vendorId) {
                $vendorName = \App\Models\Vendor::query()->whereKey($vendorId)->value('name') ?: $vendorName;
            }

            $quotes[] = [
                'vendor_id' => $vendorId,
                'vendor_stock_id' => $quote['vendor_stock_id'] ?? null,
                'vendor_name' => $vendorName,
                'product_name' => $itemName,
                'status' => $quote['status'],
                'top' => CustomerTop::isValid($quote['top'] ?? null)
                    ? CustomerTop::normalize($quote['top'])
                    : CustomerTop::DAYS_30,
                'unit_price' => $this->normalizeQuoteUnitPrice($quote),
                'price_basis' => $this->normalizeQuotePriceBasis($quote),
                'is_pkp' => $this->normalizeQuoteIsPkp($quote, $vendorId),
                'quoted_at' => $this->normalizeQuoteDate($quote['quoted_at'] ?? null),
                'is_selected' => $quote['is_selected'] ?? false,
            ];
        }

        if ($quotes !== [] && ! collect($quotes)->contains(fn ($q) => in_array($q['is_selected'], [true, 1, '1', 'true', 'on'], true))) {
            $quotes[0]['is_selected'] = true;
        }

        return $quotes;
    }

    /**
     * @return array{number:string,payment_term:string,vendor_id:int,vendor_name:?string,items:list<array<string, mixed>>}
     */
    protected function validatePayload(Request $request, ?PurchaseOrder $existing = null): array
    {
        $data = $request->validate([
            'number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('crm_purchase_orders', 'number')->ignore($existing?->id),
                $this->poNumberSequenceRule(),
            ],
            'vendor_id' => ['required', 'integer', 'exists:crm_vendors,id'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'payment_term' => ['required', Rule::in([PurchaseOrder::PAYMENT_TOP, PurchaseOrder::PAYMENT_CASH])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.opportunity_product_name' => ['required', 'string', 'max:255'],
            'items.*.brand' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.description' => ['nullable', 'string', 'max:5000'],
            'items.*.note' => ['nullable', 'string', 'max:5000'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.vendors' => ['required', 'array', 'min:1'],
            'items.*.vendors.*.vendor_id' => ['nullable', 'integer', 'exists:crm_vendors,id'],
            'items.*.vendors.*.vendor_stock_id' => ['nullable', 'integer', 'exists:crm_vendor_stocks,id'],
            'items.*.vendors.*.vendor_name' => ['nullable', 'string', 'max:255'],
            'items.*.vendors.*.status' => ['required', Rule::in(array_keys(VendorStock::STATUSES))],
            'items.*.vendors.*.top' => ['nullable', 'string', Rule::in(CustomerTop::OPTIONS)],
            'items.*.vendors.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.vendors.*.price_basis' => ['nullable', Rule::in(['exclude', 'include'])],
            'items.*.vendors.*.is_pkp' => ['nullable'],
            'items.*.vendors.*.quoted_at' => ['nullable', 'date', 'before_or_equal:today'],
            'items.*.vendors.*.is_selected' => ['nullable'],
        ], [
            'number.required' => 'Isi nomor urut PO. Contoh: '.PurchaseOrderService::numberExample().'.',
            'vendor_id.required' => 'Pilih vendor untuk Purchase Order ini (satu PO = satu vendor).',
            'items.*.opportunity_product_name.required' => 'Setiap item harus terikat ke produk opportunity.',
            'items.*.vendors.*.quoted_at.before_or_equal' => 'Tanggal vendor tidak boleh lebih dari hari ini.',
        ]);

        $data['number'] = trim($data['number']);
        $data['vendor_id'] = (int) $data['vendor_id'];
        $vendor = \App\Models\Vendor::query()->whereKey($data['vendor_id'])->first();
        $data['vendor_name'] = $vendor?->name ?: trim((string) ($data['vendor_name'] ?? ''));

        $items = [];
        foreach ($data['items'] as $itemIndex => $item) {
            $itemName = trim((string) $item['product_name']);
            $oppProductName = trim((string) ($item['opportunity_product_name'] ?? ''));
            if ($itemName === '' || $oppProductName === '') {
                continue;
            }

            $quotes = [];
            foreach ($item['vendors'] ?? [] as $quote) {
                $vendorName = trim((string) ($quote['vendor_name'] ?? ''));
                $vendorId = isset($quote['vendor_id']) && $quote['vendor_id'] !== '' && $quote['vendor_id'] !== null
                    ? (int) $quote['vendor_id']
                    : null;
                if ($vendorName === '' && empty($vendorId)) {
                    continue;
                }

                if (! $vendorId && $vendorName !== '') {
                    $matched = \App\Models\Vendor::query()
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($vendorName)])
                        ->value('id');
                    if ($matched) {
                        $vendorId = (int) $matched;
                    }
                }
                if ($vendorId) {
                    $vendorName = \App\Models\Vendor::query()->whereKey($vendorId)->value('name') ?: $vendorName;
                }

                $quotes[] = [
                    'vendor_id' => $vendorId,
                    'vendor_stock_id' => $quote['vendor_stock_id'] ?? null,
                    'vendor_name' => $vendorName,
                    'product_name' => $itemName,
                    'status' => $quote['status'],
                    'top' => CustomerTop::isValid($quote['top'] ?? null)
                    ? CustomerTop::normalize($quote['top'])
                    : CustomerTop::DAYS_30,
                    'unit_price' => $quote['unit_price'],
                    'is_pkp' => $this->normalizeQuoteIsPkp($quote, $vendorId),
                    'quoted_at' => $this->normalizeQuoteDate($quote['quoted_at'] ?? null),
                    'is_selected' => $quote['is_selected'] ?? false,
                ];
            }

            if ($quotes === []) {
                throw ValidationException::withMessages([
                    "items.$itemIndex.vendors" => 'Setiap item PO wajib punya minimal satu vendor pembanding.',
                ]);
            }

            $hasSelected = collect($quotes)->contains(
                fn ($q) => in_array($q['is_selected'], [true, 1, '1', 'true', 'on'], true)
            );
            if (! $hasSelected) {
                $quotes[0]['is_selected'] = true;
            }

            foreach ($quotes as $qi => $q) {
                $isSelected = in_array($q['is_selected'], [true, 1, '1', 'true', 'on'], true);
                if (! $isSelected) {
                    continue;
                }
                $selectedVendorId = isset($q['vendor_id']) && $q['vendor_id'] !== '' && $q['vendor_id'] !== null
                    ? (int) $q['vendor_id']
                    : null;
                if ($selectedVendorId && $selectedVendorId !== $data['vendor_id']) {
                    throw ValidationException::withMessages([
                        "items.$itemIndex.vendors" => 'Vendor yang dipilih pada item harus sama dengan Vendor PO (satu PO = satu vendor).',
                    ]);
                }
                $quotes[$qi]['vendor_id'] = $data['vendor_id'];
                $quotes[$qi]['vendor_name'] = $data['vendor_name'] ?: $q['vendor_name'];
                $quotes[$qi]['is_selected'] = true;
            }

            $items[] = [
                'opportunity_product_name' => $oppProductName,
                'product_name' => $itemName,
                'brand' => trim((string) ($item['brand'] ?? '')) ?: null,
                'quantity' => $item['quantity'],
                'description' => $item['description'] ?? null,
                'note' => $item['note'] ?? null,
                'unit_price' => $item['unit_price'] ?? 0,
                'vendors' => $quotes,
            ];
        }

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Minimal satu item produk wajib diisi.',
            ]);
        }

        $data['items'] = $items;

        return $data;
    }

    protected function authorizeManage(Opportunity $opportunity): void
    {
        $user = auth()->user();

        if (! $user || ! $user->canManagePurchaseOrders()) {
            abort(403, 'Hanya Purchasing / Superadmin yang dapat mengelola Purchase Order.');
        }

        if ($opportunity->stage !== Opportunity::WON_STAGE) {
            abort(403, 'Purchase Order hanya untuk opportunity Closed Won.');
        }

        $this->authorizeAccess($opportunity);
    }

    protected function authorizeView(Opportunity $opportunity): void
    {
        $user = auth()->user();

        if (! $user || ! $user->canViewPurchaseOrders()) {
            abort(403, 'Anda tidak memiliki akses untuk melihat Purchase Order.');
        }

        if ($opportunity->stage !== Opportunity::WON_STAGE) {
            abort(403, 'Purchase Order hanya untuk opportunity Closed Won.');
        }

        $this->authorizeAccess($opportunity);
    }

    protected function authorizeAccess(Opportunity $opportunity): void
    {
        // Purchasing/superadmin melihat semua via canViewAllOpportunities;
        // tetap hormati scoping sales bila role lain ikut (harusnya tidak).
        if ($this->isAdmin() || auth()->user()?->canViewAllOpportunities()) {
            return;
        }

        if ($opportunity->assigned_user_id !== auth()->id()) {
            abort(403, 'You do not have access to this opportunity.');
        }
    }

    protected function ensureBelongsToOpportunity(Opportunity $opportunity, PurchaseOrder $purchaseOrder): void
    {
        if ($purchaseOrder->opportunity_id !== $opportunity->id) {
            abort(404);
        }
    }

    /**
     * @param  array<string, mixed>  $quote
     */
    protected function normalizeQuotePriceBasis(array $quote): string
    {
        $basis = (string) ($quote['price_basis'] ?? 'exclude');

        return in_array($basis, ['include', 'exclude'], true) ? $basis : 'exclude';
    }

    /**
     * Simpan harga modal selalu exclude; konversi jika user input include.
     *
     * @param  array<string, mixed>  $quote
     */
    protected function normalizeQuoteUnitPrice(array $quote): float
    {
        $amount = round((float) ($quote['unit_price'] ?? 0), 2);
        if ($amount <= 0) {
            return 0.0;
        }

        if ($this->normalizeQuotePriceBasis($quote) === 'include') {
            return OpportunityProductPricing::excludeFromInclude($amount);
        }

        return $amount;
    }

    /**
     * @param  array<string, mixed>  $quote
     */
    protected function normalizeQuoteIsPkp(array $quote, ?int $vendorId): bool
    {
        if (array_key_exists('is_pkp', $quote)) {
            return in_array($quote['is_pkp'], [true, 1, '1', 'true', 'on', 'yes'], true);
        }

        if ($vendorId) {
            $vendorIsPkp = \App\Models\Vendor::query()->whereKey($vendorId)->value('is_pkp');

            return $vendorIsPkp === null ? true : (bool) $vendorIsPkp;
        }

        return true;
    }

    protected function normalizeQuoteDate(mixed $value): string
    {
        $today = Carbon::today()->toDateString();
        $raw = trim((string) $value);
        if ($raw === '') {
            return $today;
        }

        try {
            $date = Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return $today;
        }

        return $date > $today ? $today : $date;
    }

    /**
     * Nomor PO wajib punya urutan (bukan hanya prefix AGC/YY/MM/).
     * Format tetap bebas diedit purchasing.
     */
    protected function poNumberSequenceRule(): \Closure
    {
        $example = PurchaseOrderService::numberExample();

        return function (string $attribute, mixed $value, \Closure $fail) use ($example) {
            if (! PurchaseOrderService::numberHasSequence((string) $value)) {
                $fail('Isi nomor urut PO. Contoh: '.$example.'.');
            }
        };
    }
}
