<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ketersediaan barang vendor (master) + perbandingan vendor per item Purchase Order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_vendor_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('crm_vendors')->cascadeOnDelete();
            $table->string('product_name');
            $table->string('product_key');
            $table->string('sku')->nullable();
            $table->string('status', 16)->default('ready');
            $table->decimal('price', 18, 2)->default(0);
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 24)->nullable()->index();
            $table->timestamps();

            $table->unique(['vendor_id', 'product_key']);
            $table->index(['product_key', 'status']);
            $table->index('product_name');
        });

        Schema::create('crm_purchase_order_item_vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_item_id')
                ->constrained('crm_purchase_order_items')
                ->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('crm_vendors')->nullOnDelete();
            $table->foreignId('vendor_stock_id')->nullable()->constrained('crm_vendor_stocks')->nullOnDelete();
            $table->string('product_name');
            $table->string('vendor_name');
            $table->string('status', 16)->default('ready');
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->boolean('is_selected')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['purchase_order_item_id', 'is_selected'], 'po_item_vendor_selected_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_purchase_order_item_vendors');
        Schema::dropIfExists('crm_vendor_stocks');
    }
};
