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

    public const APPROVAL_PENDING = 'pending';

    public const APPROVAL_APPROVED = 'approved';

    public const APPROVAL_REJECTED = 'rejected';

    protected $table = 'crm_purchase_orders';

    protected $fillable = [
        'opportunity_id',
        'sales_order_id',
        'number',
        'vendor_id',
        'vendor_name',
        'payment_term',
        'report_top',
        'total',
        'currency',
        'created_by',
        'approval_status',
        'approved_by',
        'approved_at',
        'approval_note',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'sales_order_id' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(OpportunitySalesOrder::class, 'sales_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function displayVendorName(): string
    {
        return $this->vendor?->name ?: ($this->vendor_name ?: '—');
    }

    public function isApprovalPending(): bool
    {
        return ($this->approval_status ?: self::APPROVAL_PENDING) === self::APPROVAL_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->approval_status === self::APPROVAL_APPROVED;
    }

    public function isApprovalRejected(): bool
    {
        return $this->approval_status === self::APPROVAL_REJECTED;
    }

    public function approvalLabel(): string
    {
        return match ($this->approval_status) {
            self::APPROVAL_APPROVED => 'Approved',
            self::APPROVAL_REJECTED => 'Rejected',
            default => 'Menunggu approval',
        };
    }

    public function approvalBadgeColor(): string
    {
        return match ($this->approval_status) {
            self::APPROVAL_APPROVED => 'green',
            self::APPROVAL_REJECTED => 'red',
            default => 'amber',
        };
    }

    public function isCash(): bool
    {
        return $this->payment_term === self::PAYMENT_CASH;
    }

    public function paymentTermLabel(): string
    {
        return $this->isCash() ? 'Cash' : 'TOP';
    }

    /**
     * Total PO harga include (PPN jika PKP + tambahan Cash/TOP di sisi include).
     */
    public function totalInclude(): float
    {
        $this->loadMissing(['items.vendorQuotes', 'vendor']);

        return round((float) $this->items->sum(
            fn (PurchaseOrderItem $item) => $item->lineTotalInclude($this->payment_term)
        ), 2);
    }
}
