@extends('layouts.app')
@section('title', 'Edit Ketersediaan Vendor')

@section('content')
<x-page-header title="Edit Ketersediaan" :description="$stock->product_name" :back="route('vendor-stocks.index')" backLabel="Ketersediaan Vendor">
</x-page-header>

<div class="max-w-3xl">
    <x-card>
        <form method="POST" action="{{ route('vendor-stocks.update', $stock) }}" class="space-y-5">
            @csrf
            @method('PUT')
            @include('vendor-stocks._form')
        </form>
    </x-card>
</div>
@endsection
