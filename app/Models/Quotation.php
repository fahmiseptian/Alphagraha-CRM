<?php

namespace App\Models;

use App\Models\Espo\Account;
use App\Models\Espo\Opportunity;
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
     */
    public function hasBeenSent(): bool
    {
        return $this->sent_at !== null || $this->status === 'sent';
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
}
