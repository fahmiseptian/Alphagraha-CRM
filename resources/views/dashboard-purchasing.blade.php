@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h2 class="crm-page-title">Hi, {{ Str::before(auth()->user()->name, ' ') }}!</h2>
        <p class="crm-page-desc">
            Closed Won {{ $periodLabel }} yang perlu dibuatkan PO
            @if ($needsPoCount > 0)
                · <span class="font-medium text-brand-700">{{ number_format($needsPoCount) }} menunggu PO</span>
            @endif
            .
        </p>
    </div>
    <form method="GET" action="{{ route('dashboard') }}" class="flex items-center gap-2">
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

<div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
    <a href="{{ route('purchase-orders.index') }}"
       class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-brand-200 hover:shadow-md">
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-brand-50 text-brand-600 group-hover:bg-brand-100">
            <i class="bi bi-cart-check text-lg"></i>
        </span>
        <span>
            <span class="block text-sm font-semibold text-slate-800">Daftar PO</span>
            <span class="block text-xs text-slate-500">Lihat Purchase Order yang sudah dibuat</span>
        </span>
    </a>
    <a href="{{ route('vendor-stocks.index') }}"
       class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-brand-200 hover:shadow-md">
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-brand-50 text-brand-600 group-hover:bg-brand-100">
            <i class="bi bi-boxes text-lg"></i>
        </span>
        <span>
            <span class="block text-sm font-semibold text-slate-800">Product</span>
            <span class="block text-xs text-slate-500">Cek stok & harga vendor</span>
        </span>
    </a>
    <a href="{{ route('vendors.index') }}"
       class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-brand-200 hover:shadow-md">
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-brand-50 text-brand-600 group-hover:bg-brand-100">
            <i class="bi bi-truck text-lg"></i>
        </span>
        <span>
            <span class="block text-sm font-semibold text-slate-800">Vendors</span>
            <span class="block text-xs text-slate-500">Master data vendor</span>
        </span>
    </a>
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <x-stat-card title="Perlu PO · {{ $periodLabel }}" :value="number_format($needsPoCount)" icon="bi-hourglass-split" color="amber"
                 sub="Closed Won belum ada Purchase Order"
                 :href="route('opportunities.index')" />
    <x-stat-card title="Closed Won · {{ $periodLabel }}" :value="number_format($wonPeriodCount)" icon="bi-trophy" color="green"
                 :sub="number_format($wonWithPoCount).' sudah ada PO'"
                 :href="route('opportunities.index')" />
    <x-stat-card title="PO dibuat · {{ $periodLabel }}" :value="number_format($poPeriodCount)" icon="bi-cart-check" color="brand"
                 :href="route('purchase-orders.index', ['period' => $period])" />
</div>

<div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3 lg:items-start">
    <x-card class="lg:col-span-2" :padding="false" title="Menunggu Purchase Order · {{ $periodLabel }}">
        <x-slot:action>
            <a href="{{ route('opportunities.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">
                Semua Closed Won
            </a>
        </x-slot:action>

        @if ($needsPo->isNotEmpty())
            <div class="crm-table-wrap">
                <table class="crm-table">
                    <thead>
                        <tr>
                            <th>Opportunity</th>
                            <th>Sales</th>
                            <th class="text-right">Closed</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($needsPo as $opp)
                            @php
                                $poUrl = route('opportunities.purchase-orders.index', $opp);
                                $closeDate = $opp->close_date
                                    ? \Illuminate\Support\Carbon::parse($opp->close_date)->translatedFormat('d M Y')
                                    : '—';
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ $poUrl }}" class="font-medium text-brand-600 hover:text-brand-700">
                                        {{ $opp->name }}
                                    </a>
                                    @if ($opp->account?->name)
                                        <div class="text-xs text-slate-400">{{ $opp->account->name }}</div>
                                    @endif
                                </td>
                                <td class="text-slate-600">
                                    {{ optional($opp->assignedUser)->display_name ?: '—' }}
                                </td>
                                <td class="text-right text-slate-600">{{ $closeDate }}</td>
                                <td class="text-right">
                                    <a href="{{ $poUrl }}" class="crm-icon-btn" title="Buat PO">
                                        <i class="bi bi-arrow-right"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($needsPoCount > $needsPo->count())
                <div class="border-t border-slate-100 px-5 py-3">
                    <a href="{{ route('opportunities.index') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">
                        Lihat semua {{ number_format($needsPoCount) }} yang menunggu PO
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            @endif
        @else
            <x-empty-state icon="bi-check2-circle" title="Tidak ada antrian PO"
                           message="Semua Closed Won {{ $periodLabel }} sudah punya Purchase Order." />
        @endif
    </x-card>

    <x-card :padding="false" title="PO terbaru · {{ $periodLabel }}">
        <x-slot:action>
            <a href="{{ route('purchase-orders.index', ['period' => $period]) }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">
                Daftar PO
            </a>
        </x-slot:action>

        @forelse ($recentPos as $po)
            @php
                $detailUrl = $po->opportunity
                    ? route('opportunities.purchase-orders.index', $po->opportunity)
                    : route('purchase-orders.index');
            @endphp
            <a href="{{ $detailUrl }}" class="flex items-center gap-3 border-b border-slate-50 px-5 py-3 last:border-0 hover:bg-slate-50">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-slate-800">{{ $po->number }}</p>
                    <p class="truncate text-xs text-slate-400">
                        {{ $po->displayVendorName() }}
                        @if ($po->opportunity?->name)
                            · {{ $po->opportunity->name }}
                        @endif
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-semibold text-slate-700">{{ money($po->totalInclude(), $po->currency ?: 'IDR') }}</p>
                    <x-badge :color="$po->isCash() ? 'amber' : 'blue'">{{ $po->paymentTermLabel() }}</x-badge>
                </div>
            </a>
        @empty
            <x-empty-state icon="bi-cart-check" title="Belum ada PO" message="Belum ada Purchase Order pada {{ $periodLabel }}." />
        @endforelse
    </x-card>
</div>
@endsection
