<?php

namespace App\Support;

use App\Models\CrmSetting;
use App\Models\Espo\Account;
use App\Models\WilayahRegency;
use Illuminate\Support\Collection;

class FreeShippingZone
{
    /** @deprecated Prefer SETTING_KEY_CODES */
    public const SETTING_KEY = 'shipping.free_cities';

    public const SETTING_KEY_CODES = 'shipping.free_regency_codes';

    /**
     * Daftar kota/kab dari master wilayah untuk checklist settings.
     *
     * @return list<array{code: string, name: string, province_code: string, province_name: string}>
     */
    public static function availableRegencies(): array
    {
        return WilayahRegency::query()
            ->with('province:code,name')
            ->orderBy('name')
            ->get()
            ->map(fn (WilayahRegency $r) => [
                'code' => $r->code,
                'name' => $r->name,
                'province_code' => $r->province_code,
                'province_name' => $r->province?->name ?? '',
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string> Nama kota (untuk kompatibilitas lama / tampilan sederhana)
     */
    public static function availableCities(): array
    {
        $fromWilayah = WilayahRegency::query()->orderBy('name')->pluck('name');
        if ($fromWilayah->isNotEmpty()) {
            return self::uniqueStrings($fromWilayah);
        }

        $rows = Account::query()
            ->where('deleted', 0)
            ->whereNotNull('billing_address_city')
            ->where('billing_address_city', '!=', '')
            ->orderBy('billing_address_city')
            ->pluck('billing_address_city');

        return self::uniqueStrings($rows);
    }

    /**
     * Kode kota/kab yang dipilih sebagai kawasan free ongkir.
     *
     * @return list<string>
     */
    public static function selectedRegencyCodes(): array
    {
        $stored = CrmSetting::get(self::SETTING_KEY_CODES, null);
        if (is_array($stored) && $stored !== []) {
            return self::uniqueStrings(collect($stored));
        }

        // Migrasi soft dari setting lama (nama kota) → kode bila cocok.
        $legacyNames = self::selectedCities();
        if ($legacyNames === []) {
            return [];
        }

        $codes = [];
        $regencies = WilayahRegency::query()->get(['code', 'name']);
        foreach ($legacyNames as $name) {
            $needle = mb_strtolower($name);
            $match = $regencies->first(fn (WilayahRegency $r) => mb_strtolower($r->name) === $needle);
            if ($match) {
                $codes[] = $match->code;
            }
        }

        return self::uniqueStrings(collect($codes));
    }

    /**
     * @return list<string>
     */
    public static function selectedCities(): array
    {
        $codes = CrmSetting::get(self::SETTING_KEY_CODES, null);
        if (is_array($codes) && $codes !== []) {
            return WilayahRegency::query()
                ->whereIn('code', $codes)
                ->orderBy('name')
                ->pluck('name')
                ->all();
        }

        $stored = CrmSetting::get(self::SETTING_KEY, []);
        if (! is_array($stored)) {
            $stored = [];
        }

        return self::uniqueStrings(collect($stored));
    }

    public static function isFreeRegencyCode(?string $regencyCode): bool
    {
        $regencyCode = trim((string) $regencyCode);
        if ($regencyCode === '') {
            return false;
        }

        return in_array($regencyCode, self::selectedRegencyCodes(), true);
    }

    public static function isFreeCity(?string $city): bool
    {
        $city = trim((string) $city);
        if ($city === '') {
            return false;
        }

        $needle = mb_strtolower($city);
        foreach (self::selectedCities() as $selected) {
            if (mb_strtolower($selected) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cek free ongkir untuk customer (prioritas kode kota, fallback nama).
     */
    public static function isFreeForAccount(?Account $account): bool
    {
        if (! $account) {
            return false;
        }

        if (self::isFreeRegencyCode($account->crm_regency_code)) {
            return true;
        }

        return self::isFreeCity($account->billing_address_city);
    }

    /**
     * @param  list<string>|array<int, mixed>  $codes
     * @return list<string>
     */
    public static function normalizeCodes(array $codes): array
    {
        $normalized = self::uniqueStrings(collect($codes));
        if ($normalized === []) {
            return [];
        }

        $valid = WilayahRegency::query()
            ->whereIn('code', $normalized)
            ->pluck('code')
            ->all();

        return array_values(array_intersect($normalized, $valid));
    }

    /**
     * @param  list<string>|array<int, mixed>  $cities
     * @return list<string>
     */
    public static function normalize(array $cities): array
    {
        return self::uniqueStrings(collect($cities));
    }

    /**
     * @param  Collection<int, mixed>  $values
     * @return list<string>
     */
    protected static function uniqueStrings(Collection $values): array
    {
        $seen = [];
        $unique = [];

        foreach ($values as $raw) {
            $value = trim((string) $raw);
            if ($value === '') {
                continue;
            }

            $key = mb_strtolower($value);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $value;
        }

        usort($unique, fn (string $a, string $b) => strcasecmp($a, $b));

        return array_values($unique);
    }
}
