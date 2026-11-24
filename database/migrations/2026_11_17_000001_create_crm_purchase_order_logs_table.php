<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit PO untuk Superadmin. PO yang dihapus tetap punya snapshot di log.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_purchase_order_logs')) {
            return;
        }

        Schema::create('crm_purchase_order_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id')->nullable()->index();
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

    public function down(): void
    {
        Schema::dropIfExists('crm_purchase_order_logs');
    }
};
