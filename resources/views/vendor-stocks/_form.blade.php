@php
    $stock = $stock ?? new \App\Models\VendorStock(['status' => 'ready', 'is_active' => true]);
    $vendors = $vendors ?? collect();
@endphp
<div class="space-y-4">
    <div>
        <label class="crm-label">Vendor <span class="text-red-500">*</span></label>
        <select name="vendor_id" required class="select2 select2-search w-full @error('vendor_id') border-red-400 @enderror"
                data-placeholder="— Pilih vendor —">
            <option value="">— Pilih vendor —</option>
            @foreach ($vendors as $vendor)
                <option value="{{ $vendor->id }}" @selected((string) old('vendor_id', $stock->vendor_id) === (string) $vendor->id)>
                    {{ $vendor->name }}
                </option>
            @endforeach
        </select>
        @error('vendor_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="crm-label">Nama produk <span class="text-red-500">*</span></label>
        <input type="text" name="product_name" value="{{ old('product_name', $stock->product_name) }}" required maxlength="255"
               class="crm-field @error('product_name') border-red-400 @enderror" placeholder="Contoh: Monitor 24 inch">
        @error('product_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="crm-label">SKU</label>
            <input type="text" name="sku" value="{{ old('sku', $stock->sku) }}" maxlength="100"
                   class="crm-field @error('sku') border-red-400 @enderror" placeholder="Opsional">
            @error('sku')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="crm-label">Status <span class="text-red-500">*</span></label>
            <select name="status" required class="select2 w-full @error('status') border-red-400 @enderror">
                @foreach (\App\Models\VendorStock::STATUSES as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $stock->status) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('status')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
    <div>
        <label class="crm-label">Harga (excl.) <span class="text-red-500">*</span></label>
        <input type="text" inputmode="decimal" name="price" data-crm-number data-decimals="0"
               value="{{ old('price', $stock->price ?? 0) }}" required
               class="crm-field text-right tabular-nums @error('price') border-red-400 @enderror" placeholder="0">
        @error('price')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="crm-label">Catatan</label>
        <textarea name="note" rows="3" maxlength="2000"
                  class="crm-field @error('note') border-red-400 @enderror"
                  placeholder="Catatan ketersediaan, lead time, dll.">{{ old('note', $stock->note) }}</textarea>
        @error('note')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $stock->is_active ?? true))
               class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
        Aktif (muncul sebagai opsi perbandingan di Purchase Order)
    </label>

    <div class="flex items-center gap-2 pt-2">
        <button class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">Save</button>
        <a href="{{ route('vendor-stocks.index') }}" class="rounded-lg border border-slate-300 px-5 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Cancel</a>
    </div>
</div>
