<?php

namespace App\Support;

/**
 * Terms of Payment (TOP) customer.
 */
class CustomerTop
{
    public const CASH = 'cash';

    public const DAYS_7 = '7';

    public const DAYS_14 = '14';

    public const DAYS_30 = '30';

    public const DAYS_45 = '45';

    public const DAYS_60 = '60';

    public const DEFAULT = self::CASH;

    public const OPTIONS = [
        self::CASH,
        self::DAYS_7,
        self::DAYS_14,
        self::DAYS_30,
        self::DAYS_45,
        self::DAYS_60,
    ];

    public const LABELS = [
        self::CASH => 'Cash',
        self::DAYS_7 => 'TOP 7 hari',
        self::DAYS_14 => 'TOP 14 hari',
        self::DAYS_30 => 'TOP 30 hari',
        self::DAYS_45 => 'TOP 45 hari',
        self::DAYS_60 => 'TOP 60 hari',
    ];

    public static function isValid(?string $value): bool
    {
        return in_array((string) $value, self::OPTIONS, true);
    }

    public static function normalize(?string $value): string
    {
        return self::isValid($value) ? (string) $value : self::DEFAULT;
    }

    public static function label(?string $value): string
    {
        $key = self::normalize($value);

        return self::LABELS[$key] ?? self::LABELS[self::DEFAULT];
    }

    /**
     * Jumlah hari jatuh tempo. Cash = 0.
     */
    public static function days(?string $value): int
    {
        $key = self::normalize($value);

        return $key === self::CASH ? 0 : (int) $key;
    }
}
