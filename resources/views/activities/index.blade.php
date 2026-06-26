@extends('layouts.app')
@section('title', 'Activities')

@section('content')
<x-page-header title="Activities & Tasks" description="Follow-up schedule, reminders, and sales notes">
    <x-slot:actions>
        <x-btn href="{{ route('activities.create') }}" icon="bi-plus-lg">New Activity</x-btn>
    </x-slot:actions>
</x-page-header>

<div class="mb-4 flex flex-wrap items-center gap-2">
    @php $filters = ['upcoming'=>'Upcoming','overdue'=>'Overdue','completed'=>'Completed','all'=>'All']; @endphp
    @foreach ($filters as $key => $label)
        <a href="{{ route('activities.index', ['filter' => $key, 'type' => $type]) }}"
           class="rounded-full px-4 py-1.5 text-sm font-medium transition {{ $filter === $key ? 'bg-brand-600 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
            {{ $label }}
        </a>
    @endforeach
    <form method="GET" class="ml-auto">
        <input type="hidden" name="filter" value="{{ $filter }}">
        <select name="type" data-auto-submit class="select2 select2-compact w-40" data-placeholder="All types">
            <option value="">All types</option>
            @foreach ($types as $key => $label)
                <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </form>
</div>

<x-card :padding="false">
    @if ($activities->count())
        <ul class="divide-y divide-slate-100">
            @foreach ($activities as $activity)
                <li class="flex items-center gap-4 px-5 py-4 transition hover:bg-slate-50">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $activity->isOverdue() ? 'bg-red-50 text-red-600' : 'bg-brand-50 text-brand-600' }}">
                        <i class="bi {{ ['call'=>'bi-telephone','meeting'=>'bi-people','email'=>'bi-envelope','task'=>'bi-check2-square','followup'=>'bi-arrow-repeat','note'=>'bi-sticky'][$activity->type] ?? 'bi-check2-square' }}"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-slate-800">{{ $activity->subject }}</p>
                            <x-badge :color="$activity->priority === 'high' ? 'red' : ($activity->priority === 'low' ? 'slate' : 'amber')">{{ ucfirst($activity->priority) }}</x-badge>
                        </div>
                        <p class="mt-0.5 text-xs text-slate-400">
                            {{ $activity->typeLabel() }}
                            @if ($activity->account) &middot; {{ $activity->account->name }} @endif
                            @if ($activity->lead) &middot; {{ $activity->lead->full_name }} @endif
                            @if ($activity->assignee) &middot; {{ $activity->assignee->name }} @endif
                        </p>
                    </div>
                    <div class="hidden text-right sm:block">
                        @if ($activity->due_at)
                            <p class="text-sm font-medium {{ $activity->isOverdue() ? 'text-red-600' : 'text-slate-600' }}">{{ $activity->due_at->translatedFormat('d M Y') }}</p>
                            <p class="text-xs text-slate-400">{{ $activity->due_at->format('H:i') }}</p>
                        @else
                            <span class="text-xs text-slate-400">No due date</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-0.5">
                        @if ($activity->status !== 'completed')
                            <form method="POST" action="{{ route('activities.complete', $activity) }}">@csrf @method('PATCH')
                                <button class="crm-icon-btn text-green-600 hover:bg-green-50" title="Complete"><i class="bi bi-check-lg"></i></button>
                            </form>
                        @else
                            <span class="crm-icon-btn text-green-500" title="Completed"><i class="bi bi-check-circle-fill"></i></span>
                        @endif
                        <a href="{{ route('activities.edit', $activity) }}" class="crm-icon-btn" title="Edit"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="{{ route('activities.destroy', $activity) }}" onsubmit="return confirm('Delete this activity?')">@csrf @method('DELETE')
                            <button class="crm-icon-btn text-red-500 hover:bg-red-50" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="crm-table-footer">{{ $activities->links() }}</div>
    @else
        <x-empty-state icon="bi-calendar-check" title="No activities found" message="Create a new activity or follow-up.">
            <x-slot:action>
                <x-btn href="{{ route('activities.create') }}" icon="bi-plus-lg">New Activity</x-btn>
            </x-slot:action>
        </x-empty-state>
    @endif
</x-card>
@endsection
