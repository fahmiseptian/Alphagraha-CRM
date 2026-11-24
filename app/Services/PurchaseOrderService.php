<?php

namespace App\Services;

use App\Models\Espo\Opportunity;
use App\Models\OpportunityLog;
use App\Models\OpportunitySalesOrder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderLog;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorStock;
use App\Support\CustomerTop;
use App\Support\OpportunityProductPricing;
use App\Support\PurchaseOrderPricing;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(
        protected VendorStockService $vendorStocks
    ) {}

    /**
     * Buat PO baru (1 PO = 1 vendor = 1 Sales Order) beserta item & referensi harga; total dihitung server-side.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function create(
        Opportunity $opportunity,
        string $number,
        array $items,
        string $paymentTerm = PurchaseOrder::PAYMENT_TOP,
        ?string $createdBy = null,
        ?int $vendorId = null,
        ?string $vendorName = null,
        ?int $salesOrderId = null,
        ?string $reportTop = null,
        ?string $reportTopSource = null
    ): PurchaseOrder {
        return DB::transaction(function () use ($opportunity, $number, $items, $paymentTerm, $createdBy, $vendorId, $vendorName, $salesOrderId, $reportTop, $reportTopSource) {
            [$vendorId, $vendorName] = $this->resolveVendor($vendorId, $vendorName);
            $salesOrderId = $this->resolveSalesOrderId($opportunity, $salesOrderId);

            $po = PurchaseOrder::query()->create([
                'opportunity_id' => $opportunity->id,
                'sales_order_id' => $salesOrderId,
                'number' => $number,
                'vendor_id' => $vendorId,
                'vendor_name' => $vendorName,
                'payment_term' => $this->normalizePaymentTerm($paymentTerm),
                'report_top' => $this->normalizeReportTop($reportTop),
                'report_top_source' => $this->normalizeReportTopSource($reportTopSource),
                'currency' => $opportunity->amount_currency ?: 'IDR',
                'total' => 0,
                'created_by' => $createdBy,
                'approval_status' => $this->initialApprovalStatus($createdBy),
                'approved_by' => $this->initialApproverId($createdBy),
                'approved_at' => $this->initialApprovedAt($createdBy),
            ]);

            $this->syncItems($po, $items, $createdBy);
            $this->syncOpportunityCostsFromPo($opportunity);
            $notifications = app(NotificationService::class);
            $notifications->markActioned(
                \App\Models\CrmNotification::TYPE_SALES_ORDER_CREATED,
                ['opportunity_id' => $opportunity->id]
            );

            $po = $po->fresh(['items.vendorQuotes', 'vendor', 'salesOrder', 'creator', 'opportunity.account']);
            $this->dispatchApprovalNotifications($po, $opportunity);
            $this->recordLog($po, PurchaseOrderLog::ACTION_CREATED);
            $this->clearPurchasingRevision($po);

            return $po;
        });
    }

    /**
     * Buat banyak PO sekaligus dari rencana pembelian untuk satu Sales Order.
     * Satu vendor = satu PO; item dari produk berbeda digabung ke PO vendor yang sama.
     * Kondisi TOP/Cash mengikuti per PO (default dari master vendor).
     * report_top sama untuk seluruh batch (isi kotak TOP di laporan PO).
     *
     * @param  list<array{number:string,vendor_id:int,vendor_name?:?string,payment_term?:string,items:list<array<string,mixed>>}>  $pos
     * @return list<PurchaseOrder>
     */
    public function createMany(
        Opportunity $opportunity,
        array $pos,
        ?string $createdBy = null,
        ?int $salesOrderId = null,
        ?string $reportTop = null,
        ?string $reportTopSource = null
    ): array {
        return DB::transaction(function () use ($opportunity, $pos, $createdBy, $salesOrderId, $reportTop, $reportTopSource) {
            $created = [];
            $salesOrderId = $this->resolveSalesOrderId($opportunity, $salesOrderId);
            $reportTop = $this->normalizeReportTop($reportTop);
            $reportTopSource = $this->normalizeReportTopSource($reportTopSource);

            foreach (array_values($pos) as $poData) {
                [$vendorId, $vendorName] = $this->resolveVendor(
                    isset($poData['vendor_id']) ? (int) $poData['vendor_id'] : null,
                    $poData['vendor_name'] ?? null
                );

                $term = $this->normalizePaymentTerm(
                    (string) ($poData['payment_term'] ?? $this->defaultPaymentTermForVendor($vendorId))
                );

                $po = PurchaseOrder::query()->create([
                    'opportunity_id' => $opportunity->id,
                    'sales_order_id' => $salesOrderId,
                    'number' => trim((string) ($poData['number'] ?? '')),
                    'vendor_id' => $vendorId,
                    'vendor_name' => $vendorName,
                    'payment_term' => $term,
                    'report_top' => $reportTop,
                    'report_top_source' => $reportTopSource,
                    'currency' => $opportunity->amount_currency ?: 'IDR',
                    'total' => 0,
                    'created_by' => $createdBy,
                    'approval_status' => $this->initialApprovalStatus($createdBy),
                    'approved_by' => $this->initialApproverId($createdBy),
                    'approved_at' => $this->initialApprovedAt($createdBy),
                ]);

                $this->syncItems($po, $poData['items'] ?? [], $createdBy);
                $freshPo = $po->fresh(['items.vendorQuotes', 'vendor', 'salesOrder', 'creator', 'opportunity.account']);
                $this->recordLog($freshPo, PurchaseOrderLog::ACTION_CREATED);
                $created[] = $freshPo;
            }

            $this->syncOpportunityCostsFromPo($opportunity);
            $notifications = app(NotificationService::class);
            $notifications->markActioned(
                \App\Models\CrmNotification::TYPE_SALES_ORDER_CREATED,
                ['opportunity_id' => $opportunity->id]
            );

            foreach ($created as $po) {
                $this->dispatchApprovalNotifications($po, $opportunity);
                $this->clearPurchasingRevision($po);
            }

            return $created;
        });
    }

    /**
     * Default kondisi PO dari master vendor: cash → Cash, selain itu TOP.
     */
    public function defaultPaymentTermForVendor(?int $vendorId): string
    {
        if (! $vendorId) {
            return PurchaseOrder::PAYMENT_TOP;
        }

        $top = Vendor::query()->whereKey($vendorId)->value('top');
        $top = CustomerTop::isValid($top) ? CustomerTop::normalize($top) : CustomerTop::DAYS_30;

        return $top === CustomerTop::CASH
            ? PurchaseOrder::PAYMENT_CASH
            : PurchaseOrder::PAYMENT_TOP;
    }

    /**
     * Prefix otomatis nomor PO: AGC/YY/MM/
     * Nomor urut diisi purchasing; seluruh string tetap bisa diedit.
     */
    public static function numberPrefix(?Carbon $date = null): string
    {
        $date ??= now();
        $prefix = trim((string) config('crm.purchase_order_number.prefix', 'AGC')) ?: 'AGC';

        return sprintf('%s/%s/%s/', $prefix, $date->format('y'), $date->format('m'));
    }

    public static function numberExample(?Carbon $date = null): string
    {
        return rtrim(self::numberPrefix($date), '/').'/1367';
    }

    public static function numberHasSequence(string $number): bool
    {
        $number = trim($number);

        return $number !== '' && ! str_ends_with($number, '/');
    }

    /**
     * Default nomor PO (prefix tahun/bulan). Nomor urut dikosongkan untuk diisi purchasing.
     */
    public function suggestNumber(Opportunity $opportunity, string $vendorName): string
    {
        return self::numberPrefix();
    }

    /**
     * Update nomor + vendor + kondisi bayar + ganti seluruh item; total dihitung ulang.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function update(
        PurchaseOrder $purchaseOrder,
        string $number,
        array $items,
        string $paymentTerm = PurchaseOrder::PAYMENT_TOP,
        ?string $updatedBy = null,
        ?int $vendorId = null,
        ?string $vendorName = null,
        ?int $salesOrderId = null,
        ?string $reportTop = null,
        ?string $reportTopSource = null
    ): PurchaseOrder {
        return DB::transaction(function () use ($purchaseOrder, $number, $items, $paymentTerm, $updatedBy, $vendorId, $vendorName, $salesOrderId, $reportTop, $reportTopSource) {
            $before = $this->captureForDiff($purchaseOrder);
            [$vendorId, $vendorName] = $this->resolveVendor($vendorId, $vendorName);

            $payload = [
                'number' => $number,
                'vendor_id' => $vendorId,
                'vendor_name' => $vendorName,
                'payment_term' => $this->normalizePaymentTerm($paymentTerm),
            ];

            if ($salesOrderId !== null) {
                $opportunity = $purchaseOrder->opportunity()->first();
                if ($opportunity) {
                    $payload['sales_order_id'] = $this->resolveSalesOrderId($opportunity, $salesOrderId);
                }
            }

            $resolvedSalesOrderId = (int) ($payload['sales_order_id'] ?? $purchaseOrder->sales_order_id);
            $shouldSyncReportTop = $reportTop !== null || $reportTopSource !== null;

            if ($shouldSyncReportTop) {
                $normalizedTop = $reportTop !== null
                    ? $this->normalizeReportTop($reportTop)
                    : $this->normalizeReportTop($purchaseOrder->report_top);
                $normalizedSource = $reportTopSource !== null
                    ? $this->normalizeReportTopSource($reportTopSource)
                    : $this->normalizeReportTopSource($purchaseOrder->report_top_source);

                $payload['report_top'] = $normalizedTop;
                $payload['report_top_source'] = $normalizedSource;
            }

            $purchaseOrder->update($payload);
            $this->syncItems($purchaseOrder, $items, $updatedBy);

            if ($shouldSyncReportTop && $resolvedSalesOrderId > 0) {
                $this->syncReportTopForSalesOrder(
                    $resolvedSalesOrderId,
                    $payload['report_top'] ?? null,
                    $payload['report_top_source'] ?? null,
                    (int) $purchaseOrder->id
                );
            }

            $opportunity = $purchaseOrder->opportunity()->first();
            if ($opportunity) {
                $this->syncOpportunityCostsFromPo($opportunity);
                app(NotificationService::class)->markActioned(
                    \App\Models\CrmNotification::TYPE_SALES_ORDER_CREATED,
                    ['opportunity_id' => $opportunity->id]
                );
            }

            $fresh = $purchaseOrder->fresh(['items.vendorQuotes', 'vendor', 'salesOrder', 'opportunity.account', 'creator', 'approver']);
            $this->recordIfChanged($fresh, $before);
            $this->clearPurchasingRevision($fresh);

            return $fresh;
        });
    }

    /**
     * Samakan TOP laporan untuk semua PO yang terikat Sales Order yang sama.
     */
    public function syncReportTopForSalesOrder(
        int $salesOrderId,
        ?string $reportTop,
        ?string $reportTopSource,
        ?int $exceptPurchaseOrderId = null
    ): void {
        $siblings = PurchaseOrder::query()
            ->where('sales_order_id', $salesOrderId)
            ->when($exceptPurchaseOrderId, fn ($q) => $q->where('id', '!=', $exceptPurchaseOrderId))
            ->get();
        $befores = [];
        foreach ($siblings as $po) {
            $befores[$po->id] = $this->captureForDiff($po);
        }

        PurchaseOrder::query()
            ->where('sales_order_id', $salesOrderId)
            ->update([
                'report_top' => $this->normalizeReportTop($reportTop),
                'report_top_source' => $this->normalizeReportTopSource($reportTopSource),
            ]);

        foreach ($siblings as $po) {
            $fresh = $po->fresh(['items', 'vendor', 'salesOrder', 'opportunity.account', 'creator', 'approver']);
            if ($fresh) {
                $this->recordIfChanged($fresh, $befores[$po->id]);
            }
        }
    }

    /**
     * Jika TOP laporan diambil dari SO (atau data lama tanpa source),
     * ikuti perubahan payment SO. Source 'custom' tidak diubah.
     */
    public function syncReportTopFromSalesOrder(OpportunitySalesOrder $salesOrder): void
    {
        $label = trim($salesOrder->paymentLabel());
        if ($label === '' || $label === '—') {
            return;
        }

        $affected = PurchaseOrder::query()
            ->where('sales_order_id', $salesOrder->id)
            ->where(function ($q) {
                $q->where('report_top_source', PurchaseOrder::REPORT_TOP_FROM_SO)
                    ->orWhereNull('report_top_source')
                    ->orWhere('report_top_source', '');
            })
            ->get();
        if ($affected->isEmpty()) {
            return;
        }
        $befores = [];
        foreach ($affected as $po) {
            $befores[$po->id] = $this->captureForDiff($po);
        }

        PurchaseOrder::query()
            ->whereIn('id', $affected->pluck('id'))
            ->update([
                'report_top' => $this->normalizeReportTop($label),
                'report_top_source' => PurchaseOrder::REPORT_TOP_FROM_SO,
            ]);

        foreach ($affected as $po) {
            $fresh = $po->fresh(['items', 'vendor', 'salesOrder', 'opportunity.account', 'creator', 'approver']);
            if ($fresh) {
                $this->recordIfChanged($fresh, $befores[$po->id]);
            }
        }
    }

    /**
     * Isi / ganti Sales Order terkait untuk PO yang sudah ada (backfill Purchasing).
     */
    public function attachSalesOrder(
        Opportunity $opportunity,
        PurchaseOrder $purchaseOrder,
        int $salesOrderId
    ): PurchaseOrder {
        $before = $this->captureForDiff($purchaseOrder);
        $salesOrderId = $this->resolveSalesOrderId($opportunity, $salesOrderId);
        $purchaseOrder->update(['sales_order_id' => $salesOrderId]);

        $fresh = $purchaseOrder->fresh(['items.vendorQuotes', 'vendor', 'salesOrder', 'opportunity.account', 'creator', 'approver']);
        $this->recordIfChanged($fresh, $before);

        return $fresh;
    }

    /**
     * Pastikan SO milik opportunity yang sama.
     */
    public function resolveSalesOrderId(Opportunity $opportunity, ?int $salesOrderId): int
    {
        if (! $salesOrderId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'sales_order_id' => 'Pilih Sales Order terkait untuk Purchase Order ini.',
            ]);
        }

        $exists = $opportunity->salesOrders()->whereKey($salesOrderId)->exists();
        if (! $exists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'sales_order_id' => 'Sales Order tidak ditemukan di opportunity ini.',
            ]);
        }

        return $salesOrderId;
    }

    /**
     * Label TOP untuk kotak laporan PO (contoh: TOP 30).
     */
    public function normalizeReportTop(?string $reportTop): ?string
    {
        $label = trim((string) $reportTop);

        return $label !== '' ? mb_substr($label, 0, 50) : null;
    }

    public function normalizeReportTopSource(?string $source): ?string
    {
        $source = trim((string) $source);

        return in_array($source, [
            PurchaseOrder::REPORT_TOP_CUSTOM,
            PurchaseOrder::REPORT_TOP_FROM_SO,
        ], true) ? $source : null;
    }

    /**
     * @return array{0:?int,1:?string}
     */
    protected function resolveVendor(?int $vendorId, ?string $vendorName): array
    {
        $vendorName = trim((string) $vendorName);
        if ($vendorId) {
            $vendor = Vendor::query()->whereKey($vendorId)->first();
            if ($vendor) {
                return [(int) $vendor->id, $vendor->name];
            }
        }

        return [$vendorId ?: null, $vendorName !== '' ? $vendorName : null];
    }

    /**
     * Hapus semua item lama, insert ulang, update total header.
     * line_total = qty × jumlah_exclude (modal + tambahan Cash/TOP di sisi exclude).
     * Harga modal diambil dari vendor yang dipilih (is_selected).
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function syncItems(PurchaseOrder $purchaseOrder, array $items, ?string $actorId = null): void
    {
        $purchaseOrder->items()->delete();

        $total = 0.0;

        foreach (array_values($items) as $index => $item) {
            $quotes = $this->normalizeQuotes($item);
            $selected = $this->selectedQuote($quotes);
            $qty = round((float) ($item['quantity'] ?? 0), 2);
            $unitPrice = $selected !== null
                ? round((float) ($selected['unit_price'] ?? 0), 2)
                : round((float) ($item['unit_price'] ?? 0), 2);
            $rate = PurchaseOrderPricing::surchargeRate($purchaseOrder->payment_term);
            $extraExclude = $rate > 0 ? round($unitPrice * $rate, 2) : 0.0;
            $lineTotal = round($qty * round($unitPrice + $extraExclude, 2), 2);
            $total += $lineTotal;

            $oppProductName = trim((string) ($item['opportunity_product_name'] ?? ''));
            if ($oppProductName === '') {
                $oppProductName = trim((string) ($item['product_name'] ?? ''));
            }
            $itemName = trim((string) ($item['product_name'] ?? ''));

            $row = $purchaseOrder->items()->create([
                'opportunity_product_name' => $oppProductName !== '' ? $oppProductName : null,
                'opportunity_product_key' => $oppProductName !== '' ? mb_strtolower($oppProductName) : null,
                'product_name' => $itemName,
                'brand' => isset($item['brand']) ? (trim((string) $item['brand']) ?: null) : null,
                'quantity' => $qty,
                'description' => isset($item['description']) ? trim((string) $item['description']) ?: null : null,
                'note' => isset($item['note']) ? trim((string) $item['note']) ?: null : null,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'sort_order' => $index,
            ]);

            $this->syncVendorQuotes($purchaseOrder, $row, $quotes, $actorId);
        }

        $purchaseOrder->update(['total' => round($total, 2)]);
    }

    /**
     * Mirror total modal ke opportunity dari SEMUA PO di opportunity ini.
     * Satu produk opp bisa tersebar di beberapa PO (mis. barang + aksesoris) — biayanya dijumlahkan.
     * Total grup = Σ (qty × harga vendor terpilih); unit modal = total ÷ qty produk opp.
     */
    public function syncOpportunityCostsFromPo(Opportunity $opportunity, ?PurchaseOrder $purchaseOrder = null): void
    {
        $allPos = PurchaseOrder::query()
            ->where('opportunity_id', $opportunity->id)
            ->with(['items.vendorQuotes', 'vendor'])
            ->orderBy('id')
            ->get();

        /** @var array<string, array{name: string, total_cost: float, vendor: string}> $costByProduct */
        $costByProduct = [];

        foreach ($allPos as $po) {
            $vendorName = $po->displayVendorName();
            if ($vendorName === '—') {
                $vendorName = '';
            }

            foreach ($po->items as $item) {
                $oppName = trim((string) ($item->opportunity_product_name ?: $item->product_name));
                if ($oppName === '') {
                    continue;
                }
                $selected = $item->selectedVendorQuote();
                $unitPrice = $selected
                    ? (float) $selected->unit_price
                    : (float) $item->unit_price;
                $lineCost = round((float) $item->quantity * $unitPrice, 2);
                $key = mb_strtolower($oppName);
                if (! isset($costByProduct[$key])) {
                    $costByProduct[$key] = [
                        'name' => $oppName,
                        'total_cost' => 0.0,
                        'vendor' => '',
                    ];
                }
                $costByProduct[$key]['total_cost'] = round($costByProduct[$key]['total_cost'] + $lineCost, 2);
                if ($vendorName !== '') {
                    $costByProduct[$key]['vendor'] = $vendorName;
                }
            }
        }

        if ($costByProduct === []) {
            // Tidak ada item PO tersisa — biarkan modal opportunity apa adanya.
            return;
        }

        $before = app(OpportunityLogService::class)->capture($opportunity);

        $rows = $opportunity->products->values()->map(function (array $p) use ($costByProduct) {
            $key = mb_strtolower(trim((string) ($p['name'] ?? '')));
            if ($key !== '' && isset($costByProduct[$key])) {
                $qty = max(0.01, (float) ($p['quantity'] ?? 1));
                $p['cost_exclude'] = round($costByProduct[$key]['total_cost'] / $qty, 2);
                if ($costByProduct[$key]['vendor'] !== '') {
                    $p['vendor'] = $costByProduct[$key]['vendor'];
                }
            }

            return OpportunityProductPricing::enrichRow($p);
        })->filter(fn ($p) => filled($p['name'] ?? null))->values();

        if ($rows->isEmpty()) {
            return;
        }

        $opportunity->applyProductRows($rows);
        $opportunity->syncWonMargin();
        $opportunity->modified_at = Carbon::now()->format('Y-m-d H:i:s');
        $opportunity->modified_by_id = auth()->id();
        $opportunity->save();
        app(OpportunityLogService::class)->record(
            $opportunity,
            OpportunityLog::ACTION_PO_SYNCED,
            $before
        );
        $opportunity->syncProductsToLinkedQuotation();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<array{vendor_id:?int,vendor_stock_id:?int,vendor_name:string,product_name:string,status:string,top:string,unit_price:float,is_pkp:bool,quoted_at:string,is_selected:bool}>
     */
    protected function normalizeQuotes(array $item): array
    {
        $productName = trim((string) ($item['product_name'] ?? ''));
        $raw = is_array($item['vendors'] ?? null) ? $item['vendors'] : [];
        $quotes = [];

        foreach (array_values($raw) as $row) {
            if (! is_array($row)) {
                continue;
            }

            [$vendorId, $vendorName] = $this->resolveQuoteVendor(
                $row['vendor_id'] ?? null,
                $row['vendor_name'] ?? null
            );
            if ($vendorName === '' && ! $vendorId) {
                continue;
            }

            $quotes[] = [
                'vendor_id' => $vendorId,
                'vendor_stock_id' => isset($row['vendor_stock_id']) && $row['vendor_stock_id'] !== ''
                    ? (int) $row['vendor_stock_id']
                    : null,
                'vendor_name' => $vendorName,
                'product_name' => trim((string) ($row['product_name'] ?? $productName)) ?: $productName,
                'status' => VendorStock::normalizeStatus($row['status'] ?? VendorStock::STATUS_READY),
                'top' => CustomerTop::isValid($row['top'] ?? null) ? CustomerTop::normalize($row['top']) : CustomerTop::DAYS_30,
                'unit_price' => round((float) ($row['unit_price'] ?? 0), 2),
                'is_pkp' => $this->resolveQuoteIsPkp($row, $vendorId),
                'quoted_at' => $this->normalizeQuoteDate($row['quoted_at'] ?? null),
                'is_selected' => $this->truthy($row['is_selected'] ?? false),
            ];
        }

        if ($quotes === []) {
            return [];
        }

        $selectedCount = 0;
        foreach ($quotes as $i => $quote) {
            if ($quote['is_selected']) {
                $selectedCount++;
                if ($selectedCount > 1) {
                    $quotes[$i]['is_selected'] = false;
                }
            }
        }
        if ($selectedCount === 0) {
            $quotes[0]['is_selected'] = true;
        }

        return $quotes;
    }

    /**
     * @return array{0:?int,1:string}
     */
    protected function resolveQuoteVendor(mixed $vendorId, mixed $vendorName): array
    {
        $id = ($vendorId !== '' && $vendorId !== null) ? (int) $vendorId : null;
        $name = trim((string) $vendorName);

        if ($id) {
            $resolved = Vendor::query()->whereKey($id)->value('name');
            if ($resolved) {
                return [$id, (string) $resolved];
            }
        }

        if ($name !== '') {
            $matched = Vendor::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first(['id', 'name']);
            if ($matched) {
                return [(int) $matched->id, $matched->name];
            }
        }

        return [$id ?: null, $name];
    }

    /**
     * @param  list<array<string, mixed>>  $quotes
     * @return array<string, mixed>|null
     */
    protected function selectedQuote(array $quotes): ?array
    {
        foreach ($quotes as $quote) {
            if (! empty($quote['is_selected'])) {
                return $quote;
            }
        }

        return $quotes[0] ?? null;
    }

    /**
     * Simpan quote vendor + upsert harga/status ke daftar ketersediaan vendor.
     *
     * @param  list<array<string, mixed>>  $quotes
     */
    protected function syncVendorQuotes(
        PurchaseOrder $purchaseOrder,
        PurchaseOrderItem $item,
        array $quotes,
        ?string $actorId = null
    ): void {
        $productName = trim((string) $item->product_name);
        $poVendorId = $purchaseOrder->vendor_id ? (int) $purchaseOrder->vendor_id : null;
        $poVendorName = trim((string) ($purchaseOrder->vendor_name ?? ''));

        foreach ($quotes as $index => $quote) {
            $vendorId = ! empty($quote['vendor_id']) ? (int) $quote['vendor_id'] : null;
            $vendorName = trim((string) ($quote['vendor_name'] ?? ''));
            $isSelected = (bool) ($quote['is_selected'] ?? false);

            // Vendor terpilih wajib mengikuti Vendor PO.
            if ($isSelected && $poVendorId) {
                $vendorId = $poVendorId;
                if ($poVendorName !== '') {
                    $vendorName = $poVendorName;
                }
            }

            if (! $vendorId && $vendorName !== '') {
                [$vendorId, $resolvedName] = $this->resolveQuoteVendor(null, $vendorName);
                if ($resolvedName !== '') {
                    $vendorName = $resolvedName;
                }
            }

            $quoteProductName = trim((string) ($quote['product_name'] ?? '')) ?: $productName;
            $stockId = $quote['vendor_stock_id'] ?? null;

            // Setiap harga di PO otomatis masuk/update ke ketersediaan vendor.
            if ($vendorId && $quoteProductName !== '') {
                $stock = $this->vendorStocks->upsert(
                    $vendorId,
                    $quoteProductName,
                    (string) $quote['status'],
                    (float) $quote['unit_price'],
                    null,
                    null,
                    $actorId
                );
                $stockId = $stock->id;
                $vendorName = $stock->vendor?->name ?: ($vendorName ?: $poVendorName);
            }

            $item->vendorQuotes()->create([
                'vendor_id' => $vendorId ?: null,
                'vendor_stock_id' => $stockId ?: null,
                'product_name' => $quoteProductName ?: $item->product_name,
                'vendor_name' => $vendorName,
                'status' => $quote['status'],
                'top' => $quote['top'] ?? CustomerTop::DAYS_30,
                'unit_price' => $quote['unit_price'],
                'unit_price_basis' => 'exclude',
                'is_pkp' => (bool) ($quote['is_pkp'] ?? true),
                'quoted_at' => $quote['quoted_at'] ?? null,
                'is_selected' => $isSelected,
                'sort_order' => $index,
            ]);
        }
    }

    protected function truthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function resolveQuoteIsPkp(array $row, ?int $vendorId): bool
    {
        if (array_key_exists('is_pkp', $row)) {
            return $this->truthy($row['is_pkp']);
        }

        if ($vendorId) {
            $vendorIsPkp = Vendor::query()->whereKey($vendorId)->value('is_pkp');

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

    protected function normalizePaymentTerm(string $paymentTerm): string
    {
        return $paymentTerm === PurchaseOrder::PAYMENT_CASH
            ? PurchaseOrder::PAYMENT_CASH
            : PurchaseOrder::PAYMENT_TOP;
    }

    public function approve(PurchaseOrder $purchaseOrder, Opportunity $opportunity, string $approvedBy, ?string $note = null): PurchaseOrder
    {
        if (! $purchaseOrder->isApprovalPending()) {
            abort(422, 'Purchase Order tidak dalam status menunggu approval.');
        }

        $before = $this->captureForDiff($purchaseOrder);

        $purchaseOrder->forceFill([
            'approval_status' => PurchaseOrder::APPROVAL_APPROVED,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'approval_note' => filled($note) ? trim($note) : null,
        ])->save();

        $notifications = app(NotificationService::class);
        $notifications->markPurchaseOrderApprovalActioned($purchaseOrder->id);
        $fresh = $purchaseOrder->fresh(['items.vendorQuotes', 'vendor', 'salesOrder', 'approver', 'opportunity.account', 'creator']);
        $notifications->notifyPurchaseOrderApproved($fresh, $opportunity, $note);
        $this->recordDecision($fresh, PurchaseOrderLog::ACTION_APPROVED, $before, $note);

        return $fresh;
    }

    public function reject(PurchaseOrder $purchaseOrder, Opportunity $opportunity, string $rejectedBy, ?string $note = null): PurchaseOrder
    {
        if (! $purchaseOrder->isApprovalPending()) {
            abort(422, 'Purchase Order tidak dalam status menunggu approval.');
        }

        $before = $this->captureForDiff($purchaseOrder);

        $purchaseOrder->forceFill([
            'approval_status' => PurchaseOrder::APPROVAL_REJECTED,
            'approved_by' => $rejectedBy,
            'approved_at' => now(),
            'approval_note' => filled($note) ? trim($note) : null,
        ])->save();

        $notifications = app(NotificationService::class);
        $notifications->markPurchaseOrderApprovalActioned($purchaseOrder->id);
        $fresh = $purchaseOrder->fresh(['items.vendorQuotes', 'vendor', 'salesOrder', 'approver', 'opportunity.account', 'creator']);
        $notifications->notifyPurchaseOrderRejected($fresh, $opportunity, $note);
        $this->recordDecision($fresh, PurchaseOrderLog::ACTION_REJECTED, $before, $note);

        return $fresh;
    }

    protected function creatorIsSuperAdmin(?string $createdBy): bool
    {
        if (! filled($createdBy)) {
            return false;
        }

        $creator = User::query()->whereKey($createdBy)->first();

        return (bool) $creator?->isSuperAdmin();
    }

    protected function initialApprovalStatus(?string $createdBy): string
    {
        return $this->creatorIsSuperAdmin($createdBy)
            ? PurchaseOrder::APPROVAL_APPROVED
            : PurchaseOrder::APPROVAL_PENDING;
    }

    protected function initialApproverId(?string $createdBy): ?string
    {
        return $this->creatorIsSuperAdmin($createdBy) ? $createdBy : null;
    }

    protected function initialApprovedAt(?string $createdBy): ?Carbon
    {
        return $this->creatorIsSuperAdmin($createdBy) ? now() : null;
    }

    protected function dispatchApprovalNotifications(PurchaseOrder $purchaseOrder, Opportunity $opportunity): void
    {
        $notifications = app(NotificationService::class);

        if ($purchaseOrder->isApprovalPending()) {
            $notifications->notifyPurchaseOrderApprovalRequested($purchaseOrder, $opportunity);

            return;
        }

        if ($purchaseOrder->isApproved()) {
            $notifications->notifyPurchaseOrderApproved($purchaseOrder, $opportunity);
        }
    }

    /**
     * Update PO AGC menghapus status Revisi di SO terkait
     * dan SO lain pada opportunity yang sama.
     */
    protected function clearPurchasingRevision(PurchaseOrder $purchaseOrder): void
    {
        $ids = collect();
        $linkedId = (int) $purchaseOrder->sales_order_id;
        if ($linkedId > 0) {
            $ids->push($linkedId);
        }

        if (filled($purchaseOrder->opportunity_id)) {
            $revisedIds = OpportunitySalesOrder::query()
                ->where('opportunity_id', $purchaseOrder->opportunity_id)
                ->where('agc_payload->purchasing_revision_pending', true)
                ->pluck('id');
            $ids = $ids->merge($revisedIds);
        }

        if ($ids->isEmpty()) {
            return;
        }

        $service = app(SalesOrderService::class);
        OpportunitySalesOrder::query()
            ->whereIn('id', $ids->unique()->all())
            ->get()
            ->each(fn (OpportunitySalesOrder $salesOrder) => $service->clearPurchasingRevision($salesOrder));
    }

    /**
     * @param  array<string, mixed>|null  $changes
     */
    public function recordLog(PurchaseOrder $purchaseOrder, string $action, ?array $changes = null, ?User $actor = null): PurchaseOrderLog
    {
        $actor ??= auth()->user();

        return PurchaseOrderLog::query()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'opportunity_id' => $purchaseOrder->opportunity_id,
            'action' => $action,
            'number' => $purchaseOrder->number,
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->display_name,
            'snapshot' => $purchaseOrder->toLogSnapshot(),
            'changes' => $changes,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function captureForDiff(PurchaseOrder $purchaseOrder): array
    {
        $purchaseOrder->loadMissing(['items', 'vendor', 'salesOrder']);

        $items = [];
        foreach ($purchaseOrder->items as $index => $item) {
            $name = trim((string) ($item->product_name ?: $item->opportunity_product_name));
            $items[] = [
                'name' => $name !== '' ? $name : 'Item',
                'brand' => trim((string) $item->brand),
                'qty' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
                'description' => trim(strip_tags((string) $item->description)),
                'note' => trim((string) $item->note),
                'index' => $index,
            ];
        }

        $vendor = $purchaseOrder->displayVendorName();

        return [
            'number' => trim((string) $purchaseOrder->number),
            'vendor' => $vendor === '—' ? '' : $vendor,
            'payment_term' => $purchaseOrder->paymentTermLabel(),
            'sales_order' => trim((string) ($purchaseOrder->salesOrder?->displayNumber() ?? '')),
            'report_top' => trim((string) $purchaseOrder->report_top),
            'approval' => $purchaseOrder->approvalLabel(),
            'approval_note' => trim((string) $purchaseOrder->approval_note),
            'total' => round((float) $purchaseOrder->total, 2),
            'items' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     */
    public function recordIfChanged(PurchaseOrder $purchaseOrder, array $before, ?User $actor = null): void
    {
        $diff = $this->diffPurchaseOrder($before, $this->captureForDiff($purchaseOrder));
        if (! $this->diffHasChanges($diff)) {
            return;
        }

        $diff['summary'] = $this->summarizeDiff((string) $purchaseOrder->number, $diff);
        $this->recordLog($purchaseOrder, PurchaseOrderLog::ACTION_UPDATED, $diff, $actor);
    }

    /**
     * @param  array<string, mixed>  $before
     */
    protected function recordDecision(PurchaseOrder $purchaseOrder, string $action, array $before, ?string $note): void
    {
        $diff = $this->diffPurchaseOrder($before, $this->captureForDiff($purchaseOrder));
        $verb = $action === PurchaseOrderLog::ACTION_APPROVED ? 'disetujui' : 'ditolak';
        $diff['summary'] = 'PO '.$purchaseOrder->number.' '.$verb;
        $this->recordLog($purchaseOrder, $action, $diff);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{fields: list<array<string, mixed>>, products: array{added: list<array<string, mixed>>, removed: list<array<string, mixed>>, changed: list<array<string, mixed>>}}
     */
    public function diffPurchaseOrder(array $before, array $after): array
    {
        $labels = [
            'number' => 'No. PO',
            'vendor' => 'Vendor',
            'payment_term' => 'Kondisi',
            'sales_order' => 'Sales Order',
            'report_top' => 'TOP laporan',
            'approval' => 'Status approval',
            'approval_note' => 'Catatan approval',
            'total' => 'Total exclude',
        ];

        $fields = [];
        foreach ($labels as $key => $label) {
            $fromRaw = $before[$key] ?? '';
            $toRaw = $after[$key] ?? '';
            $from = $key === 'total' ? $this->moneyLabel($fromRaw) : $this->stringifyDiffValue($fromRaw);
            $to = $key === 'total' ? $this->moneyLabel($toRaw) : $this->stringifyDiffValue($toRaw);
            if ($from === $to) {
                continue;
            }
            $fields[] = [
                'field' => 'po_'.$key,
                'label' => $label,
                'from' => $from,
                'to' => $to,
                'from_display' => $from !== '' ? $from : '—',
                'to_display' => $to !== '' ? $to : '—',
            ];
        }

        return [
            'fields' => $fields,
            'products' => $this->diffPurchaseOrderItems(
                is_array($before['items'] ?? null) ? $before['items'] : [],
                is_array($after['items'] ?? null) ? $after['items'] : []
            ),
        ];
    }

    /**
     * @param  array{fields?: list<array<string, mixed>>, products?: array<string, mixed>}  $diff
     */
    public function diffHasChanges(array $diff): bool
    {
        if (($diff['fields'] ?? []) !== []) {
            return true;
        }

        $products = $diff['products'] ?? [];

        return ($products['added'] ?? []) !== []
            || ($products['removed'] ?? []) !== []
            || ($products['changed'] ?? []) !== [];
    }

    /**
     * @param  array{fields?: list<array<string, mixed>>, products?: array<string, mixed>}  $diff
     */
    public function summarizeDiff(string $number, array $diff): string
    {
        $bits = [];
        foreach (array_slice($diff['fields'] ?? [], 0, 5) as $field) {
            $bits[] = ((string) ($field['label'] ?? 'Field')).' '
                .((string) ($field['from_display'] ?? '—')).' → '
                .((string) ($field['to_display'] ?? '—'));
        }
        if (($diff['products']['added'] ?? []) !== []) {
            $bits[] = count($diff['products']['added']).' item ditambah';
        }
        if (($diff['products']['removed'] ?? []) !== []) {
            $bits[] = count($diff['products']['removed']).' item dihapus';
        }
        if (($diff['products']['changed'] ?? []) !== []) {
            $bits[] = count($diff['products']['changed']).' item diubah';
        }

        $detail = $bits !== [] ? implode(' · ', $bits) : 'ada perubahan';

        return mb_substr('PO '.$number.' diubah: '.$detail, 0, 500);
    }

    /**
     * @param  list<array<string, mixed>>  $before
     * @param  list<array<string, mixed>>  $after
     * @return array{added: list<array<string, mixed>>, removed: list<array<string, mixed>>, changed: list<array<string, mixed>>}
     */
    protected function diffPurchaseOrderItems(array $before, array $after): array
    {
        $labels = [
            'name' => 'Nama produk',
            'brand' => 'Brand',
            'qty' => 'Qty',
            'unit_price' => 'Harga',
            'line_total' => 'Jumlah',
            'description' => 'Spesifikasi',
            'note' => 'Catatan',
        ];
        $moneyKeys = ['unit_price', 'line_total'];

        $beforeByKey = $this->indexPoItems($before);
        $afterByKey = $this->indexPoItems($after);
        $added = [];
        $removed = [];
        $changed = [];

        foreach ($beforeByKey as $key => $old) {
            if (! isset($afterByKey[$key])) {
                $removed[] = [
                    'name' => (string) ($old['name'] ?? 'Item'),
                    'quantity' => $old['qty'] ?? null,
                ];
                continue;
            }

            $new = $afterByKey[$key];
            $itemChanges = [];
            foreach ($labels as $field => $label) {
                $from = in_array($field, $moneyKeys, true)
                    ? $this->moneyLabel($old[$field] ?? 0)
                    : $this->stringifyDiffValue($old[$field] ?? '');
                $to = in_array($field, $moneyKeys, true)
                    ? $this->moneyLabel($new[$field] ?? 0)
                    : $this->stringifyDiffValue($new[$field] ?? '');
                if ($field === 'qty') {
                    $from = $this->qtyLabel($old[$field] ?? '');
                    $to = $this->qtyLabel($new[$field] ?? '');
                }
                if ($from === $to) {
                    continue;
                }
                $itemChanges[] = [
                    'field' => $field,
                    'label' => $label,
                    'from_display' => $from !== '' ? $from : '—',
                    'to_display' => $to !== '' ? $to : '—',
                ];
            }

            if ($itemChanges !== []) {
                $changed[] = [
                    'name' => (string) ($new['name'] ?? $old['name'] ?? 'Item'),
                    'changes' => $itemChanges,
                ];
            }
        }

        foreach ($afterByKey as $key => $new) {
            if (isset($beforeByKey[$key])) {
                continue;
            }
            $added[] = [
                'name' => (string) ($new['name'] ?? 'Item'),
                'quantity' => $new['qty'] ?? null,
            ];
        }

        return compact('added', 'removed', 'changed');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, array<string, mixed>>
     */
    protected function indexPoItems(array $items): array
    {
        $counts = [];
        $indexed = [];
        foreach ($items as $row) {
            $name = mb_strtolower(trim((string) ($row['name'] ?? 'item')));
            $counts[$name] = ($counts[$name] ?? 0) + 1;
            $indexed[$name.'#'.$counts[$name]] = $row;
        }

        return $indexed;
    }

    protected function stringifyDiffValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }
        if (is_float($value) || is_int($value)) {
            $text = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');

            return $text === '' ? '0' : $text;
        }

        return trim((string) $value);
    }

    protected function moneyLabel(mixed $value): string
    {
        return 'Rp '.number_format((float) $value, 0, ',', '.');
    }

    protected function qtyLabel(mixed $value): string
    {
        $number = (float) $value;
        if (abs($number - round($number)) < 0.001) {
            return (string) (int) round($number);
        }

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }
}
