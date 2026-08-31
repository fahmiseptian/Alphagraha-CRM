<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Link PO line ke produk opportunity (1 produk opp bisa punya banyak item PO).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_purchase_order_items')) {
            return;
        }

        Schema::table('crm_purchase_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_purchase_order_items', 'opportunity_product_name')) {
                $table->string('opportunity_product_name')->nullable()->after('purchase_order_id');
            }
            if (! Schema::hasColumn('crm_purchase_order_items', 'opportunity_product_key')) {
                $table->string('opportunity_product_key')->nullable()->index()->after('opportunity_product_name');
            }
        });

        // Legacy: anggap product_name = produk opportunity.
        DB::table('crm_purchase_order_items')
            ->whereNull('opportunity_product_key')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $name = trim((string) $row->product_name);
                    DB::table('crm_purchase_order_items')->where('id', $row->id)->update([
                        'opportunity_product_name' => $name !== '' ? $name : null,
                        'opportunity_product_key' => $name !== '' ? mb_strtolower($name) : null,
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_purchase_order_items')) {
            return;
        }

        Schema::table('crm_purchase_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('crm_purchase_order_items', 'opportunity_product_key')) {
                $table->dropColumn('opportunity_product_key');
            }
            if (Schema::hasColumn('crm_purchase_order_items', 'opportunity_product_name')) {
                $table->dropColumn('opportunity_product_name');
            }
        });
    }
};
