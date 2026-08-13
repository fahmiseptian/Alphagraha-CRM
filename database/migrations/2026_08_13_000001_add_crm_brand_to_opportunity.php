<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brand opportunity — opsi dari AGC_URL/api/v1/brands.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('opportunity', 'crm_brand')) {
            Schema::table('opportunity', function (Blueprint $table) {
                $table->string('crm_brand', 255)->nullable()->after('crm_top');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('opportunity', 'crm_brand')) {
            Schema::table('opportunity', function (Blueprint $table) {
                $table->dropColumn('crm_brand');
            });
        }
    }
};
