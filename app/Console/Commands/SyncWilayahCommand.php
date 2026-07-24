<?php

namespace App\Console\Commands;

use App\Services\WilayahSyncService;
use Illuminate\Console\Command;

class SyncWilayahCommand extends Command
{
    protected $signature = 'wilayah:sync
        {--full : Sync provinsi + kota/kab + semua kecamatan (lama)}
        {--regency= : Sync kecamatan untuk satu kode kota/kab saja}';

    protected $description = 'Sinkronisasi master wilayah Indonesia dari wilayah.id ke database';

    public function handle(WilayahSyncService $service): int
    {
        @set_time_limit(0);

        $regency = $this->option('regency');
        if (is_string($regency) && $regency !== '') {
            $count = $service->syncDistrictsForRegency($regency, fn ($m) => $this->line($m));
            $this->info("OK — {$count} kecamatan.");

            return self::SUCCESS;
        }

        if ($this->option('full')) {
            $this->warn('Full sync bisa memakan waktu lama (ribuan request API).');
            $counts = $service->syncAll(fn ($m) => $this->line($m));
            $this->info(sprintf(
                'Selesai: %d provinsi, %d kota/kab, %d kecamatan.',
                $counts['provinces'],
                $counts['regencies'],
                $counts['districts']
            ));

            return self::SUCCESS;
        }

        $counts = $service->syncProvincesAndRegencies(fn ($m) => $this->line($m));
        $this->info(sprintf(
            'Selesai (tanpa kecamatan): %d provinsi, %d kota/kab. Jalankan --full atau sync kecamatan per kota dari admin.',
            $counts['provinces'],
            $counts['regencies']
        ));

        return self::SUCCESS;
    }
}
