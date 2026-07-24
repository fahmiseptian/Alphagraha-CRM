<?php

namespace App\Services;

use App\Models\Espo\Opportunity;
use App\Models\PurchaseOrder;
use App\Support\PurchaseOrderPricing;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    /**
     * Buat PO baru beserta item; total dihitung server-side.
     * Tambahan % sesuai payment_term dari settings.
     *
     * @param  list<array{product_name:string,quantity:float|int|string,description?:?string,note?:?string,unit_price:float|int|string}>  $items
     */
    public function create(
        Opportunity $opportunity,
        string $number,
        array $items,
        string $paymentTerm = PurchaseOrder::PAYMENT_TOP,
        ?string $createdBy = null
    ): PurchaseOrder {
        return DB::transaction(function () use ($opportunity, $number, $items, $paymentTerm, $createdBy) {
            $po = PurchaseOrder::query()->create([
                'opportunity_id' => $opportunity->id,
                'number' => $number,
                'payment_term' => $this->normalizePaymentTerm($paymentTerm),
                'currency' => $opportunity->amount_currency ?: 'IDR',
                'total' => 0,
                'created_by' => $createdBy,
            ]);

            $this->syncItems($po, $items);

            return $po->fresh(['items']);
        });
    }

    /**
     * Update nomor + kondisi bayar + ganti seluruh item; total dihitung ulang.
     *
     * @param  list<array{product_name:string,quantity:float|int|string,description?:?string,note?:?string,unit_price:float|int|string}>  $items
     */
    public function update(
        PurchaseOrder $purchaseOrder,
        string $number,
        array $items,
        string $paymentTerm = PurchaseOrder::PAYMENT_TOP
    ): PurchaseOrder {
        return DB::transaction(function () use ($purchaseOrder, $number, $items, $paymentTerm) {
            $purchaseOrder->update([
                'number' => $number,
                'payment_term' => $this->normalizePaymentTerm($paymentTerm),
            ]);
            $this->syncItems($purchaseOrder, $items);

            return $purchaseOrder->fresh(['items']);
        });
    }

    /**
     * Hapus semua item lama, insert ulang, update total header.
     * line_total = qty × jumlah_exclude (modal + surcharge dari settings).
     *
     * @param  list<array{product_name:string,quantity:float|int|string,description?:?string,note?:?string,unit_price:float|int|string}>  $items
     */
    public function syncItems(PurchaseOrder $purchaseOrder, array $items): void
    {
        $purchaseOrder->items()->delete();

        $rate = PurchaseOrderPricing::surchargeRate($purchaseOrder->payment_term);
        $total = 0.0;
        $rows = [];

        foreach (array_values($items) as $index => $item) {
            $qty = round((float) ($item['quantity'] ?? 0), 2);
            $unitPrice = round((float) ($item['unit_price'] ?? 0), 2);
            $extra = $rate > 0 ? round($unitPrice * $rate, 2) : 0.0;
            $jumlahExclude = round($unitPrice + $extra, 2);
            $lineTotal = round($qty * $jumlahExclude, 2);
            $total += $lineTotal;

            $rows[] = [
                'purchase_order_id' => $purchaseOrder->id,
                'product_name' => trim((string) ($item['product_name'] ?? '')),
                'quantity' => $qty,
                'description' => isset($item['description']) ? trim((string) $item['description']) ?: null : null,
                'note' => isset($item['note']) ? trim((string) $item['note']) ?: null : null,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'sort_order' => $index,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            $purchaseOrder->items()->insert($rows);
        }

        $purchaseOrder->update(['total' => round($total, 2)]);
    }

    protected function normalizePaymentTerm(string $paymentTerm): string
    {
        return $paymentTerm === PurchaseOrder::PAYMENT_CASH
            ? PurchaseOrder::PAYMENT_CASH
            : PurchaseOrder::PAYMENT_TOP;
    }
}
