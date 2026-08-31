@extends('layouts.app')
@section('title', 'Log SO '.$log->number)

@php
    $snap = is_array($log->snapshot) ? $log->snapshot : [];
    $status = is_array($snap['status'] ?? null) ? $snap['status'] : [];
    $items = is_array($snap['items'] ?? null) ? $snap['items'] : [];
    $changes = is_array($log->changes) ? $log->changes : [];
    $so = $log->salesOrder;
@endphp

@section('content')
<x-page-header title="Log {{ $log->number ?: 'Sales Order' }}" :description="$log->actionLabel().' · '.optional($log->created_at)->translatedFormat('d M Y H:i')" back="{{ route('sales-order-logs.index') }}" backLabel="Kembali ke Log SO">
    <x-slot:actions>
        <x-badge :color="$log->actionBadgeColor()">{{ $log->actionLabel() }}</x-badge>
    </x-slot:actions>
</x-page-header>

<div class="grid gap-4 lg:grid-cols-3">
    <x-card class="lg:col-span-2">
        <h3 class="mb-3 text-sm font-semibold text-slate-800">Snapshot SO</h3>
        <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2 text-sm">
            <div>
                <dt class="text-xs text-slate-400">No. SO</dt>
                <dd class="font-medium text-slate-800">{{ $snap['number'] ?? $log->number ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">PSO</dt>
                <dd class="font-medium text-slate-800">{{ $snap['pso_number'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">Nomor ref</dt>
                <dd class="font-medium text-slate-800">{{ $snap['nomor_ref'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">PO customer</dt>
                <dd class="font-medium text-slate-800">{{ $snap['po_number'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">Opportunity</dt>
                <dd class="font-medium text-slate-800">{{ $snap['opportunity_name'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">Customer</dt>
                <dd class="font-medium text-slate-800">{{ $snap['customer'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">Pembayaran</dt>
                <dd class="font-medium text-slate-800">{{ $snap['payment_label'] ?? ($snap['payment'] ?? '—') }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">Required delivery</dt>
                <dd class="font-medium text-slate-800">{{ $snap['required_delivery'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">Status SO</dt>
                <dd class="font-medium text-slate-800">{{ $status['so_status'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">Pembayaran / Pengiriman</dt>
                <dd class="font-medium text-slate-800">{{ ($status['payment_status'] ?? '—').' / '.($status['delivery_status'] ?? '—') }}</dd>
            </div>
        </dl>
        @if (! empty($snap['note']))
            <p class="mt-4 text-sm text-slate-600"><span class="text-xs text-slate-400">Catatan:</span> {{ $snap['note'] }}</p>
        @endif

        @if (count($items))
            <h4 class="mt-5 mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Item</h4>
            <div class="overflow-x-auto rounded-lg border border-slate-100">
                <table class="crm-table">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>SKU</th>
                            <th class="text-right">Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>{{ $item['name'] ?? '—' }}</td>
                                <td class="text-slate-500">{{ $item['sku'] ?? '—' }}</td>
                                <td class="text-right">{{ $item['qty'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="space-y-4">
        <x-card>
            <h3 class="mb-3 text-sm font-semibold text-slate-800">Aksi</h3>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs text-slate-400">Dilakukan oleh</dt>
                    <dd class="font-medium text-slate-800">{{ $log->actor?->display_name ?: ($log->actor_name ?: '—') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">Waktu</dt>
                    <dd class="font-medium text-slate-800">{{ $log->created_at?->translatedFormat('d M Y H:i:s') }}</dd>
                </div>
                @if ($log->ip_address)
                    <div>
                        <dt class="text-xs text-slate-400">IP</dt>
                        <dd class="font-mono text-xs text-slate-600">{{ $log->ip_address }}</dd>
                    </div>
                @endif
                @if ($changes)
                    <div>
                        <dt class="text-xs text-slate-400">Field diubah</dt>
                        <dd class="mt-1 flex flex-wrap gap-1">
                            @foreach ($changes as $field)
                                <x-badge color="slate">{{ is_string($field) ? $field : json_encode($field) }}</x-badge>
                            @endforeach
                        </dd>
                    </div>
                @endif
            </dl>
        </x-card>

        <x-card>
            <h3 class="mb-3 text-sm font-semibold text-slate-800">Data hidup</h3>
            @if ($so)
                <p class="text-sm text-slate-600">
                    @if ($so->trashed())
                        SO ini diarsipkan (soft delete), tidak tampil di daftar aktif.
                    @else
                        SO ini masih aktif.
                    @endif
                </p>
                @if ($so->opportunity && ! $so->trashed())
                    <a href="{{ route('opportunities.sales-orders.show', [$so->opportunity, $so]) }}"
                       class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700">
                        Buka SO aktif <i class="bi bi-arrow-right"></i>
                    </a>
                @endif
            @else
                <p class="text-sm text-slate-500">Rekaman SO tidak ditemukan; snapshot di samping tetap tersimpan.</p>
            @endif
        </x-card>
    </div>
</div>
@endsection
