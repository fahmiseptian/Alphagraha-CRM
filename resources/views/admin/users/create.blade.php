@extends('layouts.app')
@section('title', 'New User')

@section('content')
<div class="mb-4">
    <h2 class="text-lg font-semibold text-slate-800">New User</h2>
    <p class="text-sm text-slate-500">Add a new account to the EspoCRM user table</p>
</div>

<div class="max-w-2xl">
    <x-card>
        <form method="POST" action="{{ route('users.store') }}" class="space-y-5">
            @include('admin.users._form', ['mode' => 'create'])
        </form>
    </x-card>
</div>
@endsection
