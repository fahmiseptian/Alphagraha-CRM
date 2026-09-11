<?php

namespace App\Models;

use App\Models\Espo\Opportunity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OpportunitySalesOrder extends Model
{
    use SoftDeletes;

    public const CANCEL_PENDING = 'pending';

    public const CANCEL_APPROVED = 'approved';

    public const CANCEL_REJECTED = 'rejected';

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
        'deleted_by',
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

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SalesOrderLog::class, 'sales_order_id')->orderByDesc('id');
    }

    /**
     * SO tidak boleh dihapus permanen — tetap ada di log Superadmin.
     */
    public function forceDelete()
    {
        throw new \RuntimeException('Sales Order tidak dapat dihapus permanen.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toLogSnapshot(): array
    {
        $this->loadMissing(['opportunity.account', 'creator']);

        return [
            'id' => $this->id,
            'number' => $this->displayNumber(),
            'pso_number' => $this->displayPsoNumber(),
            'nomor_ref' => $this->displayRefNumber(),
            'opportunity_id' => $this->opportunity_id,
            'opportunity_name' => $this->opportunity?->name,
            'customer' => $this->opportunity?->account?->name ?: $this->opportunity?->company,
            'email' => $this->email,
            'payment' => $this->payment,
            'payment_label' => $this->paymentLabel(),
            'po_number' => $this->po_number,
            'required_delivery' => optional($this->required_delivery)->toDateString(),
            'note' => $this->note,
            'items' => $this->items,
            'payload' => $this->agc_payload,
            'status' => $this->statusSnapshot(),
            'cancel_status' => $this->cancelStatus(),
            'cancel_reason' => $this->cancelReason(),
            'created_by' => $this->created_by,
            'created_by_name' => $this->creator?->display_name,
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'deleted_at' => optional($this->deleted_at)->toDateTimeString(),
            'deleted_by' => $this->deleted_by,
        ];
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
            'cancel_status' => $data['cancel_status'] ?? null,
        ];
    }

    public function payloadValue(string $key, mixed $default = null): mixed
    {
        $payload = is_array($this->agc_payload) ? $this->agc_payload : [];

        return $payload[$key] ?? $default;
    }

    public function cancelStatus(): ?string
    {
        $status = strtolower(trim((string) $this->payloadValue('cancel_status', '')));

        return $status !== '' ? $status : null;
    }

    public function cancelReason(): string
    {
        return trim((string) $this->payloadValue('cancel_reason', ''));
    }

    public function cancelReviewNote(): string
    {
        return trim((string) $this->payloadValue('cancel_review_note', ''));
    }

    public function isCancelPending(): bool
    {
        return $this->cancelStatus() === self::CANCEL_PENDING;
    }

    public function isCancelled(): bool
    {
        $soStatus = strtolower((string) ($this->statusSnapshot()['so_status'] ?? ''));

        return $soStatus === 'cancelled' || $this->cancelStatus() === self::CANCEL_APPROVED;
    }
}
