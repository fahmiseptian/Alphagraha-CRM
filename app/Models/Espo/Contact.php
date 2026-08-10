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

    protected $fillable = [
        'first_name', 'last_name', 'job_role', 'middle_name', 'salutation_name', 'name',
        'description', 'account_id', 'assigned_user_id',
    ];

    public const TITLES = [
        'Mr.', 'Mrs.', 'Ms.', 'Miss',
        'Bapak', 'Ibu', 'Saudara', 'Saudari',
        'Dr.', 'Ir.', 'Prof.',
    ];

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
        $name = trim(($this->first_name ?? '').' '.($this->last_name ?? '')) ?: ($this->name ?? '-');
        $title = trim((string) ($this->salutation_name ?? ''));

        return $title !== '' ? $title.' '.$name : $name;
    }
}
