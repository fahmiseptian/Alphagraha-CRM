<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmNotification extends Model
{
    protected $table = 'crm_notifications';

    public const TYPE_DISCOUNT_APPROVED = 'discount_approved';

    public const TYPE_DISCOUNT_REVISED = 'discount_revised';

    public const TYPE_DISCOUNT_REJECTED = 'discount_rejected';

    public const TYPE_DISCOUNT_REVERTED = 'discount_reverted';

    public const TYPE_DISCOUNT_REQUESTED = 'discount_requested';

    public const TYPE_MARGIN_REQUESTED = 'margin_requested';

    public const TYPE_MARGIN_APPROVED = 'margin_approved';

    public const TYPE_MARGIN_REJECTED = 'margin_rejected';

    public const TYPE_ACTIVITY_DUE = 'activity_due';

    public const TYPE_EVENT_APPROVAL_REQUESTED = 'event_approval_requested';

    public const TYPE_EVENT_APPROVED = 'event_approved';

    public const TYPE_EVENT_REJECTED = 'event_rejected';

    public const TYPE_OPPORTUNITY_DEADLINE = 'opportunity_deadline';

    public const TYPE_SALES_ORDER_CREATED = 'sales_order_created';

    public const TYPE_OPPORTUNITY_ASSIGNED = 'opportunity_assigned';

    public const TYPE_CUSTOMER_INDUSTRY_UPDATE = 'customer_industry_update';

    public const TYPE_GENERAL = 'general';

    protected $fillable = [
        'user_id', 'type', 'title', 'body', 'link', 'unique_key',
        'data', 'show_popup', 'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'show_popup' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    /**
     * Notifikasi yang butuh aksi bisnis — tidak boleh ditandai dibaca hanya karena dibuka/ditutup.
     */
    public function requiresAction(): bool
    {
        return in_array($this->type, [
            self::TYPE_DISCOUNT_REQUESTED,
            self::TYPE_MARGIN_REQUESTED,
            self::TYPE_ACTIVITY_DUE,
            self::TYPE_EVENT_APPROVAL_REQUESTED,
            self::TYPE_OPPORTUNITY_DEADLINE,
            self::TYPE_SALES_ORDER_CREATED,
            self::TYPE_CUSTOMER_INDUSTRY_UPDATE,
        ], true);
    }

    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill([
                'read_at' => now(),
                'show_popup' => false,
            ])->save();
        }
    }

    public function icon(): string
    {
        return match ($this->type) {
            self::TYPE_DISCOUNT_APPROVED, self::TYPE_DISCOUNT_REVISED, self::TYPE_DISCOUNT_REJECTED,
            self::TYPE_DISCOUNT_REVERTED, self::TYPE_DISCOUNT_REQUESTED => 'bi-percent',
            self::TYPE_MARGIN_REQUESTED, self::TYPE_MARGIN_APPROVED, self::TYPE_MARGIN_REJECTED => 'bi-graph-up-arrow',
            self::TYPE_ACTIVITY_DUE => 'bi-calendar-event',
            self::TYPE_EVENT_APPROVAL_REQUESTED, self::TYPE_EVENT_APPROVED, self::TYPE_EVENT_REJECTED => 'bi-calendar-event',
            self::TYPE_OPPORTUNITY_DEADLINE => 'bi-briefcase',
            self::TYPE_SALES_ORDER_CREATED => 'bi-receipt',
            self::TYPE_OPPORTUNITY_ASSIGNED => 'bi-person-check',
            self::TYPE_CUSTOMER_INDUSTRY_UPDATE => 'bi-buildings',
            default => 'bi-bell',
        };
    }

    public function colorClass(): string
    {
        return match ($this->type) {
            self::TYPE_DISCOUNT_APPROVED => 'bg-green-50 text-green-600',
            self::TYPE_DISCOUNT_REVISED => 'bg-amber-50 text-amber-600',
            self::TYPE_DISCOUNT_REJECTED => 'bg-red-50 text-red-600',
            self::TYPE_DISCOUNT_REVERTED => 'bg-slate-100 text-slate-600',
            self::TYPE_DISCOUNT_REQUESTED => 'bg-rose-50 text-rose-600',
            self::TYPE_MARGIN_APPROVED => 'bg-green-50 text-green-600',
            self::TYPE_MARGIN_REJECTED => 'bg-red-50 text-red-600',
            self::TYPE_MARGIN_REQUESTED => 'bg-amber-50 text-amber-600',
            self::TYPE_ACTIVITY_DUE => 'bg-blue-50 text-blue-600',
            self::TYPE_EVENT_APPROVED => 'bg-green-50 text-green-600',
            self::TYPE_EVENT_REJECTED => 'bg-red-50 text-red-600',
            self::TYPE_EVENT_APPROVAL_REQUESTED => 'bg-amber-50 text-amber-600',
            self::TYPE_OPPORTUNITY_DEADLINE => 'bg-rose-50 text-rose-600',
            self::TYPE_SALES_ORDER_CREATED => 'bg-indigo-50 text-indigo-600',
            self::TYPE_OPPORTUNITY_ASSIGNED => 'bg-brand-50 text-brand-600',
            self::TYPE_CUSTOMER_INDUSTRY_UPDATE => 'bg-amber-50 text-amber-600',
            default => 'bg-slate-100 text-slate-600',
        };
    }
}
