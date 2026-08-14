<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ongkir per item (exclude). Dipotong dari margin satuan item.
 * Terpisah dari crm_shipping_cost (ongkir opportunity di area PO)
 * dan crm_shipping_sell (ongkir jual ke customer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            if (! Schema::hasColumn('opportunity', 'crm_item_shipping')) {
                $table->mediumText('crm_item_shipping')->nullable()->after('crm_item_discount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            if (Schema::hasColumn('opportunity', 'crm_item_shipping')) {
                $table->dropColumn('crm_item_shipping');
            }
        });
    }
};
