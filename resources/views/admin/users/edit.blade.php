@extends('layouts.app')
@section('title', 'Edit User')

@section('content')
<div class="mb-4">
    <h2 class="text-lg font-semibold text-slate-800">Edit User</h2>
    <p class="text-sm text-slate-500">{{ $user->display_name }} (&commat;{{ $user->user_name }})</p>
</div>

<div class="max-w-2xl">
    <x-card>
        <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-5">
            @include('admin.users._form', ['mode' => 'edit'])
        </form>
    </x-card>
</div>
@endsection
