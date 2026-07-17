<?php

namespace App\Models\Espo;

use App\Models\Espo\Concerns\EspoEntity;
use App\Models\OpportunityNote;
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
        'close_date', 'probability', 'lead_source', 'description', 'assigned_user_id',
        'contact_id', 'vendor',
        'crm_tax_category', 'crm_item_kind', 'crm_sell_exclude', 'crm_cost_exclude',
        'crm_won_margin',
        'crm_has_discount', 'crm_discount_amount', 'crm_discount_status',
        'crm_discount_requested_by', 'crm_discount_requested_at',
        'crm_discount_reviewed_by', 'crm_discount_reviewed_at', 'crm_discount_note',
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
        'crm_won_margin' => 'float',
        'crm_has_discount' => 'boolean',
        'crm_discount_amount' => 'float',
        'crm_discount_requested_at' => 'datetime',
        'crm_discount_reviewed_at' => 'datetime',
    ];

    public const DISCOUNT_PENDING = 'pending';

    public const DISCOUNT_APPROVED = 'approved';

    public const DISCOUNT_REJECTED = 'rejected';

    public const OPEN_STAGES = ['Prospecting', 'Qualification', 'Proposal', 'Negotiation'];
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

        return [self::WON_STAGE, self::LOST_STAGE];
    }

    /** Jenis pengadaan EspoCRM (kolom type). */
    public const TYPES = [
        'Quatation', 'PL', 'E-Purchasing', 'Tender', 'E-Auction',
        'Tender Cepat', 'Siplah', 'Bela Pengadaan', 'Simpel',
    ];

    /** Perusahaan internal (kolom company). */
    public const COMPANIES = [
        'Alpha Graha',
        'Alpha Graha Computindo',
        'Elite Proxy',
        'Power Sistem',
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

        $count = max(
            count($names), count($qtys), count($prices), count($costs), count($vendors),
            count($taxCategories), count($itemKinds), count($sellExcludes), count($costExcludes)
        );

        if ($count === 0) {
            return collect();
        }

        return collect(range(0, $count - 1))
            ->map(function ($i) use ($names, $qtys, $prices, $costs, $vendors, $taxCategories, $itemKinds, $sellExcludes, $costExcludes) {
                $priceInclude = (float) ($prices[$i] ?? 0);
                $costInclude = (float) ($costs[$i] ?? 0);
                $sellExclude = isset($sellExcludes[$i]) && $sellExcludes[$i] !== ''
                    ? (float) $sellExcludes[$i]
                    : OpportunityProductPricing::excludeFromInclude($priceInclude);
                $costExclude = isset($costExcludes[$i]) && $costExcludes[$i] !== ''
                    ? (float) $costExcludes[$i]
                    : OpportunityProductPricing::excludeFromInclude($costInclude);
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
     * Simpan margin ke deal bila Closed Won; kosongkan jika stage berubah.
     */
    public function syncWonMargin(): void
    {
        $this->crm_won_margin = $this->stage === self::WON_STAGE
            ? $this->totalProductsMargin()
            : null;
    }

    public function hasActiveDiscount(): bool
    {
        return (bool) $this->crm_has_discount && (float) $this->crm_discount_amount > 0;
    }

    public function discountPercent(): ?float
    {
        $amount = (float) $this->amount;
        $discount = (float) $this->crm_discount_amount;
        if ($amount <= 0 || $discount <= 0) {
            return null;
        }

        return round(($discount / $amount) * 100, 2);
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
        return $this->hasActiveDiscount()
            && $this->crm_discount_status === self::DISCOUNT_PENDING;
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
