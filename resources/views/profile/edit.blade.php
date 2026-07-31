@extends('layouts.app')
@section('title', 'Profile')

@section('content')
<div class="mb-4">
    <h2 class="text-lg font-semibold text-slate-800">Profile & Security</h2>
    <p class="text-sm text-slate-500">Kelola password dan tanda tangan digital untuk penawaran</p>
</div>

<div class="max-w-3xl space-y-4">
    <x-card title="Tanda Tangan Digital">
        @if (auth()->user()->isSales() || auth()->user()->isAdmin())
            <p class="mb-4 text-sm text-slate-600">
                Upload <strong>3 tanda tangan</strong> sesuai perusahaan. Saat generate PDF Quotation,
                sistem memakai TTD yang cocok dengan kategori perusahaan template.
            </p>

            @php
                $missing = $user->missingSignatureLabels();
            @endphp
            @if (count($missing) > 0)
                <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <i class="bi bi-exclamation-triangle"></i>
                    Belum lengkap:
                    <strong>{{ implode(', ', $missing) }}</strong>.
                    Upload semua agar preview/PDF penawaran tidak terblokir.
                </div>
            @endif

            <div class="space-y-4">
                @foreach ($signatureCompanies as $key => $label)
                    @php
                        $has = (bool) $user->profile?->hasSignatureFor($key);
                        $url = $user->profile?->signatureUrl($key);
                    @endphp
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold text-slate-800">{{ $label }}</p>
                                <p class="text-xs text-slate-400">Kode: {{ strtoupper($key) }}</p>
                            </div>
                            @if ($has)
                                <span class="rounded-full bg-green-50 px-2 py-0.5 text-[11px] font-semibold text-green-700">Sudah diunggah</span>
                            @else
                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700">Belum ada</span>
                            @endif
                        </div>

                        @if ($has && $url)
                            <div class="mb-3 rounded-lg border border-slate-100 bg-slate-50 p-3">
                                <img src="{{ $url }}" alt="TTD {{ $label }}" class="max-h-20">
                            </div>
                            <form method="POST" action="{{ route('profile.signature.destroy') }}" class="mb-3"
                                  onsubmit="return confirm('Hapus tanda tangan {{ $label }}?')">
                                @csrf @method('DELETE')
                                <input type="hidden" name="company" value="{{ $key }}">
                                <x-btn type="submit" variant="ghost" icon="bi-trash">Hapus TTD</x-btn>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('profile.signature.store') }}" enctype="multipart/form-data" class="flex flex-col gap-2 sm:flex-row sm:items-end">
                            @csrf
                            <input type="hidden" name="company" value="{{ $key }}">
                            <div class="min-w-0 flex-1">
                                <label class="crm-label">{{ $has ? 'Ganti' : 'Upload' }} TTD (PNG/JPG, max 2MB)</label>
                                <input type="file" name="signature" accept="image/png,image/jpeg,image/webp" required class="crm-field">
                            </div>
                            <x-btn type="submit" variant="primary" icon="bi-upload">Upload</x-btn>
                        </form>
                    </div>
                @endforeach
            </div>
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
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Job Position</label>
                    <input type="text" name="job_position" value="{{ old('job_position', $user->profile?->job_position) }}"
                           placeholder="e.g. Account Manager, Sales Executive"
                           class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    <p class="mt-1 text-xs text-slate-400">Jabatan ini muncul di penawaran (PDF) sebagai posisi sales yang membuat quotation.</p>
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
