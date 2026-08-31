<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SO tidak dihapus permanen: soft delete + log audit (hanya Superadmin yang melihat).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_opportunity_sales_orders')) {
            Schema::table('crm_opportunity_sales_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('crm_opportunity_sales_orders', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (! Schema::hasColumn('crm_opportunity_sales_orders', 'deleted_by')) {
                    $table->string('deleted_by', 24)->nullable()->after('created_by')->index();
                }
            });
        }

        if (! Schema::hasTable('crm_sales_order_logs')) {
            Schema::create('crm_sales_order_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sales_order_id')->nullable()->index();
                $table->string('opportunity_id', 24)->nullable()->index();
                $table->string('action', 20)->index();
                $table->string('number', 100)->nullable()->index();
                $table->string('actor_id', 24)->nullable()->index();
                $table->string('actor_name', 150)->nullable();
                $table->json('snapshot')->nullable();
                $table->json('changes')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->useCurrent()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_sales_order_logs');

        if (Schema::hasTable('crm_opportunity_sales_orders')) {
            Schema::table('crm_opportunity_sales_orders', function (Blueprint $table) {
                if (Schema::hasColumn('crm_opportunity_sales_orders', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
                if (Schema::hasColumn('crm_opportunity_sales_orders', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }
    }
};
