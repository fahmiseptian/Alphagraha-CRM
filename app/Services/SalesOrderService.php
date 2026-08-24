<?php

namespace App\Services;

use App\Models\Espo\Opportunity;
use App\Models\CustomerAddress;
use App\Models\OpportunitySalesOrder;
use App\Models\User;
use App\Support\CustomerTop;
use App\Support\OpportunityProductPricing;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;

/**
 * Sales Order lokal CRM (tanpa API AGC).
 */
class SalesOrderService
{
    /**
     * Generate No SO + No PSO (pasangan, urutan sama) + Nomor ref.
     * No SO:  SO26081800001
     * PSO:    PSO26081800001
     * Nomor ref: 0001/DP/SO/VIII/26  (pasangan PSO: 0001/DP/PSO/VIII/26)
     *
     * @return array{number: string, pso_number: string, nomor_ref: string, pso_nomor_ref: string}
     */
    public function generateDocumentNumbers(
        ?User $forUser = null,
        ?Carbon $date = null,
        ?Opportunity $opportunity = null,
        bool $lock = true
    ): array {
        $date = $date ?? now();
        $ymd = $date->format('ymd');
        $pad = (int) config('crm.sales_order_number.sequence_pad', 5);
        $soPrefix = $this->compactPrefix('so');
        $psoPrefix = $this->compactPrefix('pso');
        $seq = str_pad((string) $this->nextDailySequence($soPrefix, $ymd, $lock), $pad, '0', STR_PAD_LEFT);
        $nomorRef = $this->makeRefNumber('so', $forUser, $date, $opportunity, $lock);

        return [
            'number' => $soPrefix.$ymd.$seq,
            'pso_number' => $psoPrefix.$ymd.$seq,
            'nomor_ref' => $nomorRef,
            'pso_nomor_ref' => preg_replace('#/SO/#', '/PSO/', $nomorRef, 1) ?: $nomorRef,
        ];
    }

    /**
     * @return array{number: string, pso_number: string, nomor_ref: ?string, pso_nomor_ref: ?string}
     */
    public function previewDocumentNumbers(
        ?Opportunity $opportunity = null,
        ?User $forUser = null,
        ?Carbon $date = null
    ): array {
        $date = $date ?? now();
        $ymd = $date->format('ymd');
        $pad = (int) config('crm.sales_order_number.sequence_pad', 5);
        $soPrefix = $this->compactPrefix('so');
        $psoPrefix = $this->compactPrefix('pso');
        $seq = str_pad((string) $this->nextDailySequence($soPrefix, $ymd, lock: false), $pad, '0', STR_PAD_LEFT);
        $nomorRef = null;
        try {
            $nomorRef = $this->makeRefNumber('so', $forUser, $date, $opportunity, lock: false);
        } catch (\RuntimeException $e) {
            $nomorRef = null;
        }

        return [
            'number' => $soPrefix.$ymd.$seq,
            'pso_number' => $psoPrefix.$ymd.$seq,
            'nomor_ref' => $nomorRef,
            'pso_nomor_ref' => $nomorRef ? (preg_replace('#/SO/#', '/PSO/', $nomorRef, 1) ?: $nomorRef) : null,
        ];
    }

    public function psoNumberFromSo(?string $soNumber): ?string
    {
        if (! is_string($soNumber) || ! preg_match('/^SO(\d{11})$/', $soNumber, $m)) {
            return null;
        }

        return $this->compactPrefix('pso').$m[1];
    }

    public function compactPrefix(string $type = 'so'): string
    {
        $type = $this->normalizeDocType($type);

        return $type === 'pso'
            ? (string) config('crm.sales_order_number.pso_prefix', 'PSO')
            : (string) config('crm.sales_order_number.prefix', 'SO');
    }

    public function normalizeDocType(string $type): string
    {
        return strtolower(trim($type)) === 'pso' ? 'pso' : 'so';
    }

    /**
     * @return array{code: string, user: ?User, owner: string}
     */
    public function salesCodeContext(?Opportunity $opportunity = null, ?User $forUser = null): array
    {
        $quotations = app(QuotationService::class);
        $context = $quotations->resolveSalesCodeContext($opportunity?->id, $forUser ?? auth()->user());

        return [
            'code' => (string) ($context['code'] ?? ''),
            'user' => $context['user'] ?? null,
            'owner' => $quotations->salesCodeOwnerLabel($context['user'] ?? $forUser),
        ];
    }

