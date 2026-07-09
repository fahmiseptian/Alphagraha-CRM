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
        'tax_category', 'item_kind', 'sell_exclude', 'cost_exclude', 'vendor',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
        'sell_exclude' => 'decimal:2',
        'cost_exclude' => 'decimal:2',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * Margin per baris item (margin satuan × qty).
     */
    public function lineMargin(): float
    {
        $sellExclude = $this->sell_exclude !== null && $this->sell_exclude !== ''
            ? (float) $this->sell_exclude
            : OpportunityProductPricing::excludeFromInclude((float) $this->unit_price);

        $enriched = OpportunityProductPricing::enrichRow([
            'quantity' => (float) $this->quantity,
            'sell_exclude' => $sellExclude,
            'cost_exclude' => (float) ($this->cost_exclude ?? 0),
            'tax_category' => $this->tax_category ?? OpportunityProductPricing::TAX_NON_WAPU,
            'item_kind' => $this->item_kind ?? OpportunityProductPricing::KIND_BARANG,
        ]);

        return round((float) $enriched['quantity'] * (float) $enriched['margin'], 2);
    }
}
