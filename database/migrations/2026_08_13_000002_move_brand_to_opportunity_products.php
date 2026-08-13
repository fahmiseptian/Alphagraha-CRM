<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brand per baris Product List (nama brand, bukan id).
 * Kolom skalar crm_brand (level opportunity) diganti jadi array crm_item_brand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            if (Schema::hasColumn('opportunity', 'crm_brand')) {
                $table->dropColumn('crm_brand');
            }
        });

        Schema::table('opportunity', function (Blueprint $table) {
            if (! Schema::hasColumn('opportunity', 'crm_item_brand')) {
                $after = Schema::hasColumn('opportunity', 'vendor') ? 'vendor' : 'crm_top';
                $table->mediumText('crm_item_brand')->nullable()->after($after);
            }
        });
    }

    public function down(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            if (Schema::hasColumn('opportunity', 'crm_item_brand')) {
                $table->dropColumn('crm_item_brand');
            }
        });

        Schema::table('opportunity', function (Blueprint $table) {
            if (! Schema::hasColumn('opportunity', 'crm_brand')) {
                $table->string('crm_brand', 255)->nullable()->after('crm_top');
            }
        });
    }
};
