<?php

namespace App\Support;

use App\Models\CrmSetting;
use App\Models\PurchaseOrder;

/**
 * Biaya tambahan kondisi bayar PO (TOP / Cash) dari crm_settings.
 */
class PurchaseOrderPricing
{
    public const DEFAULT_CASH_PERCENT = 1.0;

    public const DEFAULT_TOP_PERCENT = 0.0;

    public static function cashSurchargePercent(): float
    {
        return CrmSetting::getFloat('po.surcharge_cash_percent', self::DEFAULT_CASH_PERCENT);
    }

    public static function topSurchargePercent(): float
    {
        return CrmSetting::getFloat('po.surcharge_top_percent', self::DEFAULT_TOP_PERCENT);
    }

    /**
     * Persentase tambahan untuk payment_term (cash|top).
     */
    public static function surchargePercent(?string $paymentTerm): float
    {
        if ($paymentTerm === PurchaseOrder::PAYMENT_CASH) {
            return self::cashSurchargePercent();
        }

        return self::topSurchargePercent();
    }

    /**
     * Rate desimal (contoh: 1% → 0.01).
     */
    public static function surchargeRate(?string $paymentTerm): float
    {
        return self::surchargePercent($paymentTerm) / 100;
    }

    public static function hasSurcharge(?string $paymentTerm): bool
    {
        return self::surchargePercent($paymentTerm) > 0;
    }
}
