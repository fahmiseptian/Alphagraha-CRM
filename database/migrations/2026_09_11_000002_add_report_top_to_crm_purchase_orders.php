<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_purchase_orders')) {
            return;
        }

        Schema::table('crm_purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_purchase_orders', 'report_top')) {
                $table->string('report_top', 50)->nullable()->after('payment_term');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_purchase_orders')) {
            return;
        }

        Schema::table('crm_purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('crm_purchase_orders', 'report_top')) {
                $table->dropColumn('report_top');
            }
        });
    }
};
