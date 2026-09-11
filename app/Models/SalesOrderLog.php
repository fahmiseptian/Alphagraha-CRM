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
}
