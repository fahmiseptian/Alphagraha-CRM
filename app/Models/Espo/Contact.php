<?php

namespace App\Models\Espo;

use App\Models\Espo\Concerns\EspoEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kontak person (EspoCRM: Contact).
 */
class Contact extends Model
{
    use EspoEntity;

    protected $table = 'contact';

    public function espoEntityType(): string
    {
        return 'Contact';
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(EspoUser::class, 'assigned_user_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')) ?: ($this->name ?? '-');
    }
}
