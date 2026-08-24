{{-- Sales Order lokal CRM — 1 opportunity : banyak SO --}}
@php
    $soList = $opportunity->salesOrders ?? collect();
    $canAddSo = auth()->user()->canCreateSalesOrder()
        && $opportunity->stage === \App\Models\Espo\Opportunity::WON_STAGE;
    $showSoCard = $canAddSo || $soList->isNotEmpty();
    $createSoUrl = $canAddSo ? route('opportunities.sales-orders.create', $opportunity) : '';
@endphp

@if ($showSoCard)
    <x-card :padding="false">
        <div class="flex items-center justify-between px-5 pt-4">
            <h3 class="text-sm font-semibold text-slate-800">Sales Order</h3>
            @if ($canAddSo)
                <a href="{{ $createSoUrl }}"
                   class="inline-flex items-center gap-1 rounded-lg bg-brand-50 px-3 py-1.5 text-sm font-medium text-brand-600 hover:bg-brand-100"
                   title="Tambah Sales Order">
                    <i class="bi bi-plus-lg"></i>
                </a>
            @endif
        </div>

        @if ($soList->count())
            <ul class="mt-2 divide-y divide-slate-50">
                @foreach ($soList as $so)
                    @php
                        $itemCount = is_array($so->items) ? count($so->items) : 0;
                        $detailUrl = route('opportunities.sales-orders.show', [$opportunity, $so]);
                        $status = $so->statusSnapshot();
                        $statusColor = fn (?string $value) => match (strtolower((string) $value)) {
                            'pending', 'unpaid' => 'amber',
                            'onprocess', 'ondelivery' => 'blue',
                            'completed', 'paid', 'settlement' => 'green',
                            'cancelled', 'rejected', 'refund' => 'red',
                            default => 'slate',
                        };
                    @endphp
                    <li class="flex items-start gap-2 px-5 py-3 hover:bg-slate-50">
                        <i class="bi bi-receipt mt-0.5 shrink-0 text-brand-500"></i>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800">{{ $so->displayNumber() }}</p>
                            @if ($so->displayPsoNumber() !== '')
                                <p class="mt-0.5 font-mono text-xs text-slate-500">PSO {{ $so->displayPsoNumber() }}</p>
                            @endif
                            @if ($so->displayRefNumber() !== '')
                                <p class="mt-0.5 font-mono text-xs text-slate-400">{{ $so->displayRefNumber() }}</p>
                            @endif
                            <div class="mt-1 flex flex-wrap items-center gap-1">
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
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $so->paymentLabel() }}
                                @if ($itemCount > 0)
                                    &middot; {{ $itemCount }} item
                                @endif
                                @if ($so->po_number)
                                    &middot; PO {{ $so->po_number }}
                                @endif
                            </p>
                            <p class="mt-1 text-xs text-slate-400">
                                {{ optional($so->creator)->display_name ?: '—' }}
                                &middot;
                                {{ $so->created_at?->translatedFormat('d M Y H:i') }}
                            </p>
                        </div>
                        <a href="{{ $detailUrl }}"
                           class="shrink-0 rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50"
                           title="Lihat detail Sales Order">
                            <i class="bi bi-eye"></i> Detail
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="px-5 py-8 text-center text-sm text-slate-400">
                Belum ada Sales Order.
                @if ($canAddSo)
                    Klik <i class="bi bi-plus-lg"></i> untuk membuat SO.
                @endif
            </div>
        @endif
    </x-card>
@endif
