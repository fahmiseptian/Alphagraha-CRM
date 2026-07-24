<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed setting kawasan free ongkir (daftar kota).
 */
return new class extends Migration
{
    public function up(): void
    {
        $key = 'shipping.free_cities';
        if (DB::table('crm_settings')->where('key', $key)->exists()) {
            return;
        }

        $now = now();
        DB::table('crm_settings')->insert([
            'key' => $key,
            'value' => '[]',
            'type' => 'json',
            'group' => 'shipping',
            'label' => 'Kawasan free ongkir (kota)',
            'description' => 'Daftar kota (unik) yang mendapat free ongkir. Diambil dari kota customer.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (class_exists(\App\Models\CrmSetting::class)) {
            \App\Models\CrmSetting::forgetCache();
        }
    }

    public function down(): void
    {
        DB::table('crm_settings')->where('key', 'shipping.free_cities')->delete();

        if (class_exists(\App\Models\CrmSetting::class)) {
            \App\Models\CrmSetting::forgetCache();
        }
    }
};
