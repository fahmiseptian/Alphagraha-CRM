<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail Opportunity: buat, ubah harga/barang, stage, approval, dll.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_opportunity_logs')) {
            return;
        }

        Schema::create('crm_opportunity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('opportunity_id', 24)->index();
            $table->string('action', 40)->index();
            $table->string('summary', 500)->nullable();
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
        Schema::dropIfExists('crm_opportunity_logs');
    }
};
