@extends('layouts.app')
@section('title', 'Log PO '.$log->number)

@php
    $snap = is_array($log->snapshot) ? $log->snapshot : [];
    $items = is_array($snap['items'] ?? null) ? $snap['items'] : [];
    $fieldChanges = $log->fieldChanges();
    $productChanges = $log->productChanges();
    $hasDiff = $log->hasDetailedChanges();
    $po = $log->purchaseOrder;
    $currency = $snap['currency'] ?? 'IDR';
@endphp

@section('content')
<x-page-header title="Log {{ $log->number ?: 'Purchase Order' }}" :description="$log->actionLabel().' · '.optional($log->created_at)->translatedFormat('d M Y H:i')" back="{{ route('purchase-order-logs.index') }}" backLabel="Kembali ke Log PO">
    <x-slot:actions>
        <x-badge :color="$log->actionBadgeColor()">{{ $log->actionLabel() }}</x-badge>
    </x-slot:actions>
</x-page-header>

<div class="grid gap-4 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">
        @if ($log->changeSummary() !== '')
            <x-card>
                <p class="text-sm text-slate-700">{{ $log->changeSummary() }}</p>
            </x-card>
        @endif

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
            <h3 class="mb-3 text-sm font-semibold text-slate-800">Snapshot PO</h3>
            <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2 text-sm">
                <div>
                    <dt class="text-xs text-slate-400">No. PO</dt>
                    <dd class="font-medium text-slate-800">{{ $snap['number'] ?? $log->number ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">Vendor</dt>
                    <dd class="font-medium text-slate-800">{{ $snap['vendor'] ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">Sales Order</dt>
                    <dd class="font-medium text-slate-800">{{ $snap['sales_order_number'] ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">Kondisi</dt>
                    <dd class="font-medium text-slate-800">{{ $snap['payment_term'] ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">TOP laporan</dt>
                    <dd class="font-medium text-slate-800">{{ $snap['report_top'] ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">Total exclude</dt>
                    <dd class="font-medium text-slate-800">{{ isset($snap['total']) ? money($snap['total'], $currency) : '—' }}</dd>
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
                    <dt class="text-xs text-slate-400">Approval</dt>
                    <dd class="font-medium text-slate-800">{{ $snap['approval_status'] ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">Disetujui / ditolak oleh</dt>
                    <dd class="font-medium text-slate-800">{{ $snap['approved_by_name'] ?? '—' }}</dd>
                </div>
            </dl>
            @if (! empty($snap['approval_note']))
                <p class="mt-4 text-sm text-slate-600"><span class="text-xs text-slate-400">Catatan approval:</span> {{ $snap['approval_note'] }}</p>
            @endif

            @if (count($items))
                <h4 class="mt-5 mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Item</h4>
                <div class="overflow-x-auto rounded-lg border border-slate-100">
                    <table class="crm-table">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Brand</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Harga</th>
                                <th class="text-right">Jumlah</th>
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
                                    <td class="text-slate-500">{{ $item['brand'] ?? '—' }}</td>
                                    <td class="text-right">{{ $item['qty'] ?? '—' }}</td>
                                    <td class="text-right">{{ isset($item['unit_price']) ? money($item['unit_price'], $currency) : '—' }}</td>
                                    <td class="text-right">{{ isset($item['line_total']) ? money($item['line_total'], $currency) : '—' }}</td>
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
            @if ($po)
                <p class="text-sm text-slate-600">PO ini masih ada.</p>
                @if ($po->opportunity)
                    <a href="{{ route('opportunities.purchase-orders.index', $po->opportunity) }}"
                       class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700">
                        Buka PO di opportunity <i class="bi bi-arrow-right"></i>
                    </a>
                @endif
            @else
                <p class="text-sm text-slate-500">PO ini sudah dihapus. Snapshot di samping tetap tersimpan.</p>
                @if ($log->opportunity)
                    <a href="{{ route('opportunities.purchase-orders.index', $log->opportunity) }}"
                       class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700">
                        Buka opportunity <i class="bi bi-arrow-right"></i>
                    </a>
                @endif
            @endif
        </x-card>
    </div>
</div>
@endsection
