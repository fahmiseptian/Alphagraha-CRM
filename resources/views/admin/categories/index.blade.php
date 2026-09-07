@extends('layouts.app')
@section('title', 'Categories')

@section('content')
<x-page-header title="Categories" description="Master kategori produk untuk opportunity. Dikelola superadmin dan tim Product.">
    <x-slot:actions>
        <x-btn href="{{ route('categories.create') }}" icon="bi-plus-lg">New Category</x-btn>
    </x-slot:actions>
</x-page-header>

<div class="mb-4">
    @include('admin.catalog._excel-actions', [
        'exportRoute' => route('categories.export'),
        'templateRoute' => route('categories.template'),
        'importRoute' => route('categories.import'),
        'label' => 'category',
    ])
</div>

<x-card :padding="false">
    @if ($categories->count())
        @include('admin.catalog._data-table', [
            'tableId' => 'categories-table',
            'items' => $categories,
            'editRoute' => 'categories.edit',
            'destroyRoute' => 'categories.destroy',
            'searchPlaceholder' => 'Cari category...',
            'confirmMessage' => 'Hapus category ini?',
        ])
    @else
        <x-empty-state icon="bi-folder" title="Belum ada category" message="Tambah category agar muncul di form opportunity.">
            <x-slot:action>
                <x-btn href="{{ route('categories.create') }}" icon="bi-plus-lg">New Category</x-btn>
            </x-slot:action>
        </x-empty-state>
    @endif
</x-card>
@endsection
