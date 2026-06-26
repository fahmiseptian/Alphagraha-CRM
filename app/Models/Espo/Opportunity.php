<?php

namespace App\Models\Espo;

use App\Models\Espo\Concerns\EspoEntity;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
    ];

    protected $casts = [
        'item' => 'array',
        'quantity' => 'array',
        'price' => 'array',
        'cost' => 'array',
    ];

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

        $count = max(count($names), count($qtys), count($prices), count($costs));

        if ($count === 0) {
            return collect();
        }

        return collect(range(0, $count - 1))
            ->map(function ($i) use ($names, $qtys, $prices, $costs) {
                $qty = (float) ($qtys[$i] ?? 1);
                $price = (float) ($prices[$i] ?? 0);

                return [
                    'name' => (string) ($names[$i] ?? ''),
                    'quantity' => $qty,
                    'price' => $price,
                    'cost' => (float) ($costs[$i] ?? 0),
                    'subtotal' => $qty * $price,
                ];
            })
            ->filter(fn ($row) => $row['name'] !== '' || $row['price'] > 0)
            ->values();
    }

    public function stageColor(): string
    {
        return match ($this->stage) {
            self::WON_STAGE => 'green',
            self::LOST_STAGE => 'red',
            default => 'blue',
        };
    }
}
