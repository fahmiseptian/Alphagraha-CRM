<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pemilik komisi SO bisa berbeda dari pemilik opportunity.
 * Null = ikut assigned_user_id opportunity.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_opportunity_sales_orders')) {
            return;
        }

        if (! Schema::hasColumn('crm_opportunity_sales_orders', 'assigned_user_id')) {
            Schema::table('crm_opportunity_sales_orders', function (Blueprint $table) {
                $table->string('assigned_user_id', 24)->nullable()->after('created_by')->index();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_opportunity_sales_orders')) {
            return;
        }

        if (Schema::hasColumn('crm_opportunity_sales_orders', 'assigned_user_id')) {
            Schema::table('crm_opportunity_sales_orders', function (Blueprint $table) {
                $table->dropColumn('assigned_user_id');
            });
        }
    }
};
