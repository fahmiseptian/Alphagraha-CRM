@extends('layouts.app')
@section('title', 'Sales Orders')

@section('content')
    @php
        $canDeleteSo = auth()->user()?->canAccessAdministration() ?? false;
        $hasExtraFilters = ($search ?? '') !== ''
            || ($accountId ?? '') !== ''
            || ($companyFilter ?? '') !== ''
            || ($selectedUserId ?? '') !== '';
        $hasFilters = $hasExtraFilters || ($period ?? 'year') !== 'year';
    @endphp

    <x-page-header title="Sales Orders" :description="$salesOrders->total().' SO · '.($periodLabel ?? 'tahun ini')">
        <x-slot:actions>
            @if (auth()->user()?->canViewSalesOrderLogs())
                <x-btn href="{{ route('sales-order-logs.index') }}" variant="secondary" icon="bi-journal-text">Log SO</x-btn>
            @endif
            <x-btn href="{{ route('opportunities.index') }}" icon="bi bi-briefcase">Lihat Opportunity</x-btn>
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-4" :padding="false">
        <form method="GET" action="{{ route('sales-orders.index') }}" class="crm-opp-filters">
            <div @class([
                'crm-opp-filters__grid',
                'crm-opp-filters__grid--admin' => ($canFilterSales ?? false),
            ])>
                <div class="crm-opp-filters__field">
                    <label class="crm-label">Pencarian</label>
                    <div class="crm-search">
                        <i class="bi bi-search"></i>
                        <input type="text" name="q" value="{{ $search }}"
                               placeholder="No. SO, PSO, PO, referensi, opportunity, customer, sales..."
                               class="crm-field" autocomplete="off">
                    </div>
                </div>

                <div class="crm-opp-filters__field">
                    <label class="crm-label">Customer</label>
                    <select name="account_id" class="select2 select2-search w-full" data-placeholder="Cari customer...">
                        <option value="">Semua customer</option>
                        @foreach ($filterAccounts as $acc)
                            <option value="{{ $acc->id }}" @selected(($accountId ?? '') === $acc->id)>{{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="crm-opp-filters__field">
                    <label class="crm-label">Nama perusahaan</label>
                    <select name="company" class="select2 select2-compact w-full" data-placeholder="Semua perusahaan">
                        <option value="">Semua perusahaan</option>
                        @foreach ($companies as $co)
                            <option value="{{ $co }}" @selected(($companyFilter ?? '') === $co)>{{ $co }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($canFilterSales ?? false)
                    <div class="crm-opp-filters__field">
                        <label class="crm-label">Sales</label>
                        <select name="assigned_user_id" class="select2 select2-search w-full" data-placeholder="Semua sales">
                            <option value="">Semua sales</option>
                            @foreach ($salesUsers as $user)
                                <option value="{{ $user->id }}" @selected(($selectedUserId ?? '') === $user->id)>
                                    {{ $user->display_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="crm-opp-filters__field">
                    <label class="crm-label">Periode</label>
                    <select name="period" class="select2 select2-compact w-full" data-placeholder="Periode">
                        <option value="year" @selected(($period ?? 'year') === 'year')>Tahun ini</option>
                        <option value="month" @selected(($period ?? 'year') === 'month')>Bulan ini</option>
                        <option value="3months" @selected(($period ?? 'year') === '3months')>3 Bulan</option>
                        <option value="6months" @selected(($period ?? 'year') === '6months')>6 Bulan</option>
                        <option value="alltime" @selected(($period ?? 'year') === 'alltime')>All Time</option>
                    </select>
                </div>
            </div>

            <div class="crm-opp-filters__actions">
                <div class="crm-opp-filters__buttons">
                    <x-btn type="submit" variant="primary" icon="bi-funnel">Filter</x-btn>
                    @if ($hasFilters)
                        <x-btn href="{{ route('sales-orders.index') }}" variant="ghost">Reset</x-btn>
                    @endif
                </div>
                <p class="crm-opp-filters__total">
                    Total: <strong>{{ number_format($salesOrders->total()) }}</strong>
                    <span>· {{ $periodLabel }}</span>
                </p>
            </div>
        </form>
    </x-card>

    <x-card :padding="false">
        <div class="crm-table-wrap">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th>No. SO / PSO</th>
                        <th>Opportunity</th>
                        @if ($canFilterSales ?? false)
                            <th>Sales</th>
                        @endif
                        <th class="text-right">Harga jual (excl)</th>
                        <th class="text-right">Modal (excl)</th>
                        <th>Status</th>
                        <th class="text-right">Dibuat</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($salesOrders as $so)
                        @php
                            $status = $so->statusSnapshot();
                            $statusColor = fn (?string $value) => match (strtolower((string) $value)) {
                                'pending', 'unpaid' => 'amber',
                                'onprocess', 'ondelivery' => 'blue',
                                'completed', 'paid', 'settlement' => 'green',
                                'cancelled', 'rejected', 'refund' => 'red',
                                default => 'slate',
                            };
                            $opp = $so->opportunity;
                            $currency = $opp?->amount_currency ?: 'IDR';
                            $nilaiJual = $opp
                                ? round($opp->products->sum(fn ($p) => (float) ($p['quantity'] ?? 1) * (float) ($p['effective_sell_exclude'] ?? $p['sell_exclude'] ?? 0)), 2)
                                : 0;
                            $modal = $opp
                                ? round((float) $opp->purchaseOrders->sum('total'), 2)
                                : 0;
                            if ($modal <= 0 && $opp) {
                                $modal = round($opp->products->sum(fn ($p) => (float) ($p['quantity'] ?? 1) * (float) ($p['cost_exclude'] ?? 0)), 2);
                            }
                        @endphp
                        <tr>
                            <td style="white-space: nowrap;">
                                <a href="{{ route('opportunities.sales-orders.show', [$so->opportunity, $so]) }}"
                                   class="font-medium text-brand-600 hover:text-brand-700"
                                >
                                    {{ $so->displayNumber() }}
                                </a>
                                <div class="text-xs text-slate-400">
                                    @if ($so->displayPsoNumber() !== '')
                                        PSO {{ $so->displayPsoNumber() }}
                                        &middot;
                                    @endif
                                    @if ($so->displayRefNumber() !== '')
                                        {{ $so->displayRefNumber() }}
                                        &middot;
                                    @endif
                                    {{ $so->paymentLabel() }}
                                    @if ($so->po_number)
                                        &middot; PO {{ $so->po_number }}
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="font-medium text-slate-800">{{ $so->opportunity?->name ?? '—' }}</span>
                                @if ($so->opportunity?->account?->name)
                                    <div class="text-xs text-slate-400">{{ $so->opportunity->account->name }}</div>
                                @endif
                            </td>
                            @if ($canFilterSales ?? false)
                                <td class="text-slate-600">
                                    {{ optional($so->opportunity?->assignedUser)->display_name ?: '—' }}
                                </td>
                            @endif
                            <td class="text-right tabular-nums text-slate-700">{{ money($nilaiJual, $currency) }}</td>
                            <td class="text-right tabular-nums text-slate-700">{{ money($modal, $currency) }}</td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @if (! empty($status['so_status']))
                                        <x-badge :color="$statusColor($status['so_status'])">{{ $status['so_status'] }}</x-badge>
                                    @endif
                                    @if (! empty($status['payment_status']))
                                        <x-badge :color="$statusColor($status['payment_status'])">{{ $status['payment_status'] }}</x-badge>
                                    @endif
                                    @if (! empty($status['delivery_status']))
                                        <x-badge :color="$statusColor($status['delivery_status'])">{{ $status['delivery_status'] }}</x-badge>
                                    @endif
                                </div>
                            </td>
                            <td class="text-right text-slate-600">
                                {{ $so->created_at?->translatedFormat('d M Y H:i') }}
                            </td>
                            <td class="text-right">
                                <a href="{{ route('opportunities.sales-orders.show', [$so->opportunity, $so]) }}" class="crm-icon-btn" title="Detail">
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                                @if ($canDeleteSo)
                                    <form method="POST"
                                          action="{{ route('opportunities.sales-orders.destroy', [$so->opportunity, $so]) }}"
                                          style="display:inline-block;"
                                          onsubmit="return confirm('Sales Order akan diarsipkan ke Log SO, tidak dihapus permanen. Lanjutkan?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="crm-icon-btn text-red-500 hover:bg-red-50" title="Arsipkan SO" type="submit">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ ($canFilterSales ?? false) ? 8 : 7 }}" class="py-10 text-center text-slate-500">
                                @if ($hasFilters)
                                    Tidak ada Sales Order sesuai filter.
                                    <a href="{{ route('sales-orders.index') }}" class="ml-1 text-brand-600 hover:underline">Reset</a>
                                @else
                                    Belum ada Sales Order.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $salesOrders->links() }}
        </div>
    </x-card>
@endsection
