<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed PNBP berjenjang (tax.pnbp_tiers) berdasarkan harga jual include.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tiers = [
            ['max' => 200000000, 'rate_percent' => 0.4, 'cap' => 600000],
            ['max' => 1000000000, 'rate_percent' => 0.3, 'cap' => 2000000],
            ['max' => 5000000000, 'rate_percent' => 0.2, 'cap' => 5000000],
            ['max' => 50000000000, 'rate_percent' => 0.1, 'cap' => 25000000],
            ['max' => null, 'rate_percent' => 0.05, 'cap' => 200000000],
        ];

        $exists = DB::table('crm_settings')->where('key', 'tax.pnbp_tiers')->exists();
        if (! $exists) {
            DB::table('crm_settings')->insert([
                'key' => 'tax.pnbp_tiers',
                'value' => json_encode($tiers),
                'type' => 'json',
                'group' => 'tax',
                'label' => 'PNBP berjenjang',
                'description' => 'Jenjang PNBP Inaproc: batas jual include, rate %, dan cap (MIN).',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (class_exists(\App\Models\CrmSetting::class)) {
            \App\Models\CrmSetting::forgetCache();
        }
    }

    public function down(): void
    {
        DB::table('crm_settings')->where('key', 'tax.pnbp_tiers')->delete();

        if (class_exists(\App\Models\CrmSetting::class)) {
            \App\Models\CrmSetting::forgetCache();
        }
    }
};
