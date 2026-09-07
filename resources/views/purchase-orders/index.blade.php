@extends('layouts.app')
@section('title', 'Purchase Orders')

@section('content')
    @php
        $hasExtraFilters = ($search ?? '') !== ''
            || ($accountId ?? '') !== ''
            || ($companyFilter ?? '') !== ''
            || (int) ($vendorId ?? 0) > 0
            || ($paymentTerm ?? '') !== ''
            || ($selectedUserId ?? '') !== '';
        $hasFilters = $hasExtraFilters || ($period ?? 'year') !== 'year';
    @endphp

    <x-page-header title="Purchase Orders" :description="$purchaseOrders->total().' PO · '.($periodLabel ?? 'tahun ini')">
        <x-slot:actions>
            <x-btn href="{{ route('opportunities.index') }}" icon="bi bi-briefcase">Lihat Opportunity</x-btn>
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-4" :padding="false">
        <form method="GET" action="{{ route('purchase-orders.index') }}" class="crm-opp-filters">
            <div @class([
                'crm-opp-filters__grid',
                'crm-opp-filters__grid--admin' => ($canFilterSales ?? false),
            ])>
                <div class="crm-opp-filters__field">
                    <label class="crm-label">Pencarian</label>
                    <div class="crm-search">
                        <i class="bi bi-search"></i>
                        <input type="text" name="q" value="{{ $search }}"
                               placeholder="No. PO, vendor, produk, opportunity, customer, sales..."
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

                <div class="crm-opp-filters__field">
                    <label class="crm-label">Vendor</label>
                    <select name="vendor_id" class="select2 select2-search w-full" data-placeholder="Semua vendor">
                        <option value="">Semua vendor</option>
                        @foreach ($filterVendors as $vendor)
                            <option value="{{ $vendor->id }}" @selected((int) ($vendorId ?? 0) === (int) $vendor->id)>{{ $vendor->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="crm-opp-filters__field">
                    <label class="crm-label">Kondisi</label>
                    <select name="payment_term" class="select2 select2-compact w-full" data-placeholder="Semua kondisi">
                        <option value="">Semua kondisi</option>
                        <option value="{{ \App\Models\PurchaseOrder::PAYMENT_TOP }}" @selected(($paymentTerm ?? '') === \App\Models\PurchaseOrder::PAYMENT_TOP)>TOP</option>
                        <option value="{{ \App\Models\PurchaseOrder::PAYMENT_CASH }}" @selected(($paymentTerm ?? '') === \App\Models\PurchaseOrder::PAYMENT_CASH)>Cash</option>
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
                        <x-btn href="{{ route('purchase-orders.index') }}" variant="ghost">Reset</x-btn>
                    @endif
                </div>
                <p class="crm-opp-filters__total">
                    Total: <strong>{{ number_format($purchaseOrders->total()) }}</strong>
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
                        <th>No. PO</th>
                        <th>Vendor</th>
                        <th>Opportunity</th>
                        @if ($canFilterSales ?? false)
                            <th>Sales</th>
                        @endif
                        <th>Kondisi</th>
                        <th class="text-right">Total (incl)</th>
                        <th class="text-right">Dibuat</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchaseOrders as $po)
                        @php
                            $opp = $po->opportunity;
                            $currency = $po->currency ?: ($opp?->amount_currency ?: 'IDR');
                            $itemCount = (int) ($po->items_count ?? 0);
                            $detailUrl = $opp
                                ? route('opportunities.purchase-orders.index', $opp)
                                : null;
                        @endphp
                        <tr>
                            <td style="white-space: nowrap;">
                                @if ($detailUrl)
                                    <a href="{{ $detailUrl }}"
                                       class="font-medium text-brand-600 hover:text-brand-700">
                                        {{ $po->number }}
                                    </a>
                                @else
                                    <span class="font-medium text-slate-800">{{ $po->number }}</span>
                                @endif
                                <div class="text-xs text-slate-400">
                                    {{ $itemCount }} item
                                </div>
                            </td>
                            <td>
                                <span class="font-medium text-slate-800">{{ $po->displayVendorName() }}</span>
                            </td>
                            <td>
                                <span class="font-medium text-slate-800">{{ $opp?->name ?? '—' }}</span>
                                @if ($opp?->account?->name)
                                    <div class="text-xs text-slate-400">{{ $opp->account->name }}</div>
                                @endif
                            </td>
                            @if ($canFilterSales ?? false)
                                <td class="text-slate-600">
                                    {{ optional($opp?->assignedUser)->display_name ?: '—' }}
                                </td>
                            @endif
                            <td>
                                <x-badge :color="$po->isCash() ? 'amber' : 'blue'">{{ $po->paymentTermLabel() }}</x-badge>
                            </td>
                            <td class="text-right tabular-nums text-slate-700">{{ money($po->totalInclude(), $currency) }}</td>
                            <td class="text-right text-slate-600">
                                {{ $po->created_at?->translatedFormat('d M Y H:i') }}
                                @if ($po->creator)
                                    <div class="text-xs text-slate-400">{{ $po->creator->name }}</div>
                                @endif
                            </td>
                            <td class="text-right">
                                @if ($detailUrl)
                                    <a href="{{ $detailUrl }}" class="crm-icon-btn" title="Detail">
                                        <i class="bi bi-arrow-right"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ ($canFilterSales ?? false) ? 8 : 7 }}" class="py-10 text-center text-slate-500">
                                @if ($hasFilters)
                                    Tidak ada Purchase Order sesuai filter.
                                    <a href="{{ route('purchase-orders.index') }}" class="ml-1 text-brand-600 hover:underline">Reset</a>
                                @else
                                    Belum ada Purchase Order.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $purchaseOrders->links() }}
        </div>
    </x-card>
@endsection
