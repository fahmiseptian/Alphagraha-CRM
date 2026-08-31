<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_vendor_brand')) {
            return;
        }

        Schema::create('crm_vendor_brand', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')
                ->constrained('crm_vendors')
                ->cascadeOnDelete();
            $table->foreignId('brand_id')
                ->constrained('crm_brands')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['vendor_id', 'brand_id'], 'vendor_brand_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_vendor_brand');
    }
};
