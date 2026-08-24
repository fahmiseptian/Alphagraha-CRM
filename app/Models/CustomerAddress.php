<?php

namespace App\Models;

use App\Models\Espo\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    protected $table = 'crm_customer_addresses';

    protected $fillable = [
        'account_id',
        'label',
        'contact_name',
        'phone',
        'street',
        'province_code',
        'regency_code',
        'district_code',
        'province',
        'city',
        'district',
        'postal_code',
        'country',
        'is_default_billing',
        'is_default_shipping',
        'sort_order',
    ];

    protected $casts = [
        'is_default_billing' => 'boolean',
        'is_default_shipping' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function line(): string
    {
        return collect([
            $this->street,
            $this->district,
            $this->city,
            $this->province,
            $this->postal_code,
            $this->country,
        ])->filter(fn ($v) => filled($v))->implode(', ');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSnapshot(?string $contactFallback = null, ?string $phoneFallback = null, ?string $email = null): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'contact' => $this->contact_name ?: $contactFallback,
            'phone' => $this->phone ?: $phoneFallback,
            'email' => $email,
            'province' => $this->province,
            'city' => $this->city,
            'district' => $this->district,
            'postal' => $this->postal_code,
            'address' => $this->street,
        ];
    }
}
