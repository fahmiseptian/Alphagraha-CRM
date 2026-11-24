<?php

namespace App\Models;

use App\Models\Espo\Opportunity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLog extends Model
{
    protected $table = 'crm_purchase_order_logs';

    public $timestamps = false;

    public const ACTION_CREATED = 'created';

    public const ACTION_UPDATED = 'updated';

    public const ACTION_DELETED = 'deleted';

    public const ACTION_APPROVED = 'approved';

    public const ACTION_REJECTED = 'rejected';

    public const ACTIONS = [
        self::ACTION_CREATED => 'Dibuat',
        self::ACTION_UPDATED => 'Diubah',
        self::ACTION_DELETED => 'Dihapus',
        self::ACTION_APPROVED => 'Disetujui',
        self::ACTION_REJECTED => 'Ditolak',
    ];

    protected $fillable = [
        'purchase_order_id',
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

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
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
            self::ACTION_CREATED, self::ACTION_APPROVED => 'green',
            self::ACTION_UPDATED => 'blue',
            self::ACTION_DELETED => 'amber',
            self::ACTION_REJECTED => 'red',
            default => 'slate',
        };
    }

    public function snapshotValue(string $key, mixed $default = null): mixed
    {
        $snap = is_array($this->snapshot) ? $this->snapshot : [];

        return data_get($snap, $key, $default);
    }

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

    /**
     * @return list<array<string, mixed>>
     */
    public function fieldChanges(): array
    {
        $changes = $this->changePayload();
        $fields = $changes['fields'] ?? null;
        if (is_array($fields)) {
            return array_values($fields);
        }

        return [];
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
};
