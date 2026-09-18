<?php

namespace App\Services;

use App\Models\Espo\Opportunity;
use App\Models\OpportunitySalesOrder;
use App\Models\PurchaseOrder;
use App\Support\OpportunityProductPricing;
use Illuminate\Support\Collection;

/**
 * Laporan rekap PO:
 * - keseluruhan opportunity, atau
 * - per Sales Order (nilai jual dari item SO, modal dari PO terkait SO).
 */
class PurchaseOrderReportService
{
    /**
     * @return array<string, mixed>
     */
    public function build(Opportunity $opportunity, ?OpportunitySalesOrder $salesOrder = null): array
    {
        $opportunity->loadMissing([
            'account',
            'assignedUser',
            'purchaseOrders.vendor',
            'purchaseOrders.items',
            'purchaseOrders.salesOrder',
            'salesOrders',
        ]);

        if ($salesOrder && $salesOrder->opportunity_id !== $opportunity->id) {
            abort(404);
        }

        $currency = $opportunity->amount_currency ?: 'IDR';
        $isPerSo = $salesOrder !== null;
        $isWapu = $this->isWapuOpportunity($opportunity);

        if ($isPerSo) {
            [$nilaiJualExcl, $nilaiJualIncl, $pph23] = $this->sumSalesFromSalesOrder($opportunity, $salesOrder);
            $purchaseOrders = $opportunity->purchaseOrders
                ->where('sales_order_id', $salesOrder->id)
                ->values();
            // Ongkir/diskon bersifat opportunity-level — tidak dialokasikan ke laporan per SO.
            $modalOngkirExcl = null;
            $modalOngkirIncl = null;
            $jualOngkirExcl = null;
            $jualOngkirIncl = null;
            $diskonAmount = 0.0;
        } else {
            $products = $opportunity->products;
            $nilaiJualExcl = round($products->sum(
                fn (array $p) => (float) ($p['quantity'] ?? 1) * (float) ($p['effective_sell_exclude'] ?? $p['sell_exclude'] ?? 0)
            ), 2);
            $nilaiJualIncl = round($products->sum(
                fn (array $p) => (float) ($p['quantity'] ?? 1) * (float) ($p['effective_sell_include'] ?? $p['sell_include'] ?? 0)
            ), 2);
            $pph23 = round($products->sum(
                fn (array $p) => (float) ($p['quantity'] ?? 1) * (float) ($p['pph'] ?? 0)
            ), 2);
            $purchaseOrders = $opportunity->purchaseOrders;
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
        }

        $poRows = $this->buildPoRows($purchaseOrders);
        $hasSurcharge = $poRows->contains(fn (array $row) => ($row['surcharge_percent'] ?? 0) > 0);
        $hasCash = $poRows->contains(fn (array $row) => $row['is_cash']);

        $modalExcl = round((float) $poRows->sum('jumlah_exclude'), 2);
        $modalIncl = round((float) $poRows->sum('jumlah_include'), 2);

        if ($isWapu) {
            // WAPU:
            // Terima Uang = Nilai Jual Excl PPN − PPh 23
            // Profit Barang = Terima Uang − Modal Incl PPN − Diskon
            $terimaUang = round($nilaiJualExcl - $pph23, 2);
            $grossMarginBase = round($terimaUang - $modalIncl, 2);
            $profitBarang = round($grossMarginBase - $diskonAmount, 2);
            $netJualExclBeforeDiskon = $terimaUang;
            $netJualExcl = round($terimaUang - $diskonAmount, 2);
        } else {
            $terimaUang = round($nilaiJualIncl - $pph23, 2);
            $netJualExclBeforeDiskon = round($nilaiJualExcl - $pph23, 2);
            $grossMarginBase = round($nilaiJualExcl - $modalExcl - $pph23, 2);
            $profitBarang = round($grossMarginBase - $diskonAmount, 2);
            $netJualExcl = round($nilaiJualExcl - $diskonAmount - $pph23, 2);
        }

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
        $marginPercent = $netJualExcl > 0
            ? round(($totalProfit / $netJualExcl) * 100, 2)
            : null;

        return [
            'opportunity' => $opportunity,
            'sales_order' => $salesOrder,
            'scope' => $isPerSo ? 'sales_order' : 'overall',
            'scope_label' => $isPerSo
                ? ('Per SO · '.$salesOrder->displayNumber())
                : 'Keseluruhan',
            'currency' => $currency,
            'is_wapu' => $isWapu,
            'invoice_date' => null,
            'invoice_number' => null,
            'payment_term_label' => $this->resolvePaymentTermLabel($opportunity, $salesOrder),
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
     * Opportunity dianggap WAPU jika kategori pajak produk dominan / pertama adalah Wapu.
     */
    protected function isWapuOpportunity(Opportunity $opportunity): bool
    {
        $products = $opportunity->products;
        if ($products->isEmpty()) {
            return false;
        }

        $wapuCount = $products->filter(
            fn (array $p) => ($p['tax_category'] ?? '') === OpportunityProductPricing::TAX_WAPU
        )->count();

        if ($wapuCount === 0) {
            return false;
        }

        // Semua produk Wapu, atau mayoritas Wapu.
        return $wapuCount >= (int) ceil($products->count() / 2);
    }

    /**
     * Nilai jual & PPh dari item Sales Order (bukan seluruh produk opportunity).
     *
     * @return array{0:float,1:float,2:float}
     */
    protected function sumSalesFromSalesOrder(Opportunity $opportunity, OpportunitySalesOrder $salesOrder): array
    {
        $oppProducts = $opportunity->products->values();
        $items = is_array($salesOrder->items) ? $salesOrder->items : [];
        $payloadItems = data_get($salesOrder->agc_payload, 'items');
        if ((! is_array($items) || $items === []) && is_array($payloadItems)) {
            $items = $payloadItems;
        }

        $nilaiJualExcl = 0.0;
        $nilaiJualIncl = 0.0;
        $pph23 = 0.0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $qty = (float) ($item['qty'] ?? $item['quantity'] ?? 1);
            if ($qty <= 0) {
                continue;
            }

            $product = $this->matchOpportunityProduct($oppProducts, $item);
            $unitExcl = (float) (
                $item['price']
                ?? $item['sell_exclude']
                ?? $item['price_display']
                ?? ($product['effective_sell_exclude'] ?? $product['sell_exclude'] ?? 0)
            );
            $unitIncl = isset($product['effective_sell_include'])
                ? (float) $product['effective_sell_include']
                : (isset($product['sell_include'])
                    ? (float) $product['sell_include']
                    : OpportunityProductPricing::includeFromExclude($unitExcl));
            // Jika harga SO beda dari snapshot produk opp, include dihitung dari unit excl SO.
            if ($product && abs($unitExcl - (float) ($product['effective_sell_exclude'] ?? $product['sell_exclude'] ?? 0)) > 0.009) {
                $unitIncl = OpportunityProductPricing::includeFromExclude($unitExcl);
            } elseif (! $product) {
                $unitIncl = OpportunityProductPricing::includeFromExclude($unitExcl);
            }

            $pphUnit = (float) ($product['pph'] ?? 0);

            $nilaiJualExcl += $qty * $unitExcl;
            $nilaiJualIncl += $qty * $unitIncl;
            $pph23 += $qty * $pphUnit;
        }

        return [
            round($nilaiJualExcl, 2),
            round($nilaiJualIncl, 2),
            round($pph23, 2),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $oppProducts
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    protected function matchOpportunityProduct(Collection $oppProducts, array $item): ?array
    {
        if (array_key_exists('index', $item) && $item['index'] !== null && $item['index'] !== '') {
            $byIndex = $oppProducts->get((int) $item['index']);
            if (is_array($byIndex)) {
                return $byIndex;
            }
        }

        $name = mb_strtolower(trim((string) ($item['name'] ?? '')));
        if ($name === '') {
            return null;
        }

        $matched = $oppProducts->first(
            fn ($p) => is_array($p) && mb_strtolower(trim((string) ($p['name'] ?? ''))) === $name
        );

        return is_array($matched) ? $matched : null;
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

    protected function resolvePaymentTermLabel(
        Opportunity $opportunity,
        ?OpportunitySalesOrder $salesOrder = null
    ): string {
        $purchaseOrders = $salesOrder
            ? $opportunity->purchaseOrders->where('sales_order_id', $salesOrder->id)
            : $opportunity->purchaseOrders;

        foreach ($purchaseOrders as $po) {
            $fromPo = trim((string) ($po->report_top ?? ''));
            if ($fromPo !== '') {
                return $fromPo;
            }
        }

        if ($salesOrder) {
            $label = trim($salesOrder->paymentLabel());

            return $label !== '' && $label !== '—' ? $label : '—';
        }

        foreach ($opportunity->purchaseOrders as $po) {
            $linked = $po->salesOrder;
            if (! $linked) {
                continue;
            }
            $label = trim($linked->paymentLabel());
            if ($label !== '' && $label !== '—') {
                return $label;
            }
        }

        $firstSo = $opportunity->salesOrders->first();
        if (! $firstSo) {
            return '—';
        }

        $label = trim($firstSo->paymentLabel());

        return $label !== '' && $label !== '—' ? $label : '—';
    }
}
