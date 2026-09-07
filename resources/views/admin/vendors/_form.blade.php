@php
    $vendor = $vendor ?? new \App\Models\Vendor(['is_active' => true, 'top' => \App\Support\CustomerTop::DAYS_30, 'is_pkp' => true]);
    $brandOptions = $brandOptions ?? collect();
    $selectedBrandIds = collect(old('brand_ids', $vendor->exists ? $vendor->brands->pluck('id')->all() : []))
        ->map(fn ($id) => (string) $id)
        ->all();
    $picRows = old('pics');
    if (! is_array($picRows)) {
        $source = $vendor->exists ? $vendor->pics : collect();
        $picRows = $source->map(fn ($pic) => [
            'id' => $pic->id,
            'name' => $pic->name,
            'job_role' => $pic->job_role,
            'phone' => $pic->phone,
            'email' => $pic->email,
        ])->values()->all();
    }
    $picRows = array_values(array_map(function ($row, $i) {
        $row = is_array($row) ? $row : [];

        return [
            '_uid' => $i + 1,
            'id' => $row['id'] ?? '',
            'name' => $row['name'] ?? '',
            'job_role' => $row['job_role'] ?? '',
            'phone' => $row['phone'] ?? '',
            'email' => $row['email'] ?? '',
        ];
    }, $picRows, array_keys($picRows)));
    if ($picRows === []) {
        $picRows = [['_uid' => 1, 'id' => '', 'name' => '', 'job_role' => '', 'phone' => '', 'email' => '']];
    }
@endphp
<div class="space-y-4">
    <div>
        <label class="crm-label">Nama Perusahaan <span class="text-red-500">*</span></label>
        <input type="text" name="name" value="{{ old('name', $vendor->name) }}" required maxlength="255"
               class="crm-field @error('name') border-red-400 @enderror" placeholder="Contoh: PT. Synnex Metrodata Indonesia">
        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="crm-label">Status Perusahaan</label>
        <input type="text" name="company_status" value="{{ old('company_status', $vendor->company_status) }}" maxlength="100"
               class="crm-field @error('company_status') border-red-400 @enderror" placeholder="Contoh: Distributor">
        @error('company_status')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="crm-label">Brand</label>
        <select name="brand_ids[]" multiple class="select2 select2-search w-full @error('brand_ids') border-red-400 @enderror"
                data-placeholder="— Pilih brand —">
            @foreach ($brandOptions as $brand)
                <option value="{{ $brand->id }}" @selected(in_array((string) $brand->id, $selectedBrandIds, true))>
                    {{ $brand->name }}{{ $brand->is_active ? '' : ' (nonaktif)' }}
                </option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-slate-400">Satu vendor bisa punya banyak brand. Kosongkan jika belum ditentukan.</p>
        @error('brand_ids')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        @error('brand_ids.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="crm-label">TOP <span class="text-red-500">*</span></label>
        <select name="top" required class="crm-field @error('top') border-red-400 @enderror">
            @foreach (\App\Support\CustomerTop::LABELS as $value => $label)
                <option value="{{ $value }}" @selected(old('top', $vendor->topValue()) === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-slate-400">Terms of Payment. Default: TOP 30 hari.</p>
        @error('top')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="hidden" name="is_pkp" value="0">
        <input type="checkbox" name="is_pkp" value="1" @checked(old('is_pkp', $vendor->is_pkp ?? true))
               class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
        PKP (Pengusaha Kena Pajak)
    </label>
    <p class="text-xs text-slate-400 -mt-2">Jika non-PKP, harga vendor di PO default tanpa PPN. Bisa diubah per item di form PO.</p>
    <div>
        <label class="crm-label">Urutan</label>
        <input type="number" name="sort_order" value="{{ old('sort_order', $vendor->sort_order ?? 0) }}" min="0" max="9999"
               class="crm-field @error('sort_order') border-red-400 @enderror">
        <p class="mt-1 text-xs text-slate-400">Angka kecil tampil lebih dulu. Default urut nama.</p>
        @error('sort_order')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $vendor->is_active ?? true))
               class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
        Aktif (muncul di dropdown form)
    </label>

    <div class="border-t border-slate-200 pt-4"
         x-data="{
            pics: {{ \Illuminate\Support\Js::from($picRows) }},
            nextUid: {{ count($picRows) + 1 }},
            addPic() {
                this.pics.push({ _uid: this.nextUid++, id: '', name: '', job_role: '', phone: '', email: '' });
            },
            removePic(i) {
                if (this.pics.length <= 1) {
                    this.pics = [{ _uid: this.nextUid++, id: '', name: '', job_role: '', phone: '', email: '' }];
                    return;
                }
                this.pics.splice(i, 1);
            }
         }">
        <div class="mb-2 flex items-center justify-between gap-2">
            <div>
                <label class="crm-label mb-0">PIC</label>
                <p class="text-xs text-slate-400">Satu vendor bisa punya banyak PIC.</p>
            </div>
            <button type="button" @click="addPic()"
                    class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                <i class="bi bi-plus-lg"></i> Tambah PIC
            </button>
        </div>
        <div class="space-y-3">
            <template x-for="(pic, i) in pics" :key="pic._uid">
                <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                    <input type="hidden" :name="`pics[${i}][id]`" :value="pic.id">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-500" x-text="'PIC ' + (i + 1)"></span>
                        <button type="button" @click="removePic(i)" class="rounded p-1 text-red-500 hover:bg-red-50" title="Hapus PIC">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="crm-label">Nama PIC</label>
                            <input type="text" :name="`pics[${i}][name]`" x-model="pic.name" maxlength="255"
                                   class="crm-field" placeholder="Nama PIC">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="crm-label">Job Role</label>
                            <input type="text" :name="`pics[${i}][job_role]`" x-model="pic.job_role" maxlength="150"
                                   class="crm-field" placeholder="Contoh: Channel Sales">
                        </div>
                        <div>
                            <label class="crm-label">No Telp</label>
                            <input type="text" :name="`pics[${i}][phone]`" x-model="pic.phone" maxlength="50"
                                   class="crm-field" placeholder="0853-5254-4923">
                        </div>
                        <div>
                            <label class="crm-label">Email</label>
                            <input type="email" :name="`pics[${i}][email]`" x-model="pic.email" maxlength="150"
                                   class="crm-field" placeholder="nama@perusahaan.co.id">
                        </div>
                    </div>
                </div>
            </template>
        </div>
        @error('pics')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        @error('pics.*.email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        @error('pics.*.name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="flex items-center gap-2 pt-2">
        <button class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">Save</button>
        <a href="{{ route('vendors.index') }}" class="rounded-lg border border-slate-300 px-5 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Cancel</a>
    </div>
</div>
