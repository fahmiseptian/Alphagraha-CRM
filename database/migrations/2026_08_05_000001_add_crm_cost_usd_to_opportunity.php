<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modal USD per baris produk: nilai dolar + rate yang diisi sales.
 * cost_exclude (IDR) tetap menjadi sumber perhitungan margin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            $table->mediumText('crm_cost_in_usd')->nullable()->after('crm_cost_exclude');
            $table->mediumText('crm_cost_usd')->nullable()->after('crm_cost_in_usd');
            $table->mediumText('crm_cost_usd_rate')->nullable()->after('crm_cost_usd');
        });
    }

    public function down(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            $table->dropColumn([
                'crm_cost_in_usd',
                'crm_cost_usd',
                'crm_cost_usd_rate',
            ]);
        });
    }
};
