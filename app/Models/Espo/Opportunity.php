<?php

namespace App\Models\Espo;

use App\Models\Espo\Concerns\EspoEntity;
use App\Models\OpportunityNote;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Support\OpportunityProductPricing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
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
        'name', 'account_id', 'company', 'stage', 'type', 'amount', 'amount_currency',
        'close_date', 'probability', 'lead_source', 'description', 'crm_lost_reason', 'assigned_user_id',
        'contact_id', 'vendor',
        'crm_tax_category', 'crm_item_kind', 'crm_sell_exclude', 'crm_cost_exclude',
        'crm_item_discount',
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
        'crm_tax_category' => 'array',
        'crm_item_kind' => 'array',
        'crm_sell_exclude' => 'array',
        'crm_cost_exclude' => 'array',
        'crm_item_discount' => 'array',
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
        $taxCategories = array_values((array) ($this->crm_tax_category ?? []));
        $itemKinds = array_values((array) ($this->crm_item_kind ?? []));
        $sellExcludes = array_values((array) ($this->crm_sell_exclude ?? []));
        $costExcludes = array_values((array) ($this->crm_cost_exclude ?? []));
        $itemDiscounts = array_values((array) ($this->crm_item_discount ?? []));

        $count = max(
            count($names), count($qtys), count($prices), count($costs), count($vendors),
            count($taxCategories), count($itemKinds), count($sellExcludes), count($costExcludes),
            count($itemDiscounts)
        );

        if ($count === 0) {
            return collect();
        }

        return collect(range(0, $count - 1))
            ->map(function ($i) use ($names, $qtys, $prices, $costs, $vendors, $taxCategories, $itemKinds, $sellExcludes, $costExcludes, $itemDiscounts) {
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
                $taxCategory = (string) ($taxCategories[$i] ?? OpportunityProductPricing::TAX_NON_WAPU);
                $itemKind = (string) ($itemKinds[$i] ?? OpportunityProductPricing::KIND_BARANG);

                return OpportunityProductPricing::enrichRow([
                    'name' => (string) ($names[$i] ?? ''),
                    'quantity' => (float) ($qtys[$i] ?? 1),
                    'vendor' => (string) ($vendors[$i] ?? ''),
                    'tax_category' => $taxCategory,
                    'item_kind' => $itemKind,
                    'sell_exclude' => $sellExclude,
                    'cost_exclude' => $costExclude,
                    'discount_exclude' => $itemDiscount,
                ]);
            })
            ->filter(fn ($row) => $row['name'] !== '' || $row['sell_exclude'] > 0 || $row['price'] > 0)
            ->values();
    }

    /**
     * Total margin semua produk (per baris: margin satuan × qty).
     */
    public function totalProductsMargin(): float
    {
        return round(
            $this->products->sum(fn (array $row) => (float) ($row['quantity'] ?? 1) * (float) ($row['margin'] ?? 0)),
            2
        );
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
        return round(
            $this->products->sum(function (array $row) {
                $qty = (float) ($row['quantity'] ?? 1);
                $taxCategory = (string) ($row['tax_category'] ?? OpportunityProductPricing::TAX_NON_WAPU);
                $effectiveSell = (float) ($row['effective_sell_exclude'] ?? $row['sell_exclude'] ?? 0);

                if ($taxCategory === OpportunityProductPricing::TAX_WAPU) {
                    $pph = (float) ($row['pph'] ?? 0);
                    return $qty * ($effectiveSell - $pph);
                }

                return $qty * $effectiveSell;
            }),
            2
        );
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

    public function isCustomerFreeShipping(): bool
    {
        $this->loadMissing('account');

        return \App\Support\FreeShippingZone::isFreeForAccount($this->account);
    }

    public function requiredMarginNominalThreshold(): float
    {
        return \App\Support\PaymentLevel::requiredMarginNominal(
            $this->isCustomerFreeShipping(),
            (bool) $this->crm_has_shipping_charge
        );
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
        $account = $this->account;

        $marginPct = $this->overallMarginPercent();
        $marginNominal = $this->totalProductsMargin();
        $pctThreshold = $account?->minMarginPercent();
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
        $belowNominal = $nominalThreshold > 0 && $marginNominal < $nominalThreshold;
        $below = $belowPct || $belowNominal;

        $wasPending = $this->crm_margin_status === self::MARGIN_PENDING;
        $wasApproved = $this->crm_margin_status === self::MARGIN_APPROVED;

        if (! $below) {
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
