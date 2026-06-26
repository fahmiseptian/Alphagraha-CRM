@extends('layouts.app')
@section('title', 'New Contact')

@section('content')
<x-page-header title="Add Contact" :back="route('contacts.index')" backLabel="Back to list" />

<div class="max-w-3xl">
    <x-card>
        @include('contacts._form', [
            'action' => route('contacts.store'),
            'method' => 'POST',
            'cancelUrl' => route('contacts.index'),
            'accounts' => $accounts,
            'selectedAccountId' => $selectedAccountId,
        ])
    </x-card>
</div>
@endsection
