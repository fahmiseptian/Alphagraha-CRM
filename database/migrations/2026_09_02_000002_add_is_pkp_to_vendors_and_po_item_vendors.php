<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_vendors') && ! Schema::hasColumn('crm_vendors', 'is_pkp')) {
            Schema::table('crm_vendors', function (Blueprint $table) {
                $table->boolean('is_pkp')->default(true)->after('top');
            });
        }

        if (Schema::hasTable('crm_purchase_order_item_vendors') && ! Schema::hasColumn('crm_purchase_order_item_vendors', 'is_pkp')) {
            Schema::table('crm_purchase_order_item_vendors', function (Blueprint $table) {
                $table->boolean('is_pkp')->default(true)->after('unit_price_basis');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_purchase_order_item_vendors')
            && Schema::hasColumn('crm_purchase_order_item_vendors', 'is_pkp')) {
            Schema::table('crm_purchase_order_item_vendors', function (Blueprint $table) {
                $table->dropColumn('is_pkp');
            });
        }

        if (Schema::hasTable('crm_vendors') && Schema::hasColumn('crm_vendors', 'is_pkp')) {
            Schema::table('crm_vendors', function (Blueprint $table) {
                $table->dropColumn('is_pkp');
            });
        }
    }
};
