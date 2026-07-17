@extends('layouts.app')
@section('title', 'New '.$roleLabel)

@section('content')
<div class="mb-4">
    <a href="{{ route('users.index', ['role' => $role]) }}" class="crm-back"><i class="bi bi-arrow-left"></i> Back to {{ $roleLabel }}</a>
    <h2 class="text-lg font-semibold text-slate-800">New {{ $roleLabel }}</h2>
    <p class="text-sm text-slate-500">Tambah akun baru dengan role {{ $roleLabel }}</p>
</div>

<div class="max-w-2xl">
    <x-card>
        <form method="POST" action="{{ route('users.store') }}" class="space-y-5">
            @include('admin.users._form', [
                'mode' => 'create',
                'role' => $role,
                'lockRole' => $lockRole ?? true,
            ])
        </form>
    </x-card>
</div>
@endsection
