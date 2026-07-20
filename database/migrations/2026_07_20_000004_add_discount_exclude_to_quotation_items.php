<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diskon per item pada baris QO (harga net exclude).
 * Dipakai placeholder {{ items_table_diskon_item }}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_quotation_items', function (Blueprint $table) {
            $table->decimal('discount_exclude', 15, 2)->nullable()->after('cost_exclude');
        });
    }

    public function down(): void
    {
        Schema::table('crm_quotation_items', function (Blueprint $table) {
            $table->dropColumn('discount_exclude');
        });
    }
};
