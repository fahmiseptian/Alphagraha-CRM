<?php

namespace App\Services;

use App\Models\CustomerAddress;
use App\Models\Espo\Account;
use App\Models\WilayahDistrict;
use App\Models\WilayahProvince;
use App\Models\WilayahRegency;
use Illuminate\Validation\ValidationException;

class CustomerAddressService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function save(Account $account, array $data, ?CustomerAddress $address = null): CustomerAddress
    {
        $resolved = $this->resolveWilayah($data);
        $isFirst = $account->addresses()->doesntExist();
        $address = $address ?: new CustomerAddress(['account_id' => $account->id]);

        $address->fill([
            'account_id' => $account->id,
            'label' => trim((string) ($data['label'] ?? '')) ?: 'Alamat',
            'contact_name' => $this->nullable($data['contact_name'] ?? null),
            'phone' => $this->nullable($data['phone'] ?? null),
            'street' => $this->nullable($data['street'] ?? $data['billing_address_street'] ?? null),
            'province_code' => $resolved['province_code'],
            'regency_code' => $resolved['regency_code'],
            'district_code' => $resolved['district_code'],
            'province' => $resolved['province'],
            'city' => $resolved['city'],
            'district' => $resolved['district'],
            'postal_code' => $this->nullable($data['postal_code'] ?? $data['billing_address_postal_code'] ?? null),
            'country' => $this->nullable($data['country'] ?? $data['billing_address_country'] ?? null) ?: 'Indonesia',
            'is_default_billing' => $isFirst || ! empty($data['is_default_billing']),
            'is_default_shipping' => $isFirst || ! empty($data['is_default_shipping']),
            'sort_order' => (int) ($data['sort_order'] ?? $address->sort_order ?? 0),
        ]);

        $address->save();
        $this->ensureSingleDefaults($account, $address);

        if (! $account->addresses()->where('is_default_billing', true)->exists()) {
            $address->is_default_billing = true;
            $address->save();
            $this->ensureSingleDefaults($account, $address);
        }
        if (! $account->addresses()->where('is_default_shipping', true)->exists()) {
            $address->is_default_shipping = true;
            $address->save();
            $this->ensureSingleDefaults($account, $address);
        }

        if ($address->fresh()->is_default_billing) {
            $this->syncAccountBilling($account, $address->fresh());
        }

        return $address->fresh();
    }

    /**
     * Buat/perbarui alamat utama dari field billing di form customer.
     */
    public function upsertPrimaryFromAccount(Account $account): ?CustomerAddress
    {
        $hasContent = filled($account->billing_address_street)
            || filled($account->billing_address_city)
            || filled($account->billing_address_state)
            || filled($account->crm_billing_district);

        if (! $hasContent) {
            return $account->addresses()->orderByDesc('is_default_billing')->orderBy('sort_order')->first();
        }

        $primary = $account->addresses()
            ->where('is_default_billing', true)
            ->orderBy('id')
            ->first()
            ?: $account->addresses()->orderBy('sort_order')->orderBy('id')->first();

        return $this->save($account, [
            'label' => $primary?->label ?: 'Alamat utama',
            'contact_name' => $primary?->contact_name,
            'phone' => $primary?->phone,
            'street' => $account->billing_address_street,
            'province_code' => $account->crm_province_code,
            'regency_code' => $account->crm_regency_code,
            'district_code' => $account->crm_district_code,
            'postal_code' => $account->billing_address_postal_code,
            'country' => $account->billing_address_country ?: 'Indonesia',
            'is_default_billing' => true,
            'is_default_shipping' => $primary?->is_default_shipping ?? true,
            'sort_order' => $primary?->sort_order ?? 0,
        ], $primary);
    }

    /**
     * Pastikan customer punya minimal 1 alamat (dari billing account jika ada).
     */
    public function ensurePrimaryFromAccount(?Account $account): ?CustomerAddress
    {
        if (! $account) {
            return null;
        }

        $existing = $account->addresses()->orderByDesc('is_default_billing')->orderBy('sort_order')->first();
        if ($existing) {
            return $existing;
        }

        return $this->upsertPrimaryFromAccount($account);
    }

    public function delete(Account $account, CustomerAddress $address): void
    {
        $wasBilling = $address->is_default_billing;
        $wasShipping = $address->is_default_shipping;
        $address->delete();

        $next = $account->addresses()->orderBy('sort_order')->orderBy('id')->first();
        if (! $next) {
            return;
        }

        if ($wasBilling) {
            $next->is_default_billing = true;
        }
        if ($wasShipping) {
            $next->is_default_shipping = true;
        }
        if ($wasBilling || $wasShipping) {
            $next->save();
            $this->ensureSingleDefaults($account, $next);
            if ($next->is_default_billing) {
                $this->syncAccountBilling($account, $next);
            }
        }
    }

    public function syncAccountBilling(Account $account, CustomerAddress $address): void
    {
        $account->fill([
            'billing_address_street' => $address->street,
            'billing_address_city' => $address->city,
            'billing_address_state' => $address->province,
            'billing_address_country' => $address->country ?: 'Indonesia',
            'billing_address_postal_code' => $address->postal_code,
            'crm_province_code' => $address->province_code,
            'crm_regency_code' => $address->regency_code,
            'crm_district_code' => $address->district_code,
            'crm_billing_district' => $address->district,
        ]);
        $account->save();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function optionsForAccount(?Account $account): array
    {
        if (! $account) {
            return [];
        }

        $this->ensurePrimaryFromAccount($account);

        return $account->addresses()
            ->orderByDesc('is_default_billing')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (CustomerAddress $address) => [
                'id' => $address->id,
                'label' => $address->label,
                'line' => $address->line(),
                'contact' => $address->contact_name,
                'phone' => $address->phone,
                'is_default_billing' => $address->is_default_billing,
                'is_default_shipping' => $address->is_default_shipping,
            ])
            ->values()
            ->all();
    }

    protected function ensureSingleDefaults(Account $account, CustomerAddress $keep): void
    {
        if ($keep->is_default_billing) {
            $account->addresses()
                ->where('id', '!=', $keep->id)
                ->where('is_default_billing', true)
                ->update(['is_default_billing' => false]);
        }
        if ($keep->is_default_shipping) {
            $account->addresses()
                ->where('id', '!=', $keep->id)
                ->where('is_default_shipping', true)
                ->update(['is_default_shipping' => false]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{province_code: ?string, regency_code: ?string, district_code: ?string, province: ?string, city: ?string, district: ?string}
     */
    public function resolveWilayah(array $data): array
    {
        $provinceCode = $this->nullable($data['province_code'] ?? $data['crm_province_code'] ?? null);
        $regencyCode = $this->nullable($data['regency_code'] ?? $data['crm_regency_code'] ?? null);
        $districtCode = $this->nullable($data['district_code'] ?? $data['crm_district_code'] ?? null);

        $province = $provinceCode ? WilayahProvince::query()->find($provinceCode) : null;
        $regency = $regencyCode ? WilayahRegency::query()->find($regencyCode) : null;
        $district = $districtCode ? WilayahDistrict::query()->find($districtCode) : null;

        if ($regency && $province && $regency->province_code !== $province->code) {
            throw ValidationException::withMessages([
                'crm_regency_code' => 'Kota/kabupaten tidak sesuai dengan provinsi.',
            ]);
        }
        if ($district && $regency && $district->regency_code !== $regency->code) {
            throw ValidationException::withMessages([
                'crm_district_code' => 'Kecamatan tidak sesuai dengan kota/kabupaten.',
            ]);
        }

        return [
            'province_code' => $province?->code,
            'regency_code' => $regency?->code,
            'district_code' => $district?->code,
            'province' => $province?->name,
            'city' => $regency?->name,
            'district' => $district?->name,
        ];
    }

    protected function nullable(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return filled($value) ? (string) $value : null;
    }
}
