<?php

namespace App\Models;

use App\Models\Espo\Account;
use App\Models\Espo\Lead;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'reminder_at' => 'datetime',
        'completed_at' => 'datetime',
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
