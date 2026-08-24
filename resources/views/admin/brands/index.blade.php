@extends('layouts.app')
@section('title', 'Brands')

@section('content')
<x-page-header title="Brands" description="Master brand untuk opportunity & quotation. Dikelola superadmin, tanpa API AGC.">
    <x-slot:actions>
        <x-btn href="{{ route('brands.create') }}" icon="bi-plus-lg">New Brand</x-btn>
    </x-slot:actions>
</x-page-header>

<div class="mb-4">
    @include('admin.catalog._excel-actions', [
        'exportRoute' => route('brands.export'),
        'templateRoute' => route('brands.template'),
        'importRoute' => route('brands.import'),
        'label' => 'brand',
    ])
</div>

<x-card :padding="false">
    @if ($brands->count())
        @include('admin.catalog._data-table', [
            'tableId' => 'brands-table',
            'items' => $brands,
            'editRoute' => 'brands.edit',
            'destroyRoute' => 'brands.destroy',
            'searchPlaceholder' => 'Cari brand...',
            'confirmMessage' => 'Hapus brand ini?',
        ])
    @else
        <x-empty-state icon="bi-tags" title="Belum ada brand" message="Tambah brand agar muncul di form opportunity dan quotation.">
            <x-slot:action>
                <x-btn href="{{ route('brands.create') }}" icon="bi-plus-lg">New Brand</x-btn>
            </x-slot:action>
        </x-empty-state>
    @endif
</x-card>
@endsection
