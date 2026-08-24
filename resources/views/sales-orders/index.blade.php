@extends('layouts.app')
@section('title', 'Sales Orders')

@section('content')
    @php
        $canDeleteSo = auth()->user()?->canAccessAdministration() ?? false;
    @endphp

    <x-page-header title="Sales Orders" :description="$salesOrders->total().' SO'">
        <x-slot:actions>
            <x-btn href="{{ route('opportunities.index') }}" icon="bi bi-briefcase">Lihat Opportunity</x-btn>
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-4" :padding="false">
        <form method="GET" action="{{ route('sales-orders.index') }}" class="crm-filter-form">
            <div class="min-w-0 flex-1">
                <label class="crm-label">Pencarian</label>
                <div class="crm-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="q" value="{{ $search }}"
                           placeholder="No. SO, PSO, PO, referensi, opportunity, customer..."
                           class="crm-field" autocomplete="off">
                </div>
            </div>
            <div class="flex items-end gap-2">
                <x-btn type="submit" variant="primary" icon="bi-search">Cari</x-btn>
                @if ($search)
                    <x-btn href="{{ route('sales-orders.index') }}" variant="ghost">Reset</x-btn>
                @endif
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
                            </td>
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
                                          onsubmit="return confirm('Delete Sales Order ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="crm-icon-btn text-red-500 hover:bg-red-50" title="Delete SO" type="submit">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center text-slate-500">
                                @if ($search)
                                    Tidak ada Sales Order sesuai pencarian.
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
