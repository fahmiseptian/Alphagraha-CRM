@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
@include('partials.deadline-alert')

<div class="mb-6">
    <h2 class="crm-page-title">Hi, {{ Str::before(auth()->user()->name, ' ') }}!</h2>
    <p class="crm-page-desc">Here's your sales activity summary for today.</p>
</div>

{{-- Main stats --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat-card title="Total Customers" :value="number_format($customersCount)" icon="bi-people" color="brand" />
    <x-stat-card title="Total Leads" :value="number_format($leadsCount)" icon="bi-funnel" color="purple" />
    <x-stat-card title="Total Quotations" :value="number_format($quotationsCount)" icon="bi-file-earmark-text" color="amber"
                 :sub="$acceptedCount.' accepted'" />
    <x-stat-card title="Active Quotation Value" :value="money($quotationsValue)" icon="bi-cash-stack" color="green" />
</div>

{{-- Sales Leaderboard --}}
<x-card class="mt-4" :padding="false">
    <x-slot:title>
        <span class="inline-flex items-center gap-2">
            <i class="bi bi-trophy text-amber-500"></i> Sales Leaderboard
        </span>
    </x-slot:title>
    <x-slot:action>
        <form method="GET" action="{{ route('dashboard') }}">
            <select name="leaderboard_period" onchange="this.form.submit()"
                    class="rounded-lg border border-slate-300 bg-white py-1.5 pl-2 pr-8 text-xs font-medium text-slate-700 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
                <option value="alltime" @selected($leaderboardPeriod === 'alltime')>All Time</option>
                <option value="month" @selected($leaderboardPeriod === 'month')>This Month</option>
                <option value="year" @selected($leaderboardPeriod === 'year')>This Year</option>
            </select>
        </form>
    </x-slot:action>

    @if ($salesLeaderboard->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th class="w-14">#</th>
                        <th>Sales</th>
                        <th class="text-right">Deals Won</th>
                        <th class="text-right">Total Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($salesLeaderboard as $entry)
                        <tr @class(['bg-brand-50/40' => $entry['user_id'] === auth()->id()])>
                            <td>
                                @if ($entry['rank'] === 1)
                                    <span class="crm-leaderboard-rank crm-leaderboard-rank--gold">1</span>
                                @elseif ($entry['rank'] === 2)
                                    <span class="crm-leaderboard-rank crm-leaderboard-rank--silver">2</span>
                                @elseif ($entry['rank'] === 3)
                                    <span class="crm-leaderboard-rank crm-leaderboard-rank--bronze">3</span>
                                @else
                                    <span class="crm-leaderboard-rank">{{ $entry['rank'] }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="crm-avatar">{{ initials($entry['name']) }}</span>
                                    <div>
                                        <p class="font-medium text-slate-800">{{ $entry['name'] }}</p>
                                        @if ($entry['user_id'] === auth()->id())
                                            <p class="text-[11px] text-brand-600">You</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="text-right font-medium text-slate-700">{{ number_format($entry['won_count']) }}</td>
                            <td class="text-right font-semibold text-slate-800">{{ money($entry['won_value']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <x-empty-state icon="bi-trophy" title="No closed won deals yet"
                       message="Leaderboard akan muncul setelah ada deal Closed Won pada periode ini." />
    @endif
</x-card>

<div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
    {{-- Opportunity pipeline --}}
    <x-card title="Sales Pipeline" class="lg:col-span-2">
        <div class="mb-5 grid grid-cols-2 gap-4">
            <div class="rounded-lg bg-slate-50 p-4">
                <p class="text-xs text-slate-500">Open Pipeline</p>
                <p class="mt-1 text-xl font-bold text-slate-800">{{ money($openPipeline) }}</p>
            </div>
            <div class="rounded-lg bg-green-50 p-4">
                <p class="text-xs text-slate-500">Won This Month</p>
                <p class="mt-1 text-xl font-bold text-green-700">{{ money($wonThisMonth) }}</p>
            </div>
        </div>

        @php $maxStage = max($stageDistribution ?: [1]); @endphp
        <div class="space-y-3">
            @forelse ($stageDistribution as $stage => $total)
                <div>
                    <div class="mb-1 flex items-center justify-between text-xs">
                        <span class="font-medium text-slate-600">{{ $stage }}</span>
                        <span class="text-slate-400">{{ $total }}</span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-brand-500" style="width: {{ max(($total / $maxStage) * 100, 4) }}%"></div>
                    </div>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-slate-400">No opportunity data yet.</p>
            @endforelse
        </div>
    </x-card>

    {{-- Quotation status --}}
    <x-card title="Quotation Status">
        @php
            $statusMeta = [
                'draft' => ['Draft', 'bg-slate-400'],
                'sent' => ['Sent', 'bg-blue-500'],
                'accepted' => ['Accepted', 'bg-green-500'],
                'rejected' => ['Rejected', 'bg-red-500'],
                'expired' => ['Expired', 'bg-amber-500'],
            ];
            $totalQuo = array_sum($quotationStatus) ?: 1;
        @endphp
        @if (array_sum($quotationStatus) > 0)
            <div class="mb-4 flex h-3 w-full overflow-hidden rounded-full bg-slate-100">
                @foreach ($statusMeta as $key => [$label, $bar])
                    @if (($quotationStatus[$key] ?? 0) > 0)
                        <div class="{{ $bar }}" style="width: {{ (($quotationStatus[$key] ?? 0)/$totalQuo)*100 }}%"></div>
                    @endif
                @endforeach
            </div>
            <ul class="space-y-2 text-sm">
                @foreach ($statusMeta as $key => [$label, $bar])
                    <li class="flex items-center justify-between">
                        <span class="flex items-center gap-2 text-slate-600">
                            <span class="h-2.5 w-2.5 rounded-full {{ $bar }}"></span> {{ $label }}
                        </span>
                        <span class="font-medium text-slate-700">{{ $quotationStatus[$key] ?? 0 }}</span>
                    </li>
                @endforeach
            </ul>
        @else
            <x-empty-state icon="bi-file-earmark" title="No quotations yet" message="Your quotations will appear here." />
        @endif
    </x-card>
</div>

<div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
    {{-- Upcoming activities --}}
    <x-card class="lg:col-span-2" :padding="false">
        <x-slot:title>Upcoming Activities & Follow-ups</x-slot:title>
        <x-slot:action>
            @if ($overdueCount > 0)
                <x-badge color="red"><i class="bi bi-exclamation-circle"></i> {{ $overdueCount }} overdue</x-badge>
            @endif
        </x-slot:action>

        @forelse ($upcomingActivities as $activity)
            <div class="flex items-center gap-4 border-b border-slate-50 px-5 py-3 last:border-0">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                    <i class="bi {{ ['call'=>'bi-telephone','meeting'=>'bi-people','email'=>'bi-envelope','task'=>'bi-check2-square','followup'=>'bi-arrow-repeat','note'=>'bi-sticky'][$activity->type] ?? 'bi-check2-square' }}"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-slate-800">{{ $activity->subject }}</p>
                    <p class="truncate text-xs text-slate-400">
                        {{ $activity->typeLabel() }}
                        @if ($activity->account) &middot; {{ $activity->account->name }} @endif
                    </p>
                </div>
                <div class="text-right">
                    @if ($activity->due_at)
                        <p class="text-xs font-medium {{ $activity->isOverdue() ? 'text-red-600' : 'text-slate-600' }}">
                            {{ $activity->due_at->translatedFormat('d M') }}
                        </p>
                        <p class="text-[11px] text-slate-400">{{ $activity->due_at->format('H:i') }}</p>
                    @else
                        <span class="text-xs text-slate-300">—</span>
                    @endif
                </div>
            </div>
        @empty
            <x-empty-state icon="bi-calendar-check" title="No activities" message="You're all caught up!" />
        @endforelse

        <div class="border-t border-slate-100 px-5 py-3">
            <a href="{{ route('activities.index') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">
                View all activities <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </x-card>

    {{-- Recent quotations --}}
    <x-card :padding="false">
        <x-slot:title>Recent Quotations</x-slot:title>
        @forelse ($recentQuotations as $quo)
            <a href="{{ route('quotations.show', $quo) }}" class="flex items-center gap-3 border-b border-slate-50 px-5 py-3 last:border-0 hover:bg-slate-50">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-slate-800">{{ $quo->number }}</p>
                    <p class="truncate text-xs text-slate-400">{{ $quo->customer_name ?: '—' }}</p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-semibold text-slate-700">{{ money($quo->total, $quo->currency) }}</p>
                    <x-badge :color="$quo->statusColor()">{{ $quo->statusLabel() }}</x-badge>
                </div>
            </a>
        @empty
            <x-empty-state icon="bi-file-earmark-text" title="No quotations yet" />
        @endforelse
        <div class="border-t border-slate-100 px-5 py-3">
            <a href="{{ route('quotations.create') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">
                <i class="bi bi-plus-lg"></i> Create new quotation
            </a>
        </div>
    </x-card>
</div>
@endsection
