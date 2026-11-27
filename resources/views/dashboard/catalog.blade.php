@extends('layouts.app')
@section('title', $typeLabel.' '.$displayName)

@section('content')
<x-page-header
    :title="$typeLabel.': '.$displayName"
    :description="'Opportunity yang memakai '.$typeLabel.' ini · '.$periodLabel"
    :back="route('dashboard.catalog-index', $indexQuery ?? ['type' => $type, 'leaderboard_period' => $period])"
    :back-label="'Kembali ke daftar '.strtolower($typeLabel)">
    <x-slot:actions>
        <form method="GET" action="{{ route('dashboard.catalog') }}" class="flex items-center gap-2">
            <input type="hidden" name="type" value="{{ $type }}">
            <input type="hidden" name="name" value="{{ $name }}">
            @if ($selectedSalesId)<input type="hidden" name="sales" value="{{ $selectedSalesId }}">@endif
            @if (! empty($indexQuery['catalog_sort']))<input type="hidden" name="catalog_sort" value="{{ $indexQuery['catalog_sort'] }}">@endif
            @if (! empty($indexQuery['q']))<input type="hidden" name="q" value="{{ $indexQuery['q'] }}">@endif
            @foreach ($selectedCategories as $selectedCategory)
                <input type="hidden" name="categories[]" value="{{ $selectedCategory }}">
            @endforeach
            <label class="text-xs font-medium text-slate-500">Periode</label>
            <select name="leaderboard_period" onchange="this.form.submit()"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-200">
                <option value="year" @selected($period === 'year')>Tahun ini</option>
                <option value="month" @selected($period === 'month')>Bulan ini</option>
                <option value="3months" @selected($period === '3months')>3 Bulan</option>
                <option value="6months" @selected($period === '6months')>6 Bulan</option>
                <option value="alltime" @selected($period === 'alltime')>All Time</option>
            </select>
        </form>
    </x-slot:actions>
</x-page-header>

@if ($selectedSales)
    <p class="mb-4 text-sm text-slate-500">
        Filter sales: <span class="font-medium text-brand-700">{{ $selectedSales->display_name }}</span>
    </p>
@endif

@if ($type === 'brand' && $availableCategories->isNotEmpty())
    <div class="mb-4 rounded-xl border border-slate-200/80 bg-white px-5 py-4 shadow-sm">
        <form method="GET" action="{{ route('dashboard.catalog') }}" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="type" value="{{ $type }}">
            <input type="hidden" name="name" value="{{ $name }}">
            <input type="hidden" name="leaderboard_period" value="{{ $period }}">
            @if ($selectedSalesId)<input type="hidden" name="sales" value="{{ $selectedSalesId }}">@endif
            @if (! empty($indexQuery['catalog_sort']))<input type="hidden" name="catalog_sort" value="{{ $indexQuery['catalog_sort'] }}">@endif
            @if (! empty($indexQuery['q']))<input type="hidden" name="q" value="{{ $indexQuery['q'] }}">@endif
            <div class="min-w-[260px] flex-1">
                <label class="crm-label">Kategori</label>
                <select name="categories[]" multiple class="select2 select2-search w-full" data-auto-submit
                        data-placeholder="— Semua kategori —">
                    @foreach ($availableCategories as $categoryName)
                        <option value="{{ $categoryName }}" @selected($selectedCategories->contains($categoryName))>
                            {{ $categoryName }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-400">
                    Pilih satu atau beberapa kategori. Tabel dan kartu statistik mengikuti pilihan ini.
                </p>
            </div>
            @if ($selectedCategories->isNotEmpty())
                <a href="{{ route('dashboard.catalog', array_filter([
                        'type' => $type,
                        'name' => $name,
                        'leaderboard_period' => $period,
                        'sales' => $selectedSalesId,
                    ])) }}"
                   class="mb-5 inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
                    <i class="bi bi-x-lg text-[10px]"></i> Reset filter
                </a>
            @endif
        </form>
        @if ($selectedCategories->isNotEmpty())
            <p class="mt-2 text-xs text-slate-500">
                Filter aktif:
                @foreach ($selectedCategories as $selectedCategory)
                    <span class="ml-1 inline-flex items-center rounded-full bg-brand-50 px-2 py-0.5 font-medium text-brand-700">{{ $selectedCategory }}</span>
                @endforeach
            </p>
        @endif
    </div>
@endif

<div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
    <x-stat-card title="Jumlah Oppti" :value="number_format($totals['oppti_count'] ?? 0)" icon="bi-briefcase" color="slate"
                 sub="Semua stage" />
    <x-stat-card title="Jumlah Close Won" :value="number_format($totals['won_count'] ?? $totals['deal_count'])" icon="bi-trophy" color="brand"
                 :sub="number_format($totals['item_count']).' item'" />
    <x-stat-card title="Total Penjualan" :value="money($totals['won_total'])" icon="bi-cash-coin" color="green" />
    <x-stat-card title="Margin" :value="money($totals['won_margin'])" icon="bi-graph-up-arrow" color="purple" />
    <x-stat-card title="Rata-rata / Close Won" :value="money(($totals['won_count'] ?? $totals['deal_count']) > 0 ? $totals['won_total'] / ($totals['won_count'] ?? $totals['deal_count']) : 0)" icon="bi-calculator" color="amber" />
