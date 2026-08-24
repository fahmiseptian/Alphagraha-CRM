<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Histori Sales Order AGC per opportunity (1 opportunity : banyak SO).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_opportunity_sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('opportunity_id', 24)->index();
            $table->unsignedBigInteger('agc_sales_order_id')->nullable()->index();
            $table->string('number', 100)->nullable()->index();
            $table->string('email', 255)->nullable();
            $table->string('payment', 50)->nullable();
            $table->unsignedBigInteger('billing_address_id')->nullable();
            $table->unsignedBigInteger('shipping_address_id')->nullable();
            $table->string('po_number', 100)->nullable();
            $table->string('nomor_ref', 100)->nullable();
            $table->date('required_delivery')->nullable();
            $table->text('note')->nullable();
            $table->json('items')->nullable();
            $table->json('agc_payload')->nullable();
            $table->string('created_by', 24)->nullable()->index();
            $table->timestamps();
        });

        if (Schema::hasColumn('opportunity', 'crm_sales_order_id')
            || Schema::hasColumn('opportunity', 'crm_sales_order_no')) {
            $rows = DB::table('opportunity')
                ->where('deleted', 0)
                ->where(function ($q) {
                    $q->whereNotNull('crm_sales_order_id')
                        ->orWhereNotNull('crm_sales_order_no');
                })
                ->get(['id', 'crm_sales_order_id', 'crm_sales_order_no']);

            foreach ($rows as $row) {
                $exists = DB::table('crm_opportunity_sales_orders')
                    ->where('opportunity_id', $row->id)
                    ->where(function ($q) use ($row) {
                        if ($row->crm_sales_order_id) {
                            $q->orWhere('agc_sales_order_id', $row->crm_sales_order_id);
                        }
                        if ($row->crm_sales_order_no) {
                            $q->orWhere('number', $row->crm_sales_order_no);
                        }
                    })
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('crm_opportunity_sales_orders')->insert([
                    'opportunity_id' => $row->id,
                    'agc_sales_order_id' => $row->crm_sales_order_id,
                    'number' => $row->crm_sales_order_no,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_opportunity_sales_orders');
    }
};
