<?php

namespace App\Models\Espo;

use App\Models\Activity;
use App\Models\Espo\Concerns\EspoEntity;
use App\Models\Quotation;
use App\Support\PaymentLevel;
use App\Support\CustomerTop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pelanggan (EspoCRM: Account).
 */
class Account extends Model
{
    use EspoEntity;

    protected $table = 'account';

    protected $fillable = [
        'name', 'type', 'industry', 'website', 'description',
        'billing_address_street', 'billing_address_city', 'billing_address_state',
        'billing_address_country', 'billing_address_postal_code', 'assigned_user_id',
        'crm_payment_level', 'crm_top',
        'crm_province_code', 'crm_regency_code', 'crm_district_code', 'crm_billing_district',
    ];

    public const TYPES = ['Customer', 'Reseller', 'Partner'];

    public function espoEntityType(): string
    {
        return 'Account';
    }

    public function paymentLevel(): string
    {
        $level = (string) ($this->crm_payment_level ?: PaymentLevel::LANCAR);

        return PaymentLevel::isValid($level) ? $level : PaymentLevel::LANCAR;
    }

    public function paymentLevelLabel(): string
    {
        return PaymentLevel::label($this->paymentLevel());
    }

    public function isPaymentSuspended(): bool
    {
        return PaymentLevel::isSuspended($this->paymentLevel());
    }

    /**
     * Minimal margin (%) efektif: max(level pembayaran, TOP).
     * Null bila Suspend (tidak boleh quote).
     */
    public function minMarginPercent(): ?float
    {
        if ($this->isPaymentSuspended()) {
            return null;
        }

        $fromLevel = PaymentLevel::minMarginPercent($this->paymentLevel()) ?? 0.0;
        $fromTop = CustomerTop::minMarginPercent($this->crm_top);

        return max($fromLevel, $fromTop);
    }

    /**
     * Minimal margin (%) khusus dari setting TOP customer.
     */
    public function topMinMarginPercent(): float
    {
        return CustomerTop::minMarginPercent($this->crm_top);
    }

    public function top(): string
    {
        return CustomerTop::normalize($this->crm_top);
    }

    public function topLabel(): string
    {
        return CustomerTop::label($this->crm_top);
    }

    public function topDays(): int
    {
        return CustomerTop::days($this->crm_top);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'account_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'account_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(EspoUser::class, 'assigned_user_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class, 'account_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'account_id');
    }

    public function getBillingAddressAttribute(): ?string
    {
        $parts = array_filter([
            $this->billing_address_street,
            $this->crm_billing_district,
            $this->billing_address_city,
            $this->billing_address_state,
            $this->billing_address_postal_code,
            $this->billing_address_country,
        ]);

        return $parts ? implode(', ', $parts) : null;
    }
}
