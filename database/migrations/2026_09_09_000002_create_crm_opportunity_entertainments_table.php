<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_opportunity_entertainments')) {
            return;
        }

        Schema::create('crm_opportunity_entertainments', function (Blueprint $table) {
            $table->id();
            $table->string('opportunity_id', 24)->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('photo_path')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status', 20)->default('pending')->index();
            $table->string('created_by', 24)->nullable()->index();
            $table->string('completed_by', 24)->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_opportunity_entertainments');
    }
};
