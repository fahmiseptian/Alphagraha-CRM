<?php

namespace App\Support;

use App\Models\CrmSetting;

class OpportunityProductPricing
{
    public const TAX_WAPU = 'wapu';

    public const TAX_NON_WAPU = 'non_wapu';

    public const TAX_INAPROC = 'inaproc';

    public const KIND_BARANG = 'barang';

    public const KIND_JASA = 'jasa';

    /**
     * @return list<string>
     */
    public static function taxCategories(): array
    {
        return [self::TAX_NON_WAPU, self::TAX_WAPU, self::TAX_INAPROC];
    }

    public static function taxCategoryLabel(string $taxCategory): string
    {
        return match ($taxCategory) {
            self::TAX_WAPU => 'Wapu',
            self::TAX_INAPROC => 'Inaproc',
            default => 'Non Wapu',
        };
    }

    /**
     * Ringkasan komponen pajak per kategori (untuk UI / calculator).
     *
     * @return array{label: string, barang: string, jasa: string}
     */
    public static function taxCategorySummary(string $taxCategory): array
    {
        $ppn = self::formatPercentLabel(self::ppnPercent());
        $pphJasaNw = self::formatPercentLabel(self::pphNonWapuJasaPercent());
        $pphBarang = self::formatPercentLabel(self::pphWapuBarangPercent());
        $pphJasa = self::formatPercentLabel(self::pphWapuJasaPercent());
        $pnbp = self::formatPercentLabel(self::pnbpPercent());
        $pph29 = self::formatPercentLabel(self::pph29Percent());

        return match ($taxCategory) {
            self::TAX_WAPU => [
                'label' => 'Wapu',
                'barang' => "PPN {$ppn}% + PPH {$pphBarang}%",
                'jasa' => "PPN {$ppn}% + PPH {$pphJasa}%",
            ],
            self::TAX_INAPROC => [
                'label' => 'Inaproc',
                'barang' => "PPN {$ppn}% + PPH {$pphBarang}% + PNBP {$pnbp}% + PPH 29 {$pph29}%",
                'jasa' => "PPN {$ppn}% + PPH {$pphJasa}% + PNBP {$pnbp}% + PPH 29 {$pph29}%",
            ],
            default => [
                'label' => 'Non Wapu',
                'barang' => "PPN {$ppn}%",
                'jasa' => "PPN {$ppn}% + PPH {$pphJasaNw}%",
            ],
        };
    }

    public static function formatPercentLabel(float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');
    }

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
     * Legacy single PPH % (fallback). Prefer pphPercentFor().
     */
    public static function pphPercent(): float
    {
        return CrmSetting::getFloat(
            'tax.pph_non_wapu_jasa',
            CrmSetting::getFloat(
                'tax.pph_percent',
                (float) config('crm.tax.pph_percent', 2)
            )
        );
    }

    public static function pphNonWapuJasaPercent(): float
    {
        return CrmSetting::getFloat('tax.pph_non_wapu_jasa', self::pphPercent());
    }

    public static function pphWapuBarangPercent(): float
    {
        return CrmSetting::getFloat('tax.pph_wapu_barang', 1.5);
    }

    public static function pphWapuJasaPercent(): float
    {
        return CrmSetting::getFloat('tax.pph_wapu_jasa', 2.0);
    }

    public static function pnbpPercent(): float
    {
        return CrmSetting::getFloat('tax.pnbp_percent', 0.4);
    }

    public static function pph29Percent(): float
    {
        return CrmSetting::getFloat('tax.pph29_percent', 22.0);
    }

    public static function appliesPnbp(string $taxCategory): bool
    {
        return $taxCategory === self::TAX_INAPROC;
    }

    public static function appliesPph29(string $taxCategory): bool
    {
        return $taxCategory === self::TAX_INAPROC;
    }

    /**
     * PPH % sesuai kategori pajak + jenis item.
     * Non Wapu + Barang → 0.
     * Inaproc memakai rate Wapu (Barang 1.5% / Jasa 2%).
     */
    public static function pphPercentFor(string $taxCategory, string $itemKind): float
    {
        if ($taxCategory === self::TAX_NON_WAPU && $itemKind === self::KIND_BARANG) {
            return 0.0;
        }

        if ($taxCategory === self::TAX_NON_WAPU && $itemKind === self::KIND_JASA) {
            return self::pphNonWapuJasaPercent();
        }

        if (in_array($taxCategory, [self::TAX_WAPU, self::TAX_INAPROC], true) && $itemKind === self::KIND_BARANG) {
            return self::pphWapuBarangPercent();
        }

        if (in_array($taxCategory, [self::TAX_WAPU, self::TAX_INAPROC], true) && $itemKind === self::KIND_JASA) {
            return self::pphWapuJasaPercent();
        }

        return self::pphPercent();
    }

    /**
     * Rate PPH desimal untuk kombinasi kategori/jenis.
     */
    public static function pphRateFor(string $taxCategory, string $itemKind): float
    {
        return self::pphPercentFor($taxCategory, $itemKind) / 100;
    }

    /**
     * @deprecated Gunakan pphRateFor(). Rate legacy (Non Wapu Jasa).
     */
    public static function pphRate(): float
    {
        return self::pphPercent() / 100;
    }

    /**
     * Faktor sisa setelah PPH untuk rumus margin reverse (legacy / Non Wapu Jasa).
     */
    public static function afterPphFactor(): float
    {
        return 1 - self::pphRate();
    }

