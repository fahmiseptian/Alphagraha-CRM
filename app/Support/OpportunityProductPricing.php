<?php

namespace App\Support;

class OpportunityProductPricing
{
    public const TAX_MULTIPLIER = 1.11;

    public const TAX_WAPU = 'wapu';

    public const TAX_NON_WAPU = 'non_wapu';

    public const KIND_BARANG = 'barang';

    public const KIND_JASA = 'jasa';

    public const PPH_RATE = 0.02;

    public static function includeFromExclude(float $exclude): float
    {
        return round($exclude * self::TAX_MULTIPLIER, 2);
    }

    public static function excludeFromInclude(float $include): float
    {
        if ($include <= 0) {
            return 0;
        }

        return round($include / self::TAX_MULTIPLIER, 2);
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

        return round($sellExclude * self::PPH_RATE, 2);
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
