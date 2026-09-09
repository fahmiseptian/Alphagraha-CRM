@extends('layouts.app')
@section('title', 'New Industry')

@section('content')
<div class="mb-4">
    <a href="{{ route('industries.index') }}" class="crm-back"><i class="bi bi-arrow-left"></i> Back</a>
    <h2 class="mt-1 text-lg font-semibold text-slate-800">New Industry</h2>
    <p class="text-sm text-slate-500">Industri akan muncul di dropdown form customer.</p>
</div>

<div class="max-w-xl">
    <x-card>
        <form method="POST" action="{{ route('industries.store') }}" class="space-y-5">
            @csrf
            @include('admin.industries._form')
        </form>
    </x-card>
</div>
@endsection
