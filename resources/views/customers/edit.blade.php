@extends('layouts.app')
@section('title', 'Edit Customer')

@section('content')
<x-page-header title="Edit Customer" :back="route('customers.show', $account->id)" backLabel="Back to detail" />

<div class="max-w-3xl">
    <x-card>
        @include('customers._form', [
            'action' => route('customers.update', $account->id),
            'method' => 'PUT',
            'cancelUrl' => route('customers.show', $account->id),
        ])
    </x-card>
</div>
@endsection
