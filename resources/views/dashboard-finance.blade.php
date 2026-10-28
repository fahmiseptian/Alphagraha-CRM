@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
@php
    $soQueue = fn (string $queue = '') => route('sales-orders.index', array_filter([
        'period' => $period,
        'queue' => $queue !== '' ? $queue : null,
    ]));
@endphp

<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h2 class="crm-page-title">Hi, {{ Str::before(auth()->user()->name, ' ') }}!</h2>
        <p class="crm-page-desc">
            Antrian Finance · {{ $periodLabel }}
            @if ($entertainmentPendingCount > 0 || $unpaidCount > 0)
                ·
                <span class="font-medium text-brand-700">
                    {{ number_format($entertainmentPendingCount) }} entertainment pending
                    · {{ number_format($unpaidCount) }} unpaid
                </span>
            @endif
        </p>
    </div>
    <form method="GET" action="{{ route('dashboard') }}" class="flex items-center gap-2">
        <label class="text-xs font-medium text-slate-500">Periode SO</label>
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
    <a href="{{ route('commissions.index') }}"
       class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-brand-200 hover:shadow-md">
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-brand-50 text-brand-600 group-hover:bg-brand-100">
            <i class="bi bi-cash-coin text-lg"></i>
        </span>
        <span>
            <span class="block text-sm font-semibold text-slate-800">Komisi Sales</span>
            <span class="block text-xs text-slate-500">
                {{ number_format($salesUsersCount) }} sales · approve entertainment & biaya
            </span>
        </span>
    </a>
    <a href="{{ $soQueue() }}"
       class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-brand-200 hover:shadow-md">
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-brand-50 text-brand-600 group-hover:bg-brand-100">
            <i class="bi bi-receipt text-lg"></i>
        </span>
        <span>
            <span class="block text-sm font-semibold text-slate-800">Sales Orders</span>
            <span class="block text-xs text-slate-500">Invoice, mark paid & DO</span>
        </span>
    </a>
    <a href="{{ route('customers.index') }}"
       class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-brand-200 hover:shadow-md">
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-brand-50 text-brand-600 group-hover:bg-brand-100">
            <i class="bi bi-people text-lg"></i>
        </span>
        <span>
            <span class="block text-sm font-semibold text-slate-800">Customers</span>
            <span class="block text-xs text-slate-500">Payment level, TOP & dokumen</span>
        </span>
    </a>
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat-card title="Entertainment pending" :value="number_format($entertainmentPendingCount)" icon="bi-cup-hot" color="amber"
                 :sub="money($entertainmentPendingAmount, 'IDR').' menunggu approval'"
                 href="#entertainment-queue" />
    <x-stat-card title="Unpaid · {{ $periodLabel }}" :value="number_format($unpaidCount)" icon="bi-cash-stack" color="rose"
                 sub="Mark paid / settlement"
                 :href="$soQueue('unpaid')" />
    <x-stat-card title="Belum invoice · {{ $periodLabel }}" :value="number_format($needsInvoiceCount)" icon="bi-file-earmark-text" color="purple"
                 sub="SO Closed Won tanpa nomor invoice"
                 :href="$soQueue('needs_invoice')" />
    <x-stat-card title="Sudah paid · {{ $periodLabel }}" :value="number_format($paidCount)" icon="bi-check2-circle" color="green"
                 sub="Settlement selesai"
                 :href="$soQueue('paid')" />
</div>

<div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <x-stat-card title="SO · {{ $periodLabel }}" :value="number_format($soPeriodCount)" icon="bi-receipt" color="brand"
                 :href="$soQueue()" />
    <x-stat-card title="DO pending · {{ $periodLabel }}" :value="number_format($deliveryPendingCount)" icon="bi-truck" color="slate"
                 sub="Pengiriman belum complete"
                 :href="$soQueue('delivery_pending')" />
    <x-stat-card title="Komisi sales" :value="number_format($salesUsersCount)" icon="bi-people" color="brand"
                 sub="Kelola komisi & biaya tambahan"
                 :href="route('commissions.index')" />
</div>

@if ($topApprovalPending->isNotEmpty())
    <x-card id="top-approval-queue" class="mt-4 scroll-mt-20" :padding="false" title="Approval TOP Opportunity ({{ $topApprovalPending->count() }})">
        <div class="crm-table-wrap">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th>Opportunity</th>
                        <th>Sales</th>
                        <th>TOP customer</th>
                        <th>TOP diajukan</th>
                        <th>Diajukan</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($topApprovalPending as $opp)
                        <tr>
                            <td>
                                <a href="{{ route('opportunities.show', $opp) }}" class="font-medium text-brand-600 hover:text-brand-700">{{ $opp->name }}</a>
                                <div class="text-xs text-slate-400">{{ $opp->account?->name ?: '—' }}</div>
                            </td>
                            <td class="text-slate-600">{{ optional($opp->assignedUser)->display_name ?: '—' }}</td>
                            <td class="text-slate-600">{{ \App\Support\CustomerTop::label($opp->crm_top_customer ?: $opp->customerTop()) }}</td>
                            <td class="font-semibold text-amber-700">{{ $opp->topLabel() }}</td>
                            <td class="text-slate-600">{{ optional($opp->crm_top_requested_at)->timezone(config('app.timezone'))?->format('d M Y H:i') ?: '—' }}</td>
                            <td class="text-right">
                                <div class="inline-flex items-center justify-end gap-2">
                                    <a href="{{ route('opportunities.show', $opp) }}" class="crm-icon-btn" title="Buka opportunity">
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                    <form method="POST" action="{{ route('opportunities.top.approve', $opp) }}"
                                          onsubmit="return confirm('Approve {{ $opp->topLabel() }} untuk opportunity ini?')" class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex items-center gap-1 rounded-lg bg-green-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-green-700">
                                            <i class="bi bi-check-lg"></i> Approve
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
@endif

