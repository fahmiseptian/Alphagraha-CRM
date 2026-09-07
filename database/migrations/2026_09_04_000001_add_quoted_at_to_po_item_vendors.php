<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_purchase_order_item_vendors', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_purchase_order_item_vendors', 'quoted_at')) {
                $table->date('quoted_at')->nullable()->after('is_pkp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_purchase_order_item_vendors', function (Blueprint $table) {
            if (Schema::hasColumn('crm_purchase_order_item_vendors', 'quoted_at')) {
                $table->dropColumn('quoted_at');
            }
        });
    }
};
