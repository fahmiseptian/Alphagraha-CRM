<form method="POST" action="{{ $action }}" class="space-y-5"
      x-data="customerAddressForm({{ \Illuminate\Support\Js::from([
          'provinceCode' => old('crm_province_code', $account->crm_province_code),
          'regencyCode' => old('crm_regency_code', $account->crm_regency_code),
          'districtCode' => old('crm_district_code', $account->crm_district_code),
          'provincesUrl' => route('wilayah.provinces'),
          'regenciesUrl' => route('wilayah.regencies'),
          'districtsUrl' => route('wilayah.districts'),
          'initialProvinces' => ($provinces ?? collect())->map(fn ($p) => ['code' => $p->code, 'name' => $p->name])->values()->all(),
      ]) }})">
    @csrf
    @if (($method ?? 'POST') === 'PUT')@method('PUT')@endif

    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label class="crm-label">Customer Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $account->name) }}" required
                   class="crm-field">
        </div>
        <div>
            <label class="crm-label">Type</label>
            <select name="type" class="select2 w-full" data-placeholder="— None —">
                <option value="">— None —</option>
                @foreach ($types as $t)
                    <option value="{{ $t }}" @selected(old('type', $account->type) === $t)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="crm-label">Industry <span class="text-red-500">*</span></label>
            <select name="industry" class="select2 w-full @error('industry') border-red-400 @enderror" required data-placeholder="— Pilih industri —">
                <option value="">— Pilih industri —</option>
                @foreach ($industries ?? [] as $industry)
                    <option value="{{ $industry->name }}" @selected(old('industry', $account->industry) === $industry->name)>{{ $industry->name }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-400">Wajib sesuai master industri. Jika belum ada, hubungi Superadmin.</p>
            @error('industry')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="crm-label">Email</label>
            <input type="email" name="email" value="{{ old('email', $account->email ?? '') }}"
                   class="crm-field">
        </div>
        <div>
            <label class="crm-label">Phone</label>
            <input type="text" name="phone" value="{{ old('phone', $account->phone ?? '') }}"
                   class="crm-field">
        </div>
        <div class="sm:col-span-2">
            <label class="crm-label">Website</label>
            <input type="text" name="website" value="{{ old('website', $account->website) }}"
                   class="crm-field">
        </div>
        <div class="sm:col-span-2">
            <label class="crm-label">Street Address</label>
            <textarea name="billing_address_street" rows="3" class="crm-field"
                      placeholder="Jl. ...">{{ old('billing_address_street', $account->billing_address_street) }}</textarea>
            <p class="mt-1 text-xs text-slate-400">Alamat utama (default billing). Tambahan alamat cabang/gudang dikelola di halaman detail customer.</p>
        </div>
        <div>
            <label class="crm-label">Provinsi</label>
            <select name="crm_province_code" class="select2 select2-search w-full" data-placeholder="— Pilih provinsi —">
                <option value="">— Pilih provinsi —</option>
            </select>
            <p x-show="provinces.length === 0" class="mt-1 text-xs text-amber-600">Master wilayah belum di-sync. Minta Superadmin: Settings → Wilayah.</p>
        </div>
        <div>
            <label class="crm-label">Kota / Kabupaten</label>
            <select name="crm_regency_code" class="select2 select2-search w-full" data-placeholder="— Pilih kota/kab —"
                    :disabled="!provinceCode || loadingRegencies">
                <option value="">— Pilih kota/kab —</option>
            </select>
            <p x-show="loadingRegencies" class="mt-1 text-xs text-slate-400">Memuat kota…</p>
        </div>
        <div>
            <label class="crm-label">Kecamatan</label>
            <select name="crm_district_code" class="select2 select2-search w-full" data-placeholder="— Pilih kecamatan —"
                    :disabled="!regencyCode || loadingDistricts">
                <option value="">— Pilih kecamatan —</option>
            </select>
            <p x-show="loadingDistricts" class="mt-1 text-xs text-slate-400">Memuat kecamatan…</p>
            <p x-show="districtError" class="mt-1 text-xs text-red-600" x-text="districtError"></p>
        </div>
        <div>
            <label class="crm-label">Postal Code</label>
            <input type="text" name="billing_address_postal_code" value="{{ old('billing_address_postal_code', $account->billing_address_postal_code) }}"
                   class="crm-field">
        </div>
        <div>
            <label class="crm-label">Country</label>
            <input type="text" name="billing_address_country" value="{{ old('billing_address_country', $account->billing_address_country ?: 'Indonesia') }}"
                   class="crm-field">
        </div>
        @if (auth()->user()->isAdmin())
            <div class="sm:col-span-2">
                <label class="crm-label">Assign to Sales</label>
                <select name="assigned_user_id" class="select2 w-full" data-placeholder="— Unassigned —">
                    <option value="">— Unassigned —</option>
                    @foreach ($salesUsers as $u)
                        <option value="{{ $u->id }}" @selected(old('assigned_user_id', $account->assigned_user_id) === $u->id)>{{ $u->display_name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if (auth()->user()->canEditPaymentLevel())
            <div>
                <label class="crm-label">Level Pembayaran <span class="text-red-500">*</span></label>
                <select name="crm_payment_level" class="select2 w-full" required data-placeholder="— Pilih level —">
                    @foreach ($paymentLevels as $value => $label)
                        <option value="{{ $value }}" @selected(old('crm_payment_level', $account->crm_payment_level ?: 'lancar') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-400">Hanya Finance / Superadmin. Suspend = tidak boleh membuat Quotation.</p>
            </div>
        @else
            <div>
                <label class="crm-label">Level Pembayaran</label>
                <p class="py-2 text-sm font-medium text-slate-700">{{ $account->paymentLevelLabel() }}</p>
            </div>
        @endif
        <div>
            <label class="crm-label">TOP <span class="text-red-500">*</span></label>
            <select name="crm_top" class="select2 w-full" required data-placeholder="— Pilih TOP —">
                @foreach ($topOptions as $value => $label)
                    <option value="{{ $value }}" @selected(old('crm_top', $account->top()) === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-400">Terms of Payment. Default: CBD.</p>
        </div>
        <div class="sm:col-span-2">
            <label class="crm-label">Description</label>
            <textarea name="description" rows="3" class="crm-field">{{ old('description', $account->description) }}</textarea>
        </div>
    </div>

    <div class="flex items-center gap-2 border-t border-slate-100 pt-4">
        <x-btn type="submit" icon="bi-save">Save</x-btn>
        <x-btn href="{{ $cancelUrl }}" variant="secondary">Cancel</x-btn>
    </div>
</form>

<script>
    function customerAddressForm(config) {
        return {
            provinceCode: config.provinceCode || '',
            regencyCode: config.regencyCode || '',
            districtCode: config.districtCode || '',
            provinces: config.initialProvinces || [],
            regencies: [],
            districts: [],
            loadingRegencies: false,
            loadingDistricts: false,
            districtError: '',
            _syncingSelects: false,
            async init() {
                if (!this.provinces.length) {
                    try {
                        const res = await fetch(config.provincesUrl, { headers: { 'Accept': 'application/json' } });
                        const json = await res.json();
                        this.provinces = json.data || [];
                    } catch (e) {}
                }
                if (this.provinceCode) {
                    await this.fetchRegencies();
                }
                if (this.regencyCode) {
                    await this.fetchDistricts();
                }

                this.$nextTick(() => {
                    if (!window.CrmSelect2) return;
                    CrmSelect2.init(this.$root);
                    this._syncingSelects = true;
                    this.refreshProvinceSelect();
                    this.refreshRegencySelect();
                    this.refreshDistrictSelect();
                    this._syncingSelects = false;
                });
            },
            provinceEl() {
                return this.$root.querySelector('[name="crm_province_code"]');
            },
            regencyEl() {
                return this.$root.querySelector('[name="crm_regency_code"]');
            },
            districtEl() {
                return this.$root.querySelector('[name="crm_district_code"]');
            },
            refreshProvinceSelect() {
                const el = this.provinceEl();
                if (!el || !window.CrmSelect2) return;
                CrmSelect2.setOptions(
                    el,
                    this.provinces.map(p => ({ id: p.code, name: p.name })),
                    this.provinceCode,
                    '— Pilih provinsi —'
                );
                CrmSelect2.bindAlpine(el, this, 'provinceCode', () => {
                    if (this._syncingSelects) return;
                    this.onProvinceChange();
                });
            },
            refreshRegencySelect() {
                const el = this.regencyEl();
                if (!el || !window.CrmSelect2) return;
                el.disabled = !this.provinceCode || this.loadingRegencies;
                CrmSelect2.setOptions(
                    el,
                    this.regencies.map(r => ({ id: r.code, name: r.name })),
                    this.regencyCode,
                    this.provinceCode ? '— Pilih kota/kab —' : '— Pilih provinsi dulu —'
                );
                CrmSelect2.bindAlpine(el, this, 'regencyCode', () => {
                    if (this._syncingSelects) return;
                    this.onRegencyChange();
                });
            },
            refreshDistrictSelect() {
                const el = this.districtEl();
                if (!el || !window.CrmSelect2) return;
                el.disabled = !this.regencyCode || this.loadingDistricts;
                CrmSelect2.setOptions(
                    el,
                    this.districts.map(d => ({ id: d.code, name: d.name })),
                    this.districtCode,
                    this.regencyCode ? '— Pilih kecamatan —' : '— Pilih kota/kab dulu —'
                );
                CrmSelect2.bindAlpine(el, this, 'districtCode');
            },
            async onProvinceChange() {
                this.regencyCode = '';
                this.districtCode = '';
                this.regencies = [];
                this.districts = [];
                this.districtError = '';
                this.refreshRegencySelect();
                this.refreshDistrictSelect();
                if (this.provinceCode) {
                    await this.loadRegencies(true);
                }
            },
            async onRegencyChange() {
                this.districtCode = '';
                this.districts = [];
                this.districtError = '';
                this.refreshDistrictSelect();
                if (this.regencyCode) {
                    await this.loadDistricts(true);
                }
            },
            async fetchRegencies() {
                this.loadingRegencies = true;
                try {
                    const url = config.regenciesUrl + '?province=' + encodeURIComponent(this.provinceCode);
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const json = await res.json();
                    this.regencies = json.data || [];
                } catch (e) {
                    this.regencies = [];
                } finally {
                    this.loadingRegencies = false;
                }
            },
            async fetchDistricts() {
                this.loadingDistricts = true;
                this.districtError = '';
                try {
                    const url = config.districtsUrl + '?regency=' + encodeURIComponent(this.regencyCode);
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const json = await res.json();
                    if (!res.ok) {
                        this.districtError = json.error || 'Gagal memuat kecamatan';
                        this.districts = [];
                    } else {
                        this.districts = json.data || [];
                    }
                } catch (e) {
                    this.districts = [];
                    this.districtError = 'Gagal memuat kecamatan';
                } finally {
                    this.loadingDistricts = false;
                }
            },
            async loadRegencies(reset) {
                await this.fetchRegencies();
                if (reset) this.regencyCode = '';
                this.refreshRegencySelect();
            },
            async loadDistricts(reset) {
                await this.fetchDistricts();
                if (reset) this.districtCode = '';
                this.refreshDistrictSelect();
            },
        };
    }
</script>