</div>

@if ($type === 'brand' && $charts)
    @php
        $hasMonthly = collect($charts['monthly']['total'] ?? [])->filter(fn ($v) => (float) $v !== 0.0)->isNotEmpty();
        $hasProducts = count($charts['products']['labels'] ?? []) > 0;
        $hasCategories = count($charts['categories']['labels'] ?? []) > 0;
    @endphp

    <div class="mb-4">
        <x-card title="Tren penjualan — 12 bulan terakhir">
            @if ($hasMonthly)
                <div class="h-64">
                    <canvas id="catalog-chart-monthly"></canvas>
                </div>
                <p class="mt-2 text-xs text-slate-400">
                    Mengikuti filter kategori, tidak mengikuti filter periode.
                </p>
            @else
                <p class="py-10 text-center text-sm text-slate-400">
                    Belum ada penjualan brand ini dalam 12 bulan terakhir{{ $selectedCategories->isNotEmpty() ? ' untuk kategori terpilih' : '' }}.
                </p>
            @endif
        </x-card>
    </div>

    <div class="mb-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
        <x-card title="Top produk · {{ $periodLabel }}">
            @if ($hasProducts)
                <div class="h-72">
                    <canvas id="catalog-chart-products"></canvas>
                </div>
            @else
                <p class="py-10 text-center text-sm text-slate-400">
                    Tidak ada produk terjual pada {{ $periodLabel }}{{ $selectedCategories->isNotEmpty() ? ' untuk kategori terpilih' : '' }}.
                </p>
            @endif
        </x-card>

        <x-card title="Komposisi kategori · {{ $periodLabel }}">
            @if ($hasCategories)
                <div class="h-72">
                    <canvas id="catalog-chart-categories"></canvas>
                </div>
                <p class="mt-2 text-xs text-slate-400">
                    Menampilkan semua kategori brand ini, tidak mengikuti filter kategori.
                </p>
            @else
                <p class="py-10 text-center text-sm text-slate-400">
                    Tidak ada penjualan pada {{ $periodLabel }}.
                </p>
            @endif
        </x-card>
    </div>
@endif

