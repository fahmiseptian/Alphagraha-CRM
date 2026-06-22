@extends('layouts.app')
@section('title', 'Aktivitas & Task')

@section('content')
<div class="mb-5 flex items-center justify-between">
    <div>
        <h2 class="text-lg font-semibold text-slate-800">Aktivitas & Task</h2>
        <p class="text-sm text-slate-500">Jadwal follow-up, reminder, dan catatan sales</p>
    </div>
    <a href="{{ route('activities.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
        <i class="bi bi-plus-lg"></i> Aktivitas Baru
    </a>
</div>

<div class="mb-4 flex flex-wrap items-center gap-2">
    @php $filters = ['upcoming'=>'Mendatang','overdue'=>'Terlambat','completed'=>'Selesai','all'=>'Semua']; @endphp
    @foreach ($filters as $key => $label)
        <a href="{{ route('activities.index', ['filter' => $key, 'type' => $type]) }}"
           class="rounded-full px-4 py-1.5 text-sm font-medium transition {{ $filter === $key ? 'bg-brand-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
            {{ $label }}
        </a>
    @endforeach
    <form method="GET" class="ml-auto">
        <input type="hidden" name="filter" value="{{ $filter }}">
        <select name="type" onchange="this.form.submit()" class="rounded-lg border border-slate-300 py-1.5 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
            <option value="">Semua tipe</option>
            @foreach ($types as $key => $label)
                <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </form>
</div>

<x-card :padding="false">
    @if ($activities->count())
        <ul class="divide-y divide-slate-50">
            @foreach ($activities as $activity)
                <li class="flex items-center gap-4 px-5 py-4 hover:bg-slate-50">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg
                        {{ $activity->isOverdue() ? 'bg-red-50 text-red-600' : 'bg-brand-50 text-brand-600' }}">
                        <i class="bi {{ ['call'=>'bi-telephone','meeting'=>'bi-people','email'=>'bi-envelope','task'=>'bi-check2-square','followup'=>'bi-arrow-repeat','note'=>'bi-sticky'][$activity->type] ?? 'bi-check2-square' }}"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-slate-800">{{ $activity->subject }}</p>
                            <x-badge :color="$activity->priority === 'high' ? 'red' : ($activity->priority === 'low' ? 'slate' : 'amber')">{{ ucfirst($activity->priority) }}</x-badge>
                        </div>
                        <p class="mt-0.5 text-xs text-slate-400">
                            {{ $activity->typeLabel() }}
                            @if ($activity->account) &middot; <i class="bi bi-people"></i> {{ $activity->account->name }} @endif
                            @if ($activity->lead) &middot; <i class="bi bi-funnel"></i> {{ $activity->lead->full_name }} @endif
                            @if ($activity->assignee) &middot; {{ $activity->assignee->name }} @endif
                        </p>
                    </div>
                    <div class="text-right">
                        @if ($activity->due_at)
                            <p class="text-sm font-medium {{ $activity->isOverdue() ? 'text-red-600' : 'text-slate-600' }}">{{ $activity->due_at->translatedFormat('d M Y') }}</p>
                            <p class="text-xs text-slate-400">{{ $activity->due_at->format('H:i') }}</p>
                        @else
                            <span class="text-xs text-slate-300">Tanpa tenggat</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-1">
                        @if ($activity->status !== 'completed')
                            <form method="POST" action="{{ route('activities.complete', $activity) }}">@csrf @method('PATCH')
                                <button class="rounded-lg p-2 text-green-600 hover:bg-green-50" title="Selesai"><i class="bi bi-check-lg"></i></button>
                            </form>
                        @else
                            <span class="rounded-lg p-2 text-green-500" title="Selesai"><i class="bi bi-check-circle-fill"></i></span>
                        @endif
                        <a href="{{ route('activities.edit', $activity) }}" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" title="Ubah"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="{{ route('activities.destroy', $activity) }}" onsubmit="return confirm('Hapus aktivitas ini?')">@csrf @method('DELETE')
                            <button class="rounded-lg p-2 text-red-500 hover:bg-red-50" title="Hapus"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="border-t border-slate-100 px-5 py-3">{{ $activities->links() }}</div>
    @else
        <x-empty-state icon="bi-calendar-check" title="Tidak ada aktivitas" message="Buat aktivitas atau follow-up baru.">
            <x-slot:action>
                <a href="{{ route('activities.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><i class="bi bi-plus-lg"></i> Aktivitas Baru</a>
            </x-slot:action>
        </x-empty-state>
    @endif
</x-card>
@endsection
