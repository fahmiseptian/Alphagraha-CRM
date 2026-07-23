<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kondisi pembayaran PO: top (tanpa tambahan) atau cash (modal +1%).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('crm_purchase_orders', 'payment_term')) {
            return;
        }

        Schema::table('crm_purchase_orders', function (Blueprint $table) {
            $table->string('payment_term', 16)->default('top')->after('number');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('crm_purchase_orders', 'payment_term')) {
            return;
        }

        Schema::table('crm_purchase_orders', function (Blueprint $table) {
            $table->dropColumn('payment_term');
        });
    }
};