<x-card :padding="false">
    @if ($deals->isNotEmpty())
        <div class="crm-table-wrap">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th>Opportunity</th>
                        <th>Status</th>
                        <th>Customer</th>
                        <th>Produk {{ $typeLabel }}</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Margin</th>
                        <th>Close date</th>
                        <th>Sales</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($deals as $row)
                        @php
                            $opp = $row['opportunity'];
                            $oppUrl = route('opportunities.show', $opp);
                            $shownProducts = $row['products']->take(2);
                            $moreProducts = max(0, $row['products']->count() - $shownProducts->count());
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ $oppUrl }}" class="font-medium text-slate-800 hover:text-brand-600">
                                    {{ \Illuminate\Support\Str::limit($opp->name ?: '—', 48) }}
                                </a>
                            </td>
                            <td>
                                <x-badge :color="$opp->stageColor()">{{ $opp->stage ?: '—' }}</x-badge>
                            </td>
                            <td>
                                @if ($opp->account)
                                    <a href="{{ route('customers.show', $opp->account) }}" class="text-brand-600 hover:underline">
                                        {{ \Illuminate\Support\Str::limit($opp->account->name, 28) }}
                                    </a>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="max-w-[220px]">
                                <ul class="space-y-0.5 text-xs text-slate-600">
                                    @foreach ($shownProducts as $product)
                                        @php $productName = trim((string) ($product['name'] ?? '')) ?: '—'; @endphp
                                        <li title="{{ $productName }}">
                                            {{ \Illuminate\Support\Str::limit($productName, 36) }}
                                            @if (($product['quantity'] ?? 0) > 0)
                                                <span class="text-slate-400">× {{ rtrim(rtrim(number_format((float) $product['quantity'], 2, ',', '.'), '0'), ',') }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                    @if ($moreProducts > 0)
                                        <li class="text-slate-400">+{{ $moreProducts }} produk lainnya</li>
                                    @endif
                                </ul>
                            </td>
                            <td class="text-right tabular-nums font-medium text-slate-800">
                                @if ($row['is_won'] ?? false)
                                    {{ money($row['won_total']) }}
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td @class([
                                'text-right tabular-nums font-medium',
                                'text-green-700' => ($row['is_won'] ?? false) && $row['won_margin'] >= 0,
                                'text-red-600' => ($row['is_won'] ?? false) && $row['won_margin'] < 0,
                                'text-slate-400' => ! ($row['is_won'] ?? false),
                            ])>
                                @if ($row['is_won'] ?? false)
                                    {{ money($row['won_margin']) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-slate-600 whitespace-nowrap">
                                @if ($opp->close_date)
                                    {{ \Illuminate\Support\Carbon::parse($opp->close_date)->translatedFormat('d M Y') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-slate-600">
                                {{ $opp->assignedUser?->display_name ?: '—' }}
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ $oppUrl }}"
                                   class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium text-brand-600 hover:bg-brand-50 hover:border-brand-200">
                                    Detail <i class="bi bi-arrow-right text-[10px]"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="px-5 py-12 text-center text-sm text-slate-400">
            Tidak ada opportunity untuk {{ strtolower($typeLabel) }}
            <span class="font-medium text-slate-600">{{ $displayName }}</span>
            @if ($selectedCategories->isNotEmpty())
                pada kategori
                <span class="font-medium text-slate-600">{{ $selectedCategories->join(', ') }}</span>
            @endif
            pada {{ $periodLabel }}.
        </p>
    @endif
</x-card>
@endsection

@if ($type === 'brand' && $charts)
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
            (function () {
                if (typeof Chart === 'undefined') {
                    return;
                }

                var charts = @json($charts);
                var brandColor = '#2563eb';
                var marginColor = '#16a34a';
                var palette = ['#2563eb', '#16a34a', '#f59e0b', '#8b5cf6', '#ef4444', '#0891b2', '#db2777', '#64748b'];

                function rupiah(value) {
                    return 'Rp ' + Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
                }

                function rupiahShort(value) {
                    var n = Number(value || 0);
                    if (Math.abs(n) >= 1000000000) {
                        return 'Rp ' + (n / 1000000000).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' M';
                    }
                    if (Math.abs(n) >= 1000000) {
                        return 'Rp ' + (n / 1000000).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' jt';
                    }
                    return rupiah(n);
                }

                var baseOptions = {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { labels: { boxWidth: 10, font: { size: 11 } } },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    var label = ctx.dataset.label ? ctx.dataset.label + ': ' : '';
                                    return label + rupiah(ctx.parsed.y !== undefined ? ctx.parsed.y : ctx.parsed);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: function (value) { return rupiahShort(value); }, font: { size: 10 } },
                            grid: { color: 'rgba(148, 163, 184, 0.15)' }
                        },
                        x: { ticks: { font: { size: 10 } }, grid: { display: false } }
                    }
                };

                var monthlyEl = document.getElementById('catalog-chart-monthly');
                if (monthlyEl) {
                    new Chart(monthlyEl, {
                        type: 'line',
                        data: {
                            labels: charts.monthly.labels,
                            datasets: [
                                {
                                    label: 'Total penjualan',
                                    data: charts.monthly.total,
                                    borderColor: brandColor,
                                    backgroundColor: 'rgba(37, 99, 235, 0.12)',
                                    fill: true,
                                    tension: 0.3,
                                    pointRadius: 3,
                                    borderWidth: 2
                                },
                                {
                                    label: 'Margin',
                                    data: charts.monthly.margin,
                                    borderColor: marginColor,
                                    backgroundColor: 'rgba(22, 163, 74, 0.10)',
                                    fill: true,
                                    tension: 0.3,
                                    pointRadius: 3,
                                    borderWidth: 2
                                }
                            ]
                        },
                        options: baseOptions
                    });
                }

                var productsEl = document.getElementById('catalog-chart-products');
                if (productsEl) {
                    new Chart(productsEl, {
                        type: 'bar',
                        data: {
                            labels: charts.products.labels,
                            datasets: [{
                                label: 'Penjualan',
                                data: charts.products.values,
                                backgroundColor: brandColor,
                                borderRadius: 4,
                                maxBarThickness: 26
                            }]
                        },
                        options: Object.assign({}, baseOptions, {
                            indexAxis: 'y',
                            plugins: Object.assign({}, baseOptions.plugins, {
                                legend: { display: false }
                            }),
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    ticks: { callback: function (value) { return rupiahShort(value); }, font: { size: 10 } },
                                    grid: { color: 'rgba(148, 163, 184, 0.15)' }
                                },
                                y: { ticks: { font: { size: 10 } }, grid: { display: false } }
                            }
                        })
                    });
                }

                var categoriesEl = document.getElementById('catalog-chart-categories');
                if (categoriesEl) {
                    new Chart(categoriesEl, {
                        type: 'doughnut',
                        data: {
                            labels: charts.categories.labels,
                            datasets: [{
                                data: charts.categories.values,
                                backgroundColor: palette,
                                borderColor: '#fff',
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '58%',
                            plugins: {
                                legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } },
                                tooltip: {
                                    callbacks: {
                                        label: function (ctx) {
                                            var total = ctx.dataset.data.reduce(function (a, b) { return a + Number(b); }, 0);
                                            var pct = total > 0 ? (Number(ctx.parsed) / total) * 100 : 0;
                                            return ctx.label + ': ' + rupiah(ctx.parsed) + ' (' + pct.toFixed(1) + '%)';
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            })();
        </script>
    @endpush
@endif
