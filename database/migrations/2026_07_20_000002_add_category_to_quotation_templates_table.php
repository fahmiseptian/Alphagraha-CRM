<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori template QO = perusahaan internal (Opportunity::COMPANIES).
 * Dipakai untuk memfilter template saat membuat penawaran dari opportunity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_quotation_templates', function (Blueprint $table) {
            $table->string('category', 64)->nullable()->after('code')->index();
        });

        // Backfill dari code template yang sudah ada.
        $map = [
            'agc-indo' => 'Alpha Graha Computindo',
            'eps-indo' => 'Elite Proxy',
            'psi-indo' => 'Power Sistem',
        ];

        foreach ($map as $code => $category) {
            DB::table('crm_quotation_templates')
                ->where('code', $code)
                ->update(['category' => $category]);
        }
    }

    public function down(): void
    {
        Schema::table('crm_quotation_templates', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
