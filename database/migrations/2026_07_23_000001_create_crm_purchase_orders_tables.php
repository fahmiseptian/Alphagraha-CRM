<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase Order (PO) terikat ke Opportunity Closed Won.
 * Item bebas (tidak mengikat ke produk opportunity).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('opportunity_id', 24)->index();
            $table->string('number')->unique();
            // top = tanpa tambahan; cash = modal +1% (exclude & include)
            $table->string('payment_term', 16)->default('top');
            $table->decimal('total', 18, 2)->default(0);
            $table->string('currency', 6)->default('IDR');
            $table->string('created_by', 24)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('crm_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')
                ->constrained('crm_purchase_orders')
                ->cascadeOnDelete();
            $table->string('product_name');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->text('description')->nullable();
            $table->text('note')->nullable();
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_purchase_order_items');
        Schema::dropIfExists('crm_purchase_orders');
    }
};
