@extends('layouts.app')
@section('title', 'New Customer')

@section('content')
<x-page-header title="Create Customer" :back="route('customers.index')" backLabel="Back to list" />

<div class="max-w-3xl">
    <x-card>
        @include('customers._form', [
            'action' => route('customers.store'),
            'method' => 'POST',
            'cancelUrl' => route('customers.index'),
        ])
    </x-card>
</div>
@endsection
