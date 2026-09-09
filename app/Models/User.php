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

    public const ROLE_PRODUCT = 'product';

    public const ROLE_PURCHASING = 'purchasing';

    public const ROLE_FINANCE = 'finance';

    public const ROLE_EKSPEDISI = 'ekspedisi';

    public const ROLES = [
        self::ROLE_SUPERADMIN => 'Superadmin',
        self::ROLE_ADMIN => 'Admin',
        self::ROLE_SALES => 'Sales',
        self::ROLE_PRODUCT => 'Product',
        self::ROLE_PURCHASING => 'Purchasing',
        self::ROLE_FINANCE => 'Finance',
        self::ROLE_EKSPEDISI => 'Ekspedisi',
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

    public function isProduct(): bool
    {
        return $this->role === self::ROLE_PRODUCT;
    }

    public function isPurchasing(): bool
    {
        return $this->role === self::ROLE_PURCHASING;
    }

    public function isFinance(): bool
    {
        return $this->role === self::ROLE_FINANCE;
    }

    public function isEkspedisi(): bool
    {
        return $this->role === self::ROLE_EKSPEDISI;
    }

    public function canAccessAdministration(): bool
    {
        return $this->isSuperAdmin();
    }

    /** Brand master: Superadmin, Product */
    public function canManageBrands(): bool
    {
        return $this->isSuperAdmin() || $this->isProduct();
    }

    /** Kategori master: Superadmin, Product */
    public function canManageCategories(): bool
    {
        return $this->isSuperAdmin() || $this->isProduct();
    }

    /** Vendor master: Superadmin, Product, Purchasing */
    public function canManageVendors(): bool
    {
        return $this->isSuperAdmin() || $this->isProduct() || $this->isPurchasing();
    }

    /** Industri customer: Superadmin only */
    public function canManageIndustries(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canManageCatalog(): bool
    {
        return $this->canManageBrands() || $this->canManageCategories() || $this->canManageVendors();
    }

    /** Customer: Sales/Admin/Superadmin + Purchasing + Finance */
    public function canViewCustomers(): bool
    {
        return in_array($this->role, [
            self::ROLE_SUPERADMIN,
            self::ROLE_ADMIN,
            self::ROLE_SALES,
            self::ROLE_PURCHASING,
            self::ROLE_FINANCE,
        ], true);
    }

    public function canViewOpportunities(): bool
    {
        return ! $this->isProduct() && ! $this->isEkspedisi();
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

    public function canCreateSalesOrder(): bool
    {
        return $this->canEditOpportunityFully();
    }

    public function canViewSalesOrders(): bool
    {
        return in_array($this->role, [
            self::ROLE_SUPERADMIN,
            self::ROLE_ADMIN,
            self::ROLE_SALES,
            self::ROLE_PRODUCT,
            self::ROLE_PURCHASING,
            self::ROLE_FINANCE,
            self::ROLE_EKSPEDISI,
        ], true);
    }

    /** Invoice di SO: Finance + Superadmin */
    public function canEditSalesOrderInvoice(): bool
    {
        return $this->isFinance() || $this->isSuperAdmin();
    }

    /** DO / resi / file DO: Finance + Ekspedisi + Superadmin */
    public function canEditSalesOrderDelivery(): bool
    {
        return $this->isFinance() || $this->isEkspedisi() || $this->isSuperAdmin();
    }

    /** Boleh update field SO (invoice/DO/dll) tanpa harus bisa create SO */
    public function canUpdateSalesOrderFields(): bool
    {
        return $this->canCreateSalesOrder()
            || $this->canEditSalesOrderInvoice()
            || $this->canEditSalesOrderDelivery();
    }

    public function canViewSalesOrderLogs(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canApproveDiscount(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canApproveMargin(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canApproveEventTraining(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canEditPaymentLevel(): bool
    {
        return $this->isFinance() || $this->isSuperAdmin();
    }

    public function canEditWonCostVendor(): bool
    {
        return $this->isPurchasing() || $this->isSuperAdmin();
    }

    public function canManagePurchaseOrders(): bool
    {
        return $this->isPurchasing() || $this->isSuperAdmin();
    }

    /** Lihat PO (read-only): sales, admin, finance, purchasing, superadmin — Closed Won. */
    public function canViewPurchaseOrders(): bool
    {
        return in_array($this->role, [
            self::ROLE_SUPERADMIN,
            self::ROLE_ADMIN,
            self::ROLE_SALES,
            self::ROLE_PURCHASING,
            self::ROLE_FINANCE,
        ], true);
    }

    public function canManageVendorStocks(): bool
    {
        return $this->canManagePurchaseOrders();
    }

    public function canViewWonFinance(): bool
    {
        return $this->isFinance() || $this->isSuperAdmin() || $this->isAdmin();
    }

    public function canCreateCustomerContact(): bool
    {
        return in_array($this->role, [
            self::ROLE_SUPERADMIN,
            self::ROLE_ADMIN,
            self::ROLE_SALES,
            self::ROLE_PURCHASING,
            self::ROLE_FINANCE,
        ], true);
    }

    public function canEditCustomerContact(): bool
    {
        return $this->canCreateCustomerContact();
    }

    public function canDeleteCustomerContact(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canManageCustomerAddresses(): bool
    {
        return in_array($this->role, [
            self::ROLE_SUPERADMIN,
            self::ROLE_ADMIN,
            self::ROLE_SALES,
            self::ROLE_PURCHASING,
            self::ROLE_FINANCE,
        ], true);
    }

    public function canDeleteCustomer(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canDeleteOpportunity(): bool
    {
        return $this->isSuperAdmin() || $this->isSales();
    }

    public function canDeleteQuotation(): bool
    {
        return $this->isSuperAdmin();
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

        return (bool) $this->profile?->hasAllCompanySignatures();
    }

    public function hasDigitalSignatureFor(?string $companyKey = null): bool
    {
        $this->loadMissing('profile');

        if ($companyKey === null || $companyKey === '') {
            return $this->hasDigitalSignature();
        }

        return (bool) $this->profile?->hasSignatureFor($companyKey);
    }

    public function signatureAbsolutePath(?string $companyKey = null): ?string
    {
        $this->loadMissing('profile');

        return $this->profile?->signatureAbsolutePath($companyKey);
    }

    /**
     * @return list<string>
     */
    public function missingSignatureLabels(): array
    {
        $this->loadMissing('profile');

        return $this->profile?->missingSignatureLabels() ?? UserProfile::signatureCompanyLabels();
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
