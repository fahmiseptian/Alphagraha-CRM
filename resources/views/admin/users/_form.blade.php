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
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Full Name <span class="text-red-500">*</span></label>
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
        @if ($lockRole ?? false)
            <input type="hidden" name="role" value="{{ old('role', $role ?? $user->role) }}">
            <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                {{ \App\Models\User::ROLES[old('role', $role ?? $user->role)] ?? ucfirst($role ?? $user->role) }}
            </p>
        @else
            <select name="role" class="select2 w-full">
                @foreach (\App\Models\User::ROLES as $key => $label)
                    <option value="{{ $key }}" @selected(old('role', $role ?? $user->role) === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-400">Ubah role akan memindahkan user ke submenu role tersebut.</p>
        @endif
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Sales Code <span class="text-xs font-normal text-slate-400">(wajib untuk Sales)</span></label>
        <input type="text" name="sales_code" value="{{ old('sales_code', $user->profile?->sales_code) }}"
               placeholder="Contoh: KA" maxlength="20"
               class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm uppercase focus:border-brand-500 focus:ring-2 focus:ring-brand-200 @error('sales_code') border-red-400 @enderror">
        @error('sales_code')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @else
            <p class="mt-1 text-xs text-slate-400">Kode unik sales untuk nomor QO otomatis (mis. 0002/<strong>KA</strong>/QO/VII/26).</p>
        @enderror
    </div>
    @if (($role ?? $user->role) === \App\Models\User::ROLE_SALES || old('role') === \App\Models\User::ROLE_SALES)
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Sales Target (Margin) <span class="text-red-500">*</span></label>
            <input type="text" inputmode="decimal" name="sales_target" required data-crm-number data-decimals="0"
                   value="{{ old('sales_target', $user->profile?->resolvedSalesTarget()) }}"
                   class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm tabular-nums focus:border-brand-500 focus:ring-2 focus:ring-brand-200 @error('sales_target') border-red-400 @enderror">
            <p class="mt-1 text-xs text-slate-400">Dihitung dari total margin Closed Won, bukan amount.</p>
            @error('sales_target')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @else
                <p class="mt-1 text-xs text-slate-400">Target penjualan Closed Won. Contoh: 1.000.000.000 = Rp 1 miliar.</p>
            @enderror
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Periode Target <span class="text-red-500">*</span></label>
            <select name="sales_target_period" required
                    class="select2 w-full" data-placeholder="— Pilih periode —">
                @foreach (\App\Models\UserProfile::TARGET_PERIODS as $periodKey => $periodLabel)
                    <option value="{{ $periodKey }}" @selected(old('sales_target_period', $user->profile?->resolvedSalesTargetPeriod()) === $periodKey)>
                        {{ $periodLabel }}
                    </option>
                @endforeach
            </select>
            @error('sales_target_period')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @else
                <p class="mt-1 text-xs text-slate-400">Target selalu dihitung dari awal tahun, per segmen sesuai periode.</p>
            @enderror
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Tenggat Waktu <span class="text-xs font-normal text-slate-400">(opsional)</span></label>
            <input type="date" name="sales_target_deadline"
                   value="{{ old('sales_target_deadline', optional($user->profile?->sales_target_deadline)->format('Y-m-d')) }}"
                   class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200 @error('sales_target_deadline') border-red-400 @enderror">
            @error('sales_target_deadline')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @else
                <p class="mt-1 text-xs text-slate-400">
                    Kosongkan agar otomatis dari awal tahun.
                    Contoh: 3 bulan → Maret, Juni, September, Desember · 6 bulan → Juni, Desember.
                    @if ($user->profile)
                        Sekarang: <strong>{{ $user->profile->salesTargetAutoDeadlineScheduleLabel() }}</strong>
                        (tenggat berjalan {{ $user->profile->resolvedSalesTargetDeadline()->format('d M Y') }}).
                    @endif
                </p>
            @enderror
        </div>
    @endif
    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Password @if (($mode ?? 'create') === 'create')<span class="text-red-500">*</span>@else<span class="text-xs font-normal text-slate-400">(leave blank to keep unchanged)</span>@endif</label>
        <input type="password" name="password" {{ ($mode ?? 'create') === 'create' ? 'required' : '' }}
               class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Confirm Password</label>
        <input type="password" name="password_confirmation"
               class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
    </div>
</div>

<label class="flex items-center gap-2 text-sm text-slate-700">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))
           class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
    Active account
</label>

<div class="flex items-center gap-2 border-t border-slate-100 pt-4">
    <button class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">Save</button>
    <a href="{{ route('users.index', ['role' => $role ?? $user->role]) }}" class="rounded-lg border border-slate-300 px-5 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Cancel</a>
</div>
