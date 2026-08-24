@extends('layouts.app')
@section('title', 'Edit Category')

@section('content')
<div class="mb-4">
    <a href="{{ route('categories.index') }}" class="crm-back"><i class="bi bi-arrow-left"></i> Back</a>
    <h2 class="mt-1 text-lg font-semibold text-slate-800">Edit Category</h2>
</div>

<div class="max-w-xl">
    <x-card>
        <form method="POST" action="{{ route('categories.update', $category) }}" class="space-y-5">
            @csrf
            @method('PUT')
            @include('admin.categories._form')
        </form>
    </x-card>
</div>
@endsection
