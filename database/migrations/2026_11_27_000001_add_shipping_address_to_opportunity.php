<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambahkan kolom lokasi pengiriman (shipping address) ke opportunity.
 * Mereferensikan crm_customer_addresses.id — wajib diisi saat membuat/mengedit opportunity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            $table->unsignedBigInteger('crm_shipping_address_id')->nullable()->after('contact_id');
            $table->foreign('crm_shipping_address_id')
                ->references('id')
                ->on('crm_customer_addresses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            $table->dropForeign(['crm_shipping_address_id']);
            $table->dropColumn('crm_shipping_address_id');
        });
    }
};
