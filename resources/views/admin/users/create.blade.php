@extends('layouts.app')
@section('title', 'Pengguna Baru')

@section('content')
<div class="mb-4">
    <h2 class="text-lg font-semibold text-slate-800">Pengguna Baru</h2>
    <p class="text-sm text-slate-500">Tambah akun baru ke tabel user EspoCRM</p>
</div>

<div class="max-w-2xl">
    <x-card>
        <form method="POST" action="{{ route('users.store') }}" class="space-y-5">
            @include('admin.users._form', ['mode' => 'create'])
        </form>
    </x-card>
</div>
@endsection
