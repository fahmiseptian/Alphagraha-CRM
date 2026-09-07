<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_purchase_order_item_vendors')) {
            return;
        }

        if (! Schema::hasColumn('crm_purchase_order_item_vendors', 'unit_price_basis')) {
            Schema::table('crm_purchase_order_item_vendors', function (Blueprint $table) {
                $table->string('unit_price_basis', 10)->default('exclude')->after('unit_price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_purchase_order_item_vendors')
            && Schema::hasColumn('crm_purchase_order_item_vendors', 'unit_price_basis')) {
            Schema::table('crm_purchase_order_item_vendors', function (Blueprint $table) {
                $table->dropColumn('unit_price_basis');
            });
        }
    }
};
