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
            if (! Schema::hasColumn('crm_purchase_orders', 'vendor_id')) {
                $table->foreignId('vendor_id')
                    ->nullable()
                    ->after('number')
                    ->constrained('crm_vendors')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('crm_purchase_orders', 'vendor_name')) {
                $table->string('vendor_name')->nullable()->after('vendor_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_purchase_orders')) {
            return;
        }

        Schema::table('crm_purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('crm_purchase_orders', 'vendor_id')) {
                $table->dropConstrainedForeignId('vendor_id');
            }
            if (Schema::hasColumn('crm_purchase_orders', 'vendor_name')) {
                $table->dropColumn('vendor_name');
            }
        });
    }
};
