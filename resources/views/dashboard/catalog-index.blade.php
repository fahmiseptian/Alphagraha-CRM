@extends('layouts.app')
@section('title', 'Dashboard Brand & Kategori')

@php
    $baseQuery = array_filter([
        'type' => $type,
        'leaderboard_period' => $period !== 'year' ? $period : null,
        'q' => $search !== '' ? $search : null,
        'include_empty' => $includeEmpty ? '1' : '0',
        'catalog_sort' => $sort !== 'total' ? $sort : null,
        'dir' => ($sort === 'total' && $sortDir === 'desc') ? null : $sortDir,
    ], fn ($v) => $v !== null && $v !== '');

    $sortUrl = function (string $column) use ($baseQuery, $sort, $sortDir): string {
        $numericPreferredDesc = in_array($column, ['count', 'won', 'items', 'total', 'margin'], true);
        $nextDir = ($sort === $column)
            ? ($sortDir === 'asc' ? 'desc' : 'asc')
            : ($numericPreferredDesc ? 'desc' : 'asc');

        return route('dashboard.catalog-index', array_merge($baseQuery, [
            'catalog_sort' => $column,
            'dir' => $nextDir,
        ]));
    };

    $sortIcon = function (string $column) use ($sort, $sortDir): string {
        if ($sort !== $column) {
            return 'bi-arrow-down-up text-slate-300';
        }

        return $sortDir === 'asc' ? 'bi-sort-up-alt text-brand-600' : 'bi-sort-down text-brand-600';
    };

    $tabQuery = array_filter([
        'leaderboard_period' => $period !== 'year' ? $period : null,
        'catalog_sort' => $sort !== 'total' ? $sort : null,
        'dir' => ($sort === 'total' && $sortDir === 'desc') ? null : $sortDir,
        'q' => $search !== '' ? $search : null,
        'include_empty' => $includeEmpty ? '1' : '0',
    ], fn ($v) => $v !== null && $v !== '');

    $pageOppti = (int) $entries->sum(fn ($e) => (int) ($e['oppti_count'] ?? $e['deal_count'] ?? 0));
    $pageWon = (int) $entries->sum(fn ($e) => (int) ($e['won_count'] ?? $e['deal_count'] ?? 0));
    $pageTotal = (float) $entries->sum(fn ($e) => (float) ($e['won_total'] ?? 0));
    $pageMargin = (float) $entries->sum(fn ($e) => (float) ($e['won_margin'] ?? 0));
@endphp

@section('content')
<x-page-header
    title="Dashboard Brand & Kategori"
    :description="'Semua '.strtolower($typeLabel).' · '.$periodLabel.' · termasuk di luar top 10 leaderboard'" />

{{-- Tabs --}}
<div class="mb-4">
    <div class="inline-flex rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
        <a href="{{ route('dashboard.catalog-index', array_merge($tabQuery, ['type' => 'brand'])) }}"
           @class([
               'inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition',
               'bg-brand-600 text-white shadow-sm' => $type === 'brand',
               'text-slate-600 hover:bg-slate-50' => $type !== 'brand',
           ])>
            <i class="bi bi-tags"></i> Brand
        </a>
        <a href="{{ route('dashboard.catalog-index', array_merge($tabQuery, ['type' => 'category'])) }}"
           @class([
               'inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition',
               'bg-brand-600 text-white shadow-sm' => $type === 'category',
               'text-slate-600 hover:bg-slate-50' => $type !== 'category',
           ])>
            <i class="bi bi-folder"></i> Kategori
        </a>
    </div>
</div>

{{-- Ringkasan halaman --}}
<div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat-card :title="$typeLabel.' ditampilkan'" :value="number_format($totalCount)" :icon="$type === 'brand' ? 'bi-tags' : 'bi-folder'" color="brand"
                 :sub="'Halaman '.$entries->currentPage().' dari '.$entries->lastPage()" />
    <x-stat-card title="Jumlah Oppti (halaman ini)" :value="number_format($pageOppti)" icon="bi-briefcase" color="slate" />
    <x-stat-card title="Close Won (halaman ini)" :value="number_format($pageWon)" icon="bi-trophy" color="green" />
    <x-stat-card title="Total Penjualan (halaman ini)" :value="money($pageTotal)" icon="bi-cash-coin" color="amber"
                 :sub="'Margin '.money($pageMargin)" />
</div>

