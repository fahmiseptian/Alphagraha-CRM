<?php

namespace App\Models;

use App\Models\Espo\Opportunity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunitySalesOrder extends Model
{
    protected $table = 'crm_opportunity_sales_orders';

    protected $fillable = [
        'opportunity_id',
        'agc_sales_order_id',
        'number',
        'email',
        'payment',
        'billing_address_id',
        'shipping_address_id',
        'po_number',
        'nomor_ref',
        'required_delivery',
        'note',
        'items',
        'agc_payload',
        'created_by',
    ];

    protected $casts = [
        'required_delivery' => 'date',
        'items' => 'array',
        'agc_payload' => 'array',
        'billing_address_id' => 'integer',
        'shipping_address_id' => 'integer',
        'agc_sales_order_id' => 'integer',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function displayNumber(): string
    {
        $code = trim((string) $this->number);
        if ($code !== '') {
            return $code;
        }

        $payload = is_array($this->agc_payload) ? $this->agc_payload : [];
        $fromPayload = trim((string) (
            $payload['sales_order_code']
            ?? $payload['code']
            ?? data_get($payload, 'data.sales_order_code')
            ?? ''
        ));

        return $fromPayload !== '' ? $fromPayload : 'Tanpa kode SO';
    }

    public function displayRefNumber(): string
    {
        $ref = trim((string) $this->nomor_ref);
        if ($ref !== '') {
            return $ref;
        }

        $payload = is_array($this->agc_payload) ? $this->agc_payload : [];

        return trim((string) ($payload['nomor_ref'] ?? ''));
    }

    public function displayPsoNumber(): string
    {
        $payload = is_array($this->agc_payload) ? $this->agc_payload : [];
        $fromPayload = trim((string) ($payload['pso_number'] ?? $payload['pre_code'] ?? ''));
        if ($fromPayload !== '') {
            return $fromPayload;
        }

        if (preg_match('/^SO(\d{11})$/', (string) $this->number, $m)) {
            return 'PSO'.$m[1];
        }

        return '';
    }

    public function paymentLabel(): string
    {
        $payment = trim((string) $this->payment);
        if ($payment === '') {
            $payload = is_array($this->agc_payload) ? $this->agc_payload : [];
            $payment = trim((string) ($payload['payment_method'] ?? $payload['payment'] ?? ''));
        }
        if (preg_match('/^top(\d+)$/i', $payment, $m)) {
            return 'TOP '.$m[1];
        }

        return $payment !== '' ? strtoupper($payment) : '—';
    }

    public function statusSnapshot(): array
    {
        $payload = is_array($this->agc_payload) ? $this->agc_payload : [];
        $data = is_array($payload['data'] ?? null) ? array_merge($payload, $payload['data']) : $payload;

        return [
            'so_status' => $data['so_status'] ?? null,
            'payment_status' => $data['payment_status'] ?? null,
            'delivery_status' => $data['delivery_status'] ?? null,
        ];
    }
}
