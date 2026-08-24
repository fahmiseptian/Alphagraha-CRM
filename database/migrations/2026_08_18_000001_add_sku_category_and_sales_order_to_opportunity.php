<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SKU + kategori katalog (level terakhir) per baris produk,
 * plus referensi Sales Order AGC setelah Closed Won.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            if (! Schema::hasColumn('opportunity', 'crm_item_sku')) {
                $table->mediumText('crm_item_sku')->nullable()->after('crm_item_brand');
            }
            if (! Schema::hasColumn('opportunity', 'crm_item_category')) {
                $table->mediumText('crm_item_category')->nullable()->after('crm_item_sku');
            }
            if (! Schema::hasColumn('opportunity', 'crm_sales_order_id')) {
                $table->unsignedBigInteger('crm_sales_order_id')->nullable()->after('crm_won_margin');
            }
            if (! Schema::hasColumn('opportunity', 'crm_sales_order_no')) {
                $table->string('crm_sales_order_no', 100)->nullable()->after('crm_sales_order_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            foreach (['crm_item_sku', 'crm_item_category', 'crm_sales_order_id', 'crm_sales_order_no'] as $col) {
                if (Schema::hasColumn('opportunity', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
