<?php

namespace App\Support;

use App\Models\CrmSetting;

/**
 * Leveling status pembayaran customer + threshold margin minimal.
 */
class PaymentLevel
{
    public const LANCAR = 'lancar';

    public const MANDEK = 'mandek';

    public const JELEK = 'jelek';

    public const SUSPEND = 'suspend';

    public const LEVELS = [
        self::LANCAR,
        self::MANDEK,
        self::JELEK,
        self::SUSPEND,
    ];

    public const LABELS = [
        self::LANCAR => 'Lancar',
        self::MANDEK => 'Mandek',
        self::JELEK => 'Jelek',
        self::SUSPEND => 'Suspend',
    ];

    public const DEFAULT_MARGINS = [
        self::LANCAR => 5.0,
        self::MANDEK => 8.0,
        self::JELEK => 15.0,
    ];

    public const SETTING_NOMINAL_UMUM = 'payment_level.margin_nominal_umum';

    public const SETTING_NOMINAL_ONGKIR_PRIBADI = 'payment_level.margin_nominal_ongkir_pribadi';

    public const SETTING_MAX_PERCENT = 'payment_level.margin_max_percent';

    public const DEFAULT_NOMINAL_UMUM = 0.0;

    public const DEFAULT_NOMINAL_ONGKIR_PRIBADI = 0.0;

    public const DEFAULT_MAX_PERCENT = 90.0;

    public static function label(string $level): string
    {
        return self::LABELS[$level] ?? ucfirst($level);
    }

    public static function isValid(string $level): bool
    {
        return in_array($level, self::LEVELS, true);
    }

    public static function isSuspended(string $level): bool
    {
        return $level === self::SUSPEND;
    }

    /**
     * Minimal margin (%) untuk level. Suspend = null (tidak boleh quote).
     */
    public static function minMarginPercent(string $level): ?float
    {
        if (self::isSuspended($level)) {
            return null;
        }

        $key = match ($level) {
            self::LANCAR => 'payment_level.margin_lancar',
            self::MANDEK => 'payment_level.margin_mandek',
            self::JELEK => 'payment_level.margin_jelek',
            default => null,
        };

        if ($key === null) {
            return self::DEFAULT_MARGINS[self::LANCAR];
        }

        return CrmSetting::getFloat($key, self::DEFAULT_MARGINS[$level] ?? 5.0);
    }

    /**
     * @return array<string, float>
     */
    public static function allMinMargins(): array
    {
        return [
            self::LANCAR => self::minMarginPercent(self::LANCAR) ?? 5.0,
            self::MANDEK => self::minMarginPercent(self::MANDEK) ?? 8.0,
            self::JELEK => self::minMarginPercent(self::JELEK) ?? 15.0,
        ];
    }

    /**
     * Minimal margin nominal umum (Rp).
     */
    public static function marginNominalUmum(): float
    {
        return CrmSetting::getFloat(self::SETTING_NOMINAL_UMUM, self::DEFAULT_NOMINAL_UMUM);
    }

    /**
     * Minimal margin nominal bila ada ongkir pribadi (Rp).
     */
    public static function marginNominalOngkirPribadi(): float
    {
        return CrmSetting::getFloat(self::SETTING_NOMINAL_ONGKIR_PRIBADI, self::DEFAULT_NOMINAL_ONGKIR_PRIBADI);
    }

    /**
     * Batas atas margin (%) — hanya persentase. Default 90.
     */
    public static function maxMarginPercent(): float
    {
        return CrmSetting::getFloat(self::SETTING_MAX_PERCENT, self::DEFAULT_MAX_PERCENT);
    }

    /**
     * Minimal margin nominal (Rp) berdasarkan free ongkir kota & checkbox ongkir jual.
     *
     * - Kota free ongkir → hanya Nominal Umum
     * - Bukan free + checkbox ongkir jual OFF → Umum + Ongkir Pribadi
     * - Bukan free + checkbox ongkir jual ON → hanya Umum (Ongkir Pribadi tidak dipakai)
     */
    public static function requiredMarginNominal(bool $isFreeShippingCity, bool $hasShippingCharge): float
    {
        $umum = self::marginNominalUmum();

        if ($isFreeShippingCity || $hasShippingCharge) {
            return round($umum, 2);
        }

        return round($umum + self::marginNominalOngkirPribadi(), 2);
    }
}
