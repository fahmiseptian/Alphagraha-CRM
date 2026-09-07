@extends('layouts.app')
@section('title', 'Purchase Orders — '.$opportunity->name)

@section('content')
    @php
        $poCount = $opportunity->purchaseOrders->count();
        $poTotal = round((float) $opportunity->purchaseOrders->sum(fn ($po) => $po->totalInclude()), 2);
        $currency = $opportunity->amount_currency ?: 'IDR';
        $customerName = $opportunity->account?->name;
        $description = collect([$opportunity->name, $customerName])->filter()->implode(' · ');
        if ($poCount > 0) {
            $description .= ' · '.$poCount.' PO · '.money($poTotal, $currency).' modal';
        }
    @endphp

    <x-page-header title="Purchase Orders" :description="$description">
        <x-slot:actions>
            @if ($backUrl ?? null)
                <x-btn :href="$backUrl" variant="secondary" icon="bi-arrow-left">Kembali ke SO</x-btn>
            @else
                <x-btn href="{{ route('purchase-orders.index') }}" variant="secondary" icon="bi-arrow-left">Daftar PO</x-btn>
            @endif
            <x-btn href="{{ route('opportunities.show', $opportunity) }}" variant="ghost" icon="bi-briefcase">Opportunity</x-btn>
            <x-btn href="{{ route('opportunities.purchase-orders.preview', $opportunity) }}" variant="secondary" icon="bi-eye" target="_blank">Preview laporan</x-btn>
            <x-btn href="{{ route('opportunities.purchase-orders.pdf', $opportunity) }}" variant="secondary" icon="bi-file-earmark-pdf">PDF</x-btn>
        </x-slot:actions>
    </x-page-header>

    @if (session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    @include('opportunities._purchase_orders', ['poStandalone' => true])
@endsection
