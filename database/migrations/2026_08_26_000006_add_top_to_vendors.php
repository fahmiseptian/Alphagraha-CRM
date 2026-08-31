<?php

use App\Support\CustomerTop;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_vendors') && ! Schema::hasColumn('crm_vendors', 'top')) {
            Schema::table('crm_vendors', function (Blueprint $table) {
                $table->string('top', 10)->default(CustomerTop::DAYS_30)->after('company_status');
            });
        }

        if (Schema::hasTable('crm_purchase_order_item_vendors') && ! Schema::hasColumn('crm_purchase_order_item_vendors', 'top')) {
            Schema::table('crm_purchase_order_item_vendors', function (Blueprint $table) {
                $table->string('top', 10)->default(CustomerTop::DAYS_30)->after('status');
            });
        }

        if (Schema::hasColumn('crm_vendors', 'top')) {
            DB::table('crm_vendors')->whereNull('top')->orWhere('top', '')->update([
                'top' => CustomerTop::DAYS_30,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_purchase_order_item_vendors') && Schema::hasColumn('crm_purchase_order_item_vendors', 'top')) {
            Schema::table('crm_purchase_order_item_vendors', function (Blueprint $table) {
                $table->dropColumn('top');
            });
        }

        if (Schema::hasTable('crm_vendors') && Schema::hasColumn('crm_vendors', 'top')) {
            Schema::table('crm_vendors', function (Blueprint $table) {
                $table->dropColumn('top');
            });
        }
    }
};
