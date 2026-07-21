@extends('layouts.app')
@section('title', 'Quotations')

@section('content')
<x-page-header title="Quotations" :description="number_format($quotations->total()) . ' quotations'">
    <x-slot:actions>
        <x-btn href="{{ route('quotations.create') }}" icon="bi-plus-lg">New Quotation</x-btn>
    </x-slot:actions>
</x-page-header>

<x-card class="mb-4" :padding="false">
    <form method="GET" class="crm-filter-form">
        <div class="crm-search min-w-0 flex-1">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="{{ $search }}" placeholder="Search number, pengadaan, or customer..." class="crm-field">
        </div>
        <select name="status" class="select2 sm:w-44" data-placeholder="All statuses">
            <option value="">All statuses</option>
            @foreach ($statuses as $key => $label)
                <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <x-btn type="submit" variant="primary" icon="bi-funnel">Filter</x-btn>
        @if ($search || $status)
            <x-btn href="{{ route('quotations.index') }}" variant="ghost">Reset</x-btn>
        @endif
    </form>
</x-card>

<x-card :padding="false">
    @if ($quotations->count())
        <div class="crm-table-wrap">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th class="text-right">Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($quotations as $quo)
                        <tr>
                            <td>
                                <a href="{{ route('quotations.show', $quo) }}" class="font-medium text-brand-600 hover:text-brand-700">{{ $quo->number }}</a>
                                <span class="block text-xs text-slate-400">Rev. {{ $quo->revision }}</span>
                            </td>
                            <td>
                                @php
                                    $procurementName = $quo->opportunity?->name;
                                    $customerLabel = $quo->customer_name ?: '—';
                                @endphp
                                <span class="block font-medium text-slate-800">{{ $procurementName ?: $customerLabel }}</span>
                                @if ($procurementName && $customerLabel !== '—')
                                    <span class="block text-xs text-slate-400">{{ $customerLabel }}</span>
                                @elseif (! $procurementName && $quo->company_name && $quo->company_name !== $customerLabel)
                                    <span class="block text-xs text-slate-400">{{ $quo->company_name }}</span>
                                @endif
                            </td>
                            <td class="text-slate-600">{{ $quo->quotation_date?->translatedFormat('d M Y') }}</td>
                            <td><x-badge :color="$quo->statusColor()">{{ $quo->statusLabel() }}</x-badge></td>
                            <td class="text-right font-semibold text-slate-700">{{ money($quo->total, $quo->currency) }}</td>
                            <td class="text-right">
                                <a href="{{ route('quotations.preview', $quo) }}" target="_blank" class="crm-icon-btn" title="Preview"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('quotations.pdf', $quo) }}" class="crm-icon-btn" title="PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                                <a href="{{ route('quotations.show', $quo) }}" class="crm-icon-btn crm-icon-btn--brand" title="Detail"><i class="bi bi-arrow-right"></i></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="crm-table-footer">{{ $quotations->links() }}</div>
    @else
        <x-empty-state icon="bi-file-earmark-text" title="No quotations found" message="Create your first quotation.">
            <x-slot:action>
                <x-btn href="{{ route('quotations.create') }}" icon="bi-plus-lg">New Quotation</x-btn>
            </x-slot:action>
        </x-empty-state>
    @endif
</x-card>
@endsection
