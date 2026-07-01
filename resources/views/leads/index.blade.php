@extends('layouts.app')
@section('title', 'Leads')

@section('content')
<x-page-header title="Lead Management" description="Manage leads and sales follow-ups">
    <x-slot:actions>
        <x-btn href="{{ route('leads.create') }}" icon="bi-plus-lg">New Lead</x-btn>
    </x-slot:actions>
</x-page-header>

<div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
    @php $statusColors = ['New'=>'blue','Assigned'=>'purple','In Process'=>'amber','Converted'=>'green','Recycled'=>'slate','Dead'=>'red']; @endphp
    @foreach ($statuses as $st)
        <a href="{{ route('leads.index', ['status' => $st]) }}"
           class="rounded-xl border bg-white p-3 text-center shadow-sm transition hover:border-brand-200 hover:shadow {{ $status === $st ? 'border-brand-400 ring-2 ring-brand-100' : 'border-slate-200' }}">
            <p class="text-xl font-bold text-slate-800">{{ $statusCounts[$st] ?? 0 }}</p>
            <p class="mt-0.5 text-xs text-slate-500">{{ $st }}</p>
        </a>
    @endforeach
</div>

<x-card class="mb-4" :padding="false">
    <form method="GET" class="crm-filter-form">
        @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
        <div class="crm-search min-w-0 flex-1">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="{{ $search }}" placeholder="Search lead name or company..." class="crm-field">
        </div>
        <x-btn type="submit" variant="primary" icon="bi-search">Search</x-btn>
        @if ($search || $status)
            <x-btn href="{{ route('leads.index') }}" variant="ghost">Reset</x-btn>
        @endif
    </form>
</x-card>

<x-card :padding="false">
    @if ($leads->count())
        <div class="crm-table-wrap">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th>Lead</th>
                        <th>Company</th>
                        <th>Status</th>
                        <th>Source</th>
                        <th>Sales</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($leads as $lead)
                        <tr>
                            <td>
                                <a href="{{ route('leads.show', $lead->id) }}" class="font-medium text-slate-800 hover:text-brand-600">{{ $lead->full_name }}</a>
                                @if ($lead->title)<span class="block text-xs text-slate-400">{{ $lead->title }}</span>@endif
                            </td>
                            <td class="text-slate-600">{{ $lead->account_name ?: '—' }}</td>
                            <td><x-badge :color="$statusColors[$lead->status] ?? 'slate'">{{ $lead->status }}</x-badge></td>
                            <td class="text-slate-600">{{ $lead->source ?: '—' }}</td>
                            <td class="text-slate-600">{{ optional($lead->assignedUser)->display_name ?: '—' }}</td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('leads.edit', $lead->id) }}" class="crm-icon-btn" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <a href="{{ route('leads.show', $lead->id) }}" class="crm-icon-btn crm-icon-btn--brand"><i class="bi bi-arrow-right"></i></a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="crm-table-footer">{{ $leads->links() }}</div>
    @else
        <x-empty-state icon="bi-funnel" title="No leads found" message="No leads match your filters." />
    @endif
</x-card>
@endsection
