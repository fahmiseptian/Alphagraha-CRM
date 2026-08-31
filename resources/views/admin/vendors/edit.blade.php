@extends('layouts.app')
@section('title', 'Edit Vendor')

@section('content')
<div class="mb-4">
    <a href="{{ route('vendors.index') }}" class="crm-back"><i class="bi bi-arrow-left"></i> Back</a>
    <h2 class="mt-1 text-lg font-semibold text-slate-800">Edit Vendor</h2>
</div>

<div class="max-w-4xl">
    <x-card>
        <form method="POST" action="{{ route('vendors.update', $vendor) }}" class="space-y-5">
            @csrf
            @method('PUT')
            @include('admin.vendors._form')
        </form>
    </x-card>
</div>
@endsection