    public static function afterPphFactorFor(string $taxCategory, string $itemKind): float
    {
        return 1 - self::pphRateFor($taxCategory, $itemKind);
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
        return self::pphPercentFor($taxCategory, $itemKind) > 0;
    }

    public static function pph(float $sellExclude, string $taxCategory, string $itemKind): float
    {
        $rate = self::pphRateFor($taxCategory, $itemKind);
        if ($rate <= 0) {
            return 0;
        }

        return round($sellExclude * $rate, 2);
    }

    public static function pnbp(float $sellExclude, string $taxCategory): float
    {
        if (! self::appliesPnbp($taxCategory)) {
            return 0;
        }

        $rate = self::pnbpPercent() / 100;
        if ($rate <= 0) {
            return 0;
        }

        return round($sellExclude * $rate, 2);
    }

    /**
     * Margin kotor setelah PPH (+ PNBP untuk Inaproc), sebelum PPH 29.
     */
    public static function grossMargin(
        float $sellExclude,
        float $costExclude,
        string $taxCategory,
        string $itemKind,
        float $itemDiscount = 0
    ): float {
        $base = self::effectiveSellExclude($sellExclude, $itemDiscount);
        $pph = self::pph($base, $taxCategory, $itemKind);
        $pnbp = self::pnbp($base, $taxCategory);

        return round($base - $pph - $pnbp - $costExclude, 2);
    }

    /**
     * PPH Pasal 29 — hanya Inaproc, dihitung dari margin kotor (bila positif).
     */
    public static function pph29(
        float $sellExclude,
        float $costExclude,
        string $taxCategory,
        string $itemKind,
        float $itemDiscount = 0
    ): float {
        if (! self::appliesPph29($taxCategory)) {
            return 0;
        }

        $gross = self::grossMargin($sellExclude, $costExclude, $taxCategory, $itemKind, $itemDiscount);
        if ($gross <= 0) {
            return 0;
        }

        $rate = self::pph29Percent() / 100;
        if ($rate <= 0) {
            return 0;
        }

        return round($gross * $rate, 2);
    }

    /**
     * Basis harga untuk margin: diskon item (harga net) bila > 0, selain itu harga jual.
     */
    public static function effectiveSellExclude(float $sellExclude, float $itemDiscount = 0): float
    {
        return $itemDiscount > 0 ? $itemDiscount : $sellExclude;
    }

    /**
     * Margin bersih: basis − PPH − PNBP (Inaproc) − PPH 29 (Inaproc, dari margin kotor) − modal.
     */
    public static function margin(
        float $sellExclude,
        float $costExclude,
        string $taxCategory,
        string $itemKind,
        float $itemDiscount = 0
    ): float {
        $gross = self::grossMargin($sellExclude, $costExclude, $taxCategory, $itemKind, $itemDiscount);
        $pph29 = self::pph29($sellExclude, $costExclude, $taxCategory, $itemKind, $itemDiscount);

        return round($gross - $pph29, 2);
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
        $pphPercent = self::pphPercentFor($taxCategory, $itemKind);
        $pph = self::pph($effectiveSell, $taxCategory, $itemKind);
        $pnbp = self::pnbp($effectiveSell, $taxCategory);
        $grossMargin = self::grossMargin($sellExclude, $costExclude, $taxCategory, $itemKind, $itemDiscount);
        $pph29 = self::pph29($sellExclude, $costExclude, $taxCategory, $itemKind, $itemDiscount);
        $margin = self::margin($sellExclude, $costExclude, $taxCategory, $itemKind, $itemDiscount);
        $marginPercent = self::marginPercent($margin, $sellExclude, $itemDiscount);
        $qty = (float) ($row['quantity'] ?? 1);

        return array_merge($row, [
            'tax_category' => $taxCategory,
            'tax_category_label' => self::taxCategoryLabel($taxCategory),
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
            'pph_percent' => $pphPercent,
            'pph_applicable' => self::appliesPph($taxCategory, $itemKind),
            'pnbp' => $pnbp,
            'pnbp_percent' => self::appliesPnbp($taxCategory) ? self::pnbpPercent() : 0.0,
            'pnbp_applicable' => self::appliesPnbp($taxCategory),
            'gross_margin' => $grossMargin,
            'pph29' => $pph29,
            'pph29_percent' => self::appliesPph29($taxCategory) ? self::pph29Percent() : 0.0,
            'pph29_applicable' => self::appliesPph29($taxCategory),
            'margin' => $margin,
            'margin_percent' => $marginPercent,
            'subtotal' => round($qty * $effectiveInclude, 2),
        ]);
    }

    public static function defaultQuotationTerms(?float $ppnPercent = null): string
    {
        $ppn = $ppnPercent ?? self::ppnPercent();
        $ppnLabel = self::formatPercentLabel($ppn);

        $stored = CrmSetting::get('quotation.default_terms');
        if (is_string($stored) && trim($stored) !== '') {
            return str_replace(
                ['{{ppn}}', '{ppn}', '{{PPN}}'],
                [$ppnLabel, $ppnLabel, $ppnLabel],
                $stored
            );
        }

        return "1. Harga di atas belum termasuk PPN {$ppnLabel}%\n2. Harga dan ketersediaan barang dapat berubah sewaktu-waktu tanpa pemberitahuan terlebih dahulu.";
    }
}
