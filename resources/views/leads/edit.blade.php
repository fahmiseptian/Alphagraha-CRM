@extends('layouts.app')
@section('title', 'Edit Lead')

@section('content')
<x-page-header title="Edit Lead" :back="route('leads.show', $lead->id)" backLabel="Back to detail" />

<div class="max-w-3xl">
    <x-card>
        @include('leads._form', [
            'action' => route('leads.update', $lead->id),
            'method' => 'PUT',
            'cancelUrl' => route('leads.show', $lead->id),
        ])
    </x-card>
</div>
@endsection
