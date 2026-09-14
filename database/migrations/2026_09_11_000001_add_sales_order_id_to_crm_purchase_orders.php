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
            if (! Schema::hasColumn('crm_purchase_orders', 'sales_order_id')) {
                $table->foreignId('sales_order_id')
                    ->nullable()
                    ->after('opportunity_id')
                    ->constrained('crm_opportunity_sales_orders')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_purchase_orders')) {
            return;
        }

        Schema::table('crm_purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('crm_purchase_orders', 'sales_order_id')) {
                $table->dropConstrainedForeignId('sales_order_id');
            }
        });
    }
};