    protected function makeRefNumber(string $type, ?User $forUser, Carbon $date, ?Opportunity $opportunity, bool $lock): string
    {
        $context = $this->salesCodeContext($opportunity, $forUser);
        $salesCode = strtoupper(trim($context['code']));
        $prefix = $this->compactPrefix($type);
        $pad = (int) config('crm.sales_order_number.ref_sequence_pad', 4);
        $year = (int) $date->format('Y');
        $yy = $date->format('y');
        $romanMonth = $this->romanMonth((int) $date->format('n'));

        if ($salesCode === '') {
            throw new \RuntimeException(
                'Sales Code untuk '.$context['owner'].' belum diisi. Minta admin mengisi Sales Code di menu Users.'
            );
        }

        $sequence = $this->nextRefYearlySequence($prefix, $year, $yy, $lock);
        $seq = str_pad((string) $sequence, $pad, '0', STR_PAD_LEFT);

        return sprintf('%s/%s/%s/%s/%s', $seq, $salesCode, $prefix, $romanMonth, $yy);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    public function buildSnapshot(Opportunity $opportunity, array $data, array $items, ?string $poFileUrl = null): array
    {
        $account = $opportunity->account;
        $billing = $this->resolveAddressSnapshot($opportunity, $data, 'billing');
        $sameShipping = ! empty($data['same_as_billing'])
            || empty($data['shipping_address_id'])
            || (int) ($data['shipping_address_id'] ?? 0) === (int) ($data['billing_address_id'] ?? 0);
        $shipping = $sameShipping
            ? $billing
            : $this->resolveAddressSnapshot($opportunity, $data, 'shipping');

        $mappedItems = [];
        $itemTotal = 0.0;
        foreach ($items as $item) {
            $qty = (float) ($item['qty'] ?? 0);
            $price = (float) ($item['sell_exclude'] ?? $item['price'] ?? 0);
            $subtotal = $qty * $price;
            $itemTotal += $subtotal;
            $mappedItems[] = [
                'index' => (int) ($item['index'] ?? 0),
                'sku' => trim((string) ($item['sku'] ?? '')),
                'name' => (string) ($item['name'] ?? 'Item'),
                'brand' => trim((string) ($item['brand'] ?? '')),
                'category' => trim((string) ($item['category'] ?? '')),
                'qty' => $qty,
                'unit' => 'Unit',
                'price' => $price,
                'subtotal' => $subtotal,
                'price_display' => $price,
                'amount_display' => $subtotal,
            ];
        }

        $ppnPercent = OpportunityProductPricing::ppnPercent();
        $ppnAmount = round($itemTotal * ($ppnPercent / 100), 2);
        $shippingPrice = 0.0;
        $grandTotal = $itemTotal + $ppnAmount + $shippingPrice;
        $courier = trim((string) ($data['shipping_method'] ?? ''));

        return [
            'code' => $data['number'] ?? null,
            'pre_code' => $data['pso_number'] ?? $this->psoNumberFromSo($data['number'] ?? null),
            'pso_number' => $data['pso_number'] ?? $this->psoNumberFromSo($data['number'] ?? null),
            'pso_nomor_ref' => $data['pso_nomor_ref'] ?? null,
            'email' => $data['email'] ?? $opportunity->customerEmail(),
            'customer' => optional($account)->name ?: $opportunity->company,
            'payment' => CustomerTop::apiValue((string) ($data['payment'] ?? CustomerTop::DEFAULT)),
            'so_status' => 'pending',
            'payment_status' => 'unpaid',
            'delivery_status' => 'pending',
            'po_number' => $data['po_number'] ?? null,
            'po_file' => $poFileUrl,
            'nomor_ref' => $data['nomor_ref'] ?? null,
            'note' => $data['note'] ?? null,
            'required_delivery' => $data['required_delivery'] ?? null,
            'courier_name' => $courier !== '' ? $courier : null,
            'shipping_name' => $courier !== '' ? $courier : null,
            'billing' => $billing,
            'shipping' => $shipping,
            'billing_address' => $this->formatAddressLine($billing),
            'shipping_address' => $this->formatAddressLine($shipping),
            'items' => $mappedItems,
            'total_price_item' => $itemTotal,
            'discount_price' => 0,
            'shipping_price' => $shippingPrice,
            'grand_total' => $grandTotal,
            'ppn_percent' => $ppnPercent,
            'ppn_amount' => $ppnAmount,
            'subtotal_display' => $itemTotal,
            'prices_include_tax' => false,
            'so_date' => now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function present(OpportunitySalesOrder $salesOrder, Opportunity $opportunity): array
    {
        $snap = is_array($salesOrder->agc_payload) ? $salesOrder->agc_payload : [];
        $items = $snap['items'] ?? $salesOrder->items ?? [];
        if (! is_array($items)) {
            $items = [];
        }

        $mappedItems = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $qty = (float) ($item['qty'] ?? $item['quantity'] ?? 0);
            $price = (float) ($item['price'] ?? $item['sell_exclude'] ?? $item['unit_price'] ?? 0);
            $subtotal = (float) ($item['subtotal'] ?? $item['amount_display'] ?? ($qty * $price));
            $mappedItems[] = [
                'sku' => (string) ($item['sku'] ?? ''),
                'name' => (string) ($item['name'] ?? 'Item'),
                'brand' => trim((string) ($item['brand'] ?? '')),
                'category' => trim((string) ($item['category'] ?? '')),
                'qty' => $qty,
                'unit' => trim((string) ($item['unit'] ?? 'Unit')) ?: 'Unit',
                'price' => $price,
                'subtotal' => $subtotal,
                'price_display' => (float) ($item['price_display'] ?? $price),
                'amount_display' => (float) ($item['amount_display'] ?? $subtotal),
            ];
        }

        $itemTotal = (float) ($snap['total_price_item'] ?? collect($mappedItems)->sum('subtotal'));
        $ppnPercent = (float) ($snap['ppn_percent'] ?? OpportunityProductPricing::ppnPercent());
        $ppnAmount = (float) ($snap['ppn_amount'] ?? round($itemTotal * ($ppnPercent / 100), 2));
        $shippingPrice = (float) ($snap['shipping_price'] ?? 0);
        $discountPrice = (float) ($snap['discount_price'] ?? 0);
        $grandTotal = (float) ($snap['grand_total'] ?? ($itemTotal + $ppnAmount + $shippingPrice - $discountPrice));

        $billing = is_array($snap['billing'] ?? null) ? $snap['billing'] : $this->addressFromAccount($opportunity);
        $shipping = is_array($snap['shipping'] ?? null) ? $snap['shipping'] : $billing;

        $code = $salesOrder->number ?: ($snap['code'] ?? null);
        $psoNumber = $snap['pso_number'] ?? $snap['pre_code'] ?? $this->psoNumberFromSo($code) ?? $salesOrder->displayPsoNumber();

        return [
            'id' => $salesOrder->id,
            'code' => $code,
            'pre_code' => $psoNumber,
            'pso_number' => $psoNumber,
            'pso_nomor_ref' => $snap['pso_nomor_ref'] ?? null,
            'type' => 'SO',
            'email' => $salesOrder->email ?: ($snap['email'] ?? $opportunity->customerEmail()),
            'customer' => $snap['customer'] ?? optional($opportunity->account)->name,
            'payment' => $salesOrder->payment ?: ($snap['payment'] ?? null),
            'so_status' => $snap['so_status'] ?? 'pending',
            'payment_status' => $snap['payment_status'] ?? 'unpaid',
            'delivery_status' => $snap['delivery_status'] ?? 'pending',
            'top_payment_status' => $snap['top_payment_status'] ?? null,
            'po_number' => $salesOrder->po_number ?: ($snap['po_number'] ?? null),
            'po_agc' => $snap['po_agc'] ?? null,
            'po_file' => $snap['po_file'] ?? null,
            'nomor_ref' => $salesOrder->nomor_ref ?: ($snap['nomor_ref'] ?? null),
            'billing_address_id' => $salesOrder->billing_address_id,
            'shipping_address_id' => $salesOrder->shipping_address_id,
            'shipping_id' => null,
            'invoice_no' => $snap['invoice_no'] ?? null,
            'invoice_dt' => $snap['invoice_dt'] ?? $snap['invoiced_dt'] ?? null,
            'faktur_pajak' => $snap['faktur_pajak'] ?? null,
            'foto_serah_terima' => $snap['foto_serah_terima'] ?? null,
            'file_do' => $snap['file_do'] ?? null,
            'note' => $salesOrder->note ?: ($snap['note'] ?? null),
            'required_delivery' => optional($salesOrder->required_delivery)->format('Y-m-d')
                ?: ($snap['required_delivery'] ?? null),
            'courier_name' => $snap['courier_name'] ?? $snap['shipping_name'] ?? null,
            'courier_service_type' => $snap['courier_service_type'] ?? null,
            'shipping_name' => $snap['shipping_name'] ?? $snap['courier_name'] ?? null,
            'courier_service' => $snap['courier_service'] ?? null,
            'no_resi' => $snap['no_resi'] ?? $snap['resi'] ?? null,
            'qty' => collect($mappedItems)->sum('qty'),
            'total_price_item' => $itemTotal,
            'discount_price' => $discountPrice,
            'shipping_price' => $shippingPrice,
            'grand_total' => $grandTotal,
            'ppn_percent' => $ppnPercent,
            'ppn_amount' => $ppnAmount,
            'subtotal_display' => (float) ($snap['subtotal_display'] ?? $itemTotal),
            'prices_include_tax' => (bool) ($snap['prices_include_tax'] ?? false),
            'unique_code' => null,
            'billing' => $billing,
            'shipping' => $shipping,
            'billing_address' => $snap['billing_address'] ?? $this->formatAddressLine($billing),
            'shipping_address' => $snap['shipping_address'] ?? $this->formatAddressLine($shipping),
            'items' => $mappedItems,
            'so_date' => $snap['so_date'] ?? optional($salesOrder->created_at)->format('Y-m-d H:i:s'),
            'paid_date' => $snap['paid_date'] ?? $snap['settlement_dt'] ?? null,
            'delivery_date' => $snap['delivery_dt'] ?? $snap['delivery_date'] ?? null,
            'completed_date' => $snap['completed_dt'] ?? $snap['completed_date'] ?? null,
            'updated_date' => optional($salesOrder->updated_at)->format('Y-m-d H:i:s'),
            'expired_date' => null,
            'accept_date' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function mergeSnapshot(OpportunitySalesOrder $salesOrder, array $payload): void
    {
        $snap = is_array($salesOrder->agc_payload) ? $salesOrder->agc_payload : [];
        foreach ($payload as $key => $value) {
            if ($value === null && in_array($key, ['po_file', 'faktur_pajak', 'foto_serah_terima', 'file_do'], true)) {
                continue;
            }
            $snap[$key] = $value;
        }
        $salesOrder->agc_payload = $snap;

        if (array_key_exists('po_number', $payload)) {
            $salesOrder->po_number = $payload['po_number'];
        }
        if (array_key_exists('nomor_ref', $payload)) {
            $salesOrder->nomor_ref = $payload['nomor_ref'];
        }
        if (array_key_exists('note', $payload)) {
            $salesOrder->note = $payload['note'];
        }
        if (array_key_exists('required_delivery', $payload)) {
            $salesOrder->required_delivery = $payload['required_delivery'];
        }
        if (array_key_exists('payment', $payload) && filled($payload['payment'])) {
            $salesOrder->payment = $payload['payment'];
        }

        $salesOrder->save();
    }

    public function storeDocument(?UploadedFile $file, OpportunitySalesOrder $salesOrder, string $kind): ?string
    {
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return null;
        }

        $path = $file->store('sales-orders/'.$salesOrder->id.'/'.$kind, 'public');

        return $path ? asset('storage/'.$path) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function resolveAddressSnapshot(Opportunity $opportunity, array $data, string $kind): array
    {
        $idKey = $kind === 'shipping' ? 'shipping_address_id' : 'billing_address_id';
        $id = (int) ($data[$idKey] ?? 0);
        $accountId = (string) ($opportunity->account_id ?? '');

        if ($id > 0 && $accountId !== '') {
            $address = CustomerAddress::query()
                ->where('account_id', $accountId)
                ->find($id);
            if ($address) {
                return $address->toSnapshot(
                    $opportunity->contact?->full_name ?: $opportunity->contact?->name,
                    $opportunity->customerPhone(),
                    $opportunity->customerEmail()
                );
            }
        }

        return $this->addressFromAccount($opportunity);
    }

    /**
     * @return array<string, string|null>
     */
    public function addressFromAccount(Opportunity $opportunity): array
    {
        $opportunity->loadMissing(['account', 'contact']);
        $account = $opportunity->account;

        return [
            'label' => 'Alamat customer',
            'contact' => $opportunity->contact?->full_name ?: $opportunity->contact?->name,
            'phone' => $opportunity->customerPhone(),
            'email' => $opportunity->customerEmail(),
            'province' => $account?->billing_address_state,
            'city' => $account?->billing_address_city,
            'district' => $account?->crm_billing_district,
            'postal' => $account?->billing_address_postal_code,
            'address' => $account?->billing_address_street,
        ];
    }

    public function formatAddressLine(?array $addr): string
    {
        if (! is_array($addr)) {
            return '';
        }

        return collect([
            $addr['address'] ?? null,
            $addr['district'] ?? null,
            $addr['city'] ?? null,
            $addr['province'] ?? null,
            $addr['postal'] ?? null,
        ])->filter(fn ($v) => filled($v))->implode(', ');
    }

    /**
     * @return array<string, string|null>
     */
    protected function parseAddressInput(string $text, Opportunity $opportunity, mixed $account): array
    {
        $base = $this->addressFromAccount($opportunity);
        $text = trim($text);
        if ($text !== '') {
            $base['address'] = $text;
        }

        return $base;
    }

    protected function nextDailySequence(string $prefix, string $ymd, bool $lock = true): int
    {
        $needle = $prefix.$ymd;
        $query = OpportunitySalesOrder::query()
            ->where('number', 'like', $needle.'%');

        if ($lock) {
            $query->lockForUpdate();
        }

        $max = 0;
        foreach ($query->pluck('number') as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }
            if (preg_match('/^'.preg_quote($needle, '/').'(\d{5})$/', $candidate, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max + 1;
    }

    protected function nextRefYearlySequence(string $prefix, int $year, string $yy, bool $lock = true): int
    {
        $query = OpportunitySalesOrder::query()
            ->where(function ($q) use ($year, $yy) {
                $q->whereYear('created_at', $year)
                    ->orWhere('nomor_ref', 'like', '%/'.$yy)
                    ->orWhere('number', 'like', '%/'.$yy);
            });

        if ($lock) {
            $query->lockForUpdate();
        }

        $max = 0;
        $pattern = '/^(\d+)(?:-R\d+)?\/[A-Z0-9]+\/'.preg_quote($prefix, '/').'\//i';
        foreach ($query->get(['number', 'nomor_ref']) as $row) {
            foreach ([$row->nomor_ref, $row->number] as $candidate) {
                if (! is_string($candidate) || $candidate === '') {
                    continue;
                }
                if (preg_match($pattern, $candidate, $m)) {
                    $max = max($max, (int) $m[1]);
                }
            }
        }

        return $max + 1;
    }

    protected function romanMonth(int $month): string
    {
        return [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$month] ?? 'I';
    }

    /**
     * Isi pasangan PSO untuk setiap SO (prefix berbeda, urutan sama).
     * Rekaman lama yang tersimpan sebagai PSO saja dikembalikan ke pasangan SO+PSO.
     */
    public function backfillPsoPairs(): int
    {
        $count = 0;
        $rows = OpportunitySalesOrder::query()->orderBy('id')->get();

        foreach ($rows as $row) {
            $number = (string) $row->number;
            $soNumber = $number;
            $psoNumber = $this->psoNumberFromSo($number);

            if (preg_match('/^PSO(\d{11})$/', $number, $m)) {
                $soNumber = $this->compactPrefix('so').$m[1];
                $psoNumber = $number;
            }

            if (! $psoNumber || ! preg_match('/^SO\d{11}$/', $soNumber)) {
                continue;
            }

            $snap = is_array($row->agc_payload) ? $row->agc_payload : [];
            $ref = (string) ($row->nomor_ref ?: ($snap['nomor_ref'] ?? ''));
            $soRef = $ref !== '' ? (preg_replace('#/PSO/#', '/SO/', $ref, 1) ?: $ref) : '';
            $psoRef = $soRef !== '' ? (preg_replace('#/SO/#', '/PSO/', $soRef, 1) ?: $soRef) : null;

            $changed = $row->number !== $soNumber
                || (string) $row->nomor_ref !== $soRef
                || ($snap['code'] ?? null) !== $soNumber
                || ($snap['pre_code'] ?? null) !== $psoNumber
                || ($snap['pso_number'] ?? null) !== $psoNumber
                || ($snap['nomor_ref'] ?? null) !== ($soRef !== '' ? $soRef : null)
                || ($psoRef && ($snap['pso_nomor_ref'] ?? null) !== $psoRef)
                || array_key_exists('doc_type', $snap);

            if (! $changed) {
                continue;
            }

            $legacyNumber = $row->number;
            $row->number = $soNumber;
            if ($soRef !== '') {
                $row->nomor_ref = $soRef;
            }
            $snap['code'] = $soNumber;
            $snap['pre_code'] = $psoNumber;
            $snap['pso_number'] = $psoNumber;
            if ($soRef !== '') {
                $snap['nomor_ref'] = $soRef;
            }
            if ($psoRef) {
                $snap['pso_nomor_ref'] = $psoRef;
            }
            unset($snap['doc_type']);
            $row->agc_payload = $snap;
            $row->save();

            Opportunity::query()
                ->where(function ($q) use ($row, $legacyNumber, $soNumber) {
                    $q->where('crm_sales_order_id', $row->id)
                        ->orWhere('crm_sales_order_no', $legacyNumber)
                        ->orWhere('crm_sales_order_no', $soNumber);
                })
                ->update(['crm_sales_order_no' => $soNumber]);

            $count++;
        }

        return $count;
    }
}
