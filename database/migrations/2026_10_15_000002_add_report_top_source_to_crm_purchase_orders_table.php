<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_purchase_orders', 'report_top_source')) {
                $table->string('report_top_source', 20)->nullable()->after('report_top');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('crm_purchase_orders', 'report_top_source')) {
                $table->dropColumn('report_top_source');
            }
        });
    }
};
