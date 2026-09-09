<?php

namespace App\Models;

use App\Support\CustomerTop;
use App\Support\OpportunityProductPricing;
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
        'unit_price_basis',
        'is_pkp',
        'quoted_at',
        'is_selected',
        'sort_order',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'is_pkp' => 'boolean',
        'quoted_at' => 'date',
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

    public function statusBadgeColor(): string
    {
        return VendorStock::badgeColorForStatus($this->status);
    }

    public function topValue(): string
    {
        return CustomerTop::isValid($this->top) ? CustomerTop::normalize($this->top) : CustomerTop::DAYS_30;
    }

    public function topLabel(): string
    {
        return CustomerTop::LABELS[$this->topValue()] ?? 'TOP 30 hari';
    }

    public function displayVendorName(): string
    {
        return $this->vendor?->name ?: ($this->vendor_name ?: '—');
    }

    public function companyStatusLabel(): string
    {
        $status = trim((string) ($this->vendor?->company_status ?? ''));

        return $status !== '' ? $status : '—';
    }

    public function hargaExclude(): float
    {
        return round((float) $this->unit_price, 2);
    }

    public function hargaInclude(): float
    {
        $exclude = $this->hargaExclude();
        if ($this->is_pkp === false) {
            return $exclude;
        }

        return OpportunityProductPricing::includeFromExclude($exclude);
    }

    public function quotedAtLabel(): string
    {
        $date = $this->quoted_at ?? $this->created_at;

        return $date?->translatedFormat('d M Y') ?: '—';
    }
}
