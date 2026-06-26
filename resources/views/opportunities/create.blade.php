@extends('layouts.app')
@section('title', 'New Opportunity')

@section('content')
<x-page-header title="Create Opportunity" :back="route('opportunities.index')" backLabel="Back to list" />

@include('opportunities._form', [
    'action' => route('opportunities.store'),
    'method' => 'POST',
    'cancelUrl' => route('opportunities.index'),
])
@endsection