<div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3 lg:items-start">
    <x-card id="entertainment-queue" class="lg:col-span-2 scroll-mt-20" :padding="false" title="Approval Entertainment">
        <x-slot:action>
            <a href="{{ route('commissions.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">
                Ke Komisi
            </a>
        </x-slot:action>

        @if ($entertainmentPending->isNotEmpty())
            <div class="crm-table-wrap">
                <table class="crm-table">
                    <thead>
                        <tr>
                            <th>Entertainment</th>
                            <th>Sales</th>
                            <th>Tanggal</th>
                            <th class="text-right">Nominal</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entertainmentPending as $item)
                            @php
                                $salesId = $item->commission_sales_id;
                                $commissionUrl = $item->commission_url;
                                $dateLabel = optional($item->entertainment_date ?? $item->created_at)->translatedFormat('d M Y') ?: '—';
                            @endphp
                            <tr>
                                <td>
                                    @if ($commissionUrl)
                                        <a href="{{ $commissionUrl }}" class="font-medium text-brand-600 hover:text-brand-700">
                                            {{ $item->name }}
                                        </a>
                                    @else
                                        <span class="font-medium text-slate-800">{{ $item->name }}</span>
                                    @endif
                                    <div class="text-xs text-slate-400">
                                        {{ $item->opportunity?->name ?: '—' }}
                                        @if ($item->opportunity?->account?->name)
                                            · {{ $item->opportunity->account->name }}
                                        @endif
                                    </div>
                                </td>
                                <td class="text-slate-600">
                                    {{ optional($item->opportunity?->assignedUser)->display_name ?: '—' }}
                                </td>
                                <td class="text-slate-600">{{ $dateLabel }}</td>
                                <td class="text-right tabular-nums font-semibold text-slate-700">
                                    {{ money($item->amount, 'IDR') }}
                                </td>
                                <td class="text-right">
                                    <div class="inline-flex items-center justify-end gap-2">
                                        @if ($commissionUrl)
                                            <a href="{{ $commissionUrl }}" class="crm-icon-btn" title="Buka komisi">
                                                <i class="bi bi-cash-coin"></i>
                                            </a>
                                        @endif
                                        @if ($salesId)
                                            <form method="POST"
                                                  action="{{ route('commissions.entertainments.approve', [$salesId, $item]) }}"
                                                  onsubmit="return confirm('Approve entertainment ini? Komisi di periode tanggalnya akan berkurang.')"
                                                  class="inline">
                                                @csrf
                                                <button type="submit"
                                                        class="inline-flex items-center gap-1 rounded-lg bg-green-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-green-700"
                                                        title="Approve">
                                                    <i class="bi bi-check-lg"></i> Approve
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
            @if ($entertainmentPendingCount > $entertainmentPending->count())
                <div class="border-t border-slate-100 px-5 py-3">
                    <a href="{{ route('commissions.index') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">
                        Ada {{ number_format($entertainmentPendingCount - $entertainmentPending->count()) }} lagi — buka Komisi Sales
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            @endif
        @else
            <x-empty-state icon="bi-check2-circle" title="Tidak ada pending"
                           message="Semua permintaan entertainment sudah di-approve." />
        @endif
    </x-card>

    <div class="space-y-4">
        <x-card :padding="false" title="Unpaid · {{ $periodLabel }}">
            <x-slot:action>
                <a href="{{ $soQueue('unpaid') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">
                    Semua
                </a>
            </x-slot:action>

            @forelse ($unpaidOrders as $so)
                @php $url = route('opportunities.sales-orders.show', [$so->opportunity, $so]); @endphp
                <a href="{{ $url }}" class="flex items-center gap-3 border-b border-slate-50 px-5 py-3 last:border-0 hover:bg-slate-50">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-800">{{ $so->displayNumber() }}</p>
                        <p class="truncate text-xs text-slate-400">
                            {{ $so->opportunity?->account?->name ?: ($so->opportunity?->name ?: '—') }}
                        </p>
                    </div>
                    <x-badge color="rose">Unpaid</x-badge>
                </a>
            @empty
                <x-empty-state icon="bi-cash-coin" title="Tidak ada unpaid" message="Tidak ada SO unpaid pada {{ $periodLabel }}." />
            @endforelse
        </x-card>

        <x-card :padding="false" title="Belum invoice · {{ $periodLabel }}">
            <x-slot:action>
                <a href="{{ $soQueue('needs_invoice') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">
                    Semua
                </a>
            </x-slot:action>

            @forelse ($needsInvoice as $so)
                @php $url = route('opportunities.sales-orders.show', [$so->opportunity, $so]); @endphp
                <a href="{{ $url }}" class="flex items-center gap-3 border-b border-slate-50 px-5 py-3 last:border-0 hover:bg-slate-50">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-800">{{ $so->displayNumber() }}</p>
                        <p class="truncate text-xs text-slate-400">
                            {{ $so->opportunity?->account?->name ?: ($so->opportunity?->name ?: '—') }}
                        </p>
                    </div>
                    <x-badge color="amber">Invoice</x-badge>
                </a>
            @empty
                <x-empty-state icon="bi-file-earmark-check" title="Antrian kosong"
                               message="Semua SO {{ $periodLabel }} sudah punya invoice." />
            @endforelse
        </x-card>
    </div>
</div>
@endsection
