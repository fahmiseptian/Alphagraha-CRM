<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed setting margin nominal (umum & ongkir pribadi).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $rows = [
            [
                'key' => 'payment_level.margin_nominal_umum',
                'value' => '0',
                'type' => 'number',
                'group' => 'payment_level',
                'label' => 'Margin Nominal Umum (Rp)',
                'description' => 'Ambang margin nominal umum (Rp).',
            ],
            [
                'key' => 'payment_level.margin_nominal_ongkir_pribadi',
                'value' => '0',
                'type' => 'number',
                'group' => 'payment_level',
                'label' => 'Margin Nominal Ongkir Pribadi (Rp)',
                'description' => 'Ambang margin nominal bila memakai ongkir pribadi (Rp).',
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
        DB::table('crm_settings')->whereIn('key', [
            'payment_level.margin_nominal_umum',
            'payment_level.margin_nominal_ongkir_pribadi',
        ])->delete();

        if (class_exists(\App\Models\CrmSetting::class)) {
            \App\Models\CrmSetting::forgetCache();
        }
    }
};
