@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
@include('partials.deadline-alert')

@php
    $dashQuery = array_filter([
        'period' => $period,
        'leaderboard_period' => $leaderboardPeriod,
        'leaderboard_sort' => $leaderboardSort,
        'catalog_sort' => $catalogSort ?? null,
        'sales' => $selectedSalesId,
    ], fn ($v) => filled($v));
@endphp

<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h2 class="crm-page-title">Hi, {{ Str::before(auth()->user()->name, ' ') }}!</h2>
        <p class="crm-page-desc">
            Ringkasan aktivitas penjualan untuk {{ $periodLabel }}
            @if ($selectedSales)
                · <span class="font-medium text-brand-700">{{ $selectedSales->display_name }}</span>
            @endif
            .
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('dashboard', array_merge($dashQuery, ['detail' => 1, 'detail_start' => $detailStart->toDateString(), 'detail_end' => $detailEnd->toDateString()])) }}"
           class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
            <i class="bi bi-bar-chart-line"></i> Detail
        </a>
        <form method="GET" action="{{ route('dashboard') }}" class="flex items-center gap-2">
            @if ($leaderboardPeriod)<input type="hidden" name="leaderboard_period" value="{{ $leaderboardPeriod }}">@endif
            @if ($leaderboardSort)<input type="hidden" name="leaderboard_sort" value="{{ $leaderboardSort }}">@endif
            @if (! empty($catalogSort))<input type="hidden" name="catalog_sort" value="{{ $catalogSort }}">@endif
            @if ($selectedSalesId)<input type="hidden" name="sales" value="{{ $selectedSalesId }}">@endif
            <label class="text-xs font-medium text-slate-500">Periode</label>
            <select name="period" onchange="this.form.submit()"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-200">
                <option value="year" @selected($period === 'year')>Tahun ini</option>
                <option value="month" @selected($period === 'month')>Bulan ini</option>
                <option value="3months" @selected($period === '3months')>3 Bulan</option>
                <option value="6months" @selected($period === '6months')>6 Bulan</option>
                <option value="alltime" @selected($period === 'alltime')>All Time</option>
            </select>
        </form>
    </div>
</div>

@php
    $catalogUser = auth()->user();
    $showCatalogShortcuts = $catalogUser
        && $catalogUser->canManageCatalog()
        && ! $catalogUser->canAccessAdministration();
@endphp
@if ($showCatalogShortcuts)
    <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
        @if ($catalogUser->canManageBrands())
            <a href="{{ route('brands.index') }}"
               class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-brand-200 hover:shadow-md">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-brand-50 text-brand-600 group-hover:bg-brand-100">
                    <i class="bi bi-tags text-lg"></i>
                </span>
                <span>
                    <span class="block text-sm font-semibold text-slate-800">Brands</span>
                    <span class="block text-xs text-slate-500">Tambah & kelola master brand</span>
                </span>
            </a>
        @endif
        @if ($catalogUser->canManageCategories())
            <a href="{{ route('categories.index') }}"
               class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-brand-200 hover:shadow-md">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-brand-50 text-brand-600 group-hover:bg-brand-100">
                    <i class="bi bi-folder text-lg"></i>
                </span>
                <span>
                    <span class="block text-sm font-semibold text-slate-800">Categories</span>
                    <span class="block text-xs text-slate-500">Tambah & kelola kategori produk</span>
                </span>
            </a>
        @endif
        @if ($catalogUser->canManageVendors())
            <a href="{{ route('vendors.index') }}"
               class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-brand-200 hover:shadow-md">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-brand-50 text-brand-600 group-hover:bg-brand-100">
                    <i class="bi bi-truck text-lg"></i>
                </span>
                <span>
                    <span class="block text-sm font-semibold text-slate-800">Vendors</span>
                    <span class="block text-xs text-slate-500">Tambah & kelola master vendor</span>
                </span>
            </a>
        @endif
    </div>
@endif

