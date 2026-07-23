<?php

namespace App\Models;

use App\Support\OpportunityProductPricing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $table = 'crm_purchase_order_items';

    protected $fillable = [
        'purchase_order_id',
        'product_name',
        'quantity',
        'description',
        'note',
        'unit_price',
        'line_total',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Breakdown harga per unit (modal = unit_price exclude).
     * Cash: +1% exclude & +1% include.
     *
     * @return array{
     *     modal: float,
     *     extra_exclude: float,
     *     jumlah_exclude: float,
     *     harga_include: float,
     *     extra_include: float,
     *     jumlah_include: float,
     * }
     */
    public function pricingBreakdown(?bool $isCash = null): array
    {
        $isCash ??= $this->purchaseOrder?->isCash() ?? false;
        $modal = round((float) $this->unit_price, 2);
        $extraExclude = $isCash ? round($modal * 0.01, 2) : 0.0;
        $jumlahExclude = round($modal + $extraExclude, 2);
        $hargaInclude = OpportunityProductPricing::includeFromExclude($modal);
        $extraInclude = $isCash ? round($hargaInclude * 0.01, 2) : 0.0;
        $jumlahInclude = round($hargaInclude + $extraInclude, 2);

        return [
            'modal' => $modal,
            'extra_exclude' => $extraExclude,
            'jumlah_exclude' => $jumlahExclude,
            'harga_include' => $hargaInclude,
            'extra_include' => $extraInclude,
            'jumlah_include' => $jumlahInclude,
        ];
    }
}
