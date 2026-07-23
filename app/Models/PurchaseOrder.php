<?php

namespace App\Models;

use App\Models\Espo\Opportunity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    public const PAYMENT_TOP = 'top';

    public const PAYMENT_CASH = 'cash';

    protected $table = 'crm_purchase_orders';

    protected $fillable = [
        'opportunity_id',
        'number',
        'payment_term',
        'total',
        'currency',
        'created_by',
    ];

    protected $casts = [
        'total' => 'decimal:2',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCash(): bool
    {
        return $this->payment_term === self::PAYMENT_CASH;
    }

    public function paymentTermLabel(): string
    {
        return $this->isCash() ? 'Cash' : 'TOP';
    }
}
