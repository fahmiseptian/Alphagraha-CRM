<?php

namespace App\Models;

use App\Support\CustomerTop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItemVendor extends Model
{
    protected $table = 'crm_purchase_order_item_vendors';

    protected $fillable = [
        'purchase_order_item_id',
        'vendor_id',
        'vendor_stock_id',
        'product_name',
        'vendor_name',
        'status',
        'top',
        'unit_price',
        'is_selected',
        'sort_order',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'is_selected' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(VendorStock::class, 'vendor_stock_id');
    }

    public function isReady(): bool
    {
        return $this->status === VendorStock::STATUS_READY;
    }

    public function statusLabel(): string
    {
        return VendorStock::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function topValue(): string
    {
        return CustomerTop::isValid($this->top) ? (string) $this->top : CustomerTop::DAYS_30;
    }

    public function topLabel(): string
    {
        return CustomerTop::LABELS[$this->topValue()] ?? 'TOP 30 hari';
    }

    public function displayVendorName(): string
    {
        return $this->vendor?->name ?: ($this->vendor_name ?: '—');
    }
}
