@extends('layouts.app')
@section('title', 'Customers')

@php
    $filterQuery = array_filter([
        'q' => ($search ?? '') !== '' ? $search : null,
        'assigned_user_id' => ($assignedUserId ?? '') !== '' ? $assignedUserId : null,
        'level' => ($levelFilter ?? '') !== '' ? $levelFilter : null,
        'type' => ($type ?? '') !== '' ? $type : null,
        'industry' => ($industryFilter ?? '') !== '' ? $industryFilter : null,
        'missing_industry' => ($missingIndustry ?? false) ? 1 : null,
    ], fn ($v) => $v !== null && $v !== '');
    $hasFilters = ($search ?? '') !== ''
        || ($assignedUserId ?? '') !== ''
        || ($levelFilter ?? '') !== ''
        || ($type ?? '') !== ''
        || ($industryFilter ?? '') !== ''
        || ($missingIndustry ?? false);
@endphp

@section('content')
<x-page-header title="Customers" :description="number_format($accounts->total()) . ' customers'">
    <x-slot:actions>
        <x-btn href="{{ route('customers.create') }}" icon="bi-plus-lg">New Customer</x-btn>
    </x-slot:actions>
</x-page-header>

<x-card class="mb-4" :padding="false">
    <form method="GET" action="{{ route('customers.index') }}" class="crm-opp-filters">
        @if ($type ?? null)
            <input type="hidden" name="type" value="{{ $type }}">
        @endif

        <div @class([
            'crm-opp-filters__grid',
            'crm-opp-filters__grid--admin' => ($canFilterSales ?? false),
        ])>
            <div class="crm-opp-filters__field">
                <label class="crm-label">Nama Customer</label>
                <div class="crm-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="q" value="{{ $search }}"
                           placeholder="Ketik nama customer, email, kota, sales..."
                           class="crm-field" autocomplete="off">
                </div>
            </div>

            @if ($canFilterSales ?? false)
                <div class="crm-opp-filters__field">
                    <label class="crm-label">Sales</label>
                    <select name="assigned_user_id" class="select2 select2-search w-full" data-placeholder="Semua sales">
                        <option value="">Semua sales</option>
                        @foreach ($salesUsers as $user)
                            <option value="{{ $user->id }}" @selected(($assignedUserId ?? '') === $user->id)>
                                {{ $user->display_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="crm-opp-filters__field">
                <label class="crm-label">Level pembayaran</label>
                <select name="level" class="select2 select2-compact w-full" data-placeholder="Semua level">
                    <option value="">Semua level</option>
                    @foreach ($paymentLevels as $lv)
                        <option value="{{ $lv }}" @selected(($levelFilter ?? '') === $lv)>
                            {{ \App\Support\PaymentLevel::label($lv) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="crm-opp-filters__field">
                <label class="crm-label">Industri</label>
                <select name="industry" class="select2 select2-search w-full" data-placeholder="Semua industri">
                    <option value="">Semua industri</option>
                    @foreach ($industries ?? [] as $industry)
                        <option value="{{ $industry->name }}" @selected(($industryFilter ?? '') === $industry->name)>
                            {{ $industry->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="crm-opp-filters__actions">
            <div class="crm-opp-filters__buttons">
                <x-btn type="submit" variant="primary" icon="bi-search">Cari</x-btn>
                @if ($hasFilters)
                    <x-btn href="{{ route('customers.index') }}" variant="ghost">Reset</x-btn>
                @endif
            </div>
            <p class="crm-opp-filters__total">
                Total: <strong>{{ number_format($accounts->total()) }}</strong>
            </p>
        </div>
    </form>
</x-card>

    <div class="mb-4">
        <a href="{{ route('customers.index', collect($filterQuery)->except('industry')->put('missing_industry', 1)->all()) }}"
           @class([
               'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition',
               'border-amber-300 bg-amber-50 text-amber-800' => $missingIndustry ?? false,
               'border-slate-200 bg-white text-slate-600 hover:border-slate-300' => ! ($missingIndustry ?? false),
           ])>
            <i class="bi bi-exclamation-circle"></i> Belum ada industri
        </a>
        @if ($missingIndustry ?? false)
            <a href="{{ route('customers.index', collect($filterQuery)->except('missing_industry')->all()) }}"
               class="ml-2 text-xs font-medium text-slate-500 underline hover:text-slate-700">Tampilkan semua</a>
        @endif
    </div>

@if ($types->isNotEmpty())
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <span class="text-xs font-medium uppercase tracking-wide text-slate-400">Kategori:</span>
        <a href="{{ route('customers.index', collect($filterQuery)->except('type')->all()) }}"
           @class([
               'rounded-full border px-3 py-1 text-xs font-medium transition',
               'border-brand-300 bg-brand-50 text-brand-700' => ! ($type ?? null),
               'border-slate-200 bg-white text-slate-600 hover:border-slate-300' => ($type ?? null),
           ])>
            Semua
        </a>
        @foreach ($types as $t)
            <a href="{{ route('customers.index', collect($filterQuery)->put('type', $t)->all()) }}"
               @class([
                   'rounded-full border px-3 py-1 text-xs font-medium transition',
                   'border-brand-300 bg-brand-50 text-brand-700' => ($type ?? null) === $t,
                   'border-slate-200 bg-white text-slate-600 hover:border-slate-300' => ($type ?? null) !== $t,
               ])>
                {{ $t }}
            </a>
        @endforeach
    </div>
@endif

<x-card :padding="false">
    @if ($accounts->count())
        <div class="crm-table-wrap">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Contact</th>
                        <th>Type</th>
                        <th>Industry</th>
                        <th>Level</th>
                        <th>Sales</th>
                        <th class="text-center">Deal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($accounts as $account)
                        <tr>
                            <td>
                                <a href="{{ route('customers.show', $account->id) }}" class="flex items-center gap-3">
                                    <span class="crm-avatar">{{ initials($account->name) }}</span>
                                    <span class="min-w-0">
                                        <span class="block font-medium text-slate-800">{{ $account->name }}</span>
                                        <span class="block truncate text-xs text-slate-400">{{ $account->billing_address_city ?: ($account->website ?: '—') }}</span>
                                    </span>
                                </a>
                            </td>
                            <td>
                                <span class="block text-slate-600">{{ $account->email ?: '—' }}</span>
                                @if ($account->phone)<span class="block text-xs text-slate-400">{{ $account->phone }}</span>@endif
                            </td>
                            <td>
                                @if ($account->type)
                                    <x-badge color="slate">{{ $account->type }}</x-badge>
                                @else <span class="text-slate-300">—</span> @endif
                            </td>
                            <td>
                                @if ($account->industry)
                                    <span class="text-slate-600">{{ $account->industry }}</span>
                                @else
                                    <x-badge color="amber">Belum diisi</x-badge>
                                @endif
                            </td>
                            <td>
                                @php
                                    $levelColor = match ($account->paymentLevel()) {
                                        'lancar' => 'green',
                                        'mandek' => 'amber',
                                        'jelek' => 'rose',
                                        'suspend' => 'red',
                                        default => 'slate',
                                    };
                                @endphp
                                <x-badge :color="$levelColor">{{ $account->paymentLevelLabel() }}</x-badge>
                            </td>
                            <td class="text-slate-600">{{ optional($account->assignedUser)->display_name ?: '—' }}</td>
                            <td class="text-center">
                                <span class="inline-flex h-6 min-w-[1.5rem] items-center justify-center rounded-full bg-slate-100 px-2 text-xs font-medium text-slate-600">{{ $account->opportunities_count }}</span>
                            </td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('customers.edit', $account->id) }}" class="crm-icon-btn" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <a href="{{ route('customers.show', $account->id) }}" class="crm-icon-btn crm-icon-btn--brand"><i class="bi bi-arrow-right"></i></a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="crm-table-footer">{{ $accounts->links() }}</div>
    @else
        <x-empty-state icon="bi-people" title="No customers found" message="Coba ubah kata kunci atau filter pencarian." />
    @endif
</x-card>
@endsection
