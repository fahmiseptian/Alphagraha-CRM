<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metadata harga per baris produk opportunity (kategori Wapu, jenis barang/jasa, harga exclude).
 * Kolom ini diabaikan oleh EspoCRM; price/cost tetap menyimpan harga include.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            $table->mediumText('crm_tax_category')->nullable()->after('vendor');
            $table->mediumText('crm_item_kind')->nullable()->after('crm_tax_category');
            $table->mediumText('crm_sell_exclude')->nullable()->after('crm_item_kind');
            $table->mediumText('crm_cost_exclude')->nullable()->after('crm_sell_exclude');
        });
    }

    public function down(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            $table->dropColumn([
                'crm_tax_category',
                'crm_item_kind',
                'crm_sell_exclude',
                'crm_cost_exclude',
            ]);
        });
    }
};
