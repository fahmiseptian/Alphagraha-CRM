@extends('layouts.app')
@section('title', 'Edit Template')

@section('content')
<div class="mb-4">
    <a href="{{ route('templates.index') }}" class="text-sm text-slate-500 hover:text-slate-700"><i class="bi bi-arrow-left"></i> Back</a>
    <h2 class="mt-1 text-lg font-semibold text-slate-800">Edit Template: {{ $template->name }}</h2>
</div>

@include('admin.templates._form', ['action' => route('templates.update', $template), 'method' => 'PUT'])
@endsection
