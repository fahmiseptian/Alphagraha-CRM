<?php

namespace App\Models;

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
}
