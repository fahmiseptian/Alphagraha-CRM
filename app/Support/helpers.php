<?php

if (! function_exists('money')) {
    /**
     * Format angka menjadi mata uang yang rapi.
     * Contoh: money(1500000) => "Rp 1.500.000"
     */
    function money($amount, string $currency = 'IDR'): string
    {
        $amount = (float) $amount;

        if ($currency === 'IDR') {
            return 'Rp ' . number_format($amount, 0, ',', '.');
        }

        return $currency . ' ' . number_format($amount, 2, '.', ',');
    }
}

if (! function_exists('money_compact')) {
    /**
     * Format mata uang ringkas untuk tampilan sempit (leaderboard, dll).
     * Contoh: money_compact(48397231850) => "Rp 48,4M"
     */
    function money_compact($amount, string $currency = 'IDR'): string
    {
        $amount = (float) $amount;
        $sign = $amount < 0 ? '-' : '';
        $abs = abs($amount);

        if ($currency === 'IDR') {
            if ($abs >= 1_000_000_000) {
                $value = rtrim(rtrim(number_format($abs / 1_000_000_000, 1, ',', '.'), '0'), ',');

                return $sign.'Rp '.$value.'M';
            }

            if ($abs >= 1_000_000) {
                $value = rtrim(rtrim(number_format($abs / 1_000_000, 1, ',', '.'), '0'), ',');

                return $sign.'Rp '.$value.' Jt';
            }

            return money($amount, $currency);
        }

        if ($abs >= 1_000_000) {
            return $sign.$currency.' '.rtrim(rtrim(number_format($abs / 1_000_000, 1, '.', ','), '0'), '.').'M';
        }

        return money($amount, $currency);
    }
}

if (! function_exists('initials')) {
    /**
     * Ambil inisial dari sebuah nama (maksimal 2 huruf).
     */
    function initials(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return '?';
        }

        $parts = preg_split('/\s+/', $name);
        $first = mb_substr($parts[0], 0, 1);
        $second = isset($parts[1]) ? mb_substr($parts[1], 0, 1) : '';

        return mb_strtoupper($first . $second);
    }
}
