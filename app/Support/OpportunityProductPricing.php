<?php

namespace App\Support;

use App\Models\CrmSetting;

class OpportunityProductPricing
{
    public const TAX_WAPU = 'wapu';

    public const TAX_NON_WAPU = 'non_wapu';

    public const KIND_BARANG = 'barang';

    public const KIND_JASA = 'jasa';

    /**
     * Persentase PPN (contoh: 11). Prioritas: DB setting → config → 11.
     */
    public static function ppnPercent(): float
    {
        return CrmSetting::getFloat(
            'tax.ppn_percent',
            (float) config('crm.tax.ppn_percent', 11)
        );
    }

    /**
     * Multiplier include dari exclude (contoh: 1.11).
     */
    public static function ppnMultiplier(): float
    {
        return 1 + (self::ppnPercent() / 100);
    }

    /**
     * Persentase PPH (contoh: 2). Prioritas: DB setting → config → 2.
     */
    public static function pphPercent(): float
    {
        return CrmSetting::getFloat(
            'tax.pph_percent',
            (float) config('crm.tax.pph_percent', 2)
        );
    }

    /**
     * Rate PPH desimal (contoh: 0.02).
     */
    public static function pphRate(): float
    {
        return self::pphPercent() / 100;
    }

    /**
     * Faktor sisa setelah PPH untuk rumus margin (contoh: 0.98).
     */
    public static function afterPphFactor(): float
    {
        return 1 - self::pphRate();
    }

    public static function includeFromExclude(float $exclude): float
    {
        return round($exclude * self::ppnMultiplier(), 2);
    }

    public static function excludeFromInclude(float $include): float
    {
        if ($include <= 0) {
            return 0;
        }

        $multiplier = self::ppnMultiplier();
        if ($multiplier <= 0) {
            return 0;
        }

        return round($include / $multiplier, 2);
    }

    public static function appliesPph(string $taxCategory, string $itemKind): bool
    {
        if ($taxCategory === self::TAX_NON_WAPU && $itemKind === self::KIND_BARANG) {
            return false;
        }

        return true;
    }

    public static function pph(float $sellExclude, string $taxCategory, string $itemKind): float
    {
        if (! self::appliesPph($taxCategory, $itemKind)) {
            return 0;
        }

        return round($sellExclude * self::pphRate(), 2);
    }

    /**
     * Basis harga untuk margin: diskon item (harga net) bila > 0, selain itu harga jual.
     */
    public static function effectiveSellExclude(float $sellExclude, float $itemDiscount = 0): float
    {
        return $itemDiscount > 0 ? $itemDiscount : $sellExclude;
    }

    public static function margin(
        float $sellExclude,
        float $costExclude,
        string $taxCategory,
        string $itemKind,
        float $itemDiscount = 0
    ): float {
        $base = self::effectiveSellExclude($sellExclude, $itemDiscount);

        if (! self::appliesPph($taxCategory, $itemKind)) {
            return round($base - $costExclude, 2);
        }

        $pph = self::pph($base, $taxCategory, $itemKind);

        return round($base - $pph - $costExclude, 2);
    }

    public static function marginPercent(
        float $margin,
        float $sellExclude,
        float $itemDiscount = 0,
    ): ?float {
        $base = self::effectiveSellExclude($sellExclude, $itemDiscount);

        return $base > 0 ? round(($margin / $base) * 100, 2) : null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function enrichRow(array $row): array
    {
        $taxCategory = $row['tax_category'] ?? self::TAX_NON_WAPU;
        $itemKind = $row['item_kind'] ?? self::KIND_BARANG;
        $sellExclude = (float) ($row['sell_exclude'] ?? 0);
        $costExclude = (float) ($row['cost_exclude'] ?? 0);
        $itemDiscount = (float) ($row['discount_exclude'] ?? $row['item_discount'] ?? 0);
        $effectiveSell = self::effectiveSellExclude($sellExclude, $itemDiscount);
        $sellInclude = self::includeFromExclude($sellExclude);
        $costInclude = self::includeFromExclude($costExclude);
        $discountInclude = self::includeFromExclude($itemDiscount);
        $effectiveInclude = self::includeFromExclude($effectiveSell);
        $pph = self::pph($effectiveSell, $taxCategory, $itemKind);
        $margin = self::margin($sellExclude, $costExclude, $taxCategory, $itemKind, $itemDiscount);
        $marginPercent = self::marginPercent($margin, $sellExclude, $itemDiscount);
        $qty = (float) ($row['quantity'] ?? 1);

        return array_merge($row, [
            'tax_category' => $taxCategory,
            'item_kind' => $itemKind,
            'sell_exclude' => $sellExclude,
            'cost_exclude' => $costExclude,
            'discount_exclude' => $itemDiscount,
            'item_discount' => $itemDiscount,
            'effective_sell_exclude' => $effectiveSell,
            'price' => $effectiveInclude,
            'cost' => $costInclude,
            'sell_include' => $sellInclude,
            'effective_sell_include' => $effectiveInclude,
            'cost_include' => $costInclude,
            'discount_include' => $discountInclude,
            'pph' => $pph,
            'pph_applicable' => self::appliesPph($taxCategory, $itemKind),
            'margin' => $margin,
            'margin_percent' => $marginPercent,
            'subtotal' => round($qty * $effectiveInclude, 2),
        ]);
    }
}
