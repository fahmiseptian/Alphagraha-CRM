<?php

namespace App\Support;

use App\Models\CrmSetting;

/**
 * Terms of Payment (TOP) customer.
 */
class CustomerTop
{
    public const CASH = 'CBD';

    public const COD = 'cod';

    public const DAYS_7 = '7';

    public const DAYS_14 = '14';

    public const DAYS_30 = '30';

    public const DAYS_45 = '45';

    public const DAYS_60 = '60';

    public const DEFAULT = self::CASH;

    public const OPTIONS = [
        self::CASH,
        self::COD,
        self::DAYS_7,
        self::DAYS_14,
        self::DAYS_30,
        self::DAYS_45,
        self::DAYS_60,
    ];

    public const LABELS = [
        self::CASH => 'CBD',
        self::COD => 'COD',
        self::DAYS_7 => 'TOP 7 hari',
        self::DAYS_14 => 'TOP 14 hari',
        self::DAYS_30 => 'TOP 30 hari',
        self::DAYS_45 => 'TOP 45 hari',
        self::DAYS_60 => 'TOP 60 hari',
    ];

    /** Default minimal margin (%) per jenis TOP. */
    public const DEFAULT_MARGINS = [
        self::CASH => 0.0,
        self::COD => 0.0,
        self::DAYS_7 => 5.0,
        self::DAYS_14 => 5.0,
        self::DAYS_30 => 5.0,
        self::DAYS_45 => 7.0,
        self::DAYS_60 => 10.0,
    ];

    public static function settingKey(string $top): string
    {
        return 'customer_top.margin_'.$top;
    }

    public static function isValid(?string $value): bool
    {
        return in_array(self::canonicalize($value), self::OPTIONS, true);
    }

    public static function normalize(?string $value): string
    {
        $key = self::canonicalize($value);

        return in_array($key, self::OPTIONS, true) ? $key : self::DEFAULT;
    }

    /**
     * Samakan nilai lama `cash` / `cbd` ke `CBD`.
     */
    public static function canonicalize(?string $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        if (strcasecmp($raw, 'cash') === 0 || strcasecmp($raw, 'cbd') === 0) {
            return self::CASH;
        }

        return $raw;
    }

    public static function label(?string $value): string
    {
        $key = self::normalize($value);

        return self::LABELS[$key] ?? self::LABELS[self::DEFAULT];
    }

    /**
     * Urutan level TOP (lebih kecil = lebih ketat).
     * CBD < COD < TOP 7 < ... agar opsi SO tidak melebihi TOP customer.
     */
    public static function rank(?string $value): int
    {
        $key = self::normalize($value);

        return match ($key) {
            self::CASH => 0,
            self::COD => 1,
            default => (int) $key,
        };
    }

    /**
     * Jumlah hari jatuh tempo. CBD/COD = 0.
     */
    public static function days(?string $value): int
    {
        $key = self::normalize($value);

        return in_array($key, [self::CASH, self::COD], true) ? 0 : (int) $key;
    }

    /**
     * Nilai payment untuk API AGC: CBD/COD → top0, TOP 30 hari → top30.
     */
    public static function apiValue(?string $value): string
    {
        return 'top'.self::days($value);
    }

    /**
     * Opsi TOP yang boleh dipilih: tidak melebihi level TOP customer.
     *
     * @return array<string, string>
     */
    public static function optionsAllowedFor(?string $customerTop): array
    {
        $maxRank = self::rank($customerTop);
        $out = [];
        foreach (self::LABELS as $value => $label) {
            if (self::rank($value) <= $maxRank) {
                $out[$value] = $label;
            }
        }

        return $out !== [] ? $out : [self::CASH => self::LABELS[self::CASH]];
    }

    /**
     * Pakai TOP yang diminta jika masih dalam batas customer, selain itu TOP customer.
     */
    public static function clamp(?string $value, ?string $customerTop): string
    {
        $allowed = self::optionsAllowedFor($customerTop);
        $normalized = self::normalize($value);

        return isset($allowed[$normalized]) ? $normalized : self::normalize($customerTop);
    }

    /**
     * Label singkat untuk laporan (kotak TOP di report PO): CBD, COD, TOP 30, dst.
     */
    public static function reportLabel(?string $value): string
    {
        $key = self::normalize($value);

        if ($key === self::CASH) {
            return 'CBD';
        }
        if ($key === self::COD) {
            return 'COD';
        }

        return 'TOP '.self::days($key);
    }

    /**
     * Minimal margin (%) berdasarkan jenis TOP customer.
     */
    public static function minMarginPercent(?string $top): float
    {
        $key = self::normalize($top);
        $default = self::DEFAULT_MARGINS[$key] ?? 0.0;

        return CrmSetting::getFloat(self::settingKey($key), $default);
    }

    /**
     * @return array<string, float>
     */
    public static function allMinMargins(): array
    {
        $result = [];
        foreach (self::OPTIONS as $option) {
            $result[$option] = self::minMarginPercent($option);
        }

        return $result;
    }
}
