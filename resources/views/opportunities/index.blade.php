@extends('layouts.app')
@section('title', 'Opportunities')

@php
    $isList = ($view ?? 'kanban') === 'list';
    $baseQuery = array_filter([
        'period' => ($period ?? 'year') !== 'year' ? $period : null,
        'assigned_user_id' => $selectedUserId ?: null,
        'q' => ($search ?? '') !== '' ? $search : null,
        'stage' => ($stageFilter ?? '') !== '' ? $stageFilter : null,
        'company' => ($companyFilter ?? '') !== '' ? $companyFilter : null,
    ], fn ($v) => $v !== null && $v !== '');
    $hasExtraFilters = ($search ?? '') !== '' || ($stageFilter ?? '') !== '' || ($companyFilter ?? '') !== '';
    $hasFilters = $selectedUserId || ($period ?? 'year') !== 'year' || $hasExtraFilters;
@endphp

@section('content')
<x-page-header title="Opportunity / Deal" :description="'Sales pipeline & deal progress · '.$periodLabel">
    <x-slot:actions>
        <div class="inline-flex rounded-lg border border-slate-200 bg-white p-0.5 shadow-sm">
            <a href="{{ route('opportunities.index', $baseQuery) }}"
               @class([
                   'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition',
                   'bg-brand-600 text-white shadow-sm' => ! $isList,
                   'text-slate-600 hover:bg-slate-50' => $isList,
               ])>
                <i class="bi bi-kanban"></i> Kanban
            </a>
            <a href="{{ route('opportunities.index', $baseQuery + ['view' => 'list']) }}"
               @class([
                   'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition',
                   'bg-brand-600 text-white shadow-sm' => $isList,
                   'text-slate-600 hover:bg-slate-50' => ! $isList,
               ])>
                <i class="bi bi-list-ul"></i> List
            </a>
        </div>
        @if (auth()->user()->canCreateOpportunity())
            <x-btn href="{{ route('opportunities.create') }}" icon="bi-plus-lg">New Opportunity</x-btn>
        @endif
    </x-slot:actions>
</x-page-header>

