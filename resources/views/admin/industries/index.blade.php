@extends('layouts.app')
@section('title', 'Industries')

@section('content')
<x-page-header title="Industries" description="Master industri customer. Hanya superadmin yang dapat menambah atau mengubah.">
    <x-slot:actions>
        <x-btn href="{{ route('industries.create') }}" icon="bi-plus-lg">New Industry</x-btn>
    </x-slot:actions>
</x-page-header>

<div class="mb-4">
    @include('admin.catalog._excel-actions', [
        'exportRoute' => route('industries.export'),
        'templateRoute' => route('industries.template'),
        'importRoute' => route('industries.import'),
        'label' => 'industri',
    ])
</div>

<x-card :padding="false">
    @if ($industries->count())
        @include('admin.catalog._data-table', [
            'tableId' => 'industries-table',
            'items' => $industries,
            'editRoute' => 'industries.edit',
            'destroyRoute' => 'industries.destroy',
            'searchPlaceholder' => 'Cari industri...',
            'confirmMessage' => 'Hapus industri ini? Customer yang memakai industri ini akan dikosongkan.',
        ])
    @else
        <x-empty-state icon="bi-buildings" title="Belum ada industri" message="Tambah industri agar muncul di form customer.">
            <x-slot:action>
                <x-btn href="{{ route('industries.create') }}" icon="bi-plus-lg">New Industry</x-btn>
            </x-slot:action>
        </x-empty-state>
    @endif
</x-card>
@endsection
