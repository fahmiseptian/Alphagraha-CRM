@extends('layouts.app')
@section('title', 'Edit Activity')

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div>
        <a href="{{ route('activities.index') }}" class="text-sm text-slate-500 hover:text-slate-700"><i class="bi bi-arrow-left"></i> Back</a>
        <h2 class="mt-1 text-lg font-semibold text-slate-800">Edit Activity</h2>
    </div>
    <a href="{{ $activity->googleCalendarUrl() }}" target="_blank" rel="noopener"
       class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
        <i class="bi bi-calendar-plus text-brand-600"></i> Tambah ke Google Calendar
    </a>
</div>

<div class="max-w-3xl">
    @include('activities._form', ['action' => route('activities.update', $activity), 'method' => 'PUT'])
    @include('activities._media')
</div>
@endsection
