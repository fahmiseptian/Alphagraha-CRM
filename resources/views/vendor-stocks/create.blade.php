@extends('layouts.app')
@section('title', 'Product')

@section('content')
<x-page-header title="Tambah Product" description="Catat harga dan status ready/indent per produk per vendor. Data ini dipakai sebagai perbandingan di Purchase Order.">
    <x-slot:actions>
        <x-btn href="{{ route('vendor-stocks.index') }}" variant="secondary">Batal</x-btn>
    </x-slot:actions>
</x-page-header>

<div class="max-w-3xl">
    <x-card>
        <form method="POST" action="{{ route('vendor-stocks.store') }}" class="space-y-5">
            @csrf
            @include('vendor-stocks._form')
        </form>
    </x-card>
</div>
@endsection
