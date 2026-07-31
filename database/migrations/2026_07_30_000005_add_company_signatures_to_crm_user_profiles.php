<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tanda tangan digital sales per perusahaan (AGC / EPS / PSI).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_user_profiles', function (Blueprint $table) {
            $table->string('signature_agc_path')->nullable()->after('signature_path');
            $table->string('signature_eps_path')->nullable()->after('signature_agc_path');
            $table->string('signature_psi_path')->nullable()->after('signature_eps_path');
        });

        // Migrasi TTD lama → AGC (default).
        DB::table('crm_user_profiles')
            ->whereNotNull('signature_path')
            ->where('signature_path', '!=', '')
            ->update([
                'signature_agc_path' => DB::raw('signature_path'),
            ]);
    }

    public function down(): void
    {
        Schema::table('crm_user_profiles', function (Blueprint $table) {
            $table->dropColumn(['signature_agc_path', 'signature_eps_path', 'signature_psi_path']);
        });
    }
};
