<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kode mata uang asing untuk modal (USD, SGD, dll) — mode tetap di crm_cost_in_usd.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            $table->mediumText('crm_cost_fx_code')->nullable()->after('crm_cost_in_usd');
        });
    }

    public function down(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            $table->dropColumn('crm_cost_fx_code');
        });
    }
};
