<?php

use App\Models\Espo\Opportunity;
use App\Models\OpportunitySalesOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modal ongkir pindah ke Sales Order (1 SO = 1 ongkir).
 * Nilai yang sudah ada di opportunity dibebankan utuh ke SO terbaru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_opportunity_sales_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_opportunity_sales_orders', 'shipping_cost')) {
                $table->decimal('shipping_cost', 18, 2)->nullable();
            }
        });

        Opportunity::query()
            ->where('crm_shipping_cost', '>', 0)
            ->orderBy('id')
            ->cursor()
            ->each(function (Opportunity $opportunity) {
                $opportunity->load('salesOrders');
                $newest = $this->newestSalesOrder($opportunity);
                if (! $newest) {
                    return;
                }

                if ($newest->shipping_cost === null || (float) $newest->shipping_cost <= 0) {
                    DB::table('crm_opportunity_sales_orders')
                        ->where('id', $newest->id)
                        ->update([
                            'shipping_cost' => round((float) $opportunity->crm_shipping_cost, 2),
                            'updated_at' => now(),
                        ]);
                }

                DB::table('opportunity')
                    ->where('id', $opportunity->id)
                    ->update(['crm_shipping_cost' => null]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('crm_opportunity_sales_orders', 'shipping_cost')) {
            $rows = DB::table('crm_opportunity_sales_orders')
                ->whereNull('deleted_at')
                ->where('shipping_cost', '>', 0)
                ->groupBy('opportunity_id')
                ->select('opportunity_id', DB::raw('SUM(shipping_cost) as total'))
                ->get();

            foreach ($rows as $row) {
                DB::table('opportunity')
                    ->where('id', $row->opportunity_id)
                    ->where(function ($query) {
                        $query->whereNull('crm_shipping_cost')
                            ->orWhere('crm_shipping_cost', '<=', 0);
                    })
                    ->update(['crm_shipping_cost' => $row->total]);
            }

            Schema::table('crm_opportunity_sales_orders', function (Blueprint $table) {
                $table->dropColumn('shipping_cost');
            });
        }
    }

    protected function newestSalesOrder(Opportunity $opportunity): ?OpportunitySalesOrder
    {
        $orders = $opportunity->salesOrders;
        $pool = $orders->filter(fn (OpportunitySalesOrder $so) => ! $so->isCancelled());
        if ($pool->isEmpty()) {
            $pool = $orders;
        }

        return $pool
            ->sortByDesc(fn (OpportunitySalesOrder $so) => sprintf(
                '%s-%010d',
                optional($so->created_at)->format('Y-m-d H:i:s') ?? '',
                (int) $so->id
            ))
            ->first();
    }
};
