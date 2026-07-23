<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ongkir per opportunity (1 opp = 1 ongkir), dikelola dari area Purchase Order.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('opportunity', 'crm_shipping_cost')) {
            return;
        }

        Schema::table('opportunity', function (Blueprint $table) {
            $table->decimal('crm_shipping_cost', 18, 2)->nullable()->after('crm_won_margin');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('opportunity', 'crm_shipping_cost')) {
            return;
        }

        Schema::table('opportunity', function (Blueprint $table) {
            $table->dropColumn('crm_shipping_cost');
        });
    }
};
