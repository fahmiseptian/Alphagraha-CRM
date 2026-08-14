<?php

namespace App\Models;

use App\Support\OpportunityProductPricing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    protected $table = 'crm_quotation_items';

    protected $fillable = [
        'quotation_id', 'name', 'description', 'quantity', 'unit',
        'unit_price', 'total', 'sort_order',
        'tax_category', 'item_kind', 'has_royalty', 'royalty_type', 'sell_exclude', 'cost_exclude', 'discount_exclude', 'shipping_exclude', 'vendor', 'brand', 'image',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
        'sell_exclude' => 'decimal:2',
        'cost_exclude' => 'decimal:2',
        'discount_exclude' => 'decimal:2',
        'shipping_exclude' => 'decimal:2',
        'has_royalty' => 'boolean',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * Harga jual exclude (list) sebelum diskon item.
     */
    public function listSellExclude(): float
    {
        if ($this->sell_exclude !== null && $this->sell_exclude !== '') {
            return (float) $this->sell_exclude;
        }

        return (float) $this->unit_price;
    }

    /**
     * Harga setelah diskon item (exclude). Jika tidak ada diskon = harga list.
     */
    public function afterDiscountExclude(): float
    {
        $discount = (float) ($this->discount_exclude ?? 0);

        return $discount > 0 ? $discount : $this->listSellExclude();
    }

    /**
     * Margin per baris item (margin satuan × qty).
     */
    public function lineMargin(): float
    {
        $sellExclude = $this->listSellExclude();
        $discountExclude = (float) ($this->discount_exclude ?? 0);
        $shippingExclude = (float) ($this->shipping_exclude ?? 0);

        $enriched = OpportunityProductPricing::enrichRow([
            'quantity' => (float) $this->quantity,
            'sell_exclude' => $sellExclude,
            'cost_exclude' => (float) ($this->cost_exclude ?? 0),
            'discount_exclude' => $discountExclude,
            'shipping_exclude' => $shippingExclude,
            'tax_category' => $this->tax_category ?? OpportunityProductPricing::TAX_NON_WAPU,
            'item_kind' => $this->item_kind ?? OpportunityProductPricing::KIND_BARANG,
            'royalty_type' => OpportunityProductPricing::normalizeRoyaltyType(
                $this->royalty_type ?? (($this->has_royalty ?? false) ? OpportunityProductPricing::ROYALTY_LUAR : '')
            ),
            'has_royalty' => (bool) $this->has_royalty,
        ]);

        return round((float) $enriched['quantity'] * (float) $enriched['margin'], 2);
    }
}
