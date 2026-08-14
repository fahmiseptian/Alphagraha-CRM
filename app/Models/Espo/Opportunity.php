<?php

namespace App\Models\Espo;

use App\Models\Espo\Concerns\EspoEntity;
use App\Models\OpportunityNote;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Support\CustomerTop;
use App\Support\OpportunityProductPricing;
use App\Support\PaymentLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Peluang penjualan / deal (EspoCRM: Opportunity).
 */
class Opportunity extends Model implements HasMedia
{
    use EspoEntity, InteractsWithMedia;

    protected $table = 'opportunity';

    protected $fillable = [
        'name', 'account_id', 'crm_top', 'company', 'stage', 'type', 'amount', 'amount_currency',
        'close_date', 'probability', 'lead_source', 'description', 'crm_lost_reason', 'assigned_user_id',
        'contact_id', 'vendor', 'crm_item_brand', 'crm_item_image',
        'crm_tax_category', 'crm_item_kind', 'crm_has_royalty', 'crm_royalty_type', 'crm_sell_exclude', 'crm_cost_exclude',
        'crm_cost_in_usd', 'crm_cost_fx_code', 'crm_cost_usd', 'crm_cost_usd_rate',
        'crm_item_discount',
        'crm_item_shipping',
        'crm_won_margin',
        'crm_shipping_cost',
        'crm_has_shipping_charge', 'crm_shipping_sell',
        'crm_has_discount', 'crm_discount_amount', 'crm_discount_status',
        'crm_discount_requested_by', 'crm_discount_requested_at',
        'crm_discount_reviewed_by', 'crm_discount_reviewed_at', 'crm_discount_note',
        'crm_margin_status', 'crm_margin_percent', 'crm_margin_threshold',
        'crm_margin_nominal', 'crm_margin_nominal_threshold',
        'crm_margin_requested_at', 'crm_margin_reviewed_by', 'crm_margin_reviewed_at', 'crm_margin_note',
    ];

    protected $casts = [
        'item' => 'array',
        'quantity' => 'array',
        'price' => 'array',
        'cost' => 'array',
        'vendor' => 'array',
        'crm_item_brand' => 'array',
        'crm_item_image' => 'array',
        'crm_tax_category' => 'array',
        'crm_item_kind' => 'array',
        'crm_has_royalty' => 'array',
        'crm_royalty_type' => 'array',
        'crm_sell_exclude' => 'array',
        'crm_cost_exclude' => 'array',
        'crm_cost_in_usd' => 'array',
        'crm_cost_fx_code' => 'array',
        'crm_cost_usd' => 'array',
        'crm_cost_usd_rate' => 'array',
        'crm_item_discount' => 'array',
        'crm_item_shipping' => 'array',
        'crm_won_margin' => 'float',
        'crm_shipping_cost' => 'float',
        'crm_has_shipping_charge' => 'boolean',
        'crm_shipping_sell' => 'float',
        'crm_has_discount' => 'boolean',
        'crm_discount_amount' => 'float',
        'crm_discount_requested_at' => 'datetime',
        'crm_discount_reviewed_at' => 'datetime',
        'crm_margin_percent' => 'float',
        'crm_margin_threshold' => 'float',
        'crm_margin_nominal' => 'float',
        'crm_margin_nominal_threshold' => 'float',
        'crm_margin_requested_at' => 'datetime',
        'crm_margin_reviewed_at' => 'datetime',
    ];

    public const DISCOUNT_PENDING = 'pending';

    public const DISCOUNT_APPROVED = 'approved';

    public const DISCOUNT_REJECTED = 'rejected';

    public const MARGIN_PENDING = 'pending';

    public const MARGIN_APPROVED = 'approved';

    public const MARGIN_REJECTED = 'rejected';

    public const OPEN_STAGES = ['Prospecting', 'Qualification', 'Proposal', 'Negotiation'];

    /** Stage awal — belum butuh approval margin / diskon tambahan. */
    public const NO_APPROVAL_STAGES = ['Prospecting', 'Qualification'];

    public const PROSPECTING_STAGE = 'Prospecting';

    public const QUALIFICATION_STAGE = 'Qualification';

    public const WON_STAGE = 'Closed Won';
    public const LOST_STAGE = 'Closed Lost';

    /** Kolom pipeline Kanban (tanpa Closed Lost). */
    public const KANBAN_STAGES = [
        'Prospecting', 'Qualification', 'Proposal', 'Negotiation', 'Closed Won',
    ];

    /** Daftar stage untuk dropdown edit. */
    public const STAGES = [
        'Prospecting', 'Qualification', 'Proposal', 'Negotiation', 'Closed Won', 'Closed Lost',
    ];

    /** Stage yang boleh dipilih saat create (maksimal Proposal). */
    public const CREATE_STAGES = [
        'Prospecting', 'Qualification', 'Proposal',
    ];

    /** Probabilitas default per stage saat move over. */
    public const STAGE_PROBABILITIES = [
        'Prospecting' => 10,
        'Qualification' => 20,
        'Proposal' => 50,
        'Negotiation' => 80,
        'Closed Won' => 100,
        'Closed Lost' => 0,
    ];

    public static function defaultProbabilityForStage(string $stage): int
    {
        return self::STAGE_PROBABILITIES[$stage] ?? 10;
    }

    public function nextStage(): ?string
    {
        $index = array_search($this->stage, self::OPEN_STAGES, true);

        if ($index === false || $index >= count(self::OPEN_STAGES) - 1) {
            return null;
        }

        return self::OPEN_STAGES[$index + 1];
    }

    /**
     * Opsi penutupan deal setelah tahap Negotiation.
     *
     * @return list<string>
     */
    public function closingStageOptions(): array
    {
        if ($this->stage !== 'Negotiation') {
            return [];
        }

        $options = [self::LOST_STAGE];
        if ($this->canMoveToClosedWon()) {
            array_unshift($options, self::WON_STAGE);
        }

        return $options;
    }

