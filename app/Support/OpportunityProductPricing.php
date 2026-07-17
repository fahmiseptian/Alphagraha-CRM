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

    public static function margin(
        float $sellExclude,
        float $costExclude,
        string $taxCategory,
        string $itemKind
    ): float {
        if (! self::appliesPph($taxCategory, $itemKind)) {
            return round($sellExclude - $costExclude, 2);
        }

        $pph = self::pph($sellExclude, $taxCategory, $itemKind);

        return round($sellExclude - $pph - $costExclude, 2);
    }

    public static function marginPercent(
        float $margin,
        float $sellExclude,
    ): ?float {
        return $sellExclude > 0 ? round(($margin / $sellExclude) * 100, 2) : null;
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
        $sellInclude = self::includeFromExclude($sellExclude);
        $costInclude = self::includeFromExclude($costExclude);
        $pph = self::pph($sellExclude, $taxCategory, $itemKind);
        $margin = self::margin($sellExclude, $costExclude, $taxCategory, $itemKind);
        $marginPercent = self::marginPercent($margin, $sellExclude);
        $qty = (float) ($row['quantity'] ?? 1);

        return array_merge($row, [
            'tax_category' => $taxCategory,
            'item_kind' => $itemKind,
            'sell_exclude' => $sellExclude,
            'cost_exclude' => $costExclude,
            'price' => $sellInclude,
            'cost' => $costInclude,
            'sell_include' => $sellInclude,
            'cost_include' => $costInclude,
            'pph' => $pph,
            'pph_applicable' => self::appliesPph($taxCategory, $itemKind),
            'margin' => $margin,
            'margin_percent' => $marginPercent,
            'subtotal' => round($qty * $sellInclude, 2),
        ]);
    }
}