{{-- Filter --}}
<x-card class="mb-4" title="Filter">
    <form method="GET" action="{{ route('dashboard.catalog-index') }}" class="space-y-4">
        <input type="hidden" name="type" value="{{ $type }}">
        <input type="hidden" name="catalog_sort" value="{{ $sort }}">
        <input type="hidden" name="dir" value="{{ $sortDir }}">

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div>
                <label class="crm-label">Cari {{ $typeLabel }}</label>
                <div class="crm-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="q" value="{{ $search }}"
                           placeholder="Ketik nama {{ strtolower($typeLabel) }}..."
                           class="crm-field" autocomplete="off">
                </div>
            </div>
            <div>
                <label class="crm-label">Periode</label>
                <select name="leaderboard_period" class="select2 select2-compact w-full" data-placeholder="Periode">
                    <option value="year" @selected($period === 'year')>Tahun ini</option>
                    <option value="month" @selected($period === 'month')>Bulan ini</option>
                    <option value="3months" @selected($period === '3months')>3 Bulan</option>
                    <option value="6months" @selected($period === '6months')>6 Bulan</option>
                    <option value="alltime" @selected($period === 'alltime')>All Time</option>
                </select>
            </div>
            <div>
                <label class="crm-label">Tampilan data</label>
                <label class="mt-1 flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-3 transition hover:border-brand-200 hover:bg-brand-50/40">
                    <input type="hidden" name="include_empty" value="0">
                    <input type="checkbox" name="include_empty" value="1"
                           class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                           @checked($includeEmpty)>
                    <span>
                        <span class="block text-sm font-medium text-slate-800">Termasuk tanpa penjualan</span>
                        <span class="mt-0.5 block text-xs text-slate-500">
                            Tampilkan juga {{ strtolower($typeLabel) }} yang belum punya opportunity di periode ini.
                        </span>
                    </span>
                </label>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
            <div class="flex flex-wrap items-center gap-2">
                <x-btn type="submit" variant="primary" icon="bi-search">Cari</x-btn>
                @if ($search !== '' || $period !== 'year' || ! $includeEmpty)
                    <x-btn href="{{ route('dashboard.catalog-index', ['type' => $type]) }}" variant="ghost">Reset</x-btn>
                @endif
            </div>
            <p class="text-sm text-slate-500">
                <span class="font-semibold text-slate-800">{{ number_format($totalCount) }}</span>
                {{ strtolower($typeLabel) }} · {{ $periodLabel }}
            </p>
        </div>
    </form>
</x-card>

{{-- Tabel --}}
<x-card :title="'Daftar '.$typeLabel" :padding="false">
    <div class="crm-table-wrap">
        <table class="crm-table">
            <thead>
                <tr>
                    <th class="w-14">#</th>
                    <th>
                        <a href="{{ $sortUrl('name') }}" class="inline-flex items-center gap-1 hover:text-brand-600">
                            {{ $typeLabel }} <i class="bi {{ $sortIcon('name') }} text-[11px]"></i>
                        </a>
                    </th>
                    <th class="text-right">
                        <a href="{{ $sortUrl('count') }}" class="inline-flex items-center justify-end gap-1 hover:text-brand-600">
                            Jumlah Oppti <i class="bi {{ $sortIcon('count') }} text-[11px]"></i>
                        </a>
                    </th>
                    <th class="text-right">
                        <a href="{{ $sortUrl('won') }}" class="inline-flex items-center justify-end gap-1 hover:text-brand-600">
                            Jumlah Close Won <i class="bi {{ $sortIcon('won') }} text-[11px]"></i>
                        </a>
                    </th>
                    <th class="text-right">
                        <a href="{{ $sortUrl('items') }}" class="inline-flex items-center justify-end gap-1 hover:text-brand-600">
                            Item <i class="bi {{ $sortIcon('items') }} text-[11px]"></i>
                        </a>
                    </th>
                    <th class="text-right">
                        <a href="{{ $sortUrl('total') }}" class="inline-flex items-center justify-end gap-1 hover:text-brand-600">
                            Total Penjualan <i class="bi {{ $sortIcon('total') }} text-[11px]"></i>
                        </a>
                    </th>
                    <th class="text-right">
                        <a href="{{ $sortUrl('margin') }}" class="inline-flex items-center justify-end gap-1 hover:text-brand-600">
                            Margin <i class="bi {{ $sortIcon('margin') }} text-[11px]"></i>
                        </a>
                    </th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    @php
                        $detailUrl = route('dashboard.catalog', array_filter([
                            'type' => $type,
                            'name' => $entry['name'],
                            'leaderboard_period' => $period,
                            'catalog_sort' => $sort,
                            'dir' => $sortDir,
                            'q' => $search,
                        ], fn ($v) => filled($v)));
                    @endphp
                    <tr>
                        <td class="tabular-nums text-slate-400">{{ $entry['rank'] }}</td>
                        <td class="font-medium text-slate-800">
                            <a href="{{ $detailUrl }}" class="hover:text-brand-600">{{ $entry['name'] }}</a>
                        </td>
                        <td class="text-right tabular-nums text-slate-700">{{ number_format($entry['oppti_count'] ?? $entry['deal_count'] ?? 0) }}</td>
                        <td class="text-right tabular-nums text-slate-700">{{ number_format($entry['won_count'] ?? $entry['deal_count'] ?? 0) }}</td>
                        <td class="text-right tabular-nums text-slate-700">{{ number_format($entry['item_count']) }}</td>
                        <td class="text-right tabular-nums text-slate-800">{{ money($entry['won_total']) }}</td>
                        <td @class([
                            'text-right tabular-nums font-medium',
                            'text-green-700' => $entry['won_margin'] >= 0,
                            'text-red-600' => $entry['won_margin'] < 0,
                        ])>
                            {{ money($entry['won_margin']) }}
                        </td>
                        <td class="text-right">
                            <a href="{{ $detailUrl }}"
                               class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium text-brand-600 hover:border-brand-200 hover:bg-brand-50">
                                Detail <i class="bi bi-arrow-right text-[10px]"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-slate-400">
                            @if ($search !== '')
                                Tidak ada {{ strtolower($typeLabel) }} yang cocok dengan “{{ $search }}”.
                            @else
                                Belum ada data {{ strtolower($typeLabel) }} untuk periode ini.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($entries->hasPages())
        <div class="crm-table-footer">{{ $entries->links() }}</div>
    @endif
</x-card>
@endsection
