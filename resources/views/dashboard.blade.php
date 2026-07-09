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
    <x-stat-card title="Total Customers" :value="number_format($customersCount)" icon="bi-people" color="brand"
                 :href="route('customers.index')" />
    <x-stat-card title="Total Leads" :value="number_format($leadsCount)" icon="bi-funnel" color="purple"
                 :href="route('leads.index')" />
    <x-stat-card title="Total Quotations" :value="number_format($quotationsCount)" icon="bi-file-earmark-text" color="amber"
                 :sub="$acceptedCount.' accepted'" :href="route('quotations.index')" />
    <x-stat-card title="Active Quotation Value" :value="money($quotationsValue)" icon="bi-cash-stack" color="green"
                 :sub="money($quotationsMargin).' margin'" :href="route('quotations.index')" />
</div>

<div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3 lg:items-start">
    <div class="space-y-4">
        {{-- Leaderboard widget (kiri) --}}
        <x-card :padding="false" class="crm-leaderboard-widget">
        <div class="border-b border-slate-100 px-4 py-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-semibold text-slate-800">
                    <i class="bi bi-trophy text-amber-500"></i> Leaderboard
                </p>
                <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-center justify-end gap-1.5">
                    @if (auth()->user()->isAdmin())
                        <select name="leaderboard_sort" onchange="this.form.submit()"
                                class="crm-leaderboard-widget__select crm-leaderboard-widget__select--sort">
                            <option value="total" @selected($leaderboardSort === 'total')>Sort: Total</option>
                            <option value="margin" @selected($leaderboardSort === 'margin')>Sort: Margin</option>
                            <option value="percent" @selected($leaderboardSort === 'percent')>Sort: % Target</option>
                        </select>
                    @endif
                    <select name="leaderboard_period" onchange="this.form.submit()"
                            class="crm-leaderboard-widget__select">
                        <option value="year" @selected($leaderboardPeriod === 'year')>This Year</option>
                        <option value="month" @selected($leaderboardPeriod === 'month')>This Month</option>
                        <option value="alltime" @selected($leaderboardPeriod === 'alltime')>All Time</option>
                    </select>
                </form>
            </div>
        </div>

        @if ($salesLeaderboard->isNotEmpty())
            <div @class(['crm-leaderboard-table', 'crm-leaderboard-table--sales' => auth()->user()->isSales()])>
                <div class="crm-leaderboard-table__head">
                    <span></span>
                    <span>Sales</span>
                    @if (auth()->user()->isAdmin())
                        <span>Total</span>
                        <span>Margin</span>
                    @else
                        <span>Percentage</span>
                    @endif
                </div>
                <ul class="divide-y divide-slate-50">
                    @foreach ($salesLeaderboard as $entry)
                        <li @class(['crm-leaderboard-table__row', 'bg-brand-50/50' => $entry['user_id'] === auth()->id()])>
                            @if ($entry['rank'] === 1)
                                <span class="crm-leaderboard-rank crm-leaderboard-rank--gold crm-leaderboard-rank--sm">{{ $entry['rank'] }}</span>
                            @elseif ($entry['rank'] === 2)
                                <span class="crm-leaderboard-rank crm-leaderboard-rank--silver crm-leaderboard-rank--sm">{{ $entry['rank'] }}</span>
                            @elseif ($entry['rank'] === 3)
                                <span class="crm-leaderboard-rank crm-leaderboard-rank--bronze crm-leaderboard-rank--sm">{{ $entry['rank'] }}</span>
                            @else
                                <span class="crm-leaderboard-rank crm-leaderboard-rank--sm">{{ $entry['rank'] }}</span>
                            @endif
                            <div class="crm-leaderboard-table__sales min-w-0">
                                <p class="truncate text-xs font-medium text-slate-800">
                                    {{ $entry['name'] }}
                                    @if ($entry['user_id'] === auth()->id())
                                        <span class="text-brand-600">· You</span>
                                    @endif
                                </p>
                            </div>
                            @if (auth()->user()->isAdmin())
                                <span class="crm-leaderboard-table__value" title="{{ money($entry['won_total']) }}">
                                    {{ money_compact($entry['won_total']) }}
                                </span>
                                <span @class([
                                    'crm-leaderboard-table__value',
                                    'text-green-700' => $entry['won_margin'] >= 0,
                                    'text-red-600' => $entry['won_margin'] < 0,
                                ]) title="{{ money($entry['won_margin']) }}">
                                    {{ money_compact($entry['won_margin']) }}
                                </span>
                            @else
                                <span class="crm-leaderboard-table__value text-brand-700"
                                      title="{{ money($entry['won_total']) }} / {{ money($entry['sales_target']) }}">
                                    {{ number_format($entry['target_progress'], 1, ',', '.') }}%
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @else
            <p class="px-4 py-8 text-center text-xs text-slate-400">Belum ada deal Closed Won.</p>
        @endif
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
                <div class="mb-3 flex h-2 w-full overflow-hidden rounded-full bg-slate-100">
                    @foreach ($statusMeta as $key => [$label, $bar])
                        @if (($quotationStatus[$key] ?? 0) > 0)
                            <div class="{{ $bar }}" style="width: {{ (($quotationStatus[$key] ?? 0)/$totalQuo)*100 }}%"></div>
                        @endif
                    @endforeach
                </div>
                <ul class="space-y-1.5 text-xs">
                    @foreach ($statusMeta as $key => [$label, $bar])
                        <li class="flex items-center justify-between">
                            <span class="flex items-center gap-1.5 text-slate-600">
                                <span class="h-2 w-2 rounded-full {{ $bar }}"></span> {{ $label }}
                            </span>
                            <span class="font-medium text-slate-700">{{ $quotationStatus[$key] ?? 0 }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="py-4 text-center text-xs text-slate-400">No quotations yet.</p>
            @endif
        </x-card>
    </div>

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
                    <i class="bi {{ ['call'=>'bi-telephone','meeting'=>'bi-people','email'=>'bi-envelope','task'=>'bi-check2-square','followup'=>'bi-arrow-repeat','note'=>'bi-sticky','event_training'=>'bi-calendar-event'][$activity->type] ?? 'bi-check2-square' }}"></i>
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
