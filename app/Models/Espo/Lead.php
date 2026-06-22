<?php

namespace App\Models\Espo;

use App\Models\Activity;
use App\Models\Espo\Concerns\EspoEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Prospek (EspoCRM: Lead).
 *
 * Catatan: nama tabel `lead` adalah reserved word di MySQL 8/9,
 * Laravel otomatis mem-backtick sehingga aman digunakan.
 */
class Lead extends Model
{
    use EspoEntity;

    protected $table = 'lead';

    /** Status lead standar EspoCRM. */
    public const STATUSES = [
        'New', 'Assigned', 'In Process', 'Converted', 'Recycled', 'Dead',
    ];

    public function espoEntityType(): string
    {
        return 'Lead';
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(EspoUser::class, 'assigned_user_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'lead_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')) ?: ($this->name ?? '-');
    }
}
