@extends('layouts.app')
@section('title', 'Closed Won')

@section('content')
    @php
        $hasExtraFilters = ($search ?? '') !== ''
            || ($accountId ?? '') !== ''
            || ($companyFilter ?? '') !== ''
            || ($selectedUserId ?? '') !== ''
            || ($poStatus ?? '') !== '';
        $hasFilters = $hasExtraFilters || ($period ?? 'year') !== 'year';
    @endphp

    <x-page-header title="Closed Won" :description="'Opportunity yang perlu diproses purchasing · '.$periodLabel">
        <x-slot:actions>
            <x-btn href="{{ route('purchase-orders.index') }}" variant="secondary" icon="bi-cart-check">Daftar PO</x-btn>
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-4" :padding="false">
        <form method="GET" action="{{ route('opportunities.index') }}" class="crm-opp-filters">
            <div class="crm-opp-filters__grid crm-opp-filters__grid--admin">
                <div class="crm-opp-filters__field">
                    <label class="crm-label">Pencarian</label>
                    <div class="crm-search">
                        <i class="bi bi-search"></i>
                        <input type="text" name="q" value="{{ $search }}"
                               placeholder="Nama opportunity / customer..."
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

                <div class="crm-opp-filters__field">
                    <label class="crm-label">Status PO</label>
                    <select name="po_status" class="select2 select2-compact w-full" data-placeholder="Semua status">
                        <option value="">Semua status</option>
                        <option value="pending" @selected(($poStatus ?? '') === 'pending')>Belum ada PO</option>
                        <option value="done" @selected(($poStatus ?? '') === 'done')>Sudah ada PO</option>
                    </select>
                </div>

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
                        <x-btn href="{{ route('opportunities.index') }}" variant="ghost">Reset</x-btn>
                    @endif
                </div>
                <p class="crm-opp-filters__total">
                    Total: <strong>{{ number_format($total) }}</strong>
                    <span>· {{ $periodLabel }}</span>
                </p>
            </div>
        </form>
    </x-card>

    <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat-card title="Closed Won · {{ $periodLabel }}" :value="number_format($total)" icon="bi-trophy" color="green" />
        <x-stat-card title="Belum ada PO" :value="number_format($pendingPo)" icon="bi-hourglass-split" color="amber"
                     :href="route('opportunities.index', array_filter(['period' => $period !== 'year' ? $period : null, 'po_status' => 'pending']))" />
        <x-stat-card title="Sudah ada PO" :value="number_format($donePo)" icon="bi-cart-check" color="brand"
                     :href="route('opportunities.index', array_filter(['period' => $period !== 'year' ? $period : null, 'po_status' => 'done']))" />
    </div>

    <x-card :padding="false">
        <div class="crm-table-wrap">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th>Opportunity</th>
                        <th>Sales</th>
                        <th>Status PO</th>
                        <th class="text-right">Closed</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($opportunities as $opp)
                        @php
                            $poCount = (int) ($opp->purchase_orders_count ?? 0);
                            $hasPo = $poCount > 0;
                            $poUrl = route('opportunities.purchase-orders.index', $opp);
                            $closeDate = $opp->close_date
                                ? \Illuminate\Support\Carbon::parse($opp->close_date)->translatedFormat('d M Y')
                                : '—';
                        @endphp
                        <tr>
                            <td class="max-w-[18rem]">
                                <a href="{{ $poUrl }}"
                                   class="block truncate font-medium text-brand-600 hover:text-brand-700"
                                   title="{{ $opp->name ?: '—' }}">
                                    {{ $opp->name ?: '—' }}
                                </a>
                                @if ($opp->account?->name)
                                    <div class="truncate text-xs text-slate-400" title="{{ $opp->account->name }}">{{ $opp->account->name }}</div>
                                @elseif ($opp->company)
                                    <div class="truncate text-xs text-slate-400" title="{{ $opp->company }}">{{ $opp->company }}</div>
                                @endif
                            </td>
                            <td class="text-slate-600">
                                {{ optional($opp->assignedUser)->display_name ?: '—' }}
                            </td>
                            <td>
                                @if ($hasPo)
                                    <x-badge color="green">{{ $poCount }} PO</x-badge>
                                @else
                                    <x-badge color="amber">Belum ada PO</x-badge>
                                @endif
                            </td>
                            <td class="text-right text-slate-600">{{ $closeDate }}</td>
                            <td class="text-right">
                                <a href="{{ $poUrl }}" class="crm-icon-btn" title="{{ $hasPo ? 'Lihat PO' : 'Buat PO' }}">
                                    <i class="bi bi-cart-check"></i>
                                </a>
                                <a href="{{ route('opportunities.show', $opp) }}" class="crm-icon-btn" title="Detail">
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center text-slate-500">
                                @if ($hasFilters)
                                    Tidak ada Closed Won sesuai filter.
                                    <a href="{{ route('opportunities.index') }}" class="ml-1 text-brand-600 hover:underline">Reset</a>
                                @else
                                    Belum ada Closed Won pada {{ $periodLabel }}.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($opportunities->hasPages())
            <div class="mt-4">
                {{ $opportunities->links() }}
            </div>
        @endif
    </x-card>
@endsection
