<?php

namespace App\Services;

use App\Models\Espo\Opportunity;
use App\Models\PurchaseOrder;
use App\Support\OpportunityProductPricing;
use Illuminate\Support\Collection;

/**
 * Laporan rekap PO per opportunity (1 opp = 1 laporan), format mirip Excel finance.
 */
class PurchaseOrderReportService
{
    /**
     * @return array<string, mixed>
     */
    public function build(Opportunity $opportunity): array
    {
        $opportunity->loadMissing(['account', 'assignedUser', 'purchaseOrders.items']);

        $currency = $opportunity->amount_currency ?: 'IDR';
        $products = $opportunity->products;

        $nilaiJualExcl = round($products->sum(
            fn (array $p) => (float) ($p['quantity'] ?? 1) * (float) ($p['effective_sell_exclude'] ?? $p['sell_exclude'] ?? 0)
        ), 2);
        $nilaiJualIncl = round($products->sum(
            fn (array $p) => (float) ($p['quantity'] ?? 1) * (float) ($p['effective_sell_include'] ?? $p['sell_include'] ?? 0)
        ), 2);

        $poRows = $this->buildPoRows($opportunity->purchaseOrders);
        $hasSurcharge = $poRows->contains(fn (array $row) => ($row['surcharge_percent'] ?? 0) > 0);
        $hasCash = $poRows->contains(fn (array $row) => $row['is_cash']);

        // Modal = total semua Purchase Order (bukan cost dari produk opportunity).
        $modalExcl = round((float) $opportunity->purchaseOrders->sum('total'), 2);
        $modalIncl = round($poRows->sum('jumlah_include'), 2);

        $modalOngkirExcl = $opportunity->crm_shipping_cost !== null
            ? round((float) $opportunity->crm_shipping_cost, 2)
            : null;
        $modalOngkirIncl = $modalOngkirExcl !== null
            ? OpportunityProductPricing::includeFromExclude($modalOngkirExcl)
            : null;

        $diskonExcl = $opportunity->hasActiveDiscount()
            ? round((float) $opportunity->crm_discount_amount, 2)
            : 0.0;
        $diskonIncl = $diskonExcl > 0
            ? OpportunityProductPricing::includeFromExclude($diskonExcl)
            : 0.0;
        $diskonPercent = $nilaiJualExcl > 0 && $diskonExcl > 0
            ? round(($diskonExcl / $nilaiJualExcl) * 100, 2)
            : 0.0;

        $jualOngkirExcl = null;
        $jualOngkirIncl = null;

        $profitBarang = round($nilaiJualExcl - $modalExcl - $diskonExcl, 2);
        $profitOngkir = round(
            (float) ($jualOngkirExcl ?? 0) - (float) ($modalOngkirExcl ?? 0),
            2
        );
        $totalProfit = round($profitBarang + $profitOngkir, 2);
        $marginPercent = $nilaiJualExcl > 0
            ? round(($totalProfit / $nilaiJualExcl) * 100, 2)
            : null;

        return [
            'opportunity' => $opportunity,
            'currency' => $currency,
            'invoice_date' => null,
            'invoice_number' => null,
            'payment_term_label' => null,
            'settled_at' => null,
            'sales_name' => optional($opportunity->assignedUser)->display_name
                ?: optional($opportunity->assignedUser)->user_name
                ?: '—',
            'customer_name' => optional($opportunity->account)->name
                ?: ($opportunity->company ?: '—'),
            'has_cash' => $hasCash,
            'has_surcharge' => $hasSurcharge,
            'surcharge_cash_percent' => \App\Support\PurchaseOrderPricing::cashSurchargePercent(),
            'surcharge_top_percent' => \App\Support\PurchaseOrderPricing::topSurchargePercent(),
            'summary' => [
                'nilai_jual_incl' => $nilaiJualIncl,
                'nilai_jual_excl' => $nilaiJualExcl,
                'modal_incl' => $modalIncl,
                'modal_excl' => $modalExcl,
                'jual_ongkir_incl' => $jualOngkirIncl,
                'jual_ongkir_excl' => $jualOngkirExcl,
                'modal_ongkir_incl' => $modalOngkirIncl,
                'modal_ongkir_excl' => $modalOngkirExcl,
                'diskon_incl' => $diskonIncl > 0 ? $diskonIncl : null,
                'diskon_excl' => $diskonExcl > 0 ? $diskonExcl : null,
                'diskon_percent' => $diskonPercent,
                'profit_barang' => $profitBarang,
                'profit_ongkir' => $profitOngkir,
                'total_profit' => $totalProfit,
                'margin_percent' => $marginPercent,
            ],
            'po_rows' => $poRows->all(),
            'po_total_exclude' => $modalExcl,
            'po_total_include' => $modalIncl,
        ];
    }

    /**
     * @param  Collection<int, PurchaseOrder>  $purchaseOrders
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildPoRows(Collection $purchaseOrders): Collection
    {
        return $purchaseOrders->values()->map(function (PurchaseOrder $po, int $index) {
            $isCash = $po->isCash();
            $surchargePercent = \App\Support\PurchaseOrderPricing::surchargePercent($po->payment_term);
            $hargaExcl = 0.0;
            $extraExcl = 0.0;
            $jumlahExcl = 0.0;
            $hargaIncl = 0.0;
            $extraIncl = 0.0;
            $jumlahIncl = 0.0;

            foreach ($po->items as $item) {
                $b = $item->pricingBreakdown(null, $po->payment_term);
                $qty = (float) $item->quantity;
                $hargaExcl += $qty * $b['modal'];
                $extraExcl += $qty * $b['extra_exclude'];
                $jumlahExcl += $qty * $b['jumlah_exclude'];
                $hargaIncl += $qty * $b['harga_include'];
                $extraIncl += $qty * $b['extra_include'];
                $jumlahIncl += $qty * $b['jumlah_include'];
            }

            return [
                'no' => $index + 1,
                'number' => $po->number,
                'payment_term' => $po->payment_term,
                'is_cash' => $isCash,
                'surcharge_percent' => $surchargePercent,
                'harga_exclude' => round($hargaExcl, 2),
                'extra_exclude' => round($extraExcl, 2),
                'jumlah_exclude' => round($jumlahExcl, 2),
                'harga_include' => round($hargaIncl, 2),
                'extra_include' => round($extraIncl, 2),
                'jumlah_include' => round($jumlahIncl, 2),
            ];
        });
    }
}
