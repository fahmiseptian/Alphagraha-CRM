<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Approval Superadmin untuk Purchase Order.
 * PO lama dianggap sudah approved agar tidak mengunci transaksi existing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_purchase_orders')) {
            return;
        }

        Schema::table('crm_purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_purchase_orders', 'approval_status')) {
                $table->string('approval_status', 20)->nullable()->after('created_by')->index();
            }
            if (! Schema::hasColumn('crm_purchase_orders', 'approved_by')) {
                $table->string('approved_by', 24)->nullable()->after('approval_status');
            }
            if (! Schema::hasColumn('crm_purchase_orders', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('crm_purchase_orders', 'approval_note')) {
                $table->text('approval_note')->nullable()->after('approved_at');
            }
        });

        DB::table('crm_purchase_orders')
            ->whereNull('approval_status')
            ->update([
                'approval_status' => 'approved',
                'approved_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_purchase_orders')) {
            return;
        }

        Schema::table('crm_purchase_orders', function (Blueprint $table) {
            foreach (['approval_note', 'approved_at', 'approved_by', 'approval_status'] as $column) {
                if (Schema::hasColumn('crm_purchase_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
