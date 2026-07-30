<?php

namespace App\Models\Espo;

use App\Models\User;
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

    /**
     * User aktif dengan role aplikasi Sales (crm_user_profiles.app_role).
     * Tanpa profil + type regular tetap dihitung sales (default Espo).
     */
    public function scopeActiveSales(Builder $query): Builder
    {
        return $query
            ->where('is_active', 1)
            ->where(function (Builder $q) {
                $q->whereExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('crm_user_profiles')
                        ->whereColumn('crm_user_profiles.user_id', 'user.id')
                        ->where('crm_user_profiles.app_role', User::ROLE_SALES);
                })->orWhere(function (Builder $q2) {
                    $q2->where('type', 'regular')
                        ->whereNotExists(function ($sub) {
                            $sub->selectRaw('1')
                                ->from('crm_user_profiles')
                                ->whereColumn('crm_user_profiles.user_id', 'user.id');
                        });
                });
            });
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name
            ?: trim(($this->first_name ?? '').' '.($this->last_name ?? ''))
            ?: ($this->user_name ?? '-');
    }
}
