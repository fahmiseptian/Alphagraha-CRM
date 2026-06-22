<?php

namespace App\Models\Espo\Concerns;

use App\Models\Espo\EmailAddress;
use App\Models\Espo\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Konfigurasi bersama untuk semua entitas yang dibaca dari database EspoCRM.
 *
 * EspoCRM memakai:
 *  - primary key string (varchar 24), bukan auto-increment
 *  - kolom soft-delete `deleted`
 *  - timestamp `created_at` / `modified_at` (bukan updated_at ala Laravel)
 *  - relasi email & telepon polimorfik via tabel pivot entity_email_address / entity_phone_number
 */
trait EspoEntity
{
    public function initializeEspoEntity(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
        $this->timestamps = false;
    }

    /**
     * Sembunyikan baris yang dihapus (soft delete EspoCRM).
     */
    public static function bootEspoEntity(): void
    {
        static::addGlobalScope('notDeleted', function (Builder $builder) {
            $builder->where($builder->getModel()->getTable() . '.deleted', 0);
        });
    }

    /**
     * Nama entitas EspoCRM (mis. "Account", "Contact", "Lead").
     */
    abstract public function espoEntityType(): string;

    public function emailAddresses(): BelongsToMany
    {
        return $this->belongsToMany(
            EmailAddress::class,
            'entity_email_address',
            'entity_id',
            'email_address_id'
        )
            ->withPivot(['primary', 'entity_type'])
            ->wherePivot('deleted', 0)
            ->wherePivot('entity_type', $this->espoEntityType())
            ->orderByPivot('primary', 'desc');
    }

    public function phoneNumbers(): BelongsToMany
    {
        return $this->belongsToMany(
            PhoneNumber::class,
            'entity_phone_number',
            'entity_id',
            'phone_number_id'
        )
            ->withPivot(['primary', 'entity_type'])
            ->wherePivot('deleted', 0)
            ->wherePivot('entity_type', $this->espoEntityType())
            ->orderByPivot('primary', 'desc');
    }

    public function getEmailAttribute(): ?string
    {
        return optional($this->emailAddresses->first())->name;
    }

    public function getPhoneAttribute(): ?string
    {
        return optional($this->phoneNumbers->first())->name;
    }
}
