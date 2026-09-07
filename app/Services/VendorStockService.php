<?php

namespace App\Services;

use App\Models\Vendor;
use App\Models\VendorStock;

class VendorStockService
{
    /**
     * Simpan / perbarui ketersediaan barang vendor (satu vendor + satu nama produk).
     */
    public function upsert(
        int $vendorId,
        string $productName,
        string $status,
        float $price,
        ?string $sku = null,
        ?string $note = null,
        ?string $createdBy = null
    ): VendorStock {
        $productName = trim($productName);
        $key = VendorStock::makeProductKey($productName);

        $stock = VendorStock::query()
            ->where('vendor_id', $vendorId)
            ->where('product_key', $key)
            ->first();

        $payload = [
            'product_name' => $productName,
            'status' => VendorStock::normalizeStatus($status),
            'price' => round($price, 2),
            'sku' => $sku !== null && trim($sku) !== '' ? trim($sku) : null,
            'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            'is_active' => true,
        ];

        if ($stock) {
            if ($payload['sku'] === null) {
                unset($payload['sku']);
            }
            if ($payload['note'] === null) {
                unset($payload['note']);
            }
            $stock->update($payload);

            return $stock->fresh(['vendor']);
        }

        return VendorStock::query()->create($payload + [
            'vendor_id' => $vendorId,
            'created_by' => $createdBy,
        ])->load('vendor');
    }

    /**
     * Snapshot stok aktif untuk form PO (pencocokan nama produk di client).
     *
     * @return list<array{id:int,vendor_id:int,vendor_name:string,product_name:string,sku:?string,status:string,price:float}>
     */
    public function snapshotForPoForm(): array
    {
        return VendorStock::query()
            ->with('vendor:id,name')
            ->active()
            ->ordered()
            ->get()
            ->map(fn (VendorStock $stock) => [
                'id' => $stock->id,
                'vendor_id' => $stock->vendor_id,
                'vendor_name' => $stock->vendor?->name ?: '',
                'product_name' => $stock->product_name,
                'sku' => $stock->sku,
                'status' => $stock->status,
                'price' => (float) $stock->price,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:int,name:string,top:string,is_pkp:bool,company_status:?string}>
     */
    public function vendorOptions(): array
    {
        return Vendor::query()
            ->ordered()
            ->get(['id', 'name', 'top', 'is_pkp', 'company_status'])
            ->map(fn (Vendor $vendor) => [
                'id' => $vendor->id,
                'name' => $vendor->name,
                'top' => $vendor->topValue(),
                'is_pkp' => (bool) $vendor->is_pkp,
                'company_status' => $vendor->company_status ? (string) $vendor->company_status : null,
            ])
            ->values()
            ->all();
    }
}
