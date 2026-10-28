<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_commission_expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_commission_expenses', 'account_id')) {
                $table->string('account_id', 24)->nullable()->index()->after('sales_order_number');
            }
            if (! Schema::hasColumn('crm_commission_expenses', 'expense_date')) {
                $table->date('expense_date')->nullable()->index()->after('account_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_commission_expenses', function (Blueprint $table) {
            if (Schema::hasColumn('crm_commission_expenses', 'expense_date')) {
                $table->dropColumn('expense_date');
            }
            if (Schema::hasColumn('crm_commission_expenses', 'account_id')) {
                $table->dropColumn('account_id');
            }
        });
    }
};
