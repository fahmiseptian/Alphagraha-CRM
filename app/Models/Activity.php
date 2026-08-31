<?php

namespace App\Models;

use App\Models\Espo\Account;
use App\Models\Espo\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Activity extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $table = 'crm_activities';

    protected $fillable = [
        'type', 'subject', 'description', 'account_id', 'lead_id', 'quotation_id',
        'status', 'priority', 'due_at', 'reminder_at', 'completed_at',
        'assigned_to', 'created_by',
        'approval_status', 'approved_by', 'approved_at', 'approval_note',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'reminder_at' => 'datetime',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public const TYPES = [
        'call' => 'Call',
        'meeting' => 'Meeting',
        'email' => 'Email',
        'task' => 'Task',
        'followup' => 'Follow-up',
        'note' => 'Note',
        'event_training' => 'Event/Training',
    ];

    public const TYPE_EVENT_TRAINING = 'event_training';

    public const APPROVAL_PENDING = 'pending';

    public const APPROVAL_APPROVED = 'approved';

    public const APPROVAL_REJECTED = 'rejected';

    public const MEDIA_COLLECTIONS = [
        'invitation' => 'Undangan',
        'proof' => 'Bukti',
    ];

    public const STATUSES = [
        'planned' => 'Planned',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function isOverdue(): bool
    {
        return $this->due_at
            && $this->due_at->isPast()
            && ! in_array($this->status, ['completed', 'cancelled']);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function isEventTraining(): bool
    {
        return $this->type === self::TYPE_EVENT_TRAINING;
    }

    public function needsEventApproval(): bool
    {
        return $this->isEventTraining();
    }

    public function isEventApprovalPending(): bool
    {
        return $this->isEventTraining() && $this->approval_status === self::APPROVAL_PENDING;
    }

    public function isEventApproved(): bool
    {
        return $this->isEventTraining() && $this->approval_status === self::APPROVAL_APPROVED;
    }

    public function isEventRejected(): bool
    {
        return $this->isEventTraining() && $this->approval_status === self::APPROVAL_REJECTED;
    }

    public function eventDueHasPassed(): bool
    {
        return $this->due_at !== null && $this->due_at->lt(now());
    }

    public function approvalLabel(): ?string
    {
        if (! $this->isEventTraining()) {
            return null;
        }

        return match ($this->approval_status) {
            self::APPROVAL_PENDING => 'Menunggu approval',
            self::APPROVAL_APPROVED => 'Approved',
            self::APPROVAL_REJECTED => 'Rejected',
            default => 'Menunggu approval',
        };
    }

    public function approvalBadgeColor(): string
    {
        return match ($this->approval_status) {
            self::APPROVAL_APPROVED => 'green',
            self::APPROVAL_REJECTED => 'red',
            default => 'amber',
        };
    }

    /**
     * Event/Training: pending superadmin, kecuali tanggal sudah lewat atau pembuatnya superadmin.
     */
    public function syncEventApproval(?User $actor = null): void
    {
        if (! $this->isEventTraining()) {
            $this->approval_status = null;
            $this->approved_by = null;
            $this->approved_at = null;
            $this->approval_note = null;

            return;
        }

        $actor ??= auth()->user();

        if ($this->eventDueHasPassed()) {
            $this->approval_status = self::APPROVAL_APPROVED;
            $this->approved_at = $this->approved_at ?? now();

            return;
        }

        // Superadmin yang membuat Event/Training baru langsung approved.
        if ($actor?->isSuperAdmin() && ! $this->exists) {
            $this->approval_status = self::APPROVAL_APPROVED;
            $this->approved_by = $actor->id;
            $this->approved_at = now();
            $this->approval_note = $this->approval_note ?: null;

            return;
        }

        if ($this->approval_status === self::APPROVAL_APPROVED && filled($this->approved_by)) {
            return;
        }

        $this->approval_status = self::APPROVAL_PENDING;
        $this->approved_by = null;
        $this->approved_at = null;
        $this->approval_note = null;
    }

    /**
     * Event/Training yang sudah lewat dan masih pending langsung di-approve.
     */
    public static function approvePastPendingEvents(): void
    {
        if (! Schema::hasColumn('crm_activities', 'approval_status')) {
            return;
        }

        $ids = self::query()
            ->where('type', self::TYPE_EVENT_TRAINING)
            ->where('approval_status', self::APPROVAL_PENDING)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        self::query()->whereIn('id', $ids)->update([
            'approval_status' => self::APPROVAL_APPROVED,
            'approved_at' => now(),
        ]);

        $notifications = app(\App\Services\NotificationService::class);
        foreach ($ids as $id) {
            $notifications->markEventApprovalActioned((int) $id);
        }
    }

    /**
     * URL template Google Calendar untuk menambahkan event di kalender user.
     * Contoh: https://calendar.google.com/calendar/render?action=TEMPLATE&text=...
     */
    public function googleCalendarUrl(?string $timezone = null): string
    {
        $this->loadMissing(['account', 'lead']);

        $timezone = $timezone ?: config('crm.google_calendar_timezone', 'Asia/Jakarta');

        // Gunakan UTC + suffix Z (format paling stabil di Google Calendar).
        $start = ($this->due_at ?? now())->copy()->timezone($timezone)->utc();
        $end = $start->copy()->addHour();

        $details = collect([
            'Type: '.$this->typeLabel(),
            'Priority: '.ucfirst((string) $this->priority),
            $this->account?->name ? 'Customer: '.$this->account->name : null,
            $this->lead?->full_name ? 'Lead: '.$this->lead->full_name : null,
            $this->description ? trim(strip_tags((string) $this->description)) : null,
        ])->filter()->implode("\n");

        // Slash pada dates JANGAN di-encode (%2F) — Google Calendar sering gagal membacanya.
        $dates = $start->format('Ymd\THis\Z').'/'.$end->format('Ymd\THis\Z');

        $query = http_build_query([
            'action' => 'TEMPLATE',
            'text' => (string) $this->subject,
            'details' => $details,
            'sf' => 'true',
            'output' => 'xml',
        ], '', '&', PHP_QUERY_RFC3986);

        return 'https://calendar.google.com/calendar/render?'.$query.'&dates='.$dates;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('invitation')->useDisk('public');
        $this->addMediaCollection('proof')->useDisk('public');
    }
}
