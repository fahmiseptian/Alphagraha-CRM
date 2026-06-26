@extends('layouts.app')
@section('title', 'New Lead')

@section('content')
<x-page-header title="Create Lead" :back="route('leads.index')" backLabel="Back to list" />

<div class="max-w-3xl">
    <x-card>
        @include('leads._form', [
            'action' => route('leads.store'),
            'method' => 'POST',
            'cancelUrl' => route('leads.index'),
        ])
    </x-card>
</div>
@endsection
