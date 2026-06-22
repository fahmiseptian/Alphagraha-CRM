@extends('layouts.app')
@section('title', 'Profil')

@section('content')
<div class="mb-4">
    <h2 class="text-lg font-semibold text-slate-800">Profil & Keamanan</h2>
    <p class="text-sm text-slate-500">Perbarui informasi akun dan kata sandi Anda</p>
</div>

<div class="max-w-2xl">
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
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Nama</label>
                    <input type="text" value="{{ $user->display_name }}" disabled
                           class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 px-3 text-sm text-slate-500">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Email</label>
                    <input type="email" value="{{ $user->email }}" disabled
                           class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 px-3 text-sm text-slate-500">
                </div>
            </div>
            <p class="-mt-2 text-xs text-slate-400"><i class="bi bi-info-circle"></i> Nama & email dikelola di EspoCRM.</p>

            <div class="border-t border-slate-100 pt-5">
                <p class="mb-3 text-sm font-medium text-slate-700">Ubah Kata Sandi</p>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <div>
                        <label class="mb-1.5 block text-sm text-slate-600">Sandi Saat Ini</label>
                        <input type="password" name="current_password" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm text-slate-600">Sandi Baru</label>
                        <input type="password" name="password" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm text-slate-600">Konfirmasi</label>
                        <input type="password" name="password_confirmation" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-100 pt-4">
                <button class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">Simpan Perubahan</button>
            </div>
        </form>
    </x-card>
</div>
@endsection
