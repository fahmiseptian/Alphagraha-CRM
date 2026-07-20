<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diskon per item (harga net exclude). Bila > 0, margin dihitung dari diskon − modal.
 * Terpisah dari crm_discount_amount (diskon tambahan deal).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            $table->mediumText('crm_item_discount')->nullable()->after('crm_cost_exclude');
        });
    }

    public function down(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            $table->dropColumn('crm_item_discount');
        });
    }
};
