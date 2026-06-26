<?php

namespace App\Models\Espo;

use App\Models\Activity;
use App\Models\Espo\Concerns\EspoEntity;
use App\Models\Quotation;
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
    ];

    public const TYPES = ['Customer', 'Reseller', 'Partner'];

    public function espoEntityType(): string
    {
        return 'Account';
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
            $this->billing_address_city,
            $this->billing_address_state,
            $this->billing_address_postal_code,
            $this->billing_address_country,
        ]);

        return $parts ? implode(', ', $parts) : null;
    }
}
