<?php

namespace App\Services;

use App\Models\CrmSetting;
use App\Models\WilayahDistrict;
use App\Models\WilayahProvince;
use App\Models\WilayahRegency;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class WilayahSyncService
{
    public const BASE_URL = 'https://wilayah.id/api';

    public const SETTING_LAST_SYNC = 'wilayah.last_synced_at';

    /**
     * @param  callable(string): void|null  $log
     * @return array{provinces: int, regencies: int, districts: int}
     */
    public function syncAll(?callable $log = null): array
    {
        $counts = ['provinces' => 0, 'regencies' => 0, 'districts' => 0];

        $this->log($log, 'Mengambil daftar provinsi…');
        $provinces = $this->fetchList(self::BASE_URL.'/provinces.json');
        foreach ($provinces as $row) {
            $this->upsertProvince($row['code'], $row['name'], 'api');
            $counts['provinces']++;
        }
        $this->log($log, 'Provinsi: '.$counts['provinces']);

        foreach ($provinces as $province) {
            $code = $province['code'];
            $this->log($log, "Kota/Kab provinsi {$code}…");
            $regencies = $this->fetchList(self::BASE_URL.'/regencies/'.$code.'.json');
            foreach ($regencies as $regency) {
                $this->upsertRegency($regency['code'], $code, $regency['name'], 'api');
                $counts['regencies']++;

                $districts = $this->fetchList(self::BASE_URL.'/districts/'.$regency['code'].'.json');
                foreach ($districts as $district) {
                    $this->upsertDistrict($district['code'], $regency['code'], $district['name'], 'api');
                    $counts['districts']++;
                }
            }
        }

        CrmSetting::set(self::SETTING_LAST_SYNC, now()->toDateTimeString(), [
            'type' => 'string',
            'group' => 'wilayah',
            'label' => 'Terakhir sync wilayah.id',
            'description' => 'Waktu terakhir sinkronisasi master wilayah dari API.',
        ]);

        $this->log($log, sprintf(
            'Selesai. Provinsi %d · Kota/Kab %d · Kecamatan %d',
            $counts['provinces'],
            $counts['regencies'],
            $counts['districts']
        ));

        return $counts;
    }

    /**
     * Sync cepat: hanya provinsi + semua kota/kab (tanpa kecamatan).
     *
     * @param  callable(string): void|null  $log
     * @return array{provinces: int, regencies: int}
     */
    public function syncProvincesAndRegencies(?callable $log = null): array
    {
        $counts = ['provinces' => 0, 'regencies' => 0];

        $provinces = $this->fetchList(self::BASE_URL.'/provinces.json');
        foreach ($provinces as $row) {
            $this->upsertProvince($row['code'], $row['name'], 'api');
            $counts['provinces']++;

            $regencies = $this->fetchList(self::BASE_URL.'/regencies/'.$row['code'].'.json');
            foreach ($regencies as $regency) {
                $this->upsertRegency($regency['code'], $row['code'], $regency['name'], 'api');
                $counts['regencies']++;
            }
        }

        CrmSetting::set(self::SETTING_LAST_SYNC, now()->toDateTimeString(), [
            'type' => 'string',
            'group' => 'wilayah',
            'label' => 'Terakhir sync wilayah.id',
            'description' => 'Waktu terakhir sinkronisasi master wilayah dari API.',
        ]);

        $this->log($log, sprintf('Provinsi %d · Kota/Kab %d', $counts['provinces'], $counts['regencies']));

        return $counts;
    }

    public function syncDistrictsForRegency(string $regencyCode, ?callable $log = null): int
    {
        $count = 0;
        $districts = $this->fetchList(self::BASE_URL.'/districts/'.$regencyCode.'.json');
        foreach ($districts as $district) {
            $this->upsertDistrict($district['code'], $regencyCode, $district['name'], 'api');
            $count++;
        }
        $this->log($log, "Kecamatan {$regencyCode}: {$count}");

        return $count;
    }

    public function upsertProvince(string $code, string $name, string $source = 'manual'): WilayahProvince
    {
        $code = trim($code);
        $name = trim($name);

        $row = WilayahProvince::query()->firstOrNew(['code' => $code]);
        // Jangan timpa entri manual dengan sync API bila nama sudah diubah admin? 
        // Policy: API update name bila source api atau baru; manual tetap bisa diedit admin.
        if (! $row->exists || $row->source === 'api' || $source === 'manual') {
            $row->name = $name;
            if ($source === 'manual' || ! $row->exists) {
                $row->source = $source;
            }
        }
        $row->save();

        return $row;
    }

    public function upsertRegency(string $code, string $provinceCode, string $name, string $source = 'manual'): WilayahRegency
    {
        $row = WilayahRegency::query()->firstOrNew(['code' => trim($code)]);
        $row->province_code = trim($provinceCode);
        if (! $row->exists || $row->source === 'api' || $source === 'manual') {
            $row->name = trim($name);
            if ($source === 'manual' || ! $row->exists) {
                $row->source = $source;
            }
        }
        $row->save();

        return $row;
    }

    public function upsertDistrict(string $code, string $regencyCode, string $name, string $source = 'manual'): WilayahDistrict
    {
        $row = WilayahDistrict::query()->firstOrNew(['code' => trim($code)]);
        $row->regency_code = trim($regencyCode);
        if (! $row->exists || $row->source === 'api' || $source === 'manual') {
            $row->name = trim($name);
            if ($source === 'manual' || ! $row->exists) {
                $row->source = $source;
            }
        }
        $row->save();

        return $row;
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    protected function fetchList(string $url): array
    {
        try {
            $response = Http::timeout(60)
                ->retry(2, 500)
                ->acceptJson()
                ->get($url);

            $response->throw();
            $data = $response->json('data');
            if (! is_array($data)) {
                return [];
            }

            $rows = [];
            foreach ($data as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $code = trim((string) ($item['code'] ?? ''));
                $name = trim((string) ($item['name'] ?? ''));
                if ($code === '' || $name === '') {
                    continue;
                }
                $rows[] = ['code' => $code, 'name' => $name];
            }

            return $rows;
        } catch (RequestException|Throwable $e) {
            throw new \RuntimeException('Gagal mengambil '.$url.': '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @param  callable(string): void|null  $log
     */
    protected function log(?callable $log, string $message): void
    {
        if ($log) {
            $log($message);
        }
    }
}
