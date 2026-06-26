@extends('layouts.app')
@section('title', 'Profile')

@section('content')
<div class="mb-4">
    <h2 class="text-lg font-semibold text-slate-800">Profile & Security</h2>
    <p class="text-sm text-slate-500">Kelola password dan tanda tangan digital untuk penawaran</p>
</div>

<div class="max-w-2xl space-y-4">
    <x-card title="Tanda Tangan Digital">
        @if (auth()->user()->isSales())
            <p class="mb-4 text-sm text-slate-600">
                Tanda tangan ini otomatis muncul di penawaran (PDF) sesuai sales yang membuat quotation.
            </p>

            @if ($user->hasDigitalSignature())
                <div class="mb-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400">Preview TTD</p>
                    <img src="{{ $user->profile?->signatureUrl() }}" alt="Signature" class="max-h-24">
                </div>
                <form method="POST" action="{{ route('profile.signature.destroy') }}" class="mb-4" onsubmit="return confirm('Hapus tanda tangan digital?')">
                    @csrf @method('DELETE')
                    <x-btn type="submit" variant="ghost" icon="bi-trash">Hapus TTD</x-btn>
                </form>
            @else
                <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <i class="bi bi-exclamation-triangle"></i>
                    Anda belum mengunggah tanda tangan digital. Upload di bawah agar bisa generate PDF penawaran.
                </div>
            @endif

            <form method="POST" action="{{ route('profile.signature.store') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div>
                    <label class="crm-label">Upload TTD (PNG/JPG, max 2MB)</label>
                    <input type="file" name="signature" accept="image/png,image/jpeg,image/webp" required class="crm-field">
                </div>
                <x-btn type="submit" variant="primary" icon="bi-upload">Upload Tanda Tangan</x-btn>
            </form>
        @else
            <p class="text-sm text-slate-500">Tanda tangan digital hanya diperlukan untuk akun sales.</p>
        @endif
    </x-card>

    <x-card>
        <form method="POST" action="{{ route('profile.update') }}" class="space-y-5">
            @csrf @method('PUT')

            <div class="flex items-center gap-4 border-b border-slate-100 pb-5">
                <span class="flex h-16 w-16 items-center justify-center rounded-full bg-brand-100 text-xl font-bold text-brand-700">{{ initials($user->name) }}</span>
                <div>
                    <p class="font-semibold text-slate-800">{{ $user->name }}</p>
                    <x-badge :color="$user->role === 'admin' ? 'purple' : 'blue'">{{ ucfirst($user->role) }}</x-badge>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Name</label>
                    <input type="text" value="{{ $user->display_name }}" disabled
                           class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 px-3 text-sm text-slate-500">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Email</label>
                    <input type="email" value="{{ $user->email }}" disabled
                           class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 px-3 text-sm text-slate-500">
                </div>
            </div>
            <p class="-mt-2 text-xs text-slate-400"><i class="bi bi-info-circle"></i> Name & email are managed in EspoCRM.</p>

            <div class="border-t border-slate-100 pt-5">
                <p class="mb-3 text-sm font-medium text-slate-700">Change Password</p>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <div>
                        <label class="mb-1.5 block text-sm text-slate-600">Current Password</label>
                        <input type="password" name="current_password" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm text-slate-600">New Password</label>
                        <input type="password" name="password" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm text-slate-600">Confirm</label>
                        <input type="password" name="password_confirmation" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-100 pt-4">
                <button class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">Save Changes</button>
            </div>
        </form>
    </x-card>
</div>
@endsection
