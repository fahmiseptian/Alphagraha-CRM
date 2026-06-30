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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('invitation')->useDisk('public');
        $this->addMediaCollection('proof')->useDisk('public');
    }
}
