<?php

namespace App\Services;

use App\Models\Espo\Opportunity;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
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
     * Buat PO baru (1 PO = 1 vendor) beserta item & referensi harga; total dihitung server-side.
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
        ?string $vendorName = null
    ): PurchaseOrder {
        return DB::transaction(function () use ($opportunity, $number, $items, $paymentTerm, $createdBy, $vendorId, $vendorName) {
            [$vendorId, $vendorName] = $this->resolveVendor($vendorId, $vendorName);

            $po = PurchaseOrder::query()->create([
                'opportunity_id' => $opportunity->id,
                'number' => $number,
                'vendor_id' => $vendorId,
                'vendor_name' => $vendorName,
                'payment_term' => $this->normalizePaymentTerm($paymentTerm),
                'currency' => $opportunity->amount_currency ?: 'IDR',
                'total' => 0,
                'created_by' => $createdBy,
            ]);

            $this->syncItems($po, $items, $createdBy);
            $this->syncOpportunityCostsFromPo($opportunity);
            app(NotificationService::class)->markActioned(
                \App\Models\CrmNotification::TYPE_SALES_ORDER_CREATED,
                ['opportunity_id' => $opportunity->id]
            );

            return $po->fresh(['items.vendorQuotes', 'vendor']);
        });
    }

    /**
     * Buat banyak PO sekaligus dari rencana pembelian.
     * Satu vendor = satu PO; item dari produk berbeda digabung ke PO vendor yang sama.
     * Kondisi TOP/Cash mengikuti per PO (default dari master vendor).
     *
     * @param  list<array{number:string,vendor_id:int,vendor_name?:?string,payment_term?:string,items:list<array<string,mixed>>}>  $pos
     * @return list<PurchaseOrder>
     */
    public function createMany(
        Opportunity $opportunity,
        array $pos,
        ?string $createdBy = null
    ): array {
        return DB::transaction(function () use ($opportunity, $pos, $createdBy) {
            $created = [];

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
                    'number' => trim((string) ($poData['number'] ?? '')),
                    'vendor_id' => $vendorId,
                    'vendor_name' => $vendorName,
                    'payment_term' => $term,
                    'currency' => $opportunity->amount_currency ?: 'IDR',
                    'total' => 0,
                    'created_by' => $createdBy,
                ]);

                $this->syncItems($po, $poData['items'] ?? [], $createdBy);
                $created[] = $po->fresh(['items.vendorQuotes', 'vendor']);
            }

            $this->syncOpportunityCostsFromPo($opportunity);
            app(NotificationService::class)->markActioned(
                \App\Models\CrmNotification::TYPE_SALES_ORDER_CREATED,
                ['opportunity_id' => $opportunity->id]
            );

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
        $top = CustomerTop::isValid($top) ? (string) $top : CustomerTop::DAYS_30;

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
        ?string $vendorName = null
    ): PurchaseOrder {
        return DB::transaction(function () use ($purchaseOrder, $number, $items, $paymentTerm, $updatedBy, $vendorId, $vendorName) {
            [$vendorId, $vendorName] = $this->resolveVendor($vendorId, $vendorName);

            $purchaseOrder->update([
                'number' => $number,
                'vendor_id' => $vendorId,
                'vendor_name' => $vendorName,
                'payment_term' => $this->normalizePaymentTerm($paymentTerm),
            ]);
            $this->syncItems($purchaseOrder, $items, $updatedBy);

            $opportunity = $purchaseOrder->opportunity()->first();
            if ($opportunity) {
                $this->syncOpportunityCostsFromPo($opportunity);
                app(NotificationService::class)->markActioned(
                    \App\Models\CrmNotification::TYPE_SALES_ORDER_CREATED,
                    ['opportunity_id' => $opportunity->id]
                );
            }

            return $purchaseOrder->fresh(['items.vendorQuotes', 'vendor']);
        });
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
                'top' => CustomerTop::isValid($row['top'] ?? null) ? (string) $row['top'] : CustomerTop::DAYS_30,
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
}
