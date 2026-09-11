@extends('layouts.app')
@section('title', 'Log Opportunity')

@php
    $snap = is_array($log->snapshot) ? $log->snapshot : [];
    $products = is_array($snap['products'] ?? null) ? $snap['products'] : [];
    $fieldChanges = $log->fieldChanges();
    $productChanges = $log->productChanges();
    $currency = $snap['amount_currency'] ?? 'IDR';
    $opp = $log->opportunity;
@endphp

@section('content')
<x-page-header title="{{ $snap['name'] ?? 'Log Opportunity' }}" :description="$log->actionLabel().' · '.optional($log->created_at)->translatedFormat('d M Y H:i')" back="{{ route('opportunity-logs.index') }}" backLabel="Kembali ke Log Opportunity">
    <x-slot:actions>
        <x-badge :color="$log->actionBadgeColor()">{{ $log->actionLabel() }}</x-badge>
    </x-slot:actions>
</x-page-header>

<div class="grid gap-4 lg:grid-cols-3">
    <x-card class="lg:col-span-2">
        <h3 class="mb-3 text-sm font-semibold text-slate-800">Snapshot Opportunity</h3>
        <p class="mb-4 text-sm text-slate-600">{{ $log->summary }}</p>
        <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2 text-sm">
            <div>
                <dt class="text-xs text-slate-400">Nama</dt>
                <dd class="font-medium text-slate-800">{{ $snap['name'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">Stage</dt>
                <dd class="font-medium text-slate-800">{{ $snap['stage'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">Customer</dt>
                <dd class="font-medium text-slate-800">{{ $snap['account_name'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">Sales</dt>
                <dd class="font-medium text-slate-800">{{ $snap['assigned_user_name'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">Amount</dt>
                <dd class="font-medium text-slate-800">{{ isset($snap['amount']) ? money($snap['amount'], $currency) : '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">Diskon</dt>
                <dd class="font-medium text-slate-800">
                    {{ $snap['crm_discount_status'] ?: '—' }}
                    @if (! empty($snap['crm_discount_amount']))
                        · {{ money($snap['crm_discount_amount'], $currency) }}
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">Margin</dt>
                <dd class="font-medium text-slate-800">{{ $snap['crm_margin_status'] ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">TOP</dt>
                <dd class="font-medium text-slate-800">{{ $snap['crm_top'] ? \App\Support\CustomerTop::label($snap['crm_top']) : '—' }}</dd>
            </div>
        </dl>

        @if ($fieldChanges !== [] || $log->hasProductChanges())
            <h4 class="mt-5 mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Perubahan</h4>
            @if ($fieldChanges !== [])
                <div class="overflow-x-auto rounded-lg border border-slate-100">
                    <table class="crm-table">
                        <thead>
                            <tr>
                                <th>Field</th>
                                <th>Sebelum</th>
                                <th>Sesudah</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($fieldChanges as $change)
                                <tr>
                                    <td>{{ $change['label'] ?? $change['field'] ?? '—' }}</td>
                                    <td class="text-slate-500">{{ $change['from_display'] ?? '—' }}</td>
                                    <td>{{ $change['to_display'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($productChanges['added'] !== [])
                <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-green-700">Barang ditambah</p>
                <ul class="mt-1 list-disc pl-5 text-sm text-slate-700">
                    @foreach ($productChanges['added'] as $item)
                        <li>{{ $item['name'] ?? 'Item' }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($productChanges['removed'] !== [])
                <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-red-700">Barang dihapus</p>
                <ul class="mt-1 list-disc pl-5 text-sm text-slate-700">
                    @foreach ($productChanges['removed'] as $item)
                        <li>{{ $item['name'] ?? 'Item' }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($productChanges['changed'] !== [])
                <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-blue-700">Barang diubah</p>
                <div class="mt-1 space-y-2 text-sm">
                    @foreach ($productChanges['changed'] as $item)
                        <div>
                            <p class="font-medium text-slate-800">{{ $item['name'] ?? 'Item' }}</p>
                            <ul class="text-xs text-slate-600">
                                @foreach (($item['changes'] ?? []) as $change)
                                    <li>{{ $change['label'] ?? '' }}: {{ $change['from_display'] ?? '—' }} → {{ $change['to_display'] ?? '—' }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif

        @if (count($products))
            <h4 class="mt-5 mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Produk saat event</h4>
            <div class="overflow-x-auto rounded-lg border border-slate-100">
                <table class="crm-table">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>SKU</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Jual</th>
                            <th class="text-right">Modal</th>
                            <th>Vendor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $item)
                            <tr>
                                <td>{{ $item['name'] ?? '—' }}</td>
                                <td class="text-slate-500">{{ $item['sku'] ?? '—' }}</td>
                                <td class="text-right">{{ $item['quantity'] ?? '—' }}</td>
                                <td class="text-right">{{ isset($item['sell_exclude']) ? money($item['sell_exclude'], $currency) : '—' }}</td>
                                <td class="text-right">{{ isset($item['cost_exclude']) ? money($item['cost_exclude'], $currency) : '—' }}</td>
                                <td>{{ $item['vendor'] ?? '—' }}</td>
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
                @if (filled(data_get($log->changes, 'note')))
                    <div>
                        <dt class="text-xs text-slate-400">Catatan</dt>
                        <dd class="text-slate-700">{{ data_get($log->changes, 'note') }}</dd>
                    </div>
                @endif
            </dl>
        </x-card>

        <x-card>
            <h3 class="mb-3 text-sm font-semibold text-slate-800">Data hidup</h3>
            @if ($opp && ! $opp->deleted)
                <p class="text-sm text-slate-600">Opportunity masih aktif.</p>
                <a href="{{ route('opportunities.show', $opp) }}#activity-log"
                   class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700">
                    Buka Opportunity <i class="bi bi-arrow-right"></i>
                </a>
            @elseif ($opp)
                <p class="text-sm text-slate-500">Opportunity ini sudah dihapus; snapshot di samping tetap tersimpan.</p>
            @else
                <p class="text-sm text-slate-500">Rekaman Opportunity tidak ditemukan; snapshot di samping tetap tersimpan.</p>
            @endif
        </x-card>
    </div>
</div>
@endsection
