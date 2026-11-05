@extends('layouts.app')
@section('title', 'Log SO '.$log->number)

@php
    $snap = is_array($log->snapshot) ? $log->snapshot : [];
    $status = is_array($snap['status'] ?? null) ? $snap['status'] : [];
    $items = is_array($snap['items'] ?? null) ? $snap['items'] : [];
    $fieldChanges = $log->fieldChanges();
    $productChanges = $log->productChanges();
    $hasDiff = $log->hasDetailedChanges();
    $so = $log->salesOrder;
@endphp

@section('content')
<x-page-header title="Log {{ $log->number ?: 'Sales Order' }}" :description="$log->actionLabel().' · '.optional($log->created_at)->translatedFormat('d M Y H:i')" back="{{ route('sales-order-logs.index') }}" backLabel="Kembali ke Log SO">
    <x-slot:actions>
        <x-badge :color="$log->actionBadgeColor()">{{ $log->actionLabel() }}</x-badge>
    </x-slot:actions>
</x-page-header>

<div class="grid gap-4 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">
        @if ($hasDiff)
            <x-card>
                <h3 class="mb-4 text-sm font-semibold text-slate-800">Perubahan Before / After</h3>

                @if ($fieldChanges !== [])
                    <div class="space-y-3">
                        @foreach ($fieldChanges as $change)
                            <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                    {{ $change['label'] ?? $change['field'] ?? 'Field' }}
                                </p>
                                <dl class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 text-sm">
                                    <div>
                                        <dt class="text-xs text-slate-400">Sebelum</dt>
                                        <dd class="mt-0.5 font-medium text-slate-500 whitespace-pre-wrap break-words">
                                            {{ $change['from_display'] ?? '—' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-slate-400">Sesudah</dt>
                                        <dd class="mt-0.5 font-medium text-slate-800 whitespace-pre-wrap break-words">
                                            {{ $change['to_display'] ?? '—' }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($productChanges['added'] !== [])
                    <div class="mt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-green-700">Item ditambah</p>
                        <div class="space-y-2">
                            @foreach ($productChanges['added'] as $item)
                                <div class="rounded-xl border border-green-100 bg-green-50 px-4 py-3 text-sm text-green-900">
                                    <span class="font-medium">{{ $item['name'] ?? 'Item' }}</span>
                                    @if (! empty($item['sku']))
                                        <span class="text-green-700">· SKU {{ $item['sku'] }}</span>
                                    @endif
                                    @if (isset($item['quantity']))
                                        <span class="text-green-700">· qty {{ $item['quantity'] }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($productChanges['removed'] !== [])
                    <div class="mt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-red-700">Item dihapus</p>
                        <div class="space-y-2">
                            @foreach ($productChanges['removed'] as $item)
                                <div class="rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-900">
                                    <span class="font-medium">{{ $item['name'] ?? 'Item' }}</span>
                                    @if (! empty($item['sku']))
                                        <span class="text-red-700">· SKU {{ $item['sku'] }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($productChanges['changed'] !== [])
                    <div class="mt-4 space-y-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Item diubah</p>
                        @foreach ($productChanges['changed'] as $item)
                            <div class="rounded-xl border border-blue-100 bg-blue-50/50 p-4">
                                <p class="text-sm font-semibold text-slate-800">{{ $item['name'] ?? 'Item' }}</p>
                                <div class="mt-3 space-y-3">
                                    @foreach (($item['changes'] ?? []) as $change)
                                        <div class="rounded-lg border border-white/80 bg-white/80 px-3 py-2.5">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                                {{ $change['label'] ?? $change['field'] ?? 'Field' }}
                                            </p>
                                            <dl class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2 text-sm">
                                                <div>
                                                    <dt class="text-xs text-slate-400">Sebelum</dt>
                                                    <dd class="mt-0.5 font-medium text-slate-500 whitespace-pre-wrap break-words">
                                                        {{ $change['from_display'] ?? '—' }}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt class="text-xs text-slate-400">Sesudah</dt>
                                                    <dd class="mt-0.5 font-medium text-slate-800 whitespace-pre-wrap break-words">
                                                        {{ $change['to_display'] ?? '—' }}
                                                    </dd>
                                                </div>
                                            </dl>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>
        @endif

        <x-card>
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
                                    <td>
                                        {{ $item['name'] ?? '—' }}
                                        @if (! empty($item['description']))
                                            <div class="mt-0.5 text-xs text-slate-400">{{ \Illuminate\Support\Str::limit(strip_tags((string) $item['description']), 120) }}</div>
                                        @endif
                                    </td>
                                    <td class="text-slate-500">{{ $item['sku'] ?? '—' }}</td>
                                    <td class="text-right">{{ $item['qty'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>

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