    /**
     * Masih ada approval margin/diskon yang belum selesai.
     */
    public function hasPendingApprovals(): bool
    {
        if ($this->skipsApproval()) {
            return false;
        }

        return $this->marginNeedsApproval() || $this->discountNeedsAttention();
    }

    public function canMoveToClosedWon(): bool
    {
        return ! $this->hasPendingApprovals();
    }

    public function closedWonBlockReason(): ?string
    {
        if ($this->skipsApproval() || $this->canMoveToClosedWon()) {
            return null;
        }

        $reasons = [];
        if ($this->marginNeedsApproval()) {
            $reasons[] = 'margin masih menunggu approval Superadmin';
        }
        if ($this->discountNeedsAttention()) {
            $reasons[] = 'diskon tambahan masih menunggu approval Superadmin';
        }

        return 'Opportunity tidak bisa Closed Won: '.implode(' dan ', $reasons).'.';
    }

    /** Jenis pengadaan EspoCRM (kolom type). */
    public const TYPES = [
        'Quotation', 'PL', 'E-Purchasing', 'Tender', 'E-Auction',
        'Tender Cepat', 'Siplah', 'Bela Pengadaan', 'Simpel',
    ];

    /** Perusahaan internal (kolom company). */
    public const COMPANIES = [
        'Alpha Graha Computindo',
        'Elite Proxy Sistem',
        'Power Sistem Integrasi',
    ];

    /** Sumber lead standar EspoCRM. */
    public const LEAD_SOURCES = [
        'WhatsApp', 'Call', 'Email', 'Existing Customer', 'Partner', 'Public Relations',
        'Web Site', 'Campaign', 'Other', 'Social Media', 'Referral', 'Other',
    ];

    public function espoEntityType(): string
    {
        return 'Opportunity';
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(EspoUser::class, 'assigned_user_id');
    }

    public function discountRequestedByUser(): BelongsTo
    {
        return $this->belongsTo(EspoUser::class, 'crm_discount_requested_by');
    }

    public function discountReviewedByUser(): BelongsTo
    {
        return $this->belongsTo(EspoUser::class, 'crm_discount_reviewed_by');
    }

    public function marginReviewedByUser(): BelongsTo
    {
        return $this->belongsTo(EspoUser::class, 'crm_margin_reviewed_by');
    }

    public function discountRequesterName(): ?string
    {
        if (! $this->crm_discount_requested_by) {
            return null;
        }

        $this->loadMissing('discountRequestedByUser');

        return $this->discountRequestedByUser?->display_name;
    }

    public function discountReviewerName(): ?string
    {
        if (! $this->crm_discount_reviewed_by) {
            return null;
        }

        $this->loadMissing('discountReviewedByUser');

        return $this->discountReviewedByUser?->display_name;
    }

