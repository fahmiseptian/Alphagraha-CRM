<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ongkir per item pada baris QO (exclude). Dipotong dari margin satuan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_quotation_items', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_quotation_items', 'shipping_exclude')) {
                $table->decimal('shipping_exclude', 15, 2)->nullable()->after('discount_exclude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_quotation_items', function (Blueprint $table) {
            if (Schema::hasColumn('crm_quotation_items', 'shipping_exclude')) {
                $table->dropColumn('shipping_exclude');
            }
        });
    }
};
