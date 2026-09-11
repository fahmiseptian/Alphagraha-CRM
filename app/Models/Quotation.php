<?php

namespace App\Models;

use App\Models\Espo\Account;
use App\Models\Espo\Opportunity;
use App\Models\OpportunityLog;
use App\Services\OpportunityLogService;
use App\Support\OpportunityProductPricing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use HasFactory;

    protected $table = 'crm_quotations';

    protected $fillable = [
        'number', 'base_number', 'account_id', 'opportunity_id', 'customer_name', 'company_name', 'customer_email',
        'customer_phone', 'customer_address', 'quotation_date', 'valid_until', 'status',
        'crm_margin_status', 'crm_margin_percent', 'crm_margin_threshold',
        'crm_margin_nominal', 'crm_margin_nominal_threshold',
        'crm_margin_requested_at', 'crm_margin_reviewed_by', 'crm_margin_reviewed_at', 'crm_margin_note',
        'currency', 'subtotal', 'discount', 'tax_percent', 'tax_amount', 'total',
        'notes', 'terms', 'template_id', 'created_by', 'revision', 'document_revision', 'sent_at',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'valid_until' => 'date',
        'sent_at' => 'datetime',
        'crm_margin_requested_at' => 'datetime',
        'crm_margin_reviewed_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'crm_margin_percent' => 'decimal:2',
        'crm_margin_threshold' => 'decimal:2',
        'crm_margin_nominal' => 'decimal:2',
        'crm_margin_nominal_threshold' => 'decimal:2',
    ];

    public const STATUSES = [
        'draft' => 'Draft',
        'sent' => 'Sent',
    ];

    public const MARGIN_PENDING = 'pending';

    public const MARGIN_APPROVED = 'approved';

    public const MARGIN_REJECTED = 'rejected';

    public function marginNeedsApproval(): bool
    {
        return $this->crm_margin_status === self::MARGIN_PENDING;
    }

    public function isMarginLocked(): bool
    {
        return in_array($this->crm_margin_status, [self::MARGIN_PENDING, self::MARGIN_REJECTED], true);
    }

    public function canBeSent(): bool
    {
        return ! $this->isMarginLocked();
    }

    public function marginStatusLabel(): string
    {
        return match ($this->crm_margin_status) {
            self::MARGIN_PENDING => 'Menunggu approval margin',
            self::MARGIN_APPROVED => 'Margin disetujui',
            self::MARGIN_REJECTED => 'Margin ditolak',
            default => '—',
        };
    }
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(QuotationRevision::class)->latest();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(QuotationTemplate::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function marginReviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'crm_margin_reviewed_by');
    }

    public function marginReviewerName(): ?string
    {
        if (! $this->crm_margin_reviewed_by) {
            return null;
        }

        $this->loadMissing('marginReviewedByUser');

        return $this->marginReviewedByUser?->display_name;
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    /**
     * Hitung ulang subtotal, pajak, dan total dari item.
     */
    public function recalculateTotals(): void
    {
        $subtotal = $this->items->sum('total');
        $afterDiscount = max($subtotal - (float) $this->discount, 0);
        $taxAmount = round($afterDiscount * ((float) $this->tax_percent / 100), 2);

        $this->subtotal = $subtotal;
        $this->tax_amount = $taxAmount;
        $this->total = $afterDiscount + $taxAmount;
    }

    /**
     * Total margin dari semua item penawaran.
     */
    public function totalItemsMargin(): float
    {
        $this->loadMissing('items');

        return round($this->items->sum(fn (QuotationItem $item) => $item->lineMargin()), 2);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function statusColor(): string
    {
        return [
            'draft' => 'gray',
            'sent' => 'blue',
        ][$this->status] ?? 'gray';
    }

    /**
     * Apakah dokumen sudah pernah dikirim (status sent pernah terjadi).
     * Tetap true setelah revisi mengembalikan status ke draft, agar histori R tidak dihapus.
     */
    public function hasBeenSent(): bool
    {
        return $this->sent_at !== null
            || $this->status === 'sent'
            || (int) $this->document_revision > 0;
    }

    /**
     * Apakah edit isi saat ini akan menaikkan nomor revisi dokumen (R1, R2, …).
     * Hanya saat status masih Sent — Draft setelah R tidak naik R lagi sampai dikirim ulang.
     */
    public function willBumpDocumentRevisionOnEdit(): bool
    {
        return $this->status === 'sent';
    }

    /**
     * Label tampilan nomor dokumen (dengan -Rn bila ada).
     */
    public function displayNumber(): string
    {
        return (string) $this->number;
    }

    public function isDocumentRevision(): bool
    {
        return (int) $this->document_revision > 0;
    }

    /**
     * Mapping Product List Opportunity → payload Quotation Items (1:1).
     * description/unit QO dipertahankan per index bila ada.
     *
     * @param  \Illuminate\Support\Collection|array  $products
     * @param  \Illuminate\Support\Collection|array|null  $existingItems
     */
    public static function itemsPayloadFromOpportunityProducts($products, $existingItems = null): array
    {
        $existing = collect($existingItems ?? [])->values();

        return collect($products)->values()
            ->filter(fn ($p) => filled($p['name'] ?? null))
            ->values()
            ->map(function ($p, $i) use ($existing) {
            $prev = $existing->get($i);
            $list = (float) ($p['sell_exclude'] ?? 0);
            $discount = (float) ($p['discount_exclude'] ?? 0);
            $billed = (float) ($p['effective_sell_exclude'] ?? ($discount > 0 ? $discount : $list));

            $prevDescription = is_object($prev)
                ? (string) ($prev->description ?? '')
                : (string) ($prev['description'] ?? '');
            $prevUnit = is_object($prev)
                ? (string) ($prev->unit ?? '')
                : (string) ($prev['unit'] ?? '');

            return [
                'name' => (string) ($p['name'] ?? ''),
                'description' => $prevDescription,
                'quantity' => (float) ($p['quantity'] ?: 1),
                'unit' => $prevUnit,
                'unit_price' => $billed,
                'tax_category' => (string) ($p['tax_category'] ?? ''),
                'item_kind' => (string) ($p['item_kind'] ?? ''),
                'royalty_type' => OpportunityProductPricing::normalizeRoyaltyType(
                    $p['royalty_type'] ?? (($p['has_royalty'] ?? false) ? OpportunityProductPricing::ROYALTY_LUAR : '')
                ),
                'has_royalty' => ! empty($p['has_royalty'])
                    || OpportunityProductPricing::normalizeRoyaltyType($p['royalty_type'] ?? '') !== '',
                'sell_exclude' => $list,
                'discount_exclude' => $discount,
                'cost_exclude' => (float) ($p['cost_exclude'] ?? 0),
                'shipping_exclude' => (float) ($p['shipping_exclude'] ?? 0),
                'vendor' => (string) ($p['vendor'] ?? ''),
                'brand' => (string) ($p['brand'] ?? ''),
                'image' => (string) ($p['image'] ?? ''),
            ];
        })->all();
    }

    /**
     * Fingerprint field produk yang harus 1:1 dengan Opportunity (abaikan description/unit QO).
     */
    public static function itemsSyncFingerprint(array $payload): string
    {
        $core = collect($payload)->map(fn ($row) => [
            'name' => (string) ($row['name'] ?? ''),
            'quantity' => round((float) ($row['quantity'] ?? 0), 4),
            'unit_price' => round((float) ($row['unit_price'] ?? 0), 2),
            'sell_exclude' => round((float) ($row['sell_exclude'] ?? 0), 2),
            'discount_exclude' => round((float) ($row['discount_exclude'] ?? 0), 2),
            'cost_exclude' => round((float) ($row['cost_exclude'] ?? 0), 2),
            'shipping_exclude' => round((float) ($row['shipping_exclude'] ?? 0), 2),
            'tax_category' => (string) ($row['tax_category'] ?? ''),
            'item_kind' => (string) ($row['item_kind'] ?? ''),
            'royalty_type' => OpportunityProductPricing::normalizeRoyaltyType(
                $row['royalty_type'] ?? (($row['has_royalty'] ?? false) ? OpportunityProductPricing::ROYALTY_LUAR : '')
            ),
            'has_royalty' => ! empty($row['has_royalty'])
                || OpportunityProductPricing::normalizeRoyaltyType($row['royalty_type'] ?? '') !== '' ? 1 : 0,
            'vendor' => (string) ($row['vendor'] ?? ''),
            'brand' => (string) ($row['brand'] ?? ''),
            'image' => (string) ($row['image'] ?? ''),
        ])->values()->all();

        return md5(json_encode($core));
    }

    public function currentItemsSyncFingerprint(): string
    {
        $this->loadMissing('items');

        $payload = $this->items->map(fn ($i) => [
            'name' => $i->name,
            'quantity' => $i->quantity,
            'unit_price' => $i->unit_price,
            'sell_exclude' => $i->sell_exclude ?? 0,
            'discount_exclude' => $i->discount_exclude ?? 0,
            'cost_exclude' => $i->cost_exclude ?? 0,
            'shipping_exclude' => $i->shipping_exclude ?? 0,
            'tax_category' => $i->tax_category ?? '',
            'item_kind' => $i->item_kind ?? '',
            'royalty_type' => OpportunityProductPricing::normalizeRoyaltyType(
                $i->royalty_type ?? (($i->has_royalty ?? false) ? OpportunityProductPricing::ROYALTY_LUAR : '')
            ),
            'has_royalty' => (bool) ($i->has_royalty ?? false)
                || OpportunityProductPricing::normalizeRoyaltyType($i->royalty_type ?? '') !== '',
            'vendor' => $i->vendor ?? '',
            'brand' => $i->brand ?? '',
            'image' => $i->image ?? '',
        ])->all();

        return self::itemsSyncFingerprint($payload);
    }

    /**
     * Timpa quotation items dari Product List Opportunity (1:1).
     */
    public function syncItemsFromOpportunityProducts(Opportunity $opportunity): void
    {
        $this->loadMissing('items');
        $payload = self::itemsPayloadFromOpportunityProducts($opportunity->products, $this->items);

        $this->items()->delete();

        foreach (array_values($payload) as $index => $item) {
            $quantity = (float) $item['quantity'];
            $listPrice = (float) $item['sell_exclude'];
            $discountExclude = (float) ($item['discount_exclude'] ?? 0);
            $shippingExclude = (float) ($item['shipping_exclude'] ?? 0);
            $billedPrice = $discountExclude > 0 ? $discountExclude : $listPrice;

            $this->items()->create([
                'name' => $item['name'],
                'description' => $item['description'] !== '' ? $item['description'] : null,
                'quantity' => $quantity,
                'unit' => $item['unit'] !== '' ? $item['unit'] : null,
                'unit_price' => $billedPrice,
                'total' => round($quantity * $billedPrice, 2),
                'tax_category' => $item['tax_category'] !== '' ? $item['tax_category'] : null,
                'item_kind' => $item['item_kind'] !== '' ? $item['item_kind'] : null,
                'royalty_type' => OpportunityProductPricing::normalizeRoyaltyType($item['royalty_type'] ?? '') ?: null,
                'has_royalty' => ! empty($item['has_royalty'])
                    || OpportunityProductPricing::normalizeRoyaltyType($item['royalty_type'] ?? '') !== '',
                'sell_exclude' => $listPrice,
                'cost_exclude' => (float) $item['cost_exclude'],
                'discount_exclude' => $discountExclude > 0 ? $discountExclude : null,
                'shipping_exclude' => $shippingExclude > 0 ? $shippingExclude : null,
                'vendor' => $item['vendor'] !== '' ? $item['vendor'] : null,
                'brand' => ($item['brand'] ?? '') !== '' ? $item['brand'] : null,
                'image' => ($item['image'] ?? '') !== '' ? $item['image'] : null,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * Sinkronkan Quotation Items → Product List Opportunity (1:1).
     *
     * @return array{synced: bool, notify_margin: bool, notify_discount: bool}
     */
    public function syncItemsToLinkedOpportunity(): array
    {
        if (! $this->opportunity_id) {
            return ['synced' => false, 'notify_margin' => false, 'notify_discount' => false];
        }

        $opportunity = $this->relationLoaded('opportunity')
            ? $this->opportunity
            : $this->opportunity()->first();

        if (! $opportunity) {
            return ['synced' => false, 'notify_margin' => false, 'notify_discount' => false];
        }

        $this->loadMissing('items');

        $pricingBefore = $opportunity->productsPricingFingerprint();
        $before = app(OpportunityLogService::class)->capture($opportunity);

        if (! $opportunity->replaceProductsFromQuotationItems($this->items)) {
            return ['synced' => false, 'notify_margin' => false, 'notify_discount' => false];
        }

        $pricingChanged = $pricingBefore !== $opportunity->productsPricingFingerprint();
        $notifyDiscount = $opportunity->reopenDiscountApprovalIfPricingChanged($pricingChanged);
        $notifyMargin = $opportunity->refreshMarginApprovalState(isNew: false);
        $opportunity->modified_at = now()->format('Y-m-d H:i:s');
        $opportunity->modified_by_id = auth()->id();
        $opportunity->save();
        app(OpportunityLogService::class)->record(
            $opportunity,
            OpportunityLog::ACTION_QUOTATION_SYNCED,
            $before
        );
        $opportunity->setRelation('quotation', $this);
        $opportunity->syncLinkedQuotationMarginApproval();

        return [
            'synced' => true,
            'notify_margin' => $notifyMargin,
            'notify_discount' => $notifyDiscount,
        ];
    }
}
