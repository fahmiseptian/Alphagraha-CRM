@extends('layouts.app')
@section('title', 'Opportunities')

@section('content')
<x-page-header title="Opportunity / Deal" description="Sales pipeline & deal progress">
    <x-slot:actions>
        @if (auth()->user()->canCreateOpportunity())
            <x-btn href="{{ route('opportunities.create') }}" icon="bi-plus-lg">New Opportunity</x-btn>
        @endif
    </x-slot:actions>
</x-page-header>

{{-- Filter --}}
@if (auth()->user()->isAdmin())
    <x-card class="mb-4" :padding="false">
        <form method="GET" action="{{ route('opportunities.index') }}" class="crm-kanban-toolbar">
            <div class="min-w-0 flex-1 sm:max-w-md">
                <label class="crm-label">Assigned User</label>
                <select name="assigned_user_id" class="select2 select2-search" data-placeholder="All sales">
                    <option value="">All sales</option>
                    @foreach ($salesUsers as $user)
                        <option value="{{ $user->id }}" @selected($selectedUserId === $user->id)>
                            {{ $user->display_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex shrink-0 items-end gap-2">
                <x-btn type="submit" variant="primary" icon="bi-funnel">Filter</x-btn>
                @if ($selectedUserId)
                    <x-btn href="{{ route('opportunities.index') }}" variant="ghost">Reset</x-btn>
                @endif
                <p class="crm-kanban-total">Total: <strong>{{ number_format($summary['total']) }}</strong></p>
            </div>
        </form>
    </x-card>
@endif

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
                                                <form method="POST" action="{{ route('opportunities.stage', $opp) }}" @submit="open = false">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="stage" value="{{ $closeStage }}">
                                                    <button type="submit" @class([
                                                        'block w-full px-3 py-1.5 text-left text-xs hover:bg-slate-50',
                                                        'text-green-600 hover:bg-green-50' => $closeStage === \App\Models\Espo\Opportunity::WON_STAGE,
                                                        'text-red-600 hover:bg-red-50' => $closeStage === \App\Models\Espo\Opportunity::LOST_STAGE,
                                                    ])>
                                                        @if ($closeStage === \App\Models\Espo\Opportunity::WON_STAGE)
                                                            <i class="bi bi-trophy mr-1"></i> Closed Won
                                                        @else
                                                            <i class="bi bi-x-circle mr-1"></i> Closed Lost
                                                        @endif
                                                    </button>
                                                </form>
                                            @endforeach
                                        @endif
                                        <form method="POST" action="{{ route('opportunities.destroy', $opp) }}" onsubmit="return confirm('Hapus deal ini? Tindakan tidak bisa dibatalkan.')" @submit="open = false">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="block w-full px-3 py-1.5 text-left text-xs text-red-600 hover:bg-red-50">
                                                <i class="bi bi-trash mr-1"></i> Hapus deal
                                            </button>
                                        </form>
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
                                    {{ $opp->company ?: '—' }}
                                @endif
                            </p>

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
@endsection
