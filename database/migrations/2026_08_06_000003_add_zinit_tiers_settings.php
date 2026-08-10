<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed rate scale Zinit (tax.zinit_tiers) berdasarkan PO Dealing / RFP volume.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tiers = [
            ['max' => 20000000, 'platform_fee' => 100000, 'rate_percent' => 1.0, 'cap' => null],
            ['max' => 200000000, 'platform_fee' => 300000, 'rate_percent' => 0.8, 'cap' => null],
            ['max' => 500000000, 'platform_fee' => 1000000, 'rate_percent' => 0.6, 'cap' => null],
            ['max' => 1600000000, 'platform_fee' => 2000000, 'rate_percent' => 0.4, 'cap' => null],
            ['max' => 5000000000, 'platform_fee' => 3000000, 'rate_percent' => 0.3, 'cap' => null],
            ['max' => 16000000000, 'platform_fee' => 5000000, 'rate_percent' => 0.2, 'cap' => null],
            ['max' => null, 'platform_fee' => 10000000, 'rate_percent' => 0.1, 'cap' => 80000000],
        ];

        $exists = DB::table('crm_settings')->where('key', 'tax.zinit_tiers')->exists();
        if (! $exists) {
            DB::table('crm_settings')->insert([
                'key' => 'tax.zinit_tiers',
                'value' => json_encode($tiers),
                'type' => 'json',
                'group' => 'tax',
                'label' => 'Rate Scale Zinit',
                'description' => 'Jenjang Success Fee Zinit: batas PO Dealing, platform fee, service fee %, dan CAP.',
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
        DB::table('crm_settings')->where('key', 'tax.zinit_tiers')->delete();

        if (class_exists(\App\Models\CrmSetting::class)) {
            \App\Models\CrmSetting::forgetCache();
        }
    }
};
