<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed batas atas margin (%) — default 90. Hanya persentase, tanpa nominal.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('crm_settings')->where('key', 'payment_level.margin_max_percent')->exists();
        if (! $exists) {
            DB::table('crm_settings')->insert([
                'key' => 'payment_level.margin_max_percent',
                'value' => '90',
                'type' => 'number',
                'group' => 'payment_level',
                'label' => 'Batas Atas Margin (%)',
                'description' => 'Maksimal margin opportunity (%). Di atas nilai ini memerlukan approval Superadmin.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('crm_settings')->where('key', 'payment_level.margin_max_percent')->delete();
    }
};
