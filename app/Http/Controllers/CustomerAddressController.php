<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\CustomerAddress;
use App\Models\Espo\Account;
use App\Services\CustomerAddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerAddressController extends Controller
{
    use ScopesToUser;

    public function __construct(protected CustomerAddressService $addresses) {}

    public function store(Request $request, string $accountId): RedirectResponse
    {
        $this->authorizeManage();
        $account = $this->scopeAssigned(Account::query())->findOrFail($accountId);
        $data = $this->validated($request);

        $this->addresses->save($account, $data);

        return redirect()
            ->route('customers.show', $account->id)
            ->with('success', 'Alamat customer berhasil ditambah.');
    }

    public function update(Request $request, string $accountId, CustomerAddress $address): RedirectResponse
    {
        $this->authorizeManage();
        $account = $this->scopeAssigned(Account::query())->findOrFail($accountId);
        abort_unless((string) $address->account_id === (string) $account->id, 404);

        $data = $this->validated($request);
        $this->addresses->save($account, $data, $address);

        return redirect()
            ->route('customers.show', $account->id)
            ->with('success', 'Alamat customer berhasil diperbarui.');
    }

    public function destroy(string $accountId, CustomerAddress $address): RedirectResponse
    {
        $this->authorizeManage();
        $account = $this->scopeAssigned(Account::query())->findOrFail($accountId);
        abort_unless((string) $address->account_id === (string) $account->id, 404);

        $this->addresses->delete($account, $address);

        return redirect()
            ->route('customers.show', $account->id)
            ->with('success', 'Alamat customer berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $request->merge([
            'crm_province_code' => $request->filled('crm_province_code') ? $request->input('crm_province_code') : null,
            'crm_regency_code' => $request->filled('crm_regency_code') ? $request->input('crm_regency_code') : null,
            'crm_district_code' => $request->filled('crm_district_code') ? $request->input('crm_district_code') : null,
            'is_default_billing' => $request->boolean('is_default_billing'),
            'is_default_shipping' => $request->boolean('is_default_shipping'),
        ]);

        return $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'street' => ['required', 'string', 'max:500'],
            'crm_province_code' => ['nullable', 'string', 'max:10', Rule::exists('crm_wilayah_provinces', 'code')],
            'crm_regency_code' => ['nullable', 'string', 'max:10', Rule::exists('crm_wilayah_regencies', 'code')],
            'crm_district_code' => ['nullable', 'string', 'max:15', Rule::exists('crm_wilayah_districts', 'code')],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'is_default_billing' => ['boolean'],
            'is_default_shipping' => ['boolean'],
        ]);
    }

    protected function authorizeManage(): void
    {
        if (! auth()->user()?->canManageCustomerAddresses()) {
            abort(403, 'Anda tidak memiliki akses untuk mengelola alamat customer.');
        }
    }
}
