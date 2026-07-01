@extends('layouts.app')
@section('title', 'Customers')

@section('content')
<x-page-header title="Customers" :description="number_format($accounts->total()) . ' customers'">
    <x-slot:actions>
        <x-btn href="{{ route('customers.create') }}" icon="bi-plus-lg">New Customer</x-btn>
    </x-slot:actions>
</x-page-header>

<x-card class="mb-4" :padding="false">
    <form method="GET" action="{{ route('customers.index') }}" class="crm-filter-form">
        <div class="min-w-0 flex-1">
            <label class="crm-label">Nama Customer</label>
            <div class="crm-search">
                <i class="bi bi-search"></i>
                <input type="text" name="q" value="{{ $search }}" placeholder="Ketik nama customer..." class="crm-field" autocomplete="off">
            </div>
        </div>
        <x-btn type="submit" variant="primary" icon="bi-search">Cari</x-btn>
        @if ($search)
            <x-btn href="{{ route('customers.index', request()->only('type')) }}" variant="ghost">Reset</x-btn>
        @endif
    </form>
</x-card>

@if ($types->isNotEmpty())
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <span class="text-xs font-medium uppercase tracking-wide text-slate-400">Kategori:</span>
        <a href="{{ route('customers.index', request()->only('q')) }}"
           @class([
               'rounded-full border px-3 py-1 text-xs font-medium transition',
               'border-brand-300 bg-brand-50 text-brand-700' => ! $type,
               'border-slate-200 bg-white text-slate-600 hover:border-slate-300' => $type,
           ])>
            Semua
        </a>
        @foreach ($types as $t)
            <a href="{{ route('customers.index', array_filter(['q' => $search ?: null, 'type' => $t])) }}"
               @class([
                   'rounded-full border px-3 py-1 text-xs font-medium transition',
                   'border-brand-300 bg-brand-50 text-brand-700' => $type === $t,
                   'border-slate-200 bg-white text-slate-600 hover:border-slate-300' => $type !== $t,
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
        <x-empty-state icon="bi-people" title="No customers found" message="Try adjusting your search or filters." />
    @endif
</x-card>
@endsection
