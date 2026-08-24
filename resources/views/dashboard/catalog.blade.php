@extends('layouts.app')
@section('title', $typeLabel.' '.$displayName)

@section('content')
<x-page-header
    :title="$typeLabel.': '.$displayName"
    :description="'Opportunity Closed Won yang memakai '.$typeLabel.' ini · '.$periodLabel"
    :back="route('dashboard', $dashboardQuery)"
    backLabel="Kembali ke dashboard">
    <x-slot:actions>
        <form method="GET" action="{{ route('dashboard.catalog') }}" class="flex items-center gap-2">
            <input type="hidden" name="type" value="{{ $type }}">
            <input type="hidden" name="name" value="{{ $name }}">
            @if ($selectedSalesId)<input type="hidden" name="sales" value="{{ $selectedSalesId }}">@endif
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

<div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat-card title="Jumlah Deal" :value="number_format($totals['deal_count'])" icon="bi-briefcase" color="brand"
                 :sub="number_format($totals['item_count']).' item'" />
    <x-stat-card title="Total Penjualan" :value="money($totals['won_total'])" icon="bi-trophy" color="green" />
    <x-stat-card title="Margin" :value="money($totals['won_margin'])" icon="bi-graph-up-arrow" color="purple" />
    <x-stat-card title="Rata-rata / Deal" :value="money($totals['deal_count'] > 0 ? $totals['won_total'] / $totals['deal_count'] : 0)" icon="bi-calculator" color="amber" />
</div>

<x-card :padding="false">
    @if ($deals->isNotEmpty())
        <div class="crm-table-wrap">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th>Opportunity</th>
                        <th>Customer</th>
                        <th>Produk {{ $typeLabel }}</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Margin</th>
                        <th>Close date</th>
                        <th>Sales</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($deals as $row)
                        @php $opp = $row['opportunity']; @endphp
                        <tr>
                            <td>
                                <a href="{{ route('opportunities.show', $opp) }}" class="font-medium text-slate-800 hover:text-brand-600">
                                    {{ $opp->name ?: '—' }}
                                </a>
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
                            <td class="max-w-xs">
                                <ul class="space-y-0.5 text-xs text-slate-600">
                                    @foreach ($row['products'] as $product)
                                        <li class="truncate" title="{{ $product['name'] }}">
                                            {{ $product['name'] ?: '—' }}
                                            @if (($product['quantity'] ?? 0) > 0)
                                                <span class="text-slate-400">× {{ rtrim(rtrim(number_format((float) $product['quantity'], 2, ',', '.'), '0'), ',') }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </td>
                            <td class="text-right tabular-nums font-medium text-slate-800">
                                {{ money($row['won_total']) }}
                            </td>
                            <td @class([
                                'text-right tabular-nums font-medium',
                                'text-green-700' => $row['won_margin'] >= 0,
                                'text-red-600' => $row['won_margin'] < 0,
                            ])>
                                {{ money($row['won_margin']) }}
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
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="px-5 py-12 text-center text-sm text-slate-400">
            Tidak ada opportunity Closed Won untuk {{ strtolower($typeLabel) }}
            <span class="font-medium text-slate-600">{{ $displayName }}</span>
            pada {{ $periodLabel }}.
        </p>
    @endif
</x-card>
@endsection
