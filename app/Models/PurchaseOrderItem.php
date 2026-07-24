<?php

namespace App\Models;

use App\Support\OpportunityProductPricing;
use App\Support\PurchaseOrderPricing;
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
     * Tambahan % sesuai payment_term dari crm_settings (Cash/TOP).
     *
     * @return array{
     *     modal: float,
     *     surcharge_percent: float,
     *     extra_exclude: float,
     *     jumlah_exclude: float,
     *     harga_include: float,
     *     extra_include: float,
     *     jumlah_include: float,
     * }
     */
    public function pricingBreakdown(?bool $isCash = null, ?string $paymentTerm = null): array
    {
        $term = $paymentTerm
            ?? $this->purchaseOrder?->payment_term
            ?? PurchaseOrder::PAYMENT_TOP;

        if ($isCash !== null) {
            $term = $isCash ? PurchaseOrder::PAYMENT_CASH : PurchaseOrder::PAYMENT_TOP;
        }

        $modal = round((float) $this->unit_price, 2);
        $percent = PurchaseOrderPricing::surchargePercent($term);
        $rate = $percent / 100;
        $extraExclude = $rate > 0 ? round($modal * $rate, 2) : 0.0;
        $jumlahExclude = round($modal + $extraExclude, 2);
        $hargaInclude = OpportunityProductPricing::includeFromExclude($modal);
        $extraInclude = $rate > 0 ? round($hargaInclude * $rate, 2) : 0.0;
        $jumlahInclude = round($hargaInclude + $extraInclude, 2);

        return [
            'modal' => $modal,
            'surcharge_percent' => $percent,
            'extra_exclude' => $extraExclude,
            'jumlah_exclude' => $jumlahExclude,
            'harga_include' => $hargaInclude,
            'extra_include' => $extraInclude,
            'jumlah_include' => $jumlahInclude,
        ];
    }
}
