@extends('layouts.app')
@section('title', 'Edit Contact')

@section('content')
<x-page-header title="Edit Contact" :back="route('contacts.index')" backLabel="Back to list" />

<div class="max-w-3xl">
    <x-card>
        @include('contacts._form', [
            'action' => route('contacts.update', $contact->id),
            'method' => 'PUT',
            'cancelUrl' => route('contacts.index'),
            'accounts' => $accounts,
            'selectedAccountId' => $selectedAccountId,
            'contact' => $contact,
        ])
    </x-card>
</div>
@endsection
