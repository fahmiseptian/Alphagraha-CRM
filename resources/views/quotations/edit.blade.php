@extends('layouts.app')
@section('title', 'Edit Quotation')

@section('content')
<div class="mb-4">
    <a href="{{ route('quotations.show', $quotation) }}" class="text-sm text-slate-500 hover:text-slate-700"><i class="bi bi-arrow-left"></i> Back</a>
    <h2 class="mt-1 text-lg font-semibold text-slate-800">Edit Quotation {{ $quotation->number }}</h2>
</div>

@include('quotations._form', ['action' => route('quotations.update', $quotation), 'method' => 'PUT'])
@endsection
