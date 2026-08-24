@extends('layouts.app')
@section('title', 'New Brand')

@section('content')
<div class="mb-4">
    <a href="{{ route('brands.index') }}" class="crm-back"><i class="bi bi-arrow-left"></i> Back</a>
    <h2 class="mt-1 text-lg font-semibold text-slate-800">New Brand</h2>
    <p class="text-sm text-slate-500">Brand akan muncul di dropdown item opportunity dan quotation.</p>
</div>

<div class="max-w-xl">
    <x-card>
        <form method="POST" action="{{ route('brands.store') }}" class="space-y-5">
            @csrf
            @include('admin.brands._form')
        </form>
    </x-card>
</div>
@endsection