{{-- Filter --}}
<x-card class="mb-4" :padding="false">
    <form method="GET" action="{{ route('opportunities.index') }}" class="crm-opp-filters">
        @if ($isList)
            <input type="hidden" name="view" value="list">
        @endif

        <div @class([
            'crm-opp-filters__grid',
            'crm-opp-filters__grid--admin' => auth()->user()->isAdmin(),
        ])>
            <div class="crm-opp-filters__field">
                <label class="crm-label">Cari deal</label>
                <div class="crm-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="q" value="{{ $search }}" placeholder="Nama opportunity..."
                           class="crm-field" autocomplete="off">
                </div>
            </div>

            <div class="crm-opp-filters__field">
                <label class="crm-label">Status / Stage</label>
                <select name="stage" class="select2 select2-compact w-full" data-placeholder="Semua stage">
                    <option value="">Semua stage</option>
                    @foreach ($stages as $st)
                        <option value="{{ $st }}" @selected($stageFilter === $st)>{{ $st }}</option>
                    @endforeach
                </select>
            </div>

            <div class="crm-opp-filters__field">
                <label class="crm-label">Nama perusahaan</label>
                <select name="company" class="select2 select2-compact w-full" data-placeholder="Semua perusahaan">
                    <option value="">Semua perusahaan</option>
                    @foreach ($companies as $co)
                        <option value="{{ $co }}" @selected($companyFilter === $co)>{{ $co }}</option>
                    @endforeach
                </select>
            </div>

            @if (auth()->user()->isAdmin())
                <div class="crm-opp-filters__field">
                    <label class="crm-label">Assigned User</label>
                    <select name="assigned_user_id" class="select2 select2-search w-full" data-placeholder="All sales">
                        <option value="">All sales</option>
                        @foreach ($salesUsers as $user)
                            <option value="{{ $user->id }}" @selected($selectedUserId === $user->id)>
                                {{ $user->display_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="crm-opp-filters__field">
                <label class="crm-label">Periode</label>
                <select name="period" class="select2 select2-compact w-full" data-placeholder="Periode">
                    <option value="year" @selected($period === 'year')>Tahun ini</option>
                    <option value="month" @selected($period === 'month')>Bulan ini</option>
                    <option value="3months" @selected($period === '3months')>3 Bulan</option>
                    <option value="6months" @selected($period === '6months')>6 Bulan</option>
                    <option value="alltime" @selected($period === 'alltime')>All Time</option>
                </select>
            </div>
        </div>

        <div class="crm-opp-filters__actions">
            <div class="crm-opp-filters__buttons">
                <x-btn type="submit" variant="primary" icon="bi-funnel">Filter</x-btn>
                @if ($hasFilters)
                    <x-btn href="{{ route('opportunities.index', $isList ? ['view' => 'list'] : []) }}" variant="ghost">Reset</x-btn>
                @endif
            </div>
            <p class="crm-opp-filters__total">
                Total: <strong>{{ number_format($summary['total']) }}</strong>
                <span>· {{ $periodLabel }}</span>
            </p>
        </div>
    </form>
</x-card>

{{-- Summary --}}
<div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat-card title="Total Deals" :value="number_format($summary['total'])" icon="bi-briefcase" color="brand"
                 :sub="money($summary['total_value']) . ' total value'" />
    <x-stat-card title="Open Pipeline" :value="money($summary['open_value'])" icon="bi-graph-up-arrow" color="amber"
                 :sub="$summary['open_count'] . ' active deals'" />
    <x-stat-card title="Closed Won" :value="money($summary['won_value'])" icon="bi-trophy" color="green"
                 :sub="$summary['won_count'] . ' deals won'" />
    <x-stat-card title="Win Rate" :value="$summary['win_rate'] !== null ? $summary['win_rate'] . '%' : '—'" icon="bi-percent" color="purple"
                 :sub="$summary['lost_count'] . ' lost · ' . money($summary['lost_value'])" />
</div>

{{-- Stage summary bar --}}
@unless ($isList && $stageFilter !== '')
<x-card class="mb-4" :padding="false">
    <div class="crm-pipeline-summary">
        @foreach ($kanbanStages as $stage)
            @php
                $stats = $summary['stage_stats'][$stage];
                $isWon = $stage === \App\Models\Espo\Opportunity::WON_STAGE;
            @endphp
            <div class="crm-pipeline-summary__item">
                <p class="crm-pipeline-summary__label">{{ $stage }}</p>
                <p class="crm-pipeline-summary__count">{{ $stats['count'] }}</p>
                <p class="crm-pipeline-summary__value {{ $isWon ? 'text-green-600' : '' }}">{{ money($stats['value']) }}</p>
            </div>
        @endforeach
    </div>
</x-card>
@endunless

@if ($isList)
    {{-- List view --}}
    <x-card :padding="false">
        @if ($opportunities->count())
            <div class="crm-table-wrap">
                <table class="crm-table">
                    <thead>
                        <tr>
                            <th>Opportunity</th>
                            <th>Customer</th>
                            <th>Perusahaan</th>
                            <th>Stage</th>
                            <th class="text-right">Nominal</th>
                            <th>Close date</th>
                            @if (auth()->user()->isAdmin())
                                <th>Sales</th>
                            @endif
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($opportunities as $opp)
                            @php $hasDuplicates = ! empty($duplicateMap[$opp->id] ?? []); @endphp
                            <tr @class(['bg-amber-50/40' => $hasDuplicates])>
                                <td>
                                    <a href="{{ route('opportunities.show', $opp) }}" class="font-medium text-slate-800 hover:text-brand-600">
                                        {{ $opp->name ?: '—' }}
                                    </a>
                                    @if ($hasDuplicates)
                                        <span class="mt-0.5 block text-[11px] font-medium text-amber-600">
                                            <i class="bi bi-exclamation-triangle"></i> Kemungkinan duplikat
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if ($opp->account)
                                        <a href="{{ route('customers.show', $opp->account) }}" class="text-brand-600 hover:underline">
                                            {{ $opp->account->name }}
                                        </a>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="text-slate-600">
                                    {{ $opp->company ?: '—' }}
                                </td>
                                <td>
                                    @php
                                        $stageClass = match ($opp->stage) {
                                            \App\Models\Espo\Opportunity::WON_STAGE => 'bg-green-50 text-green-700 border-green-200',
                                            \App\Models\Espo\Opportunity::LOST_STAGE => 'bg-red-50 text-red-700 border-red-200',
                                            default => 'bg-slate-50 text-slate-700 border-slate-200',
                                        };
                                    @endphp
                                    <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium {{ $stageClass }}">
                                        {{ $opp->stage }}
                                    </span>
                                </td>
                                <td class="text-right tabular-nums font-medium text-slate-800">
                                    {{ money($opp->amount, $opp->amount_currency ?: 'IDR') }}
                                </td>
                                <td class="text-slate-600">
                                    @if ($opp->close_date)
                                        {{ \Illuminate\Support\Carbon::parse($opp->close_date)->translatedFormat('d M Y') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                @if (auth()->user()->isAdmin())
                                    <td class="text-slate-600">
                                        {{ $opp->assignedUser?->display_name ?: '—' }}
                                    </td>
                                @endif
                                <td class="text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <a href="{{ route('opportunities.show', $opp) }}"
                                           class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-brand-600" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('opportunities.edit', $opp) }}"
                                           class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-brand-600" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        @if (auth()->user()->canDeleteOpportunity())
                                            <form method="POST" action="{{ route('opportunities.destroy', $opp) }}"
                                                  onsubmit="return confirm('Hapus opportunity ini? Tindakan tidak bisa dibatalkan.')"
                                                  class="inline">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="rounded p-1.5 text-red-500 hover:bg-red-50" title="Hapus">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($opportunities->hasPages())
                <div class="crm-table-footer">
                    {{ $opportunities->links() }}
                </div>
            @endif
        @else
            <div class="px-5 py-12 text-center text-sm text-slate-400">
                Tidak ada opportunity sesuai filter.
                @if ($hasFilters)
                    <a href="{{ route('opportunities.index', ['view' => 'list']) }}" class="ml-1 text-brand-600 hover:underline">Reset filter</a>
                @endif
            </div>
        @endif
    </x-card>
@else
    {{-- Kanban board --}}
    <div class="crm-kanban-board">
        <div class="crm-kanban">
            @foreach ($kanbanStages as $index => $stage)
                @php
                    $cards = $grouped[$stage];
                    $isWon = $stage === \App\Models\Espo\Opportunity::WON_STAGE;
                    $isLast = $index === count($kanbanStages) - 1;
                @endphp
                <div class="crm-kanban-col" x-data="{ visible: 5, step: 5, total: {{ $cards->count() }} }">
                    <div @class([
                        'crm-kanban-header',
                        'crm-kanban-header--first' => $index === 0,
                        'crm-kanban-header--last' => $isLast,
                        'crm-kanban-header--won' => $isWon,
                    ])>
                        <span>{{ $stage }}</span>
                        <span class="crm-kanban-header__count">{{ $cards->count() }}</span>
                    </div>

                    <div class="crm-kanban-body">
                        @forelse ($cards as $opp)
                            @php $hasDuplicates = ! empty($duplicateMap[$opp->id] ?? []); @endphp
                            <div class="crm-kanban-card {{ $hasDuplicates ? 'ring-1 ring-amber-300' : '' }}" x-data="{ open: false }" x-show="{{ $loop->index }} < visible">
                                <div class="crm-kanban-card__top">
                                    <a href="{{ route('opportunities.show', $opp) }}" class="crm-kanban-card__title" title="{{ $opp->name }}">
                                        {{ $opp->name }}
                                    </a>
                                    <div class="relative">
                                        <button type="button" @click="open = !open" class="crm-kanban-card__menu" title="Actions">
                                            <i class="bi bi-chevron-down"></i>
                                        </button>
                                        <div x-show="open" x-cloak @click.outside="open = false"
                                             class="absolute right-0 z-10 mt-1 w-44 rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                                            <a href="{{ route('opportunities.show', $opp) }}" class="block px-3 py-1.5 text-xs hover:bg-slate-50">
                                                <i class="bi bi-eye mr-1"></i> View
                                            </a>
                                            <a href="{{ route('opportunities.edit', $opp) }}" class="block px-3 py-1.5 text-xs hover:bg-slate-50">
                                                <i class="bi bi-pencil mr-1"></i> Edit
                                            </a>

                                            @php
                                                $nextStage = $opp->nextStage();
                                                $closingStages = $opp->closingStageOptions();
                                            @endphp

                                            @if ($nextStage || ! empty($closingStages))
                                                <div class="my-1 border-t border-slate-100"></div>
                                                @if ($nextStage)
                                                    <form method="POST" action="{{ route('opportunities.stage', $opp) }}" @submit="open = false">
                                                        @csrf @method('PATCH')
                                                        <input type="hidden" name="stage" value="{{ $nextStage }}">
                                                        <button type="submit" class="block w-full px-3 py-1.5 text-left text-xs text-brand-600 hover:bg-brand-50">
                                                            <i class="bi bi-arrow-right-circle mr-1"></i> Move to {{ $nextStage }}
                                                        </button>
                                                    </form>
                                                @endif
                                                @foreach ($closingStages as $closeStage)
                                                    @if ($closeStage === \App\Models\Espo\Opportunity::WON_STAGE)
                                                        <form method="POST" action="{{ route('opportunities.stage', $opp) }}" @submit="open = false">
                                                            @csrf @method('PATCH')
                                                            <input type="hidden" name="stage" value="{{ $closeStage }}">
                                                            <button type="submit" class="block w-full px-3 py-1.5 text-left text-xs text-green-600 hover:bg-green-50">
                                                                <i class="bi bi-trophy mr-1"></i> Closed Won
                                                            </button>
                                                        </form>
                                                    @else
                                                        <x-opportunity-closed-lost-form :opportunity="$opp">
                                                            <x-slot:trigger>
                                                                <button type="button" @click="open = false" class="block w-full px-3 py-1.5 text-left text-xs text-red-600 hover:bg-red-50">
                                                                    <i class="bi bi-x-circle mr-1"></i> Closed Lost
                                                                </button>
                                                            </x-slot:trigger>
                                                        </x-opportunity-closed-lost-form>
                                                    @endif
                                                @endforeach
                                                @if ($opp->stage === 'Negotiation' && ! $opp->canMoveToClosedWon())
                                                    <p class="px-3 py-1.5 text-[11px] text-amber-700">
                                                        <i class="bi bi-lock"></i> Closed Won terkunci
                                                    </p>
                                                @endif
                                            @endif
                                            @if (auth()->user()->canDeleteOpportunity())
                                            <form method="POST" action="{{ route('opportunities.destroy', $opp) }}" onsubmit="return confirm('Hapus deal ini? Tindakan tidak bisa dibatalkan.')" @submit="open = false">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="block w-full px-3 py-1.5 text-left text-xs text-red-600 hover:bg-red-50">
                                                    <i class="bi bi-trash mr-1"></i> Hapus deal
                                                </button>
                                            </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                @if ($hasDuplicates)
                                    <p class="mb-1 text-[11px] font-medium text-amber-600" title="Deal serupa ditemukan di stage lain">
                                        <i class="bi bi-exclamation-triangle"></i> Kemungkinan duplikat
                                    </p>
                                @endif

                                <p class="crm-kanban-card__amount">{{ money($opp->amount, $opp->amount_currency ?: 'IDR') }}</p>

                                <p class="crm-kanban-card__account">
                                    @if ($opp->account)
                                        <a href="{{ route('customers.show', $opp->account) }}">{{ $opp->account->name }}</a>
                                    @else
                                        —
                                    @endif
                                </p>
                                @if ($opp->company)
                                    <p class="crm-kanban-card__company" title="{{ $opp->company }}">{{ $opp->company }}</p>
                                @endif

                                <div class="crm-kanban-card__footer">
                                    <span>
                                        @if ($opp->close_date)
                                            {{ \Illuminate\Support\Carbon::parse($opp->close_date)->translatedFormat('d M') }}
                                        @else
                                            —
                                        @endif
                                    </span>
                                    @if (auth()->user()->isAdmin() && $opp->assignedUser)
                                        <span class="crm-kanban-card__sales" title="{{ $opp->assignedUser->display_name }}">
                                            {{ initials($opp->assignedUser->display_name) }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="crm-kanban-empty">No deals</div>
                        @endforelse

                        @if ($cards->isNotEmpty())
                            <button
                                type="button"
                                class="crm-kanban-more"
                                x-show="visible < total"
                                x-cloak
                                @click="visible = Math.min(visible + step, total)"
                                title="Load more"
                            >
                                <i class="bi bi-three-dots"></i>
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
@endsection