@if ($showDetail)
    <x-card class="mb-4" title="Detail Dashboard — Rekapan Penjualan & Margin">
        <x-slot:action>
            <a href="{{ route('dashboard', $dashQuery) }}" class="text-xs font-medium text-slate-500 hover:text-slate-700">
                <i class="bi bi-x-lg"></i> Tutup
            </a>
        </x-slot:action>

        <form method="GET" action="{{ route('dashboard') }}" class="mb-4 flex flex-wrap items-end gap-3">
            <input type="hidden" name="detail" value="1">
            <input type="hidden" name="period" value="{{ $period }}">
            @if ($leaderboardPeriod)<input type="hidden" name="leaderboard_period" value="{{ $leaderboardPeriod }}">@endif
            @if ($leaderboardSort)<input type="hidden" name="leaderboard_sort" value="{{ $leaderboardSort }}">@endif
            @if (! empty($catalogSort))<input type="hidden" name="catalog_sort" value="{{ $catalogSort }}">@endif
            @if ($selectedSalesId)<input type="hidden" name="sales" value="{{ $selectedSalesId }}">@endif

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Start tanggal</label>
                <input type="date" name="detail_start" value="{{ $detailStart->toDateString() }}"
                       class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-700 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-200">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">End tanggal</label>
                <input type="date" name="detail_end" value="{{ $detailEnd->toDateString() }}"
                       class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-700 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-200">
            </div>
            <button type="submit"
                    class="rounded-lg bg-brand-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-700">
                Tampilkan
            </button>
        </form>

        @php
            $recapTotal = $dailyRecap->sum('won_total');
            $recapMargin = $dailyRecap->sum('won_margin');
            $recapDeals = $dailyRecap->sum('deal_count');
        @endphp

        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-lg border border-slate-100 bg-slate-50 px-4 py-3">
                <p class="text-xs text-slate-500">Total Penjualan</p>
                <p class="mt-1 text-lg font-bold text-slate-800">{{ money($recapTotal) }}</p>
            </div>
            <div class="rounded-lg border border-slate-100 bg-slate-50 px-4 py-3">
                <p class="text-xs text-slate-500">Total Margin</p>
                <p class="mt-1 text-lg font-bold text-green-700">{{ money($recapMargin) }}</p>
            </div>
            <div class="rounded-lg border border-slate-100 bg-slate-50 px-4 py-3">
                <p class="text-xs text-slate-500">Jumlah Deal Closed Won</p>
                <p class="mt-1 text-lg font-bold text-slate-800">{{ number_format($recapDeals) }}</p>
            </div>
        </div>

        @if ($dailyRecap->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                            <th class="pb-2 pr-4 font-medium">Tanggal</th>
                            <th class="pb-2 pr-4 font-medium text-right">Deal</th>
                            <th class="pb-2 pr-4 font-medium text-right">Penjualan</th>
                            <th class="pb-2 font-medium text-right">Margin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach ($dailyRecap as $row)
                            <tr>
                                <td class="py-2.5 pr-4 font-medium text-slate-700">
                                    {{ $row['day']->translatedFormat('d M Y') }}
                                </td>
                                <td class="py-2.5 pr-4 text-right text-slate-600">{{ $row['deal_count'] }}</td>
                                <td class="py-2.5 pr-4 text-right font-medium text-slate-800">{{ money($row['won_total']) }}</td>
                                <td class="py-2.5 text-right font-medium {{ $row['won_margin'] >= 0 ? 'text-green-700' : 'text-red-600' }}">
                                    {{ money($row['won_margin']) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="py-6 text-center text-sm text-slate-400">
                Tidak ada Closed Won pada rentang tanggal ini
                @if ($selectedSales) untuk {{ $selectedSales->display_name }} @endif.
            </p>
        @endif
    </x-card>
@endif

{{-- Nominal angka --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat-card title="Open Pipeline" :value="money($openPipeline)" icon="bi-kanban" color="brand"
                 :href="route('opportunities.index')" />
    <x-stat-card title="Active Quotation" :value="money($quotationsValue)" icon="bi-file-earmark-text" color="amber"
                 :sub="$sentCount.' sent'" :href="route('quotations.index')" />
    <x-stat-card title="Total Penjualan (Closed Won)" :value="money($wonTotal)" icon="bi-trophy" color="green"
                 :href="route('opportunities.index')" />
    <x-stat-card title="Margin" :value="money($wonMargin)" icon="bi-graph-up-arrow" color="purple"
                 :href="route('opportunities.index')" />
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
                <form method="GET" action="{{ route('dashboard') }}" class="flex flex-nowrap items-center justify-end gap-1.5">
                    <input type="hidden" name="period" value="{{ $period }}">
                    @if ($selectedSalesId)<input type="hidden" name="sales" value="{{ $selectedSalesId }}">@endif
                    @if (! empty($catalogSort))<input type="hidden" name="catalog_sort" value="{{ $catalogSort }}">@endif
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
                        <option value="3months" @selected($leaderboardPeriod === '3months')>3 Months</option>
                        <option value="6months" @selected($leaderboardPeriod === '6months')>6 Months</option>
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
                        @php
                            $isSelected = $selectedSalesId === $entry['user_id'];
                            $salesUrl = route('dashboard', array_merge(
                                array_filter([
                                    'period' => $period,
                                    'leaderboard_period' => $leaderboardPeriod,
                                    'leaderboard_sort' => $leaderboardSort,
                                    'catalog_sort' => $catalogSort ?? null,
                                ]),
                                $isSelected ? [] : ['sales' => $entry['user_id']]
                            ));
                        @endphp
                        <li @class([
                            'crm-leaderboard-table__row',
                            'bg-brand-50/50' => $entry['user_id'] === auth()->id() && ! $isSelected,
                            'bg-brand-50 ring-1 ring-inset ring-brand-200' => $isSelected,
                        ])>
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
                                @if (! auth()->user()->isSales())
                                    <a href="{{ $salesUrl }}"
                                       class="block truncate text-xs font-medium text-slate-800 hover:text-brand-700 hover:underline"
                                       title="{{ $isSelected ? 'Tampilkan semua sales' : 'Lihat pipeline '.$entry['name'] }}">
                                        {{ $entry['name'] }}
                                        @if ($entry['user_id'] === auth()->id())
                                            <span class="text-brand-600">· You</span>
                                        @endif
                                        @if ($isSelected)
                                            <span class="text-brand-600">· aktif</span>
                                        @endif
                                    </a>
                                @else
                                    <p class="truncate text-xs font-medium text-slate-800">
                                        {{ $entry['name'] }}
                                        @if ($entry['user_id'] === auth()->id())
                                            <span class="text-brand-600">· You</span>
                                        @endif
                                    </p>
                                @endif
                                @if (! empty($entry['target_period_label']))
                                    <p class="truncate text-[10px] text-slate-400">
                                        {{ $entry['target_period_label'] }}
                                        @if (! empty($entry['target_deadline_label']))
                                            · tenggat {{ $entry['target_deadline_label'] }}
                                        @endif
                                    </p>
                                @endif
                                @if (! empty($entry['target_met']))
                                    <p class="truncate text-[10px] font-medium text-green-600" title="{{ money($entry['target_won_total']) }} / {{ money($entry['sales_target']) }}">
                                        <i class="bi bi-check-circle-fill"></i> Target terpenuhi
                                    </p>
                                @endif
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
                                <span @class([
                                    'crm-leaderboard-table__value',
                                    'text-green-700' => ! empty($entry['target_met']),
                                    'text-brand-700' => empty($entry['target_met']),
                                ]) title="{{ money($entry['target_won_total'] ?? $entry['won_total']) }} / {{ money($entry['sales_target']) }} ({{ $entry['target_period_label'] ?? '' }})">
                                    {{ number_format($entry['target_progress'], 1, ',', '.') }}%
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
            @if ($selectedSalesId && ! auth()->user()->isSales())
                <div class="border-t border-slate-100 px-4 py-2">
                    <a href="{{ route('dashboard', array_filter(['period' => $period, 'leaderboard_period' => $leaderboardPeriod, 'leaderboard_sort' => $leaderboardSort, 'catalog_sort' => $catalogSort ?? null])) }}"
                       class="text-xs font-medium text-brand-600 hover:text-brand-700">
                        <i class="bi bi-x-circle"></i> Reset filter sales
                    </a>
                </div>
            @endif
        @else
            <p class="px-4 py-8 text-center text-xs text-slate-400">Belum ada data sales di periode ini.</p>
        @endif
        </x-card>

        {{-- Quotation status --}}
        <x-card title="Quotation Status">
            @php
                $statusMeta = [
                    'draft' => ['Draft', 'bg-slate-400'],
                    'sent' => ['Sent', 'bg-blue-500'],
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
    <x-card :title="$pipelineTitle" class="lg:col-span-2">
        @if ($selectedSales && ! auth()->user()->isSales())
            <x-slot:action>
                <a href="{{ route('opportunities.index', array_filter([
                        'assigned_user_id' => $selectedSalesId,
                        'period' => $period,
                    ])) }}"
                   class="text-xs font-semibold text-brand-600 hover:text-brand-700">
                    Detail
                </a>
            </x-slot:action>
        @endif

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

        {{-- Detail pipeline deals --}}
        @if ($pipelineDetails->isNotEmpty())
            <div class="mt-5 border-t border-slate-100 pt-4">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
                    Detail Pipeline
                    @if ($selectedSales)
                        — {{ $selectedSales->display_name }}
                    @endif
                </p>
                <div class="max-h-80 space-y-1 overflow-y-auto">
                    @foreach ($pipelineDetails as $opp)
                        <a href="{{ route('opportunities.show', $opp) }}"
                           class="flex items-center gap-3 rounded-lg px-2 py-2 transition hover:bg-slate-50">
                            @php
                                $stageColor = match ($opp->stage) {
                                    'Closed Won' => 'bg-green-100 text-green-700',
                                    'Closed Lost' => 'bg-red-100 text-red-700',
                                    'Negotiation' => 'bg-amber-100 text-amber-700',
                                    'Proposal' => 'bg-blue-100 text-blue-700',
                                    default => 'bg-slate-100 text-slate-600',
                                };
                            @endphp
                            <span class="w-24 shrink-0 truncate rounded-md px-2 py-0.5 text-[10px] font-semibold {{ $stageColor }}">
                                {{ $opp->stage }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-800">{{ $opp->name }}</p>
                                <p class="truncate text-[11px] text-slate-400">
                                    {{ optional($opp->account)->name ?: ($opp->company ?: '—') }}
                                </p>
                            </div>
                            <span class="shrink-0 text-xs font-semibold text-slate-700">{{ money($opp->amount) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @elseif ($selectedSales)
            <p class="mt-5 border-t border-slate-100 pt-4 text-center text-sm text-slate-400">
                Tidak ada deal di pipeline untuk {{ $selectedSales->display_name }} pada periode ini.
            </p>
        @endif
    </x-card>
</div>

@if (auth()->user()->isSuperAdmin())
    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        @include('dashboard._catalog-leaderboard', [
            'title' => 'Leaderboard Brand',
            'icon' => 'bi-tags',
            'type' => 'brand',
            'nameLabel' => 'Brand',
            'entries' => $brandLeaderboard,
            'emptyMessage' => 'Belum ada data brand Closed Won di periode ini.',
        ])
        @include('dashboard._catalog-leaderboard', [
            'title' => 'Leaderboard Category',
            'icon' => 'bi-grid-3x3-gap',
            'type' => 'category',
            'nameLabel' => 'Category',
            'entries' => $categoryLeaderboard,
            'emptyMessage' => 'Belum ada data category Closed Won di periode ini.',
        ])
    </div>
@endif

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
                        @if ($activity->isEventTraining() && $activity->approvalLabel())
                            &middot; {{ $activity->approvalLabel() }}
                        @endif
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
