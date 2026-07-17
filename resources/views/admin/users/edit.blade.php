@extends('layouts.app')
@section('title', 'Edit '.$roleLabel)

@section('content')
<div class="mb-4">
    <a href="{{ route('users.index', ['role' => $role]) }}" class="crm-back"><i class="bi bi-arrow-left"></i> Back to {{ $roleLabel }}</a>
    <h2 class="text-lg font-semibold text-slate-800">Edit {{ $roleLabel }}</h2>
    <p class="text-sm text-slate-500">{{ $user->display_name }} (&commat;{{ $user->user_name }})</p>
</div>

<div class="max-w-2xl">
    <x-card>
        <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-5">
            @include('admin.users._form', [
                'mode' => 'edit',
                'role' => $role,
                'lockRole' => $lockRole ?? false,
            ])
        </form>
    </x-card>
</div>
@endsection
