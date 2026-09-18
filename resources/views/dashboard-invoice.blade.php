@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h2 class="crm-page-title">Hi, {{ Str::before(auth()->user()->name, ' ') }}!</h2>
        <p class="crm-page-desc">
            Antrian Invoice · {{ $periodLabel }}
            @if ($needsInvoiceCount > 0)
                · <span class="font-medium text-brand-700">{{ number_format($needsInvoiceCount) }} menunggu nomor invoice</span>
            @endif
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

<div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
    <a href="{{ route('sales-orders.index') }}"
       class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-brand-200 hover:shadow-md">
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-brand-50 text-brand-600 group-hover:bg-brand-100">
            <i class="bi bi-receipt text-lg"></i>
        </span>
        <span>
            <span class="block text-sm font-semibold text-slate-800">Sales Orders</span>
            <span class="block text-xs text-slate-500">Isi invoice, mark paid & DO</span>
        </span>
    </a>
    <a href="{{ route('customers.index') }}"
       class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-brand-200 hover:shadow-md">
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-brand-50 text-brand-600 group-hover:bg-brand-100">
            <i class="bi bi-people text-lg"></i>
        </span>
        <span>
            <span class="block text-sm font-semibold text-slate-800">Customers</span>
            <span class="block text-xs text-slate-500">Data customer terkait SO</span>
        </span>
    </a>
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat-card title="Belum invoice · {{ $periodLabel }}" :value="number_format($needsInvoiceCount)" icon="bi-file-earmark-plus" color="amber"
                 sub="Prioritas: isi nomor & tanggal invoice"
                 :href="route('sales-orders.index')" />
    <x-stat-card title="Sudah invoice · {{ $periodLabel }}" :value="number_format($invoicedCount)" icon="bi-file-earmark-check" color="green"
                 :sub="number_format($soPeriodCount).' SO total'"
                 :href="route('sales-orders.index')" />
    <x-stat-card title="Invoice unpaid · {{ $periodLabel }}" :value="number_format($unpaidInvoicedCount)" icon="bi-cash-coin" color="rose"
                 sub="Sudah invoice, belum paid"
                 :href="route('sales-orders.index')" />
    <x-stat-card title="DO pending · {{ $periodLabel }}" :value="number_format($deliveryPendingCount)" icon="bi-truck" color="purple"
                 sub="Resi / file DO belum complete"
                 :href="route('sales-orders.index')" />
</div>

<div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
    <x-stat-card title="SO · {{ $periodLabel }}" :value="number_format($soPeriodCount)" icon="bi-receipt" color="brand"
                 :href="route('sales-orders.index')" />
    <x-stat-card title="Sudah paid · {{ $periodLabel }}" :value="number_format($paidCount)" icon="bi-check2-circle" color="green"
                 :href="route('sales-orders.index')" />
</div>

<div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3 lg:items-start">
    <x-card class="lg:col-span-2" :padding="false" title="Antrian isi Invoice · {{ $periodLabel }}">
        <x-slot:action>
            <a href="{{ route('sales-orders.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">
                Semua SO
            </a>
        </x-slot:action>

        @if ($needsInvoice->isNotEmpty())
            <div class="crm-table-wrap">
                <table class="crm-table">
                    <thead>
                        <tr>
                            <th>Sales Order</th>
                            <th>Customer</th>
                            <th>Sales</th>
                            <th class="text-right">Dibuat</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($needsInvoice as $so)
                            @php
                                $url = route('opportunities.sales-orders.show', [$so->opportunity, $so]);
                                $created = $so->created_at?->translatedFormat('d M Y') ?: '—';
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ $url }}" class="font-medium text-brand-600 hover:text-brand-700">
                                        {{ $so->displayNumber() }}
                                    </a>
                                    @if ($so->opportunity?->name)
                                        <div class="text-xs text-slate-400">{{ $so->opportunity->name }}</div>
                                    @endif
                                </td>
                                <td class="text-slate-600">{{ $so->opportunity?->account?->name ?: '—' }}</td>
                                <td class="text-slate-600">{{ optional($so->opportunity?->assignedUser)->display_name ?: '—' }}</td>
                                <td class="text-right text-slate-600">{{ $created }}</td>
                                <td class="text-right">
                                    <a href="{{ $url }}" class="crm-icon-btn" title="Isi invoice">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($needsInvoiceCount > $needsInvoice->count())
                <div class="border-t border-slate-100 px-5 py-3">
                    <a href="{{ route('sales-orders.index') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">
                        Lihat semua {{ number_format($needsInvoiceCount) }} yang menunggu invoice
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            @endif
        @else
            <x-empty-state icon="bi-check2-circle" title="Antrian invoice kosong"
                           message="Semua SO Closed Won {{ $periodLabel }} sudah punya nomor invoice." />
        @endif
    </x-card>

    <div class="space-y-4">
        <x-card :padding="false" title="Invoice unpaid · {{ $periodLabel }}">
            @forelse ($unpaidInvoiced as $so)
                @php
                    $url = route('opportunities.sales-orders.show', [$so->opportunity, $so]);
                    $invoiceNo = data_get($so->agc_payload, 'invoice_no') ?: '—';
                @endphp
                <a href="{{ $url }}" class="flex items-center gap-3 border-b border-slate-50 px-5 py-3 last:border-0 hover:bg-slate-50">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-800">{{ $so->displayNumber() }}</p>
                        <p class="truncate text-xs text-slate-400">
                            Invoice {{ $invoiceNo }}
                            · {{ $so->opportunity?->account?->name ?: '—' }}
                        </p>
                    </div>
                    <x-badge color="rose">Unpaid</x-badge>
                </a>
            @empty
                <x-empty-state icon="bi-cash-coin" title="Tidak ada unpaid"
                               message="Tidak ada invoice unpaid pada {{ $periodLabel }}." />
            @endforelse
        </x-card>

        <x-card :padding="false" title="DO pending · {{ $periodLabel }}">
            @forelse ($deliveryPending as $so)
                @php $url = route('opportunities.sales-orders.show', [$so->opportunity, $so]); @endphp
                <a href="{{ $url }}" class="flex items-center gap-3 border-b border-slate-50 px-5 py-3 last:border-0 hover:bg-slate-50">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-800">{{ $so->displayNumber() }}</p>
                        <p class="truncate text-xs text-slate-400">
                            {{ $so->opportunity?->account?->name ?: ($so->opportunity?->name ?: '—') }}
                        </p>
                    </div>
                    <x-badge color="purple">DO</x-badge>
                </a>
            @empty
                <x-empty-state icon="bi-truck" title="Tidak ada DO pending"
                               message="Pengiriman SO {{ $periodLabel }} sudah complete." />
            @endforelse
        </x-card>
    </div>
</div>
@endsection
