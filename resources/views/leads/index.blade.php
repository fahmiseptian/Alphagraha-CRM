@extends('layouts.app')
@section('title', 'Lead')

@section('content')
<div class="mb-5">
    <h2 class="text-lg font-semibold text-slate-800">Lead Management</h2>
    <p class="text-sm text-slate-500">Kelola prospek dan follow-up penjualan</p>
</div>

{{-- Ringkasan status --}}
<div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
    @php
        $statusColors = ['New'=>'blue','Assigned'=>'purple','In Process'=>'amber','Converted'=>'green','Recycled'=>'slate','Dead'=>'red'];
    @endphp
    @foreach ($statuses as $st)
        <a href="{{ route('leads.index', ['status' => $st]) }}"
           class="rounded-xl border bg-white p-3 text-center shadow-sm transition hover:shadow {{ $status === $st ? 'border-brand-400 ring-1 ring-brand-200' : 'border-slate-200' }}">
            <p class="text-xl font-bold text-slate-800">{{ $statusCounts[$st] ?? 0 }}</p>
            <p class="mt-0.5 text-xs text-slate-500">{{ $st }}</p>
        </a>
    @endforeach
</div>

<x-card class="mb-4" :padding="false">
    <form method="GET" class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama lead atau perusahaan..."
                   class="w-full rounded-lg border border-slate-300 py-2 pl-10 pr-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
        </div>
        <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><i class="bi bi-search"></i> Cari</button>
        @if ($search || $status)
            <a href="{{ route('leads.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-center text-sm text-slate-600 hover:bg-slate-50">Reset</a>
        @endif
    </form>
</x-card>

<x-card :padding="false">
    @if ($leads->count())
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                        <th class="px-5 py-3 font-medium">Lead</th>
                        <th class="px-5 py-3 font-medium">Perusahaan</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium">Sumber</th>
                        <th class="px-5 py-3 font-medium">Sales</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach ($leads as $lead)
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3">
                                <a href="{{ route('leads.show', $lead->id) }}" class="font-medium text-slate-800 hover:text-brand-600">{{ $lead->full_name }}</a>
                                <span class="block text-xs text-slate-400">{{ $lead->title ?: '' }}</span>
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ $lead->account_name ?: '—' }}</td>
                            <td class="px-5 py-3"><x-badge :color="$statusColors[$lead->status] ?? 'slate'">{{ $lead->status }}</x-badge></td>
                            <td class="px-5 py-3 text-slate-600">{{ $lead->source ?: '—' }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ optional($lead->assignedUser)->display_name ?: '—' }}</td>
                            <td class="px-5 py-3 text-right"><a href="{{ route('leads.show', $lead->id) }}" class="text-brand-600"><i class="bi bi-arrow-right"></i></a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-5 py-3">{{ $leads->links() }}</div>
    @else
        <x-empty-state icon="bi-funnel" title="Tidak ada lead" message="Belum ada prospek yang cocok dengan filter." />
    @endif
</x-card>
@endsection
