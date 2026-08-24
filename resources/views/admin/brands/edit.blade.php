@extends('layouts.app')
@section('title', 'Edit Brand')

@section('content')
<div class="mb-4">
    <a href="{{ route('brands.index') }}" class="crm-back"><i class="bi bi-arrow-left"></i> Back</a>
    <h2 class="mt-1 text-lg font-semibold text-slate-800">Edit Brand</h2>
</div>

<div class="max-w-xl">
    <x-card>
        <form method="POST" action="{{ route('brands.update', $brand) }}" class="space-y-5">
            @csrf
            @method('PUT')
            @include('admin.brands._form')
        </form>
    </x-card>
</div>
@endsection
