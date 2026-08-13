<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Client API Alpha Graha (AGC_URL).
 */
class AgcApiService
{
    public const BRANDS_CACHE_KEY = 'agc.api.brands';

    public const BRANDS_CACHE_SECONDS = 3600;

    public function baseUrl(): string
    {
        $url = trim((string) config('crm.agc_url', ''));
        // Perbaiki typo umum: https:///host → https://host
        $url = preg_replace('#^(https?:)/{2,}#', '$1//', $url) ?: $url;

        return rtrim($url, '/');
    }

    /**
     * Daftar brand dari AGC_URL/api/v1/brands.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function brands(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::BRANDS_CACHE_KEY);
        }

        return Cache::remember(self::BRANDS_CACHE_KEY, self::BRANDS_CACHE_SECONDS, function () {
            return $this->fetchBrands();
        });
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    protected function fetchBrands(): array
    {
        $base = $this->baseUrl();
        if ($base === '') {
            return [];
        }

        try {
            $response = Http::timeout(15)
                ->withOptions(['proxy' => false])
                ->acceptJson()
                ->get($base.'/api/v1/brands');

            if (! $response->successful()) {
                Log::warning('AGC brands API gagal', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);

                return [];
            }

            return $this->normalizeBrandList($response->json());
        } catch (Throwable $e) {
            Log::warning('AGC brands API error: '.$e->getMessage());

            return [];
        }
    }

    /**
     * @param  mixed  $payload
     * @return array<int, array{id: string, name: string}>
     */
    protected function normalizeBrandList(mixed $payload): array
    {
        $rows = $this->extractRows($payload);
        $out = [];

        foreach ($rows as $row) {
            if (is_string($row) || is_numeric($row)) {
                $name = trim((string) $row);
                if ($name === '') {
                    continue;
                }
                $out[] = ['id' => $name, 'name' => $name];
                continue;
            }

            if (! is_array($row)) {
                continue;
            }

            $name = trim((string) (
                $row['brand_name']
                ?? $row['name']
                ?? $row['brand']
                ?? $row['title']
                ?? $row['label']
                ?? ''
            ));

            if ($name === '') {
                continue;
            }

            $id = trim((string) (
                $row['brand_id']
                ?? $row['brand_code']
                ?? $row['id']
                ?? $row['slug']
                ?? $row['code']
                ?? $name
            ));

            $out[] = ['id' => $id !== '' ? $id : $name, 'name' => $name];
        }

        usort($out, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return array_values($out);
    }

    /**
     * @param  mixed  $payload
     * @return array<int, mixed>
     */
    protected function extractRows(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        if (array_is_list($payload)) {
            return $payload;
        }

        foreach (['data', 'brands', 'results', 'items'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $nested = $payload[$key];
                if (isset($nested['data']) && is_array($nested['data'])) {
                    return $nested['data'];
                }

                return $nested;
            }
        }

        return [];
    }
}
