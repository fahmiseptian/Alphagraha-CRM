@extends('layouts.app')
@section('title', 'Edit Industry')

@section('content')
<div class="mb-4">
    <a href="{{ route('industries.index') }}" class="crm-back"><i class="bi bi-arrow-left"></i> Back</a>
    <h2 class="mt-1 text-lg font-semibold text-slate-800">Edit Industry</h2>
    <p class="text-sm text-slate-500">Mengubah nama akan ikut memperbarui customer yang memakai industri ini.</p>
</div>

<div class="max-w-xl">
    <x-card>
        <form method="POST" action="{{ route('industries.update', $industry) }}" class="space-y-5">
            @csrf
            @method('PUT')
            @include('admin.industries._form')
        </form>
    </x-card>
</div>
@endsection
