@csrf
@if (($mode ?? 'create') === 'edit')
    @method('PUT')
@endif

@if ($errors->any())
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <ul class="list-inside list-disc space-y-1">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Nama Lengkap <span class="text-red-500">*</span></label>
        <input type="text" name="name" value="{{ old('name', $user->display_name === $user->user_name ? '' : $user->display_name) }}" required
               class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Username <span class="text-red-500">*</span></label>
        <input type="text" name="user_name" value="{{ old('user_name', $user->user_name) }}" required
               class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Email</label>
        <input type="email" name="email" value="{{ old('email', $user->email) }}"
               class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Role <span class="text-red-500">*</span></label>
        <select name="role" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
            <option value="sales" @selected(old('role', $user->role) === 'sales')>Sales (regular)</option>
            <option value="admin" @selected(old('role', $user->role) === 'admin')>Administrator (admin)</option>
        </select>
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Kata Sandi @if (($mode ?? 'create') === 'create')<span class="text-red-500">*</span>@else<span class="text-xs font-normal text-slate-400">(kosongkan bila tidak diubah)</span>@endif</label>
        <input type="password" name="password" {{ ($mode ?? 'create') === 'create' ? 'required' : '' }}
               class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Konfirmasi Kata Sandi</label>
        <input type="password" name="password_confirmation"
               class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
    </div>
</div>

<label class="flex items-center gap-2 text-sm text-slate-700">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))
           class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
    Akun aktif
</label>

<div class="flex items-center gap-2 border-t border-slate-100 pt-4">
    <button class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">Simpan</button>
    <a href="{{ route('users.index') }}" class="rounded-lg border border-slate-300 px-5 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Batal</a>
</div>
