<?php

namespace App\Models;

use App\Models\Espo\Opportunity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderLog extends Model
{
    protected $table = 'crm_sales_order_logs';

    public $timestamps = false;

    public const ACTION_CREATED = 'created';

    public const ACTION_UPDATED = 'updated';

    public const ACTION_DELETED = 'deleted';

    public const ACTION_CANCEL_REQUESTED = 'cancel_requested';

    public const ACTION_CANCEL_APPROVED = 'cancel_approved';

    public const ACTION_CANCEL_REJECTED = 'cancel_rejected';

    public const ACTIONS = [
        self::ACTION_CREATED => 'Dibuat',
        self::ACTION_UPDATED => 'Diubah',
        self::ACTION_DELETED => 'Diarsipkan',
        self::ACTION_CANCEL_REQUESTED => 'Request pembatalan',
        self::ACTION_CANCEL_APPROVED => 'Pembatalan disetujui',
        self::ACTION_CANCEL_REJECTED => 'Pembatalan ditolak',
    ];

    protected $fillable = [
        'sales_order_id',
        'opportunity_id',
        'action',
        'number',
        'actor_id',
        'actor_name',
        'snapshot',
        'changes',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(OpportunitySalesOrder::class, 'sales_order_id')->withTrashed();
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? ucfirst((string) $this->action);
    }

    public function actionBadgeColor(): string
    {
        return match ($this->action) {
            self::ACTION_CREATED, self::ACTION_CANCEL_APPROVED => 'green',
            self::ACTION_UPDATED => 'blue',
            self::ACTION_DELETED, self::ACTION_CANCEL_REQUESTED => 'amber',
            self::ACTION_CANCEL_REJECTED => 'red',
            default => 'slate',
        };
    }

    public function snapshotValue(string $key, mixed $default = null): mixed
    {
        $snap = is_array($this->snapshot) ? $this->snapshot : [];

        return data_get($snap, $key, $default);
    }

    /**
     * Detail field before/after (format baru). Log lama bisa hanya daftar nama field.
     *
     * @return list<array<string, mixed>>
     */
    /**
     * Kolom JSON `changes` — jangan pakai $this->changes (bentrok properti dirty Eloquent).
     *
     * @return array<string, mixed>
     */
    public function changePayload(): array
    {
        $raw = $this->getAttribute('changes');

        return is_array($raw) ? $raw : [];
    }

    public function fieldChanges(): array
    {
        $changes = $this->changePayload();
        $fields = $changes['fields'] ?? null;
        if (is_array($fields)) {
            return array_values($fields);
        }

        // Format lama: list string / map from-to sederhana.
        $legacy = [];
        foreach ($changes as $key => $value) {
            if ($key === 'summary' || $key === 'products' || $key === 'note') {
                continue;
            }
            if (is_int($key) && is_string($value)) {
                $legacy[] = [
                    'field' => $value,
                    'label' => $value,
                    'from_display' => '—',
                    'to_display' => '(diubah)',
                ];
                continue;
            }
            if (is_string($key) && is_array($value) && (array_key_exists('from', $value) || array_key_exists('to', $value))) {
                $from = $value['from'] ?? null;
                $to = $value['to'] ?? null;
                $legacy[] = [
                    'field' => $key,
                    'label' => $key,
                    'from' => $from,
                    'to' => $to,
                    'from_display' => $from !== null && $from !== '' ? (string) $from : '—',
                    'to_display' => $to !== null && $to !== '' ? (string) $to : '—',
                ];
            }
        }

        return $legacy;
    }

    /**
     * @return array{added: list<array<string, mixed>>, removed: list<array<string, mixed>>, changed: list<array<string, mixed>>}
     */
    public function productChanges(): array
    {
        $changes = $this->changePayload();
        $products = is_array($changes['products'] ?? null) ? $changes['products'] : [];

        return [
            'added' => array_values(is_array($products['added'] ?? null) ? $products['added'] : []),
            'removed' => array_values(is_array($products['removed'] ?? null) ? $products['removed'] : []),
            'changed' => array_values(is_array($products['changed'] ?? null) ? $products['changed'] : []),
        ];
    }

    public function hasDetailedChanges(): bool
    {
        $products = $this->productChanges();

        return $this->fieldChanges() !== []
            || $products['added'] !== []
            || $products['removed'] !== []
            || $products['changed'] !== [];
    }

    public function changeSummary(): string
    {
        $changes = $this->changePayload();

        return trim((string) ($changes['summary'] ?? ''));
    }
}
