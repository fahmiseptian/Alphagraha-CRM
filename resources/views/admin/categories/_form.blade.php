@php
    $category = $category ?? new \App\Models\Category(['is_active' => true]);
@endphp
<div class="space-y-4">
    <div>
        <label class="crm-label">Nama Category <span class="text-red-500">*</span></label>
        <input type="text" name="name" value="{{ old('name', $category->name) }}" required maxlength="255"
               class="crm-field @error('name') border-red-400 @enderror" placeholder="Contoh: Laptop">
        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="crm-label">Urutan</label>
        <input type="number" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}" min="0" max="9999"
               class="crm-field @error('sort_order') border-red-400 @enderror">
        <p class="mt-1 text-xs text-slate-400">Angka kecil tampil lebih dulu. Default urut nama.</p>
        @error('sort_order')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true))
               class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
        Aktif (muncul di dropdown form)
    </label>
    <div class="flex items-center gap-2 pt-2">
        <button class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">Save</button>
        <a href="{{ route('categories.index') }}" class="rounded-lg border border-slate-300 px-5 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Cancel</a>
    </div>
</div>
