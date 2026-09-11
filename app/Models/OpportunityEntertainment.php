<?php

namespace App\Models;

use App\Models\Espo\Opportunity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class OpportunityEntertainment extends Model
{
    protected $table = 'crm_opportunity_entertainments';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETE = 'complete';

    protected $fillable = [
        'opportunity_id',
        'name',
        'description',
        'photo_path',
        'amount',
        'status',
        'created_by',
        'completed_by',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'completed_at' => 'datetime',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isComplete(): bool
    {
        return $this->status === self::STATUS_COMPLETE;
    }

    public function statusLabel(): string
    {
        return $this->isComplete() ? 'Complete' : 'Pending';
    }

    public function statusColor(): string
    {
        return $this->isComplete() ? 'green' : 'amber';
    }

    public function photoUrl(): ?string
    {
        $path = trim((string) $this->photo_path);
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public function photoIsImage(): bool
    {
        $ext = strtolower(pathinfo((string) $this->photo_path, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    public function deletePhoto(): void
    {
        $path = trim((string) $this->photo_path);
        if ($path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
