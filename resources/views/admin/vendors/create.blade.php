@extends('layouts.app')
@section('title', 'New Vendor')

@section('content')
<div class="mb-4">
    <a href="{{ route('vendors.index') }}" class="crm-back"><i class="bi bi-arrow-left"></i> Back</a>
    <h2 class="mt-1 text-lg font-semibold text-slate-800">New Vendor</h2>
    <p class="text-sm text-slate-500">Vendor akan muncul di dropdown item opportunity. Satu vendor bisa punya banyak brand & PIC.</p>
</div>

<div class="max-w-4xl">
    <x-card>
        <form method="POST" action="{{ route('vendors.store') }}" class="space-y-5">
            @csrf
            @include('admin.vendors._form')
        </form>
    </x-card>
</div>
@endsection
