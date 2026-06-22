<?php

namespace App\Models\Espo;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * User EspoCRM (tabel `user`).
 * Dipakai read-only untuk menampilkan nama sales/assignment & pemetaan.
 */
class EspoUser extends Model
{
    protected $table = 'user';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected static function booted(): void
    {
        static::addGlobalScope('notDeleted', function (Builder $builder) {
            $builder->where('user.deleted', 0);
        });
    }

    public function scopeActiveRegular(Builder $query): Builder
    {
        return $query->where('is_active', 1)->whereIn('type', ['regular', 'admin']);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name
            ?: trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''))
            ?: ($this->user_name ?? '-');
    }
}
