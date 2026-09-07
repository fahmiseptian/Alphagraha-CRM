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
        $opportunity->loadMissing(['account', 'assignedUser', 'purchaseOrders.vendor', 'purchaseOrders.items', 'salesOrders']);

        $currency = $opportunity->amount_currency ?: 'IDR';
        $products = $opportunity->products;

        $nilaiJualExcl = round($products->sum(
            fn (array $p) => (float) ($p['quantity'] ?? 1) * (float) ($p['effective_sell_exclude'] ?? $p['sell_exclude'] ?? 0)
        ), 2);
        $nilaiJualIncl = round($products->sum(
            fn (array $p) => (float) ($p['quantity'] ?? 1) * (float) ($p['effective_sell_include'] ?? $p['sell_include'] ?? 0)
        ), 2);
        // PPh 23 = Σ (qty × PPH per item). Barang Non Wapu = 0; barang/jasa Wapu/Inaproc & jasa Non Wapu ikut tarif setting.
        $pph23 = round($products->sum(
            fn (array $p) => (float) ($p['quantity'] ?? 1) * (float) ($p['pph'] ?? 0)
        ), 2);
        $terimaUang = round($nilaiJualIncl - $pph23, 2);

        $poRows = $this->buildPoRows($opportunity->purchaseOrders);
        $hasSurcharge = $poRows->contains(fn (array $row) => ($row['surcharge_percent'] ?? 0) > 0);
        $hasCash = $poRows->contains(fn (array $row) => $row['is_cash']);

        $modalExcl = round((float) $poRows->sum('jumlah_exclude'), 2);
        $modalIncl = round((float) $poRows->sum('jumlah_include'), 2);

        $modalOngkirExcl = $opportunity->crm_shipping_cost !== null
            ? round((float) $opportunity->crm_shipping_cost, 2)
            : null;
        $modalOngkirIncl = $modalOngkirExcl !== null
            ? OpportunityProductPricing::includeFromExclude($modalOngkirExcl)
            : null;

        $diskonAmount = $opportunity->hasActiveDiscount()
            ? round((float) $opportunity->crm_discount_amount, 2)
            : 0.0;

        $jualOngkirExcl = null;
        $jualOngkirIncl = null;
        if ($opportunity->crm_has_shipping_charge && (float) ($opportunity->crm_shipping_sell ?? 0) > 0) {
            $jualOngkirExcl = round((float) $opportunity->crm_shipping_sell, 2);
            $jualOngkirIncl = OpportunityProductPricing::includeFromExclude($jualOngkirExcl);
        }

        $netJualExclBeforeDiskon = round($nilaiJualExcl - $pph23, 2);
        $grossMarginBase = round($nilaiJualExcl - $modalExcl - $pph23, 2);
        $profitBarang = round($grossMarginBase - $diskonAmount, 2);
        $profitOngkir = round(
            (float) ($jualOngkirExcl ?? 0) - (float) ($modalOngkirExcl ?? 0),
            2
        );
        $totalProfit = round($profitBarang + $profitOngkir, 2);

        $modalRowPercent = $netJualExclBeforeDiskon > 0
            ? round(($grossMarginBase / $netJualExclBeforeDiskon) * 100, 2)
            : null;
        $diskonPercent = $grossMarginBase > 0 && $diskonAmount > 0
            ? round(($diskonAmount / $grossMarginBase) * 100, 2)
            : null;
        $totalProfitPercent = $grossMarginBase > 0
            ? round(($totalProfit / $grossMarginBase) * 100, 2)
            : null;
        $netJualExcl = round($nilaiJualExcl - $diskonAmount - $pph23, 2);
        $marginPercent = $netJualExcl > 0
            ? round(($totalProfit / $netJualExcl) * 100, 2)
            : null;

        return [
            'opportunity' => $opportunity,
            'currency' => $currency,
            'invoice_date' => null,
            'invoice_number' => null,
            'payment_term_label' => $this->resolveSoPaymentLabel($opportunity),
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
                'pph_23' => $pph23 > 0 ? $pph23 : null,
                'terima_uang' => $terimaUang,
                'modal_incl' => $modalIncl,
                'modal_excl' => $modalExcl,
                'modal_row_percent' => $modalRowPercent,
                'jual_ongkir_incl' => $jualOngkirIncl,
                'jual_ongkir_excl' => $jualOngkirExcl,
                'modal_ongkir_incl' => $modalOngkirIncl,
                'modal_ongkir_excl' => $modalOngkirExcl,
                'diskon_incl' => $diskonAmount > 0 ? $diskonAmount : null,
                'diskon_excl' => null,
                'diskon_percent' => $diskonPercent,
                'profit_barang' => $profitBarang,
                'profit_ongkir' => $profitOngkir,
                'total_profit' => $totalProfit,
                'total_profit_percent' => $totalProfitPercent,
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
                'vendor' => $po->displayVendorName(),
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

    protected function resolveSoPaymentLabel(Opportunity $opportunity): string
    {
        $salesOrder = $opportunity->salesOrders->first();
        if (! $salesOrder) {
            return '—';
        }

        $label = trim($salesOrder->paymentLabel());

        return $label !== '' && $label !== '—' ? $label : '—';
    }
}
