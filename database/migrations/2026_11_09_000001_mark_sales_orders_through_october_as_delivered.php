<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SO yang dibuat sampai akhir Oktober 2026 (termasuk Oktober) dianggap sudah terkirim.
 * Status pengiriman di sistem memakai nilai completed.
 */
return new class extends Migration
{
    private const CUTOFF = '2026-11-01 00:00:00';

    public function up(): void
    {
        DB::table('crm_opportunity_sales_orders')
            ->whereNull('deleted_at')
            ->where('created_at', '<', self::CUTOFF)
            ->orderBy('id')
            ->select(['id', 'agc_payload'])
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $payload = json_decode((string) $row->agc_payload, true);
                    if (! is_array($payload)) {
                        $payload = [];
                    }

                    $soStatus = strtolower(trim((string) ($payload['so_status'] ?? '')));
                    $cancelStatus = strtolower(trim((string) ($payload['cancel_status'] ?? '')));
                    if ($soStatus === 'cancelled' || $cancelStatus === 'approved') {
                        continue;
                    }

                    $current = strtolower(trim((string) ($payload['delivery_status'] ?? '')));
                    if (in_array($current, ['completed', 'complete'], true)) {
                        continue;
                    }

                    $payload['delivery_status'] = 'completed';

                    DB::table('crm_opportunity_sales_orders')
                        ->where('id', $row->id)
                        ->update([
                            'agc_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Status pengiriman sebelumnya tidak disimpan, jadi tidak dikembalikan.
    }
};
