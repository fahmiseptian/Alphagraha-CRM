<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed setting biaya tambahan PO: Cash / TOP (default 1% / 0%).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $rows = [
            [
                'key' => 'po.surcharge_cash_percent',
                'value' => '1',
                'type' => 'number',
                'group' => 'po',
                'label' => 'Biaya Tambahan Cash (%)',
                'description' => 'Persentase tambahan pada modal PO bila kondisi pembayaran Cash (exclude & include).',
            ],
            [
                'key' => 'po.surcharge_top_percent',
                'value' => '0',
                'type' => 'number',
                'group' => 'po',
                'label' => 'Biaya Tambahan TOP (%)',
                'description' => 'Persentase tambahan pada modal PO bila kondisi pembayaran TOP (exclude & include).',
            ],
        ];

        foreach ($rows as $row) {
            if (DB::table('crm_settings')->where('key', $row['key'])->exists()) {
                continue;
            }
            DB::table('crm_settings')->insert(array_merge($row, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        if (class_exists(\App\Models\CrmSetting::class)) {
            \App\Models\CrmSetting::forgetCache();
        }
    }

    public function down(): void
    {
        DB::table('crm_settings')->where('group', 'po')->delete();

        if (class_exists(\App\Models\CrmSetting::class)) {
            \App\Models\CrmSetting::forgetCache();
        }
    }
};
