@extends('layouts.app')
@section('title', 'New Category')

@section('content')
<div class="mb-4">
    <a href="{{ route('categories.index') }}" class="crm-back"><i class="bi bi-arrow-left"></i> Back</a>
    <h2 class="mt-1 text-lg font-semibold text-slate-800">New Category</h2>
    <p class="text-sm text-slate-500">Category akan muncul di dropdown item opportunity.</p>
</div>

<div class="max-w-xl">
    <x-card>
        <form method="POST" action="{{ route('categories.store') }}" class="space-y-5">
            @csrf
            @include('admin.categories._form')
        </form>
    </x-card>
</div>
@endsection
