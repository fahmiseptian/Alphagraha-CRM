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
            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button type="button" @click="open = !open"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <i class="bi bi-eye"></i> Laporan
                    <i class="bi bi-chevron-down text-xs"></i>
                </button>
                <div x-show="open" x-cloak
                     class="absolute right-0 z-30 mt-1 w-72 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                    <p class="border-b border-slate-100 px-3 py-2 text-[11px] font-medium uppercase tracking-wider text-slate-400">
                        Preview / PDF
                    </p>
                    <div class="flex items-center gap-1 px-2 py-1.5 hover:bg-slate-50">
                        <a href="{{ route('opportunities.purchase-orders.preview', $opportunity) }}" target="_blank"
                           class="flex min-w-0 flex-1 items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-slate-700">
                            <i class="bi bi-collection text-slate-400"></i>
                            <span class="truncate">Keseluruhan</span>
                        </a>
                        <a href="{{ route('opportunities.purchase-orders.pdf', $opportunity) }}"
                           class="rounded p-1.5 text-red-500 hover:bg-red-50" title="PDF keseluruhan">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </a>
                    </div>
                    @foreach (($opportunity->salesOrders ?? collect()) as $soOpt)
                        <div class="flex items-center gap-1 border-t border-slate-50 px-2 py-1.5 hover:bg-slate-50">
                            <a href="{{ route('opportunities.purchase-orders.preview', ['opportunity' => $opportunity, 'sales_order_id' => $soOpt->id]) }}"
                               target="_blank"
                               class="flex min-w-0 flex-1 items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-slate-700">
                                <i class="bi bi-receipt text-slate-400"></i>
                                <span class="truncate">Per SO · {{ $soOpt->displayNumber() }}</span>
                            </a>
                            <a href="{{ route('opportunities.purchase-orders.pdf', ['opportunity' => $opportunity, 'sales_order_id' => $soOpt->id]) }}"
                               class="rounded p-1.5 text-red-500 hover:bg-red-50"
                               title="PDF {{ $soOpt->displayNumber() }}">
                                <i class="bi bi-file-earmark-pdf"></i>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-slot:actions>
    </x-page-header>

    @if (session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    @include('opportunities._purchase_orders', ['poStandalone' => true])
@endsection
