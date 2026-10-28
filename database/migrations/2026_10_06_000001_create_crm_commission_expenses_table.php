<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Biaya tambahan pengurang GP komisi sales per periode (Q).
 * Diisi Finance selama Q berjalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_commission_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 24)->index();
            $table->unsignedSmallInteger('year')->index();
            $table->string('period_key', 32)->index(); // contoh: 2026-Q1
            $table->unsignedBigInteger('sales_order_id')->nullable()->index();
            $table->string('sales_order_number', 100)->nullable();
            $table->string('name', 255);
            $table->decimal('amount', 18, 2);
            $table->text('note')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('created_by', 24)->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'year', 'period_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_commission_expenses');
    }
};
