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
        'number', 'account_id', 'opportunity_id', 'customer_name', 'company_name', 'customer_email',
        'customer_phone', 'customer_address', 'quotation_date', 'valid_until', 'status',
        'currency', 'subtotal', 'discount', 'tax_percent', 'tax_amount', 'total',
        'notes', 'terms', 'template_id', 'created_by', 'revision', 'sent_at',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'valid_until' => 'date',
        'sent_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public const STATUSES = [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
        'expired' => 'Expired',
    ];

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
            'accepted' => 'green',
            'rejected' => 'red',
            'expired' => 'amber',
        ][$this->status] ?? 'gray';
    }
}
