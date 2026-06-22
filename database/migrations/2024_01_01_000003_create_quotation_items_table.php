<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baris item/produk pada sebuah penawaran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('crm_quotations')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit')->nullable();
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_quotation_items');
    }
};
