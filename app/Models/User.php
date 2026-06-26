<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

/**
 * Pengguna aplikasi = user EspoCRM (tabel `user`).
 *
 * Autentikasi memakai algoritma hashing EspoCRM (lihat App\Services\EspoPassword),
 * sehingga login tidak melalui Auth::attempt bawaan melainkan Auth::login().
 * Role diturunkan dari kolom `type`: admin -> Administrator, selain itu -> Sales.
 */
class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'user';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'user_name', 'name', 'first_name', 'last_name', 'type', 'is_active', 'title',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('notDeleted', function (Builder $builder) {
            $builder->where('user.deleted', 0);
        });
    }

    /**
     * Hanya user internal aktif yang relevan (bukan API/portal/system).
     */
    public function scopeInternalActive(Builder $query): Builder
    {
        return $query->where('is_active', 1)->whereIn('type', ['regular', 'admin']);
    }

    // --- Role ---------------------------------------------------------------

    public function getRoleAttribute(): string
    {
        return $this->type === 'admin' ? 'admin' : 'sales';
    }

    public function isAdmin(): bool
    {
        return $this->type === 'admin';
    }

    public function isSales(): bool
    {
        return ! $this->isAdmin();
    }

    // --- Remember token dinonaktifkan (kolom tidak ada di tabel EspoCRM) -----

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        // sengaja dikosongkan
    }

    public function getRememberTokenName(): ?string
    {
        return null;
    }

    // --- Aksesor tampilan ---------------------------------------------------

    public function getDisplayNameAttribute(): string
    {
        return $this->name
            ?: trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''))
            ?: (string) $this->user_name;
    }

    /**
     * Email primary user dari EspoCRM (entity_email_address -> email_address).
     */
    public function getEmailAttribute(): ?string
    {
        $emailId = DB::table('entity_email_address')
            ->where('entity_type', 'User')
            ->where('entity_id', $this->id)
            ->where('deleted', 0)
            ->orderByDesc('primary')
            ->value('email_address_id');

        return $emailId
            ? DB::table('email_address')->where('id', $emailId)->value('name')
            : null;
    }

    // --- Relasi -------------------------------------------------------------

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class, 'created_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'assigned_to');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class, 'user_id', 'id');
    }

    public function hasDigitalSignature(): bool
    {
        $this->loadMissing('profile');

        return (bool) $this->profile?->signatureAbsolutePath();
    }

    public function signatureAbsolutePath(): ?string
    {
        $this->loadMissing('profile');

        return $this->profile?->signatureAbsolutePath();
    }
}
