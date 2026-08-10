<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pajak Royalti opsional (checkbox per item), default 20%, berlaku di semua kategori.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            if (! Schema::hasColumn('opportunity', 'crm_has_royalty')) {
                $table->mediumText('crm_has_royalty')->nullable()->after('crm_item_kind');
            }
        });

        if (Schema::hasTable('crm_quotation_items') && ! Schema::hasColumn('crm_quotation_items', 'has_royalty')) {
            Schema::table('crm_quotation_items', function (Blueprint $table) {
                $table->boolean('has_royalty')->default(false)->after('item_kind');
            });
        }

        $exists = DB::table('crm_settings')->where('key', 'tax.royalty_percent')->exists();
        if (! $exists) {
            DB::table('crm_settings')->insert([
                'key' => 'tax.royalty_percent',
                'value' => '20',
                'type' => 'number',
                'group' => 'tax',
                'label' => 'Royalti (%)',
                'description' => 'Persentase royalti bila checkbox Royalti dicentang pada item (semua kategori pajak).',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            if (Schema::hasColumn('opportunity', 'crm_has_royalty')) {
                $table->dropColumn('crm_has_royalty');
            }
        });

        if (Schema::hasTable('crm_quotation_items') && Schema::hasColumn('crm_quotation_items', 'has_royalty')) {
            Schema::table('crm_quotation_items', function (Blueprint $table) {
                $table->dropColumn('has_royalty');
            });
        }

        DB::table('crm_settings')->where('key', 'tax.royalty_percent')->delete();
    }
};
