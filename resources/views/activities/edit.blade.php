@extends('layouts.app')
@section('title', 'Edit Activity')

@section('content')
<div class="mb-4">
    <a href="{{ route('activities.index') }}" class="text-sm text-slate-500 hover:text-slate-700"><i class="bi bi-arrow-left"></i> Back</a>
    <h2 class="mt-1 text-lg font-semibold text-slate-800">Edit Activity</h2>
</div>

<div class="max-w-3xl">
    @include('activities._form', ['action' => route('activities.update', $activity), 'method' => 'PUT'])
</div>
@endsection
