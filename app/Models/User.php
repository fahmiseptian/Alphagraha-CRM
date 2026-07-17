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
 * Role aplikasi disimpan di crm_user_profiles.app_role.
 * Espo `user.type` tetap admin/regular untuk kompatibilitas EspoCRM.
 */
class User extends Authenticatable
{
    use Notifiable;

    public const ROLE_SUPERADMIN = 'superadmin';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_SALES = 'sales';

    public const ROLE_PURCHASING = 'purchasing';

    public const ROLE_FINANCE = 'finance';

    public const ROLES = [
        self::ROLE_SUPERADMIN => 'Superadmin',
        self::ROLE_ADMIN => 'Admin',
        self::ROLE_SALES => 'Sales',
        self::ROLE_PURCHASING => 'Purchasing',
        self::ROLE_FINANCE => 'Finance',
    ];

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

    public function scopeInternalActive(Builder $query): Builder
    {
        return $query->where('is_active', 1)->whereIn('type', ['regular', 'admin']);
    }

    // --- Role ---------------------------------------------------------------

    public function getRoleAttribute(): string
    {
        $this->loadMissing('profile');

        $appRole = $this->profile?->app_role;
        if ($appRole && isset(self::ROLES[$appRole])) {
            return $appRole;
        }

        // Fallback legacy: Espo admin → superadmin, selain itu sales.
        return $this->type === 'admin' ? self::ROLE_SUPERADMIN : self::ROLE_SALES;
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? ucfirst($this->role);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    /**
     * Superadmin atau Admin (bisa lihat semua opportunity).
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_SUPERADMIN, self::ROLE_ADMIN], true);
    }

    public function isSales(): bool
    {
        return $this->role === self::ROLE_SALES;
    }

    public function isPurchasing(): bool
    {
        return $this->role === self::ROLE_PURCHASING;
    }

    public function isFinance(): bool
    {
        return $this->role === self::ROLE_FINANCE;
    }

    public function canAccessAdministration(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canCreateOpportunity(): bool
    {
        return in_array($this->role, [self::ROLE_SUPERADMIN, self::ROLE_SALES], true);
    }

    public function canCreateQuotation(): bool
    {
        return in_array($this->role, [
            self::ROLE_SUPERADMIN,
            self::ROLE_ADMIN,
            self::ROLE_SALES,
        ], true);
    }

    public function canViewAllOpportunities(): bool
    {
        return in_array($this->role, [
            self::ROLE_SUPERADMIN,
            self::ROLE_ADMIN,
            self::ROLE_PURCHASING,
            self::ROLE_FINANCE,
        ], true);
    }

    public function canEditOpportunityFully(): bool
    {
        return in_array($this->role, [self::ROLE_SUPERADMIN, self::ROLE_ADMIN, self::ROLE_SALES], true);
    }

    public function canApproveDiscount(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canEditWonCostVendor(): bool
    {
        return $this->isPurchasing() || $this->isSuperAdmin();
    }

    public function canViewWonFinance(): bool
    {
        return $this->isFinance() || $this->isSuperAdmin() || $this->isAdmin();
    }

    // --- Remember token dinonaktifkan ---------------------------------------

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

    public function crmNotifications(): HasMany
    {
        return $this->hasMany(CrmNotification::class, 'user_id', 'id');
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

    /**
     * Map app_role → Espo user.type.
     */
    public static function espoTypeForRole(string $appRole): string
    {
        return in_array($appRole, [self::ROLE_SUPERADMIN, self::ROLE_ADMIN], true)
            ? 'admin'
            : 'regular';
    }
}
