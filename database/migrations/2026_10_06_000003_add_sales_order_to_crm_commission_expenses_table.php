<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('crm_commission_expenses', 'sales_order_id')) {
            Schema::table('crm_commission_expenses', function (Blueprint $table) {
                $table->unsignedBigInteger('sales_order_id')->nullable()->index()->after('period_key');
                $table->string('sales_order_number', 100)->nullable()->after('sales_order_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('crm_commission_expenses', 'sales_order_id')) {
            Schema::table('crm_commission_expenses', function (Blueprint $table) {
                $table->dropColumn(['sales_order_id', 'sales_order_number']);
            });
        }
    }
};