    public function marginReviewerName(): ?string
    {
        if (! $this->crm_margin_reviewed_by) {
            return null;
        }

        $this->loadMissing('marginReviewedByUser');

        return $this->marginReviewedByUser?->display_name;
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'entity_team', 'entity_id', 'team_id')
            ->where('entity_team.entity_type', 'Opportunity')
            ->where('entity_team.deleted', 0);
    }

    /**
     * Penawaran yang ditautkan (1 opportunity : 1 penawaran).
     */
    public function quotation(): HasOne
    {
        return $this->hasOne(Quotation::class, 'opportunity_id');
    }

    /**
     * Dokumen lama dari EspoCRM (via pivot document_opportunity).
     */
    public function legacyDocuments(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'document_opportunity', 'opportunity_id', 'document_id')
            ->withPivot('deleted')
            ->wherePivot('deleted', 0)
            ->orderByDesc('document.created_at');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(OpportunityNote::class, 'opportunity_id')->latest();
    }

    /**
     * Purchase Order (PO) — 1 opportunity : banyak PO.
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'opportunity_id')->latest();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents')->useDisk('public');
    }

    /**
     * Daftar produk dari kolom paralel EspoCRM (item/quantity/price/cost,
     * masing-masing berupa JSON array). Mengembalikan koleksi baris produk.
     */
    public function getProductsAttribute(): Collection
    {
        $names = array_values((array) ($this->item ?? []));
        $qtys = array_values((array) ($this->quantity ?? []));
        $prices = array_values((array) ($this->price ?? []));
        $costs = array_values((array) ($this->cost ?? []));
        $vendors = array_values((array) ($this->vendor ?? []));
        $brands = array_values((array) ($this->crm_item_brand ?? []));
        $images = array_values((array) ($this->crm_item_image ?? []));
        $taxCategories = array_values((array) ($this->crm_tax_category ?? []));
        $itemKinds = array_values((array) ($this->crm_item_kind ?? []));
        $hasRoyaltyFlags = array_values((array) ($this->crm_has_royalty ?? []));
        $royaltyTypes = array_values((array) ($this->crm_royalty_type ?? []));
        $sellExcludes = array_values((array) ($this->crm_sell_exclude ?? []));
        $costExcludes = array_values((array) ($this->crm_cost_exclude ?? []));
        $costInUsdFlags = array_values((array) ($this->crm_cost_in_usd ?? []));
        $costFxCodes = array_values((array) ($this->crm_cost_fx_code ?? []));
        $costUsds = array_values((array) ($this->crm_cost_usd ?? []));
        $costUsdRates = array_values((array) ($this->crm_cost_usd_rate ?? []));
        $itemDiscounts = array_values((array) ($this->crm_item_discount ?? []));
        $itemShippings = array_values((array) ($this->crm_item_shipping ?? []));

        $count = max(
            count($names), count($qtys), count($prices), count($costs), count($vendors), count($brands), count($images),
            count($taxCategories), count($itemKinds), count($hasRoyaltyFlags), count($royaltyTypes),
            count($sellExcludes), count($costExcludes),
            count($itemDiscounts), count($itemShippings), count($costInUsdFlags), count($costFxCodes), count($costUsds), count($costUsdRates)
        );

        if ($count === 0) {
            return collect();
        }

        return collect(range(0, $count - 1))
            ->map(function ($i) use (
                $names, $qtys, $prices, $costs, $vendors, $brands, $images, $taxCategories, $itemKinds, $hasRoyaltyFlags, $royaltyTypes,
                $sellExcludes, $costExcludes, $itemDiscounts, $itemShippings, $costInUsdFlags, $costFxCodes, $costUsds, $costUsdRates
            ) {
                $priceInclude = (float) ($prices[$i] ?? 0);
                $costInclude = (float) ($costs[$i] ?? 0);
                $sellExclude = isset($sellExcludes[$i]) && $sellExcludes[$i] !== ''
                    ? (float) $sellExcludes[$i]
                    : OpportunityProductPricing::excludeFromInclude($priceInclude);
                $costExclude = isset($costExcludes[$i]) && $costExcludes[$i] !== ''
                    ? (float) $costExcludes[$i]
                    : OpportunityProductPricing::excludeFromInclude($costInclude);
                $itemDiscount = isset($itemDiscounts[$i]) && $itemDiscounts[$i] !== ''
                    ? (float) $itemDiscounts[$i]
                    : 0;
                $itemShipping = isset($itemShippings[$i]) && $itemShippings[$i] !== ''
                    ? (float) $itemShippings[$i]
                    : 0;
                $taxCategory = (string) ($taxCategories[$i] ?? OpportunityProductPricing::TAX_NON_WAPU);
                $itemKind = (string) ($itemKinds[$i] ?? OpportunityProductPricing::KIND_BARANG);
                $royaltyType = OpportunityProductPricing::normalizeRoyaltyType(
                    $royaltyTypes[$i] ?? (($hasRoyaltyFlags[$i] ?? false) ? OpportunityProductPricing::ROYALTY_LUAR : '')
                );
                $costForeign = in_array((string) ($costInUsdFlags[$i] ?? '0'), ['1', 'true', 'yes'], true);
                $fxCode = strtoupper(trim((string) ($costFxCodes[$i] ?? '')));
                if ($costForeign && $fxCode === '') {
                    $fxCode = 'USD';
                }
                $costFx = isset($costUsds[$i]) && $costUsds[$i] !== '' ? (float) $costUsds[$i] : 0.0;
                $fxRate = isset($costUsdRates[$i]) && $costUsdRates[$i] !== '' ? (float) $costUsdRates[$i] : 0.0;
                $image = trim((string) ($images[$i] ?? ''));

                $row = OpportunityProductPricing::enrichRow([
                    'name' => (string) ($names[$i] ?? ''),
                    'quantity' => (float) ($qtys[$i] ?? 1),
                    'vendor' => (string) ($vendors[$i] ?? ''),
                    'brand' => (string) ($brands[$i] ?? ''),
                    'image' => $image,
                    'tax_category' => $taxCategory,
                    'item_kind' => $itemKind,
                    'royalty_type' => $royaltyType,
                    'has_royalty' => $royaltyType !== '',
                    'sell_exclude' => $sellExclude,
                    'cost_exclude' => $costExclude,
                    'discount_exclude' => $itemDiscount,
                    'shipping_exclude' => $itemShipping,
                    'cost_foreign' => $costForeign,
                    'cost_in_usd' => $costForeign, // alias legacy
                    'cost_fx_code' => $fxCode,
                    'cost_fx' => $costFx,
                    'cost_usd' => $costFx, // alias legacy
                    'fx_rate' => $fxRate,
                    'usd_rate' => $fxRate, // alias legacy
                ]);
                $row['image_url'] = self::productImageUrl($image);

                return $row;
            })
            ->filter(fn ($row) => $row['name'] !== '' || $row['sell_exclude'] > 0 || $row['price'] > 0)
            ->values();

        // Fee Zinit sekali dari grand total include (K52), tempel ke setiap baris Zinit untuk tampilan.
        $hasZinit = $rows->contains(
            fn (array $row) => ($row['tax_category'] ?? '') === OpportunityProductPricing::TAX_ZINIT
        );
        if ($hasZinit) {
            $volume = round($rows->sum(function (array $row) {
                if (($row['tax_category'] ?? '') !== OpportunityProductPricing::TAX_ZINIT) {
                    return 0;
                }

                return (float) ($row['quantity'] ?? 1) * (float) ($row['effective_sell_include'] ?? $row['sell_include'] ?? 0);
            }), 2);
            $fees = OpportunityProductPricing::zinitFeesFromVolume($volume);
            $jualExcl = round($rows->sum(function (array $row) {
                if (($row['tax_category'] ?? '') !== OpportunityProductPricing::TAX_ZINIT) {
                    return 0;
                }

                return (float) ($row['quantity'] ?? 1) * (float) ($row['effective_sell_exclude'] ?? $row['sell_exclude'] ?? 0);
            }), 2);
            $potFee = round($jualExcl - (float) $fees['success_fee'], 2);

            $rows = $rows->map(function (array $row) use ($fees, $potFee) {
                if (($row['tax_category'] ?? '') !== OpportunityProductPricing::TAX_ZINIT) {
                    return $row;
                }

                $row['zinit_volume'] = $fees['volume'];
                $row['zinit_platform_fee'] = $fees['platform_fee'];
                $row['zinit_service_fee'] = $fees['service_fee'];
                $row['zinit_success_fee'] = $fees['success_fee'];
                $row['zinit_rate_percent'] = $fees['rate_percent'];
                $row['zinit_pot_fee'] = $potFee;

                return $row;
            })->values();
        }

        return $rows;
    }

    /**
     * Tulis ulang kolom paralel Product List dari kumpulan row yang sudah di-enrich.
     *
     * @param  \Illuminate\Support\Collection|array  $rows
     */
    public function applyProductRows($rows): void
    {
        $rows = collect($rows)->values();

        $this->item = $rows->pluck('name')->map(fn ($v) => (string) $v)->all();
        $this->quantity = $rows->map(fn ($p) => (string) ($p['quantity'] ?? 1))->all();
        $this->price = $rows->map(fn ($p) => (string) ($p['price'] ?? 0))->all();
        $this->cost = $rows->map(fn ($p) => (string) ($p['cost'] ?? 0))->all();
        $this->vendor = $rows->map(fn ($p) => (string) ($p['vendor'] ?? ''))->all();
        $this->crm_item_brand = $rows->map(fn ($p) => (string) ($p['brand'] ?? ''))->all();
        $this->crm_item_image = $rows->map(fn ($p) => trim((string) ($p['image'] ?? '')))->all();
        $this->crm_tax_category = $rows->pluck('tax_category')->all();
        $this->crm_item_kind = $rows->pluck('item_kind')->all();
        $this->crm_has_royalty = $rows->map(function ($p) {
            $type = OpportunityProductPricing::normalizeRoyaltyType(
                $p['royalty_type'] ?? (($p['has_royalty'] ?? false) ? OpportunityProductPricing::ROYALTY_LUAR : '')
            );

            return $type !== '' ? '1' : '0';
        })->all();
        $this->crm_royalty_type = $rows->map(function ($p) {
            return OpportunityProductPricing::normalizeRoyaltyType(
                $p['royalty_type'] ?? (($p['has_royalty'] ?? false) ? OpportunityProductPricing::ROYALTY_LUAR : '')
            );
        })->all();
        $this->crm_sell_exclude = $rows->map(fn ($p) => (string) ($p['sell_exclude'] ?? 0))->all();
        $this->crm_cost_exclude = $rows->map(fn ($p) => (string) ($p['cost_exclude'] ?? 0))->all();
        $this->crm_item_discount = $rows->map(fn ($p) => (string) ($p['discount_exclude'] ?? 0))->all();
        $this->crm_item_shipping = $rows->map(fn ($p) => (string) ($p['shipping_exclude'] ?? 0))->all();
        $this->crm_cost_in_usd = $rows->map(fn ($p) => (! empty($p['cost_foreign']) || ! empty($p['cost_in_usd'])) ? '1' : '0')->all();
        $this->crm_cost_fx_code = $rows->map(function ($p) {
            $foreign = ! empty($p['cost_foreign']) || ! empty($p['cost_in_usd']);
            $code = strtoupper(trim((string) ($p['cost_fx_code'] ?? '')));

            return $foreign ? ($code !== '' ? $code : 'USD') : '';
        })->all();
        $this->crm_cost_usd = $rows->map(fn ($p) => (string) ($p['cost_fx'] ?? $p['cost_usd'] ?? 0))->all();
        $this->crm_cost_usd_rate = $rows->map(fn ($p) => (string) ($p['fx_rate'] ?? $p['usd_rate'] ?? 0))->all();

        $this->promoteToQualificationWhenHasProducts($rows);
    }

    /**
     * URL publik untuk image produk (path relatif di disk public).
     */
    public static function productImageUrl(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * Hapus file image produk yang tidak lagi dipakai.
     *
     * @param  list<string>  $keepPaths
     */
    public function purgeUnusedProductImages(array $keepPaths): void
    {
        $keep = collect($keepPaths)
            ->map(fn ($p) => trim((string) $p))
            ->filter()
            ->unique()
            ->all();

        $dir = 'opportunity-products/'.$this->id;
        if (! Storage::disk('public')->exists($dir)) {
            return;
        }

        foreach (Storage::disk('public')->files($dir) as $file) {
            if (! in_array($file, $keep, true)) {
                Storage::disk('public')->delete($file);
            }
        }
    }

    /**
     * Bila ada produk terisi dan stage masih Prospecting → naikkan ke Qualification.
     *
     * @param  \Illuminate\Support\Collection|array|null  $rows
     */
    public function promoteToQualificationWhenHasProducts($rows = null): bool
    {
        if ((string) $this->stage !== self::PROSPECTING_STAGE) {
            return false;
        }

        $rows = $rows !== null ? collect($rows) : $this->products;
        $hasProducts = $rows->contains(fn ($p) => filled(is_array($p) ? ($p['name'] ?? '') : ''));

        if (! $hasProducts) {
            return false;
        }

        $this->stage = self::QUALIFICATION_STAGE;
        $this->probability = self::defaultProbabilityForStage(self::QUALIFICATION_STAGE);

        return true;
    }

    /**
     * Normalisasi input produk form: bila mode asing, cost_exclude = harga_asing × rate.
     *
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    public static function normalizeProductInput(array $p): array
    {
        $costForeign = in_array(
            strtolower((string) ($p['cost_foreign'] ?? $p['cost_in_usd'] ?? '0')),
            ['1', 'true', 'yes', 'on'],
            true
        );
        $fxCode = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) ($p['cost_fx_code'] ?? '')) ?? '');
        $costFx = (float) ($p['cost_fx'] ?? $p['cost_usd'] ?? 0);
        $fxRate = (float) ($p['fx_rate'] ?? $p['usd_rate'] ?? 0);
        $costExclude = (float) ($p['cost_exclude'] ?? 0);

        if ($costForeign && $costFx > 0 && $fxRate > 0) {
            $costExclude = round($costFx * $fxRate, 2);
            if ($fxCode === '') {
                $fxCode = 'USD';
            }
        } else {
            $costForeign = false;
            $fxCode = '';
            $costFx = 0.0;
            $fxRate = 0.0;
        }

        $p['cost_foreign'] = $costForeign;
        $p['cost_in_usd'] = $costForeign;
        $p['cost_fx_code'] = $fxCode;
        $p['cost_fx'] = $costFx;
        $p['cost_usd'] = $costFx;
        $p['fx_rate'] = $fxRate;
        $p['usd_rate'] = $fxRate;
        $p['cost_exclude'] = $costExclude;
        $p['royalty_type'] = OpportunityProductPricing::normalizeRoyaltyType(
            $p['royalty_type'] ?? (($p['has_royalty'] ?? false) ? OpportunityProductPricing::ROYALTY_LUAR : '')
        );
        $p['has_royalty'] = $p['royalty_type'] !== '';

        return $p;
    }

    /**
     * Grand total include untuk kategori Zinit (basis K52 rumus Fee Zinit).
     */
    public function zinitGrandTotalInclude(): float
    {
        return round(
            $this->products->sum(function (array $row) {
                if (($row['tax_category'] ?? '') !== OpportunityProductPricing::TAX_ZINIT) {
                    return 0;
                }

                $qty = (float) ($row['quantity'] ?? 1);
                $include = (float) ($row['effective_sell_include'] ?? $row['sell_include'] ?? 0);

                return $qty * $include;
            }),
            2
        );
    }

    /**
     * Fee Zinit sekali dari grand total include.
     *
     * @return array{volume: float, platform_fee: float, service_fee: float, success_fee: float, rate_percent: float, cap: ?float, tier_max: ?float}
     */
    public function zinitDealFees(): array
    {
        return OpportunityProductPricing::zinitFeesFromVolume($this->zinitGrandTotalInclude());
    }

    /**
     * Total margin semua produk (per baris: margin satuan × qty).
     * Zinit: Fix GP = Total GP − Fee Zinit.
     */
    public function totalProductsMargin(): float
    {
        $sum = round(
            $this->products->sum(fn (array $row) => (float) ($row['quantity'] ?? 1) * (float) ($row['margin'] ?? 0)),
            2
        );

        if ($this->products->contains(
            fn (array $row) => ($row['tax_category'] ?? '') === OpportunityProductPricing::TAX_ZINIT
        )) {
            $sum = round($sum - (float) ($this->zinitDealFees()['success_fee'] ?? 0), 2);
        }

        return $sum;
    }

    /**
     * Basis margin untuk % diskon tambahan.
     * Inaproc: margin kotor (sebelum PNBP & PPH 29); lainnya: margin bersih.
     */
    public function totalDiscountBasisMargin(): float
    {
        return round(
            $this->products->sum(function (array $row) {
                $qty = (float) ($row['quantity'] ?? 1);
                $taxCategory = (string) ($row['tax_category'] ?? OpportunityProductPricing::TAX_NON_WAPU);

                if ($taxCategory === OpportunityProductPricing::TAX_INAPROC) {
                    return $qty * (float) ($row['gross_margin'] ?? $row['margin'] ?? 0);
                }

                return $qty * (float) ($row['margin'] ?? 0);
            }),
            2
        );
    }

    /**
     * Total jual exclude (qty × effective_sell_exclude).
     */
    public function totalSellExclude(): float
    {
        return round(
            $this->products->sum(function (array $row) {
                $qty = (float) ($row['quantity'] ?? 1);
                $sell = (float) ($row['effective_sell_exclude'] ?? $row['sell_exclude'] ?? 0);

                return $qty * $sell;
            }),
            2
        );
    }

    /**
     * Denominator untuk perhitungan margin %.
     *
     * Historis: untuk Wapu, margin% dibandingkan terhadap harga setelah PPH.
     * Untuk kategori lain, tetap dibandingkan dengan effective sell exclude.
     */
    public function totalMarginPercentDenominator(): float
    {
        $denom = round(
            $this->products->sum(function (array $row) {
                $qty = (float) ($row['quantity'] ?? 1);
                $taxCategory = (string) ($row['tax_category'] ?? OpportunityProductPricing::TAX_NON_WAPU);

                if ($taxCategory === OpportunityProductPricing::TAX_ZINIT) {
                    $effectiveSell = (float) ($row['effective_sell_exclude'] ?? $row['sell_exclude'] ?? 0);

                    return $qty * $effectiveSell;
                }

                $effectiveSell = (float) ($row['effective_sell_exclude'] ?? $row['sell_exclude'] ?? 0);

                if (in_array($taxCategory, [OpportunityProductPricing::TAX_WAPU, OpportunityProductPricing::TAX_INAPROC], true)) {
                    $pph = (float) ($row['pph'] ?? 0);

                    return $qty * ($effectiveSell - $pph);
                }

                return $qty * $effectiveSell;
            }),
            2
        );

        if ($this->products->contains(
            fn (array $row) => ($row['tax_category'] ?? '') === OpportunityProductPricing::TAX_ZINIT
        )) {
            // Pot Fee = total jual excl − Fee Zinit (basis Fix GP%).
            $denom = round($denom - (float) ($this->zinitDealFees()['success_fee'] ?? 0), 2);
        }

        return $denom;
    }

    /**
     * Margin keseluruhan opportunity (%): total margin / total jual exclude.
     */
    public function overallMarginPercent(): ?float
    {
        $denom = $this->totalMarginPercentDenominator();
        if ($denom <= 0) {
            return null;
        }

        return round(($this->totalProductsMargin() / $denom) * 100, 2);
    }

    /**
     * Margin yang masuk ke sales: margin produk − diskon tambahan (min 0).
     */
    public function salesMargin(): float
    {
        $margin = $this->totalProductsMargin();
        $discount = $this->hasActiveDiscount()
            ? (float) $this->crm_discount_amount
            : 0.0;

        return round(max(0, $margin - $discount), 2);
    }

    /**
     * Simpan margin ke deal bila Closed Won; kosongkan jika stage berubah.
     * Sudah dipotong diskon tambahan.
     */
    public function syncWonMargin(): void
    {
        $this->crm_won_margin = $this->stage === self::WON_STAGE
            ? $this->salesMargin()
            : null;
    }

    public function hasActiveDiscount(): bool
    {
        return (bool) $this->crm_has_discount && (float) $this->crm_discount_amount > 0;
    }

    public function discountPercent(): ?float
    {
        $margin = $this->totalDiscountBasisMargin();
        $discount = (float) $this->crm_discount_amount;
        if ($margin <= 0 || $discount <= 0) {
            return null;
        }

        return round(($discount / $margin) * 100, 2);
    }

    public function discountStatusLabel(): string
    {
        return match ($this->crm_discount_status) {
            self::DISCOUNT_PENDING => 'Menunggu Approval',
            self::DISCOUNT_APPROVED => 'Disetujui',
            self::DISCOUNT_REJECTED => 'Ditolak',
            default => '—',
        };
    }

    public function discountNeedsAttention(): bool
    {
        if ($this->skipsApproval()) {
            return false;
        }

        return $this->hasActiveDiscount()
            && $this->crm_discount_status === self::DISCOUNT_PENDING;
    }

    /**
     * Prospecting / Qualification: tidak perlu approval margin maupun diskon.
     */
    public function skipsApproval(): bool
    {
        return in_array((string) $this->stage, self::NO_APPROVAL_STAGES, true);
    }

    /**
     * Stage awal (Prospecting / Qualification) tidak boleh membuat Quotation.
     */
    public function canCreateQuotation(): bool
    {
        return ! $this->skipsApproval();
    }

    /**
     * Membuka / mengakses QO yang sudah ada — sama syaratnya dengan membuat QO.
     */
    public function canOpenQuotation(): bool
    {
        return $this->canCreateQuotation();
    }

    public function quotationBlockedReason(): ?string
    {
        if ($this->canCreateQuotation()) {
            return null;
        }

        return 'Quotation hanya bisa dibuat mulai stage Proposal. Stage Prospecting dan Qualification belum diizinkan.';
    }

    /** Pesan saat QO sudah ada tetapi stage masih Prospecting / Qualification. */
    public function quotationLockedReason(): ?string
    {
        if ($this->canOpenQuotation()) {
            return null;
        }

        return 'Quotation terkunci. Pindahkan / lanjutkan opportunity ke tahap Proposal untuk membuka QO.';
    }

    public function isCustomerFreeShipping(): bool
    {
        $this->loadMissing('account');

        return \App\Support\FreeShippingZone::isFreeForAccount($this->account);
    }

    public function requiredMarginNominalThreshold(): float
    {
        return PaymentLevel::requiredMarginNominal(
            $this->isCustomerFreeShipping(),
            (bool) $this->crm_has_shipping_charge
        );
    }

    public function top(): string
    {
        if (CustomerTop::isValid($this->crm_top)) {
            return (string) $this->crm_top;
        }

        $this->loadMissing('account');

        return $this->account?->top() ?? CustomerTop::DEFAULT;
    }

    public function topLabel(): string
    {
        return CustomerTop::label($this->top());
    }

    /**
     * Minimal margin (%) efektif: max(level pembayaran customer, TOP opportunity).
     * Null bila customer Suspend (tidak boleh quote).
     */
    public function minMarginPercent(): ?float
    {
        $this->loadMissing('account');
        $account = $this->account;

        if ($account?->isPaymentSuspended()) {
            return null;
        }

        $fromLevel = $account
            ? (PaymentLevel::minMarginPercent($account->paymentLevel()) ?? 0.0)
            : 0.0;
        $fromTop = CustomerTop::minMarginPercent($this->top());

        return max($fromLevel, $fromTop);
    }

    public function marginNeedsApproval(): bool
    {
        if ($this->skipsApproval()) {
            return false;
        }

        return $this->crm_margin_status === self::MARGIN_PENDING;
    }

    public function isMarginLocked(): bool
    {
        if ($this->skipsApproval()) {
            return false;
        }

        return in_array($this->crm_margin_status, [self::MARGIN_PENDING, self::MARGIN_REJECTED], true);
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

    /**
     * Evaluasi % + nominal vs threshold. Return true jika baru masuk pending.
     */
    public function refreshMarginApprovalState(bool $isNew = false): bool
    {
        $this->loadMissing('account');

        $marginPct = $this->overallMarginPercent();
        $marginNominal = $this->totalProductsMargin();
        $pctThreshold = $this->minMarginPercent();
        $maxPctThreshold = PaymentLevel::maxMarginPercent();
        $nominalThreshold = $this->requiredMarginNominalThreshold();

        $this->crm_margin_percent = $marginPct;
        $this->crm_margin_threshold = $pctThreshold;
        $this->crm_margin_nominal = $marginNominal;
        $this->crm_margin_nominal_threshold = $nominalThreshold;

        // Stage awal: catat metrik saja, tidak pernah minta approval.
        if ($this->skipsApproval()) {
            $this->crm_margin_status = null;
            $this->crm_margin_requested_at = null;
            $this->crm_margin_reviewed_by = null;
            $this->crm_margin_reviewed_at = null;
            $this->crm_margin_note = null;

            return false;
        }

        // Closed Lost: batalkan pending — deal sudah ditutup, tidak perlu approval.
        if ($this->stage === self::LOST_STAGE) {
            if ($this->crm_margin_status === self::MARGIN_PENDING) {
                $this->crm_margin_status = null;
                $this->crm_margin_requested_at = null;
                $this->crm_margin_reviewed_by = null;
                $this->crm_margin_reviewed_at = null;
                $this->crm_margin_note = null;
            }

            return false;
        }

        $belowPct = $pctThreshold !== null && ($marginPct === null || $marginPct < $pctThreshold);
        $abovePct = $maxPctThreshold > 0 && $marginPct !== null && $marginPct > $maxPctThreshold;
        $belowNominal = $nominalThreshold > 0 && $marginNominal < $nominalThreshold;
        $outOfRange = $belowPct || $abovePct || $belowNominal;

        $wasPending = $this->crm_margin_status === self::MARGIN_PENDING;
        $wasApproved = $this->crm_margin_status === self::MARGIN_APPROVED;

        if (! $outOfRange) {
            $this->crm_margin_status = null;
            $this->crm_margin_requested_at = null;
            $this->crm_margin_reviewed_by = null;
            $this->crm_margin_reviewed_at = null;
            $this->crm_margin_note = null;

            return false;
        }

        if (! $isNew && $wasApproved) {
            return false;
        }

        $this->crm_margin_status = self::MARGIN_PENDING;
        if (! $this->crm_margin_requested_at || ! $wasPending) {
            $this->crm_margin_requested_at = now();
        }
        $this->crm_margin_reviewed_by = null;
        $this->crm_margin_reviewed_at = null;

        return ! $wasPending;
    }

    /**
     * Timpa Product List dari Quotation Items (1:1).
     * Return false jika isi produk sudah sama (tidak perlu tulis ulang).
     *
     * @param  \Illuminate\Support\Collection|array  $items
     */
    public function replaceProductsFromQuotationItems($items): bool
    {
        $rows = collect($items)->values()
            ->filter(function ($item) {
                $name = is_object($item) ? ($item->name ?? null) : ($item['name'] ?? null);

                return filled($name);
            })
            ->map(function ($item) {
                $get = function (string $key, $default = null) use ($item) {
                    if (is_object($item)) {
                        return $item->{$key} ?? $default;
                    }

                    return $item[$key] ?? $default;
                };

                $list = (float) ($get('sell_exclude', 0) ?: 0);
                $discount = (float) ($get('discount_exclude', 0) ?: 0);
                $unitPrice = (float) ($get('unit_price', 0) ?: 0);

                // Item baru di form QO sering hanya isi unit_price.
                if ($list <= 0) {
                    $list = $unitPrice;
                }
                if ($discount <= 0 && $unitPrice > 0 && abs($unitPrice - $list) > 0.009) {
                    $discount = $unitPrice;
                    if ($list < $discount) {
                        $list = $discount;
                    }
                }

                $tax = (string) ($get('tax_category', '') ?? '');
                $kind = (string) ($get('item_kind', '') ?? '');

                return OpportunityProductPricing::enrichRow([
                    'name' => (string) $get('name', ''),
                    'quantity' => (float) ($get('quantity', 1) ?: 1),
                    'vendor' => (string) ($get('vendor', '') ?? ''),
                    'brand' => (string) ($get('brand', '') ?? ''),
                    'image' => (string) ($get('image', '') ?? ''),
                    'tax_category' => $tax !== '' ? $tax : OpportunityProductPricing::TAX_NON_WAPU,
                    'item_kind' => $kind !== '' ? $kind : OpportunityProductPricing::KIND_BARANG,
                    'royalty_type' => OpportunityProductPricing::normalizeRoyaltyType(
                        $get('royalty_type', ($get('has_royalty', false) ? OpportunityProductPricing::ROYALTY_LUAR : ''))
                    ),
                    'has_royalty' => OpportunityProductPricing::hasRoyaltyFlag($get('has_royalty', false))
                        || OpportunityProductPricing::normalizeRoyaltyType($get('royalty_type', '')) !== '',
                    'sell_exclude' => $list,
                    'cost_exclude' => (float) ($get('cost_exclude', 0) ?: 0),
                    'discount_exclude' => $discount,
                    'shipping_exclude' => (float) ($get('shipping_exclude', 0) ?: 0),
                ]);
            })
            ->values();

        $desiredFp = Quotation::itemsSyncFingerprint(
            Quotation::itemsPayloadFromOpportunityProducts($rows)
        );
        $currentFp = Quotation::itemsSyncFingerprint(
            Quotation::itemsPayloadFromOpportunityProducts($this->products)
        );

        if ($desiredFp === $currentFp) {
            return false;
        }

        // Pertahankan meta modal USD per index; brand/image ikut dari QO.
        $existing = $this->products->values();
        $rows = $rows->map(function ($row, $i) use ($existing) {
            $prev = $existing->get($i, []);
            $row['brand'] = trim((string) ($row['brand'] ?? ''));
            $row['image'] = trim((string) ($row['image'] ?? ''));
            $row['cost_foreign'] = ! empty($prev['cost_foreign']) || ! empty($prev['cost_in_usd']);
            $row['cost_in_usd'] = $row['cost_foreign'];
            $row['cost_fx_code'] = (string) ($prev['cost_fx_code'] ?? '');
            $row['cost_fx'] = (float) ($prev['cost_fx'] ?? $prev['cost_usd'] ?? 0);
            $row['cost_usd'] = $row['cost_fx'];
            $row['fx_rate'] = (float) ($prev['fx_rate'] ?? $prev['usd_rate'] ?? 0);
            $row['usd_rate'] = $row['fx_rate'];
            if ((float) ($row['shipping_exclude'] ?? 0) <= 0 && (float) ($prev['shipping_exclude'] ?? 0) > 0) {
                $row['shipping_exclude'] = (float) $prev['shipping_exclude'];
                $row = OpportunityProductPricing::enrichRow($row);
            }

            return $row;
        });

        $this->applyProductRows($rows);

        $keepImages = $rows->map(fn ($p) => trim((string) ($p['image'] ?? '')))->filter()->values()->all();
        $this->purgeUnusedProductImages($keepImages);

        $this->amount = $rows->isNotEmpty()
            ? $rows->sum(fn ($p) => (float) ($p['subtotal'] ?? ((float) ($p['quantity'] ?? 1) * (float) ($p['price'] ?? 0))))
            : $this->amount;

        $this->syncWonMargin();

        return true;
    }

    /**
     * Sinkronkan Product List → Quotation Items (1:1).
     * Bila isi berubah: status QO jadi draft; jika sebelumnya Sent, naikkan revisi dokumen.
     */
    public function syncProductsToLinkedQuotation(): bool
    {
        $quotation = $this->relationLoaded('quotation')
            ? $this->quotation
            : $this->quotation()->with('items')->first();

        if (! $quotation) {
            return false;
        }

        $quotation->loadMissing('items');

        $desiredPayload = \App\Models\Quotation::itemsPayloadFromOpportunityProducts(
            $this->products,
            $quotation->items
        );
        $desiredFp = \App\Models\Quotation::itemsSyncFingerprint($desiredPayload);
        $currentFp = $quotation->currentItemsSyncFingerprint();

        if ($desiredFp === $currentFp) {
            return false;
        }

        $wasSent = $quotation->status === 'sent';

        $quotation->syncItemsFromOpportunityProducts($this);
        $quotation->load('items');
        $quotation->recalculateTotals();

        if ($wasSent) {
            $service = app(\App\Services\QuotationService::class);
            $quotation->document_revision = (int) $quotation->document_revision + 1;
            $base = $quotation->base_number ?: $service->stripDocumentRevision($quotation->number);
            $quotation->base_number = $base;
            $quotation->number = $service->withDocumentRevision($base, (int) $quotation->document_revision);
            $quotation->revision = (int) $quotation->revision + 1;
            if (! $quotation->sent_at) {
                $quotation->sent_at = now();
            }
        }

        $quotation->status = 'draft';
        $quotation->save();

        return true;
    }

    /**
     * Samakan status approval margin Quotation terhubung dengan Opportunity.
     * Mencegah QO tetap terkunci setelah margin opportunity sudah di atas threshold / di-approve.
     */
    public function syncLinkedQuotationMarginApproval(): bool
    {
        $quotation = $this->relationLoaded('quotation')
            ? $this->quotation
            : $this->quotation()->first();

        if (! $quotation) {
            return false;
        }

        $fields = [
            'crm_margin_percent' => $this->crm_margin_percent,
            'crm_margin_threshold' => $this->crm_margin_threshold,
            'crm_margin_nominal' => $this->crm_margin_nominal,
            'crm_margin_nominal_threshold' => $this->crm_margin_nominal_threshold,
            'crm_margin_status' => $this->crm_margin_status,
            'crm_margin_requested_at' => $this->crm_margin_requested_at,
            'crm_margin_reviewed_by' => $this->crm_margin_reviewed_by,
            'crm_margin_reviewed_at' => $this->crm_margin_reviewed_at,
            'crm_margin_note' => $this->crm_margin_note,
        ];

        foreach ($fields as $key => $value) {
            $quotation->{$key} = $value;
        }

        if (! $quotation->isDirty()) {
            return false;
        }

        $quotation->save();

        return true;
    }

    /**
     * Saat naik dari Prospecting/Qualification: aktifkan approval diskon jika belum disetujui.
     * Return true jika baru masuk pending (untuk notifikasi).
     */
    public function activateDiscountApprovalIfNeeded(): bool
    {
        if ($this->skipsApproval() || ! $this->hasActiveDiscount()) {
            return false;
        }

        if ($this->stage === self::LOST_STAGE) {
            return false;
        }

        if ($this->crm_discount_status === self::DISCOUNT_APPROVED) {
            return false;
        }

        if ($this->crm_discount_status === self::DISCOUNT_PENDING) {
            return false;
        }

        $this->crm_discount_status = self::DISCOUNT_PENDING;
        $this->crm_discount_requested_by = $this->crm_discount_requested_by ?: auth()->id();
        $this->crm_discount_requested_at = now();
        $this->crm_discount_reviewed_by = null;
        $this->crm_discount_reviewed_at = null;
        $this->crm_discount_note = null;

        return true;
    }

    /**
     * Fingerprint nama barang + harga jual/modal — untuk deteksi perubahan yang memicu notif/re-approval diskon.
     */
    public function productsPricingFingerprint(): string
    {
        return md5(json_encode(
            $this->products->map(fn (array $p) => [
                'name' => (string) ($p['name'] ?? ''),
                'sell_exclude' => round((float) ($p['sell_exclude'] ?? 0), 2),
                'cost_exclude' => round((float) ($p['cost_exclude'] ?? 0), 2),
                'shipping_exclude' => round((float) ($p['shipping_exclude'] ?? 0), 2),
            ])->values()->all()
        ));
    }

    /**
     * Bila ada diskon tambahan dan nama barang / harga jual / harga modal berubah
     * → approval diskon kembali pending.
     * Return true jika status baru menjadi pending (perlu notifikasi).
     */
    public function reopenDiscountApprovalIfPricingChanged(bool $pricingChanged): bool
    {
        if (! $pricingChanged || $this->skipsApproval() || ! $this->hasActiveDiscount()) {
            return false;
        }

        if ($this->stage === self::LOST_STAGE) {
            return false;
        }

        $wasPending = $this->crm_discount_status === self::DISCOUNT_PENDING;

        $this->crm_discount_status = self::DISCOUNT_PENDING;
        $this->crm_discount_requested_by = auth()->id() ?: $this->crm_discount_requested_by;
        $this->crm_discount_requested_at = now();
        $this->crm_discount_reviewed_by = null;
        $this->crm_discount_reviewed_at = null;
        $this->crm_discount_note = null;

        return ! $wasPending;
    }

    /**
     * Batalkan status pending diskon/margin saat deal Closed Lost.
     */
    public function clearPendingApprovalsForLost(): void
    {
        if ($this->stage !== self::LOST_STAGE) {
            return;
        }

        if ($this->crm_discount_status === self::DISCOUNT_PENDING) {
            $this->crm_discount_status = null;
        }

        if ($this->crm_margin_status === self::MARGIN_PENDING) {
            $this->crm_margin_status = null;
            $this->crm_margin_requested_at = null;
            $this->crm_margin_reviewed_by = null;
            $this->crm_margin_reviewed_at = null;
            $this->crm_margin_note = null;
        }
    }

    public function stageColor(): string
    {
        return match ($this->stage) {
            self::WON_STAGE => 'green',
            self::LOST_STAGE => 'red',
            default => 'blue',
        };
    }

    /**
     * Kunci untuk mendeteksi deal duplikat (nama + akun + nilai sama).
     */
    public function duplicateKey(): string
    {
        return mb_strtolower(trim($this->name))
            .'|'.($this->account_id ?? '')
            .'|'.number_format((float) $this->amount, 2, '.', '');
    }

    /**
     * @return array<string, list<string>> opportunity_id => [other duplicate ids]
     */
    public static function duplicateMap(Collection $opportunities): array
    {
        $groups = $opportunities->groupBy(fn (self $o) => $o->duplicateKey());

        $map = [];
        foreach ($groups as $group) {
            if ($group->count() < 2) {
                continue;
            }
            $ids = $group->pluck('id')->all();
            foreach ($ids as $id) {
                $map[$id] = array_values(array_filter($ids, fn ($other) => $other !== $id));
            }
        }

        return $map;
    }

    public function potentialDuplicates(): Collection
    {
        if ($this->name === '') {
            return collect();
        }

        return static::query()
            ->with('quotation')
            ->where('id', '!=', $this->id)
            ->where('name', $this->name)
            ->where('account_id', $this->account_id)
            ->where('amount', $this->amount)
            ->orderByDesc('modified_at')
            ->get();
    }
}
