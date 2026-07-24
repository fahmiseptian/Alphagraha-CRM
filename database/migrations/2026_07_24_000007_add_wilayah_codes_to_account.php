<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kode wilayah + kecamatan pada account (customer).
 * Nama tetap di-sync ke billing_address_state (provinsi) & billing_address_city (kota).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account', function (Blueprint $table) {
            if (! Schema::hasColumn('account', 'crm_province_code')) {
                $table->string('crm_province_code', 10)->nullable()->after('billing_address_country');
            }
            if (! Schema::hasColumn('account', 'crm_regency_code')) {
                $table->string('crm_regency_code', 10)->nullable()->after('crm_province_code');
            }
            if (! Schema::hasColumn('account', 'crm_district_code')) {
                $table->string('crm_district_code', 15)->nullable()->after('crm_regency_code');
            }
            if (! Schema::hasColumn('account', 'crm_billing_district')) {
                $table->string('crm_billing_district', 100)->nullable()->after('crm_district_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('account', function (Blueprint $table) {
            foreach (['crm_province_code', 'crm_regency_code', 'crm_district_code', 'crm_billing_district'] as $col) {
                if (Schema::hasColumn('account', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
