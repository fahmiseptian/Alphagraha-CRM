<?php

namespace App\Support;

use App\Models\CrmSetting;

class OpportunityProductPricing
{
    public const TAX_WAPU = 'wapu';

    public const TAX_NON_WAPU = 'non_wapu';

    public const TAX_INAPROC = 'inaproc';

    public const TAX_ZINIT = 'zinit';

    public const KIND_BARANG = 'barang';

    public const KIND_JASA = 'jasa';

    /**
     * @return list<string>
     */
    public static function taxCategories(): array
    {
        return [self::TAX_NON_WAPU, self::TAX_WAPU, self::TAX_INAPROC, self::TAX_ZINIT];
    }

    public static function taxCategoryLabel(string $taxCategory): string
    {
        return match ($taxCategory) {
            self::TAX_WAPU => 'Wapu',
            self::TAX_INAPROC => 'Inaproc',
            self::TAX_ZINIT => 'Zinit',
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
        $pnbp = 'berjenjang (jual include)';
        $pph29 = self::formatPercentLabel(self::pph29Percent());

        return match ($taxCategory) {
            self::TAX_WAPU => [
                'label' => 'Wapu',
                'barang' => "PPN {$ppn}% + PPH {$pphBarang}%",
                'jasa' => "PPN {$ppn}% + PPH {$pphJasa}%",
            ],
            self::TAX_INAPROC => [
                'label' => 'Inaproc',
                'barang' => "PPN {$ppn}% + PPH {$pphBarang}% + PNBP {$pnbp} + PPH 29 {$pph29}%",
                'jasa' => "PPN {$ppn}% + PPH {$pphJasa}% + PNBP {$pnbp} + PPH 29 {$pph29}%",
            ],
            self::TAX_ZINIT => [
                'label' => 'Zinit',
                'barang' => 'Fee Zinit (Platform + Service% × jual include); margin = jual − fee − modal',
                'jasa' => 'Fee Zinit + PPH ' . self::formatPercentLabel(self::pphNonWapuJasaPercent()) . '%; margin = jual − PPH − fee − modal',
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
        // Legacy: rate jenjang pertama (untuk ringkasan UI lama).
        $tiers = self::pnbpTiers();

        return (float) ($tiers[0]['rate_percent'] ?? CrmSetting::getFloat('tax.pnbp_percent', 0.4));
    }

    /**
     * Default PNBP berjenjang (basis: harga jual include).
     * Setiap jenjang: MAX(jual_include) → MIN(jual_include × rate, cap).
     *
     * @return list<array{max: ?float, rate_percent: float, cap: float}>
     */
    public static function defaultPnbpTiers(): array
    {
        return [
            ['max' => 200_000_000.0, 'rate_percent' => 0.4, 'cap' => 600_000.0],
            ['max' => 1_000_000_000.0, 'rate_percent' => 0.3, 'cap' => 2_000_000.0],
            ['max' => 5_000_000_000.0, 'rate_percent' => 0.2, 'cap' => 5_000_000.0],
            ['max' => 50_000_000_000.0, 'rate_percent' => 0.1, 'cap' => 25_000_000.0],
            ['max' => null, 'rate_percent' => 0.05, 'cap' => 200_000_000.0],
        ];
    }

    /**
     * @return list<array{max: ?float, rate_percent: float, cap: float}>
     */
    public static function pnbpTiers(): array
    {
        $stored = CrmSetting::get('tax.pnbp_tiers', null);
        if (is_array($stored) && $stored !== []) {
            return self::normalizePnbpTiers($stored);
        }

        return self::defaultPnbpTiers();
    }

    /**
     * @param  list<array<string, mixed>>|array<int, mixed>  $tiers
     * @return list<array{max: ?float, rate_percent: float, cap: float}>
     */
    public static function normalizePnbpTiers(array $tiers): array
    {
        $normalized = [];

        foreach ($tiers as $tier) {
            if (! is_array($tier)) {
                continue;
            }

            $maxRaw = $tier['max'] ?? null;
            $max = ($maxRaw === null || $maxRaw === '' || (float) $maxRaw <= 0)
                ? null
                : round((float) $maxRaw, 2);
            $rate = round((float) ($tier['rate_percent'] ?? 0), 4);
            $cap = round((float) ($tier['cap'] ?? 0), 2);

            if ($rate < 0) {
                $rate = 0.0;
            }
            if ($cap < 0) {
                $cap = 0.0;
            }

            $normalized[] = [
                'max' => $max,
                'rate_percent' => $rate,
                'cap' => $cap,
            ];
        }

        if ($normalized === []) {
            return self::defaultPnbpTiers();
        }

        usort($normalized, function (array $a, array $b) {
            if ($a['max'] === null && $b['max'] === null) {
                return 0;
            }
            if ($a['max'] === null) {
                return 1;
            }
            if ($b['max'] === null) {
                return -1;
            }

            return $a['max'] <=> $b['max'];
        });

        return array_values($normalized);
    }

    /**
     * @return array{max: ?float, rate_percent: float, cap: float}
     */
    public static function matchPnbpTier(float $sellInclude): array
    {
        $tiers = self::pnbpTiers();
        $fallback = end($tiers) ?: [
            'max' => null,
            'rate_percent' => 0.0,
            'cap' => 0.0,
        ];

        foreach ($tiers as $tier) {
            if ($tier['max'] === null) {
                return $tier;
            }
            if ($sellInclude <= (float) $tier['max']) {
                return $tier;
            }
        }

        return $fallback;
    }

    /**
     * PNBP dari harga jual include: MIN(include × rate, cap) sesuai jenjang.
     */
    public static function pnbpFromInclude(float $sellInclude): float
    {
        if ($sellInclude <= 0) {
            return 0.0;
        }

        $tier = self::matchPnbpTier($sellInclude);
        $rate = ((float) $tier['rate_percent']) / 100;
        $amount = $sellInclude * $rate;
        $cap = (float) ($tier['cap'] ?? 0);
        if ($cap > 0) {
            $amount = min($amount, $cap);
        }

        return round($amount, 2);
    }

    public static function pph29Percent(): float
    {
        return CrmSetting::getFloat('tax.pph29_percent', 22.0);
    }

    public const ROYALTY_DALAM = 'dalam';

    public const ROYALTY_LUAR = 'luar';

    /**
     * @return list<string>
     */
    public static function royaltyTypes(): array
    {
        return [self::ROYALTY_DALAM, self::ROYALTY_LUAR];
    }

    public static function royaltyTypeLabel(?string $type): string
    {
        return match (self::normalizeRoyaltyType($type)) {
            self::ROYALTY_DALAM => 'Dalam negeri',
            self::ROYALTY_LUAR => 'Luar negeri',
            default => '',
        };
    }

    /**
     * Normalisasi tipe royalti: '' | dalam | luar.
     * Legacy boolean/flag true → luar (rate lama 20%).
     */
    public static function normalizeRoyaltyType(mixed $value): string
    {
        if ($value === null || $value === false || $value === '') {
            return '';
        }

        if (is_bool($value)) {
            return $value ? self::ROYALTY_LUAR : '';
        }

        $v = strtolower(trim((string) $value));
        if ($v === '' || in_array($v, ['0', 'false', 'no', 'off', 'none'], true)) {
            return '';
        }

        if (in_array($v, [self::ROYALTY_DALAM, 'dn', 'local', '15'], true)) {
            return self::ROYALTY_DALAM;
        }

        if (in_array($v, [self::ROYALTY_LUAR, 'ln', 'foreign', '20', '1', 'true', 'yes', 'on'], true)) {
            return self::ROYALTY_LUAR;
        }

        return '';
    }

    public static function hasRoyaltyFlag(mixed $value): bool
    {
        if (is_string($value) && in_array(self::normalizeRoyaltyType($value), self::royaltyTypes(), true)) {
            return true;
        }

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Persentase royalti luar negeri (legacy alias).
     */
    public static function royaltyPercent(): float
    {
        return self::royaltyLuarPercent();
    }

    public static function royaltyDalamPercent(): float
    {
        return CrmSetting::getFloat('tax.royalty_dalam_percent', 15.0);
    }

    public static function royaltyLuarPercent(): float
    {
        return CrmSetting::getFloat(
            'tax.royalty_luar_percent',
            CrmSetting::getFloat('tax.royalty_percent', 20.0)
        );
    }

    public static function royaltyPercentFor(mixed $royaltyType): float
    {
        return match (self::normalizeRoyaltyType($royaltyType)) {
            self::ROYALTY_DALAM => self::royaltyDalamPercent(),
            self::ROYALTY_LUAR => self::royaltyLuarPercent(),
            default => 0.0,
        };
    }

    /**
     * Royalti per unit dari harga modal exclude.
     */
    public static function royalty(float $costExclude, mixed $royaltyTypeOrHasRoyalty = false): float
    {
        $type = is_bool($royaltyTypeOrHasRoyalty)
            ? ($royaltyTypeOrHasRoyalty ? self::ROYALTY_LUAR : '')
            : self::normalizeRoyaltyType($royaltyTypeOrHasRoyalty);

        $rate = self::royaltyPercentFor($type) / 100;
        if ($rate <= 0) {
            return 0.0;
        }

        return round(max(0.0, $costExclude) * $rate, 2);
    }

    /**
     * Default rate scale Zinit (basis: PO Dealing / RFP volume).
     *
     * @return list<array{max: ?float, platform_fee: float, rate_percent: float, cap: ?float}>
     */
    public static function defaultZinitTiers(): array
    {
        return [
            ['max' => 20_000_000.0, 'platform_fee' => 100_000.0, 'rate_percent' => 1.0, 'cap' => null],
            ['max' => 200_000_000.0, 'platform_fee' => 300_000.0, 'rate_percent' => 0.8, 'cap' => null],
            ['max' => 500_000_000.0, 'platform_fee' => 1_000_000.0, 'rate_percent' => 0.6, 'cap' => null],
            ['max' => 1_600_000_000.0, 'platform_fee' => 2_000_000.0, 'rate_percent' => 0.4, 'cap' => null],
            ['max' => 5_000_000_000.0, 'platform_fee' => 3_000_000.0, 'rate_percent' => 0.3, 'cap' => null],
            ['max' => 16_000_000_000.0, 'platform_fee' => 5_000_000.0, 'rate_percent' => 0.2, 'cap' => null],
            ['max' => null, 'platform_fee' => 10_000_000.0, 'rate_percent' => 0.1, 'cap' => 80_000_000.0],
        ];
    }

    /**
     * @return list<array{max: ?float, platform_fee: float, rate_percent: float, cap: ?float}>
     */
    public static function zinitTiers(): array
    {
        $stored = CrmSetting::get('tax.zinit_tiers', null);
        if (is_array($stored) && $stored !== []) {
            return self::normalizeZinitTiers($stored);
        }

        return self::defaultZinitTiers();
    }

    /**
     * @param  list<array<string, mixed>>|array<int, mixed>  $tiers
     * @return list<array{max: ?float, platform_fee: float, rate_percent: float, cap: ?float}>
     */
    public static function normalizeZinitTiers(array $tiers): array
    {
        $normalized = [];

        foreach ($tiers as $tier) {
            if (! is_array($tier)) {
                continue;
            }

            $maxRaw = $tier['max'] ?? null;
            $max = ($maxRaw === null || $maxRaw === '' || (float) $maxRaw <= 0)
                ? null
                : round((float) $maxRaw, 2);
            $platform = round((float) ($tier['platform_fee'] ?? 0), 2);
            $rate = round((float) ($tier['rate_percent'] ?? 0), 4);
            $capRaw = $tier['cap'] ?? null;
            $cap = ($capRaw === null || $capRaw === '' || (float) $capRaw <= 0)
                ? null
                : round((float) $capRaw, 2);

            if ($platform < 0) {
                $platform = 0.0;
            }
            if ($rate < 0) {
                $rate = 0.0;
            }

            $normalized[] = [
                'max' => $max,
                'platform_fee' => $platform,
                'rate_percent' => $rate,
                'cap' => $cap,
            ];
        }

        if ($normalized === []) {
            return self::defaultZinitTiers();
        }

        usort($normalized, function (array $a, array $b) {
            if ($a['max'] === null && $b['max'] === null) {
                return 0;
            }
            if ($a['max'] === null) {
                return 1;
            }
            if ($b['max'] === null) {
                return -1;
            }

            return $a['max'] <=> $b['max'];
        });

        return array_values($normalized);
    }

    /**
     * @return array{max: ?float, platform_fee: float, rate_percent: float, cap: ?float}
     */
    public static function matchZinitTier(float $volume): array
    {
        $tiers = self::zinitTiers();
        $fallback = end($tiers) ?: [
            'max' => null,
            'platform_fee' => 0.0,
            'rate_percent' => 0.0,
            'cap' => null,
        ];

        // Samakan Excel IFS: tier pertama memakai < max, tier berikutnya <= max.
        foreach ($tiers as $index => $tier) {
            if ($tier['max'] === null) {
                return $tier;
            }

            $max = (float) $tier['max'];
            if ($index === 0) {
                if ($volume < $max) {
                    return $tier;
                }
                continue;
            }

            if ($volume <= $max) {
                return $tier;
            }
        }

        return $fallback;
    }

    /**
     * Hitung Fee Zinit dari volume jual include (grand total include / K52).
     * Fee = Platform Fee + MIN(volume × rate%, cap bila ada).
     *
     * @return array{
     *     volume: float,
     *     platform_fee: float,
     *     service_fee: float,
     *     success_fee: float,
     *     rate_percent: float,
     *     cap: ?float,
     *     tier_max: ?float
     * }
     */
    public static function zinitFeesFromVolume(float $volume): array
    {
        $volume = max(0.0, round($volume, 2));
        if ($volume <= 0) {
            return [
                'volume' => 0.0,
                'platform_fee' => 0.0,
                'service_fee' => 0.0,
                'success_fee' => 0.0,
                'rate_percent' => 0.0,
                'cap' => null,
                'tier_max' => null,
            ];
        }

        $tier = self::matchZinitTier($volume);
        $platform = round((float) $tier['platform_fee'], 2);
        $rate = (float) $tier['rate_percent'];
        $service = round($volume * ($rate / 100), 2);
        $cap = $tier['cap'];
        if ($cap !== null && $cap > 0) {
            $service = min($service, (float) $cap);
        }
        $success = round($platform + $service, 2);

        return [
            'volume' => $volume,
            'platform_fee' => $platform,
            'service_fee' => $service,
            'success_fee' => $success,
            'rate_percent' => $rate,
            'cap' => $cap,
            'tier_max' => $tier['max'],
        ];
    }

    /**
     * Fee Zinit dari harga jual exclude (standar) × qty.
     * Rate scale & service % memakai basis jual include.
     *
     * @return array{
     *     volume: float,
     *     platform_fee: float,
     *     service_fee: float,
     *     success_fee: float,
     *     rate_percent: float,
     *     cap: ?float,
     *     tier_max: ?float
     * }
     */
    public static function zinitFeesFromSellExclude(float $sellExclude, float $quantity = 1): array
    {
        $qty = $quantity > 0 ? $quantity : 1;
        $includeVolume = round(self::includeFromExclude($sellExclude) * $qty, 2);

        return self::zinitFeesFromVolume($includeVolume);
    }

    public static function appliesZinit(string $taxCategory): bool
    {
        return $taxCategory === self::TAX_ZINIT;
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

        if ($taxCategory === self::TAX_ZINIT && $itemKind === self::KIND_JASA) {
            return self::pphNonWapuJasaPercent();
        }

        if ($taxCategory === self::TAX_ZINIT) {
            return 0.0;
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

        return self::pnbpFromInclude(self::includeFromExclude($sellExclude));
    }

    /**
     * Margin kotor setelah PPH, sebelum PNBP / PPH 29 / Fee Zinit.
     *
     * Wapu & Inaproc: (harga jual − PPH) − modal include
     * Zinit: jual excl − PPH (jika jasa) − Fee Zinit/qty − modal exclude
     * Non Wapu: basis − PPH − modal exclude
     */
    public static function grossMargin(
        float $sellExclude,
        float $costExclude,
        string $taxCategory,
        string $itemKind,
        float $itemDiscount = 0,
        float $quantity = 1
    ): float {
        if ($taxCategory === self::TAX_ZINIT) {
            $base = self::effectiveSellExclude($sellExclude, $itemDiscount);
            $pph = self::pph($base, $taxCategory, $itemKind);

            // GP per baris tanpa Fee Zinit — fee dipotong sekali di tingkat opportunity (Fix GP).
            return round($base - $pph - $costExclude, 2);
        }

        $base = self::effectiveSellExclude($sellExclude, $itemDiscount);
        $pph = self::pph($base, $taxCategory, $itemKind);

        if ($taxCategory === self::TAX_WAPU || $taxCategory === self::TAX_INAPROC) {
            $costInclude = self::includeFromExclude($costExclude);

            return round($base - $pph - $costInclude, 2);
        }

        return round($base - $pph - $costExclude, 2);
    }

    /**
     * PPH Pasal 29 — hanya Inaproc.
     * Per unit: (harga jual exclude − modal exclude) × rate.
     * Total baris = hasil × qty (di pemanggil).
     */
    public static function pph29(
        float $sellExclude,
        float $costExclude,
        string $taxCategory,
        string $itemKind = self::KIND_BARANG,
        float $itemDiscount = 0
    ): float {
        if (! self::appliesPph29($taxCategory)) {
            return 0;
        }

        $rate = self::pph29Percent() / 100;
        if ($rate <= 0) {
            return 0;
        }

        $base = self::effectiveSellExclude($sellExclude, $itemDiscount);
        $spread = $base - $costExclude;
        if ($spread <= 0) {
            return 0;
        }

        return round($spread * $rate, 2);
    }

    /**
     * Basis harga untuk margin: diskon item (harga net) bila > 0, selain itu harga jual.
     */
    public static function effectiveSellExclude(float $sellExclude, float $itemDiscount = 0): float
    {
        return $itemDiscount > 0 ? $itemDiscount : $sellExclude;
    }

    /**
     * Margin bersih.
     * Wapu: (jual − PPH) − modal include
     * Inaproc: (jual − PPH − modal include) − PNBP − PPH 29
     * Zinit: jual excl − PPH (jasa) − modal excl (Fee Zinit dipotong sekali di opportunity)
     * Non Wapu: basis − PPH − modal exclude
     * + Royalti (opsional, semua kategori): − modal excl × royalty%
     * + Ongkir per satuan (opsional): − shipping exclude
     */
    public static function margin(
        float $sellExclude,
        float $costExclude,
        string $taxCategory,
        string $itemKind,
        float $itemDiscount = 0,
        float $quantity = 1,
        mixed $royaltyTypeOrHasRoyalty = false,
        float $shippingExclude = 0
    ): float {
        if ($taxCategory === self::TAX_ZINIT) {
            $baseMargin = self::grossMargin($sellExclude, $costExclude, $taxCategory, $itemKind, $itemDiscount, $quantity);
        } else {
            $base = self::effectiveSellExclude($sellExclude, $itemDiscount);
            $gross = self::grossMargin($sellExclude, $costExclude, $taxCategory, $itemKind, $itemDiscount);
            $pnbp = self::pnbp($base, $taxCategory);
            $pph29 = self::pph29($sellExclude, $costExclude, $taxCategory, $itemKind, $itemDiscount);
            $baseMargin = round($gross - $pnbp - $pph29, 2);
        }

        $royalty = self::royalty($costExclude, $royaltyTypeOrHasRoyalty);
        $shipping = max(0.0, round($shippingExclude, 2));

        return round($baseMargin - $royalty - $shipping, 2);
    }

    public static function marginPercent(
        float $margin,
        float $sellExclude,
        string $taxCategory,
        string $itemKind,
        float $itemDiscount = 0,
        float $quantity = 1,
    ): ?float {
        if ($taxCategory === self::TAX_ZINIT) {
            $base = self::effectiveSellExclude($sellExclude, $itemDiscount);

            return $base > 0 ? round(($margin / $base) * 100, 2) : null;
        }

        $effectiveSell = self::effectiveSellExclude($sellExclude, $itemDiscount);

        // Wapu & Inaproc: % terhadap harga setelah PPH (jual exclude − PPH).
        if ($taxCategory === self::TAX_WAPU || $taxCategory === self::TAX_INAPROC) {
            $pph = self::pph($effectiveSell, $taxCategory, $itemKind);
            $denom = $effectiveSell - $pph;
        } else {
            $denom = $effectiveSell;
        }

        return $denom > 0 ? round(($margin / $denom) * 100, 2) : null;
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
        $shippingExclude = max(0.0, (float) ($row['shipping_exclude'] ?? $row['item_shipping'] ?? 0));
        $qty = (float) ($row['quantity'] ?? 1);
        if ($qty <= 0) {
            $qty = 1;
        }
        $effectiveSell = self::effectiveSellExclude($sellExclude, $itemDiscount);
        $sellInclude = self::includeFromExclude($sellExclude);
        $costInclude = self::includeFromExclude($costExclude);
        $discountInclude = self::includeFromExclude($itemDiscount);
        $effectiveInclude = self::includeFromExclude($effectiveSell);
        $pphPercent = self::pphPercentFor($taxCategory, $itemKind);
        $pph = self::pph($effectiveSell, $taxCategory, $itemKind);
        $pnbp = self::pnbp($effectiveSell, $taxCategory);
        $pnbpTier = self::appliesPnbp($taxCategory)
            ? self::matchPnbpTier($effectiveInclude)
            : null;
        $zinitFees = self::appliesZinit($taxCategory)
            ? self::zinitFeesFromSellExclude($effectiveSell, $qty)
            : null;
        $royaltyType = self::normalizeRoyaltyType(
            $row['royalty_type'] ?? (($row['has_royalty'] ?? false) ? self::ROYALTY_LUAR : '')
        );
        $hasRoyalty = $royaltyType !== '';
        $royaltyPercent = self::royaltyPercentFor($royaltyType);
        $royalty = self::royalty($costExclude, $royaltyType);
        $grossMargin = self::grossMargin($sellExclude, $costExclude, $taxCategory, $itemKind, $itemDiscount, $qty);
        $pph29 = self::pph29($sellExclude, $costExclude, $taxCategory, $itemKind, $itemDiscount);
        $margin = self::margin($sellExclude, $costExclude, $taxCategory, $itemKind, $itemDiscount, $qty, $royaltyType, $shippingExclude);
        $marginPercent = self::marginPercent($margin, $sellExclude, $taxCategory, $itemKind, $itemDiscount, $qty);

        $zinitFeeTotal = (float) ($zinitFees['success_fee'] ?? 0);
        $zinitPotFee = self::appliesZinit($taxCategory)
            ? round(($effectiveSell * $qty) - $zinitFeeTotal, 2)
            : null;

        // Subtotal / grand total include = jual include × qty (Fee Zinit dihitung dari nilai ini, tidak ditambahkan).
        $subtotal = round($qty * $effectiveInclude, 2);
        $price = $effectiveInclude;

        return array_merge($row, [
            'tax_category' => $taxCategory,
            'tax_category_label' => self::taxCategoryLabel($taxCategory),
            'item_kind' => $itemKind,
            'sell_exclude' => $sellExclude,
            'cost_exclude' => $costExclude,
            'discount_exclude' => $itemDiscount,
            'item_discount' => $itemDiscount,
            'shipping_exclude' => $shippingExclude,
            'shipping_include' => self::includeFromExclude($shippingExclude),
            'effective_sell_exclude' => $effectiveSell,
            'price' => $price,
            'cost' => $costInclude,
            'sell_include' => $sellInclude,
            'effective_sell_include' => $effectiveInclude,
            'cost_include' => $costInclude,
            'discount_include' => $discountInclude,
            'pph' => $pph,
            'pph_percent' => $pphPercent,
            'pph_applicable' => self::appliesPph($taxCategory, $itemKind),
            'pnbp' => $pnbp,
            'pnbp_percent' => $pnbpTier ? (float) $pnbpTier['rate_percent'] : 0.0,
            'pnbp_cap' => $pnbpTier ? (float) $pnbpTier['cap'] : null,
            'pnbp_applicable' => self::appliesPnbp($taxCategory),
            'zinit_applicable' => self::appliesZinit($taxCategory),
            'zinit_volume' => $zinitFees['volume'] ?? 0.0,
            'zinit_platform_fee' => $zinitFees['platform_fee'] ?? 0.0,
            'zinit_service_fee' => $zinitFees['service_fee'] ?? 0.0,
            'zinit_success_fee' => $zinitFeeTotal,
            'zinit_rate_percent' => $zinitFees['rate_percent'] ?? 0.0,
            'zinit_pot_fee' => $zinitPotFee,
            'has_royalty' => $hasRoyalty,
            'royalty_type' => $royaltyType,
            'royalty_type_label' => self::royaltyTypeLabel($royaltyType),
            'royalty' => $royalty,
            'royalty_percent' => $royaltyPercent,
            'royalty_applicable' => $hasRoyalty && $royalty > 0,
            'gross_margin' => $grossMargin,
            'pph29' => $pph29,
            'pph29_percent' => self::appliesPph29($taxCategory) ? self::pph29Percent() : 0.0,
            'pph29_applicable' => self::appliesPph29($taxCategory),
            'margin' => $margin,
            'margin_percent' => $marginPercent,
            'subtotal' => $subtotal,
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
