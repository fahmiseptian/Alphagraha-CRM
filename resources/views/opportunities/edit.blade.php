@extends('layouts.app')
@section('title', 'Edit Opportunity')

@section('content')
<x-page-header title="Edit Opportunity" :back="route('opportunities.show', $opportunity)" backLabel="Back to detail" />

@include('opportunities._form', [
    'action' => route('opportunities.update', $opportunity),
    'method' => 'PUT',
    'cancelUrl' => route('opportunities.show', $opportunity),
    'purchasingMode' => $purchasingMode ?? false,
])
@endsection
