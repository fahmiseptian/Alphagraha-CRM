@php
    $canManageAddresses = auth()->user()->canManageCustomerAddresses();
    $editingAddressId = old('editing_address_id');
    $openFormOnError = $errors->any() && $errors->hasAny([
        'label', 'street', 'contact_name', 'phone', 'crm_province_code', 'crm_regency_code', 'crm_district_code', 'postal_code', 'country',
    ]);
    $addressFormCfg = [
        'storeUrl' => route('customers.addresses.store', $account->id),
        'updateUrlTemplate' => route('customers.addresses.update', [$account->id, '__ID__']),
        'editingId' => $editingAddressId,
        'openOnError' => $openFormOnError,
        'provinceCode' => old('crm_province_code', ''),
        'regencyCode' => old('crm_regency_code', ''),
        'districtCode' => old('crm_district_code', ''),
        'provincesUrl' => route('wilayah.provinces'),
        'regenciesUrl' => route('wilayah.regencies'),
        'districtsUrl' => route('wilayah.districts'),
        'initialProvinces' => ($provinces ?? collect())->map(fn ($p) => ['code' => $p->code, 'name' => $p->name])->values()->all(),
        'addresses' => ($addresses ?? collect())->map(fn ($a) => [
            'id' => $a->id,
            'label' => $a->label,
            'contact_name' => $a->contact_name,
            'phone' => $a->phone,
            'street' => $a->street,
            'province_code' => $a->province_code,
            'regency_code' => $a->regency_code,
            'district_code' => $a->district_code,
            'postal_code' => $a->postal_code,
            'country' => $a->country,
            'is_default_billing' => $a->is_default_billing,
            'is_default_shipping' => $a->is_default_shipping,
        ])->values()->all(),
    ];
@endphp

<x-card title="Alamat">
    <div x-data="customerAddressManager({{ \Illuminate\Support\Js::from($addressFormCfg) }})" x-init="init()">
        @if (($addresses ?? collect())->count())
            <ul class="space-y-3">
                @foreach ($addresses as $address)
                    <li class="rounded-lg border border-slate-100 p-3">
                        <div class="flex items-start gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                                <i class="bi bi-geo-alt"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <p class="text-sm font-medium text-slate-800">{{ $address->label }}</p>
                                    @if ($address->is_default_billing)
                                        <x-badge color="brand">Billing</x-badge>
                                    @endif
                                    @if ($address->is_default_shipping)
                                        <x-badge color="slate">Shipping</x-badge>
                                    @endif
                                </div>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $address->line() ?: '—' }}</p>
                                @if ($address->contact_name || $address->phone)
                                    <p class="mt-0.5 text-xs text-slate-400">
                                        {{ $address->contact_name ?: '—' }}
                                        @if ($address->phone)
                                            &middot; {{ $address->phone }}
                                        @endif
                                    </p>
                                @endif
                            </div>
                            @if ($canManageAddresses)
                                <div class="flex shrink-0 items-center gap-1">
                                    <button type="button" @click="startEdit({{ $address->id }})" class="crm-icon-btn" title="Edit alamat">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST"
                                          action="{{ route('customers.addresses.destroy', [$account->id, $address]) }}"
                                          onsubmit="return confirm('Hapus alamat ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="crm-icon-btn text-red-500 hover:bg-red-50" title="Hapus alamat">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-sm text-slate-400">Belum ada alamat. Tambah alamat untuk billing &amp; shipping Sales Order.</p>
        @endif

        @if ($canManageAddresses)
            <div class="mt-4 border-t border-slate-100 pt-4" x-show="!formOpen">
                <button type="button" @click="startCreate()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                    <i class="bi bi-plus-lg"></i> Tambah alamat
                </button>
            </div>

            <form x-show="formOpen" x-cloak
                  method="POST"
                  :action="formAction"
                  class="mt-4 space-y-3 border-t border-slate-100 pt-4">
                @csrf
                <template x-if="editingId">
                    <input type="hidden" name="_method" value="PUT">
                </template>
                <input type="hidden" name="editing_address_id" :value="editingId || ''">

                @if ($openFormOnError)
                    <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                        <ul class="list-inside list-disc space-y-1">
                            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="crm-label">Label <span class="text-red-500">*</span></label>
                        <input type="text" name="label" x-model="form.label" required maxlength="100" class="crm-field" placeholder="Kantor, Gudang, Cabang…">
                    </div>
                    <div>
                        <label class="crm-label">Kontak di alamat ini</label>
                        <input type="text" name="contact_name" x-model="form.contact_name" maxlength="150" class="crm-field">
                    </div>
                    <div>
                        <label class="crm-label">Telepon</label>
                        <input type="text" name="phone" x-model="form.phone" maxlength="50" class="crm-field">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="crm-label">Alamat <span class="text-red-500">*</span></label>
                        <textarea name="street" x-model="form.street" rows="2" required maxlength="500" class="crm-field" placeholder="Jl. …"></textarea>
                    </div>
                    <div>
                        <label class="crm-label">Provinsi</label>
                        <select name="crm_province_code" class="select2 select2-search w-full" data-placeholder="— Pilih provinsi —">
                            <option value="">— Pilih provinsi —</option>
                        </select>
                    </div>
                    <div>
                        <label class="crm-label">Kota / Kabupaten</label>
                        <select name="crm_regency_code" class="select2 select2-search w-full" data-placeholder="— Pilih kota/kab —">
                            <option value="">— Pilih kota/kab —</option>
                        </select>
                    </div>
                    <div>
                        <label class="crm-label">Kecamatan</label>
                        <select name="crm_district_code" class="select2 select2-search w-full" data-placeholder="— Pilih kecamatan —">
                            <option value="">— Pilih kecamatan —</option>
                        </select>
                        <p class="mt-1 text-xs text-red-600" x-show="districtError" x-cloak x-text="districtError"></p>
                    </div>
                    <div>
                        <label class="crm-label">Kode pos</label>
                        <input type="text" name="postal_code" x-model="form.postal_code" maxlength="20" class="crm-field">
                    </div>
                    <div>
                        <label class="crm-label">Negara</label>
                        <input type="text" name="country" x-model="form.country" maxlength="100" class="crm-field">
                    </div>
                    <div class="sm:col-span-2 flex flex-wrap gap-4 text-sm text-slate-600">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="is_default_billing" value="1" x-model="form.is_default_billing">
                            Default billing
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="is_default_shipping" value="1" x-model="form.is_default_shipping">
                            Default shipping
                        </label>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <x-btn type="submit" variant="primary" icon="bi-save">
                        <span x-text="editingId ? 'Simpan alamat' : 'Tambah alamat'"></span>
                    </x-btn>
                    <button type="button" @click="cancelForm()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Batal</button>
                </div>
            </form>
        @endif
    </div>
</x-card>
