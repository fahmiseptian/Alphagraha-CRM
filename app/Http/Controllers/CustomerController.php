<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Espo\Account;
use App\Models\Espo\EspoUser;
use App\Models\WilayahDistrict;
use App\Models\WilayahProvince;
use App\Models\WilayahRegency;
use App\Services\EspoEntityWriter;
use App\Support\PaymentLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    use ScopesToUser;

    public function __construct(
        protected EspoEntityWriter $writer
    ) {}

    public function index(Request $request)
    {
        $search = trim((string) $request->get('q'));
        $type = $request->filled('type') ? $request->get('type') : null;

        $query = $this->scopeAssigned(Account::query())
            ->with(['assignedUser', 'emailAddresses', 'phoneNumbers'])
            ->withCount('opportunities');

        if ($search !== '') {
            $like = '%'.$search.'%';

            $query->where(function ($q) use ($like) {
                $q->where('account.name', 'like', $like)
                    ->orWhere('account.website', 'like', $like)
                    ->orWhere('account.industry', 'like', $like)
                    ->orWhere('account.billing_address_city', 'like', $like)
                    ->orWhere('account.billing_address_street', 'like', $like)
                    ->orWhereHas('emailAddresses', fn ($eq) => $eq->where('name', 'like', $like))
                    ->orWhereHas('phoneNumbers', fn ($pq) => $pq->where('name', 'like', $like))
                    ->orWhereHas('contacts', function ($cq) use ($like) {
                        $cq->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('name', 'like', $like);
                    });
            });

            $query->orderByRaw('CASE WHEN account.name LIKE ? THEN 0 ELSE 1 END', [$like]);
        }

        if ($type) {
            $query->where('account.type', $type);
        }

        $accounts = $query->orderByDesc('account.created_at')->paginate(15)->withQueryString();

        $types = $this->scopeAssigned(Account::query())
            ->whereNotNull('type')->where('type', '<>', '')
            ->distinct()->orderBy('type')->pluck('type');

        return view('customers.index', compact('accounts', 'search', 'type', 'types'));
    }

    public function create()
    {
        $account = new Account([
            'type' => 'Customer',
            'assigned_user_id' => auth()->id(),
        ]);

        return view('customers.create', $this->formData() + compact('account'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $now = Carbon::now()->format('Y-m-d H:i:s');

        $account = new Account();
        $account->id = $this->writer->generateId();
        $account->deleted = 0;
        $account->created_at = $now;
        $account->modified_at = $now;
        $account->created_by_id = auth()->id();
        $this->applyValidatedData($account, $data, isNew: true);
        $account->save();

        $this->writer->syncPrimaryEmail($account->id, 'Account', $data['email'] ?? null);
        $this->writer->syncPrimaryPhone($account->id, 'Account', $data['phone'] ?? null);

        return redirect()->route('customers.show', $account->id)
            ->with('success', 'Customer created successfully.');
    }

    public function edit(string $id)
    {
        $account = $this->scopeAssigned(Account::query())
            ->with(['emailAddresses', 'phoneNumbers'])
            ->findOrFail($id);

        return view('customers.edit', $this->formData() + compact('account'));
    }

    public function update(Request $request, string $id)
    {
        $account = $this->scopeAssigned(Account::query())->findOrFail($id);
        $data = $this->validateData($request);

        $this->applyValidatedData($account, $data);
        $account->modified_at = Carbon::now()->format('Y-m-d H:i:s');
        $account->save();

        $this->writer->syncPrimaryEmail($account->id, 'Account', $data['email'] ?? null);
        $this->writer->syncPrimaryPhone($account->id, 'Account', $data['phone'] ?? null);

        return redirect()->route('customers.show', $account->id)
            ->with('success', 'Customer updated successfully.');
    }

    public function show(string $id)
    {
        $account = $this->scopeAssigned(Account::query())
            ->with(['assignedUser', 'emailAddresses', 'phoneNumbers'])
            ->findOrFail($id);

        $contacts = $account->contacts()->with(['emailAddresses', 'phoneNumbers'])->get();

        $opportunities = $account->opportunities()
            ->with('assignedUser')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $quotations = $account->quotations()->latest()->get();

        $activities = $account->activities()
            ->with('assignee')
            ->orderByRaw('COALESCE(due_at, created_at) DESC')
            ->limit(30)
            ->get();

        return view('customers.show', compact('account', 'contacts', 'opportunities', 'quotations', 'activities'));
    }

    protected function validateData(Request $request): array
    {
        $request->merge([
            'crm_province_code' => $request->filled('crm_province_code') ? $request->input('crm_province_code') : null,
            'crm_regency_code' => $request->filled('crm_regency_code') ? $request->input('crm_regency_code') : null,
            'crm_district_code' => $request->filled('crm_district_code') ? $request->input('crm_district_code') : null,
        ]);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'billing_address_street' => ['nullable', 'string', 'max:255'],
            'crm_province_code' => ['nullable', 'string', 'max:10', Rule::exists('crm_wilayah_provinces', 'code')],
            'crm_regency_code' => ['nullable', 'string', 'max:10', Rule::exists('crm_wilayah_regencies', 'code')],
            'crm_district_code' => ['nullable', 'string', 'max:15', Rule::exists('crm_wilayah_districts', 'code')],
            'billing_address_postal_code' => ['nullable', 'string', 'max:20'],
            'billing_address_country' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'assigned_user_id' => ['nullable', 'string', Rule::exists('user', 'id')->where('deleted', 0)],
        ];

        if (auth()->user()?->canEditPaymentLevel()) {
            $rules['crm_payment_level'] = ['required', Rule::in(PaymentLevel::LEVELS)];
        }

        $data = $request->validate($rules);

        // Isi nama alamat dari master wilayah (state=provinsi, city=kota, district=kecamatan).
        $province = ! empty($data['crm_province_code'])
            ? WilayahProvince::query()->find($data['crm_province_code'])
            : null;
        $regency = ! empty($data['crm_regency_code'])
            ? WilayahRegency::query()->find($data['crm_regency_code'])
            : null;
        $district = ! empty($data['crm_district_code'])
            ? WilayahDistrict::query()->find($data['crm_district_code'])
            : null;

        if ($regency && $province && $regency->province_code !== $province->code) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'crm_regency_code' => 'Kota/kabupaten tidak sesuai dengan provinsi.',
            ]);
        }
        if ($district && $regency && $district->regency_code !== $regency->code) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'crm_district_code' => 'Kecamatan tidak sesuai dengan kota/kabupaten.',
            ]);
        }

        $data['billing_address_state'] = $province?->name;
        $data['billing_address_city'] = $regency?->name;
        $data['crm_billing_district'] = $district?->name;
        $data['billing_address_country'] = $data['billing_address_country'] ?: 'Indonesia';

        return $data;
    }

    protected function applyValidatedData(Account $account, array $data, bool $isNew = false): void
    {
        $account->fill([
            'name' => $data['name'],
            'type' => ($data['type'] ?? null) ?: null,
            'industry' => $data['industry'] ?? null,
            'website' => $data['website'] ?? null,
            'billing_address_street' => $data['billing_address_street'] ?? null,
            'billing_address_city' => $data['billing_address_city'] ?? null,
            'billing_address_state' => $data['billing_address_state'] ?? null,
            'billing_address_country' => $data['billing_address_country'] ?? null,
            'billing_address_postal_code' => $data['billing_address_postal_code'] ?? null,
            'crm_province_code' => $data['crm_province_code'] ?? null,
            'crm_regency_code' => $data['crm_regency_code'] ?? null,
            'crm_district_code' => $data['crm_district_code'] ?? null,
            'crm_billing_district' => $data['crm_billing_district'] ?? null,
            'description' => $data['description'] ?? null,
        ]);

        if (auth()->user()?->canEditPaymentLevel() && isset($data['crm_payment_level'])) {
            $account->crm_payment_level = $data['crm_payment_level'];
        } elseif ($isNew && ! $account->crm_payment_level) {
            $account->crm_payment_level = PaymentLevel::LANCAR;
        }

        if ($this->isAdmin()) {
            $account->assigned_user_id = ($data['assigned_user_id'] ?? null) ?: null;
        } elseif ($isNew) {
            $account->assigned_user_id = auth()->id();
        }
    }

    protected function formData(): array
    {
        return [
            'types' => Account::TYPES,
            'paymentLevels' => PaymentLevel::LABELS,
            'salesUsers' => EspoUser::query()->activeRegular()->orderBy('name')->get(),
            'provinces' => WilayahProvince::query()->orderBy('name')->get(['code', 'name']),
        ];
    }
}
