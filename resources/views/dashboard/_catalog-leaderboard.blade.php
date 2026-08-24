<x-card :padding="false" class="crm-leaderboard-widget">
    <div class="border-b border-slate-100 px-4 py-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm font-semibold text-slate-800">
                <i class="bi {{ $icon }} text-amber-500"></i> {{ $title }}
            </p>
            <form method="GET" action="{{ route('dashboard') }}" class="flex flex-nowrap items-center justify-end gap-1.5">
                <input type="hidden" name="period" value="{{ $period }}">
                @if ($leaderboardSort)<input type="hidden" name="leaderboard_sort" value="{{ $leaderboardSort }}">@endif
                @if ($selectedSalesId)<input type="hidden" name="sales" value="{{ $selectedSalesId }}">@endif
                <select name="catalog_sort" onchange="this.form.submit()"
                        class="crm-leaderboard-widget__select crm-leaderboard-widget__select--sort">
                    <option value="total" @selected(($catalogSort ?? 'total') === 'total')>Sort: Total</option>
                    <option value="count" @selected(($catalogSort ?? '') === 'count')>Sort: Oppti</option>
                </select>
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
        @if ($selectedSales)
            <p class="mt-1 text-[11px] text-slate-400">Filter: {{ $selectedSales->display_name }}</p>
        @endif
    </div>

    @if ($entries->isNotEmpty())
        <div class="crm-leaderboard-table crm-leaderboard-table--catalog">
            <div class="crm-leaderboard-table__head">
                <span></span>
                <span>{{ $nameLabel }}</span>
                <span>Jumlah Oppti</span>
                <span>Total</span>
            </div>
            <ul class="divide-y divide-slate-50">
                @foreach ($entries as $entry)
                    @php
                        $catalogUrl = route('dashboard.catalog', array_filter([
                            'type' => $type,
                            'name' => $entry['name'],
                            'leaderboard_period' => $leaderboardPeriod,
                            'period' => $period,
                            'sales' => $selectedSalesId,
                            'catalog_sort' => $catalogSort ?? null,
                        ], fn ($value) => filled($value)));
                    @endphp
                    <li>
                        <a href="{{ $catalogUrl }}" class="crm-leaderboard-table__row" title="Lihat opportunity {{ $entry['name'] }}">
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
                                <p class="truncate text-xs font-medium text-slate-800" title="{{ $entry['name'] }}">
                                    {{ $entry['name'] }}
                                </p>
                            </div>
                            <span class="crm-leaderboard-table__value" title="{{ number_format($entry['deal_count']) }} opportunity">
                                {{ number_format($entry['deal_count']) }}
                            </span>
                            <span class="crm-leaderboard-table__value" title="{{ money($entry['won_total']) }}">
                                {{ money_compact($entry['won_total']) }}
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <p class="px-4 py-8 text-center text-xs text-slate-400">{{ $emptyMessage }}</p>
    @endif
</x-card>
