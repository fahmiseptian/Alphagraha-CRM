@extends('layouts.app')
@section('title', 'Sales Order ' . ($detail['code'] ?: $salesOrder->displayNumber()))

@php
    $soCode = $detail['code'] ?: $salesOrder->displayNumber();
    $preCode = (string) ($detail['pre_code'] ?: $salesOrder->displayPsoNumber());
    $currency = $opportunity->amount_currency ?: 'IDR';
    $customerName = $detail['customer'] ?: (optional($opportunity->account)->name ?: 'Customer');
    $pay = (string) ($detail['payment'] ?: $salesOrder->payment ?: '');
    $payLabel = preg_match('/^top(\d+)$/i', $pay, $m) ? 'TOP '.$m[1].' hari' : ($pay !== '' ? strtoupper($pay) : '—');
    $fmtDate = function ($value, string $withTime = 'd M Y H:i') {
        if (! $value) {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse($value)->translatedFormat($withTime);
        } catch (\Throwable $e) {
            return $value;
        }
    };
    $statusMeta = function (?string $status, string $kind) {
        $key = strtolower((string) $status);
        $map = [
            'pending' => ['amber', 'bi-hourglass-split', 'Menunggu'],
            'unpaid' => ['amber', 'bi-wallet2', 'Belum dibayar'],
            'onprocess' => ['blue', 'bi-gear', 'Diproses'],
            'ondelivery' => ['blue', 'bi-truck', 'Dikirim'],
            'paid' => ['green', 'bi-check-circle', 'Lunas'],
            'settlement' => ['green', 'bi-check-circle', 'Settlement'],
            'completed' => ['green', 'bi-check2-circle', 'Selesai'],
            'complete' => ['green', 'bi-check2-circle', 'Selesai'],
            'cancelled' => ['red', 'bi-x-circle', 'Dibatalkan'],
            'rejected' => ['red', 'bi-slash-circle', 'Ditolak'],
            'refund' => ['purple', 'bi-arrow-counterclockwise', 'Refund'],
        ];
        $fallbackIcon = match ($kind) {
            'payment' => 'bi-credit-card',
            'delivery' => 'bi-truck',
            default => 'bi-receipt',
        };
        [$color, $icon, $label] = $map[$key] ?? ['slate', $fallbackIcon, $status ?: '—'];

        return compact('color', 'icon', 'label', 'status');
    };
    $soMeta = $statusMeta($detail['so_status'] ?? null, 'so');
    $payMeta = $statusMeta($detail['payment_status'] ?? null, 'payment');
    $delMeta = $statusMeta($detail['delivery_status'] ?? null, 'delivery');
    $tile = [
        'amber' => 'border-amber-200 bg-amber-50 text-amber-800',
        'blue' => 'border-blue-200 bg-blue-50 text-blue-800',
        'green' => 'border-green-200 bg-green-50 text-green-800',
        'red' => 'border-red-200 bg-red-50 text-red-800',
        'purple' => 'border-purple-200 bg-purple-50 text-purple-800',
        'slate' => 'border-slate-200 bg-slate-50 text-slate-700',
    ];
    $iconWrap = [
        'amber' => 'bg-amber-100 text-amber-700',
        'blue' => 'bg-blue-100 text-blue-700',
        'green' => 'bg-green-100 text-green-700',
        'red' => 'bg-red-100 text-red-700',
        'purple' => 'bg-purple-100 text-purple-700',
        'slate' => 'bg-slate-200 text-slate-600',
    ];
    $sameAddress = ($detail['billing'] ?? null) && ($detail['shipping'] ?? null)
        && ($detail['billing']['address'] ?? '') === ($detail['shipping']['address'] ?? '')
        && ($detail['billing']['contact'] ?? '') === ($detail['shipping']['contact'] ?? '');
    $oppProducts = $opportunity->products->values();
    $items = collect($detail['items'] ?? [])->values()->map(function ($item, $i) use ($oppProducts) {
        $match = $oppProducts->first(function ($p) use ($item) {
            $name = trim((string) ($p['name'] ?? ''));

            return $name !== '' && strcasecmp($name, (string) ($item['name'] ?? '')) === 0;
        }) ?: $oppProducts->get($i);

        $brand = trim((string) ($item['brand'] ?? ''));
        if ($brand === '') {
            $brand = trim((string) ($match['brand'] ?? ''));
        }
        $category = trim((string) ($item['category'] ?? ''));
        if ($category === '') {
            $category = trim((string) ($match['category'] ?? ''));
        }
        $item['brand'] = $brand;
        $item['category'] = $category;
        $item['unit'] = trim((string) ($item['unit'] ?? 'Unit')) ?: 'Unit';

        return $item;
    })->all();
    $ppnPercent = (float) ($detail['ppn_percent'] ?? 11);
    $ppnLabel = rtrim(rtrim(number_format($ppnPercent, 2, ',', '.'), '0'), ',');
    $dates = array_filter([
        ['label' => 'Sales Order', 'value' => $fmtDate($detail['so_date'] ?? null, 'd M Y'), 'icon' => 'bi-calendar-plus'],
        ['label' => 'Invoice', 'value' => $fmtDate($detail['paid_date'] ?? $detail['invoice_dt'] ?? null, 'd M Y'), 'icon' => 'bi-cash'],
        ['label' => 'Dikirim', 'value' => $fmtDate($detail['delivery_date'] ?? null, 'd M Y'), 'icon' => 'bi-truck'],
        ['label' => 'Selesai', 'value' => $fmtDate($detail['completed_date'] ?? null, 'd M Y'), 'icon' => 'bi-flag'],
        ['label' => 'Update terakhir', 'value' => $fmtDate($detail['updated_date'] ?? null, 'd M Y'), 'icon' => 'bi-clock-history'],
    ], fn ($row) => filled($row['value']));
    $poCustomerNumber = (string) ($detail['po_number'] ?: ($salesOrder->po_number ?: ''));
    $poAgcNumber = (string) ($detail['po_agc'] ?? '');
    $canEditPoNumber = (bool) ($canEdit ?? false) && (auth()->user()?->isSuperAdmin() ?? false);
    $canEditInvoice = (bool) ($canEdit ?? false) && (auth()->user()?->isSuperAdmin() ?? false);
    $paymentStatusKey = strtolower((string) ($detail['payment_status'] ?? ''));
    $deliveryStatusKey = strtolower((string) ($detail['delivery_status'] ?? ''));
    $canMarkPaid = (bool) ($canEdit ?? false)
        && ! in_array($paymentStatusKey, ['paid', 'settlement'], true);
    $canCompleteDelivery = (bool) ($canEdit ?? false)
        && ! in_array($deliveryStatusKey, ['completed', 'complete'], true);
    $soStatusKey = strtolower((string) ($detail['so_status'] ?? ''));
    $canCompleteSo = (bool) ($canEdit ?? false)
        && ! in_array($soStatusKey, ['completed', 'complete'], true);
    $requiredDeliveryRaw = $detail['required_delivery'] ?? $salesOrder->required_delivery;
    $requiredDeliveryLabel = $requiredDeliveryRaw ? $fmtDate($requiredDeliveryRaw, 'd M Y') : null;
    $noteText = (string) ($detail['note'] ?? $salesOrder->note ?? '');
    $nomorRef = (string) ($detail['nomor_ref'] ?: ($salesOrder->nomor_ref ?: ''));
    $qoLast = (string) ($opportunity->quotation?->number ?? '');
@endphp

@push('styles')
<style>
    .so-items-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
        color: #0f172a;
    }
    .so-items-table th,
    .so-items-table td {
        border: 1px solid #0f172a;
        padding: 0.55rem 0.75rem;
        vertical-align: middle;
    }
    .so-items-table th {
        text-align: center;
        font-weight: 700;
        background: #f8fafc;
    }
    .so-items-table td.is-center { text-align: center; }
    .so-items-table td.is-left { text-align: left; }
    .so-items-table .summary-label,
    .so-items-table .summary-value {
        text-align: right;
        font-weight: 700;
    }
    .so-items-table .summary-total .summary-label,
    .so-items-table .summary-total .summary-value {
        font-size: 0.95rem;
    }
</style>
@endpush

@section('content')
<div x-data="salesOrderDetail({{ \Illuminate\Support\Js::from([
    'canEdit' => $canEdit ?? false,
    'editForm' => $editForm ?? [],
    'redirect' => url()->current(),
]) }})" x-init="init()">

<div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
    <div>
        <a href="{{ route('opportunities.show', $opportunity) }}" class="crm-back"><i class="bi bi-arrow-left"></i> Kembali ke opportunity</a>
        <div class="mt-1 flex flex-wrap items-center gap-2">
            <h2 class="crm-page-title font-mono tracking-tight">{{ $soCode }}</h2>
            <button type="button"
                    x-data="{ copied: false }"
                    @click="navigator.clipboard.writeText(@js($soCode)); copied = true; setTimeout(() => copied = false, 1500)"
                    class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-slate-500 hover:bg-slate-50"
                    title="Salin kode SO">
                <i class="bi" :class="copied ? 'bi-check2 text-green-600' : 'bi-clipboard'"></i>
                <span x-text="copied ? 'Disalin' : 'Salin'"></span>
            </button>
            <a href="{{ route('opportunities.sales-orders.preview', [$opportunity, $salesOrder]) }}"
               target="_blank"
               class="inline-flex items-center justify-center rounded-lg bg-slate-50 px-2.5 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-brand-600"
               title="Preview Sales Order">
                <i class="bi bi-eye"></i>
            </a>
            <a href="{{ route('opportunities.sales-orders.pdf', [$opportunity, $salesOrder]) }}"
               class="inline-flex items-center justify-center rounded-lg bg-slate-50 px-2.5 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-red-600"
               title="Download PDF Sales Order">
                <i class="bi bi-file-earmark-pdf"></i>
            </a>
        </div>
        <p class="crm-page-desc">{{ $opportunity->name }} · {{ $customerName }}</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <div class="rounded-2xl border border-brand-100 bg-brand-50 px-4 py-2 text-right">
            <p class="text-[11px] font-medium uppercase tracking-wider text-brand-600">Grand total</p>
            <p class="text-lg font-bold tabular-nums text-brand-800">{{ money($detail['grand_total'] ?? 0, $currency) }}</p>
        </div>
    </div>
</div>

<div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
    @foreach ([
        ['title' => 'Status SO', 'meta' => $soMeta],
        ['title' => 'Pembayaran', 'meta' => $payMeta],
        ['title' => 'Pengiriman', 'meta' => $delMeta],
    ] as $tileItem)
        <div class="rounded-2xl border {{ $tile[$tileItem['meta']['color']] }} p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl {{ $iconWrap[$tileItem['meta']['color']] }}">
                    <i class="bi {{ $tileItem['meta']['icon'] }} text-lg"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider opacity-70">{{ $tileItem['title'] }}</p>
                    <p class="truncate text-base font-semibold capitalize">{{ $tileItem['meta']['label'] }}</p>
                    @if (($tileItem['meta']['status'] ?? '') !== '' && strtolower((string) $tileItem['meta']['status']) !== strtolower((string) $tileItem['meta']['label']))
                        <p class="text-xs opacity-70">{{ $tileItem['meta']['status'] }}</p>
                    @endif
                </div>
                @if ($tileItem['title'] === 'Status SO' && $canCompleteSo)
                    <button type="button"
                            @click="openEdit('so_complete')"
                            class="rounded-lg p-2 text-current/70 hover:bg-white/50 hover:text-current"
                            title="Update status SO ke completed">
                        <i class="bi bi-arrow-repeat text-lg"></i>
                    </button>
                @endif
                @if ($tileItem['title'] === 'Pembayaran' && $canMarkPaid)
                    <button type="button"
                            @click="openEdit('payment_paid')"
                            class="rounded-lg p-2 text-current/70 hover:bg-white/50 hover:text-current"
                            title="Update pembayaran ke paid">
                        <i class="bi bi-arrow-repeat text-lg"></i>
                    </button>
                @endif
                @if ($tileItem['title'] === 'Pengiriman' && $canCompleteDelivery)
                    <button type="button"
                            @click="openEdit('file_do')"
                            class="rounded-lg p-2 text-current/70 hover:bg-white/50 hover:text-current"
                            title="Update ke complete + upload file DO">
                        <i class="bi bi-arrow-repeat text-lg"></i>
                    </button>
                @endif
            </div>
        </div>
    @endforeach
</div>

<div class="mb-5 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
    <div class="grid grid-cols-2 gap-px bg-slate-100 sm:grid-cols-3 lg:grid-cols-5">
        <div class="bg-white px-5 py-4">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Pre Sales Order</p>
            <p class="mt-1 font-mono text-sm font-semibold text-slate-800">{{ $preCode !== '' ? $preCode : '—' }}</p>
        </div>
        <div class="bg-white px-5 py-4">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Sales Order</p>
            <p class="mt-1 font-mono text-sm font-semibold text-brand-700">{{ $soCode }}</p>
        </div>
        <div class="bg-white px-5 py-4">
            <div class="flex items-start justify-between gap-2">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">No. PO customer</p>
                @if ($canEdit ?? false)
                    <button type="button" @click="openEdit('po_number')" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-brand-600" title="Edit No. PO customer">
                        <i class="bi bi-pencil text-xs"></i>
                    </button>
                @endif
            </div>
            <p class="mt-1 text-sm font-semibold text-slate-800">{{ $poCustomerNumber !== '' ? $poCustomerNumber : '—' }}</p>
        </div>
        <div class="bg-white px-5 py-4">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Pembayaran</p>
            <p class="mt-1 text-sm font-semibold text-slate-800">{{ $payLabel }}</p>
        </div>
        <div class="bg-white px-5 py-4 sm:col-span-2 lg:col-span-1">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Tipe</p>
            <p class="mt-1 text-sm font-semibold text-slate-800">Sales Order</p>
        </div>
    </div>
</div>

<div class="mb-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
    @foreach ([
        ['title' => 'Billing Address', 'icon' => 'bi-receipt', 'addr' => $detail['billing'] ?? null, 'fallback' => $detail['billing_address'] ?? null],
        ['title' => 'Shipping Address', 'icon' => 'bi-geo-alt', 'addr' => $detail['shipping'] ?? null, 'fallback' => $detail['shipping_address'] ?? null],
    ] as $block)
        <x-card :padding="false">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div class="flex items-center gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                        <i class="bi {{ $block['icon'] }}"></i>
                    </span>
                    <h3 class="text-sm font-semibold text-slate-800">{{ $block['title'] }}</h3>
                </div>
                <div class="flex items-center gap-2">
                    @if ($sameAddress && $block['title'] === 'Shipping Address')
                        <x-badge color="brand">Sama dengan billing</x-badge>
                    @endif
                </div>
            </div>
            @if (! empty($block['addr']))
                @php $addr = $block['addr']; @endphp
                <div class="grid grid-cols-2 gap-x-4 gap-y-3 px-5 py-4 text-sm">
                    <div class="col-span-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Kontak</p>
                        <p class="mt-0.5 font-semibold text-slate-800">{{ $addr['contact'] ?: $addr['label'] ?: '—' }}</p>
                        @if (! empty($addr['label']) && $addr['label'] !== $addr['contact'])
                            <p class="text-xs text-slate-500">{{ $addr['label'] }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Telepon</p>
                        <p class="mt-0.5 text-slate-700">{{ $addr['phone'] ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Kode pos</p>
                        <p class="mt-0.5 text-slate-700">{{ $addr['postal'] ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Provinsi</p>
                        <p class="mt-0.5 text-slate-700">{{ $addr['province'] ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Kota</p>
                        <p class="mt-0.5 text-slate-700">{{ $addr['city'] ?: '—' }}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Kecamatan</p>
                        <p class="mt-0.5 text-slate-700">{{ $addr['district'] ?: '—' }}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Alamat</p>
                        <p class="mt-0.5 text-slate-700">{{ $addr['address'] ?: '—' }}</p>
                    </div>
                </div>
            @else
                <p class="px-5 py-8 text-sm text-slate-400">{{ $block['fallback'] ?: 'Alamat tidak tersedia.' }}</p>
            @endif
        </x-card>
    @endforeach
</div>

<div class="mb-5 overflow-x-auto rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    @if ($items)
        <table class="so-items-table">
            <thead>
                <tr>
                    <th style="width: 4rem">No.</th>
                    <th>Spesifikasi</th>
                    <th style="width: 7rem">Qty</th>
                    <th style="width: 10rem">Harga</th>
                    <th style="width: 11rem">Total Harga</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $i => $item)
                    <tr>
                        <td class="is-center tabular-nums">{{ $i + 1 }}</td>
                        <td class="is-left">
                            @if (! empty($item['brand']))
                                <strong>{{ strtoupper($item['brand']) }}</strong> - {{ $item['name'] }}
                            @else
                                {{ $item['name'] }}
                            @endif
                            @if (! empty($item['category']))
                                <span class="mt-0.5 block text-xs font-normal text-slate-500">Category: {{ $item['category'] }}</span>
                            @endif
                        </td>
                        <td class="is-center tabular-nums">{{ number_format((float) $item['qty'], 0, ',', '.') }} {{ $item['unit'] ?? 'Unit' }}</td>
                        <td class="is-center tabular-nums">{{ money($item['price_display'] ?? $item['price'], $currency) }}</td>
                        <td class="is-center tabular-nums">{{ money($item['amount_display'] ?? $item['subtotal'], $currency) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="3"></td>
                    <td class="summary-label">Subtotal</td>
                    <td class="summary-value tabular-nums">{{ money($detail['subtotal_display'] ?? $detail['total_price_item'] ?? 0, $currency) }}</td>
                </tr>
                <tr>
                    <td colspan="3"></td>
                    <td class="summary-label">PPn {{ $ppnLabel }}%</td>
                    <td class="summary-value tabular-nums">{{ money($detail['ppn_amount'] ?? 0, $currency) }}</td>
                </tr>
                @if ((float) ($detail['discount_price'] ?? 0) > 0)
                    <tr>
                        <td colspan="3"></td>
                        <td class="summary-label">Diskon</td>
                        <td class="summary-value tabular-nums">- {{ money($detail['discount_price'], $currency) }}</td>
                    </tr>
                @endif
                @if ((float) ($detail['shipping_price'] ?? 0) > 0)
                    <tr>
                        <td colspan="3"></td>
                        <td class="summary-label">Ongkir</td>
                        <td class="summary-value tabular-nums">{{ money($detail['shipping_price'], $currency) }}</td>
                    </tr>
                @endif
                <tr class="summary-total">
                    <td colspan="3"></td>
                    <td class="summary-label">Total</td>
                    <td class="summary-value tabular-nums">{{ money($detail['grand_total'] ?? 0, $currency) }}</td>
                </tr>
            </tbody>
        </table>
    @else
        <p class="py-8 text-center text-sm text-slate-400">Tidak ada item pada Sales Order ini.</p>
    @endif
</div>

<div class="mb-5">
    <x-card title="Pengiriman">
        <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="flex items-center justify-between gap-2 text-xs text-slate-400">
                    <span>Metode pengiriman</span>
                    @if ($canEdit ?? false)
                        <button type="button" @click="openEdit('shipping_method')" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-brand-600" title="Edit metode pengiriman">
                            <i class="bi bi-pencil text-xs"></i>
                        </button>
                    @endif
                </dt>
                <dd class="mt-0.5 font-medium text-slate-800">{{ $detail['courier_name'] ?: $detail['shipping_name'] ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">No. resi / AWB</dt>
                <dd class="mt-0.5 flex items-center justify-between gap-3 font-mono font-medium text-slate-800">
                    <span>{{ $detail['no_resi'] ?: '—' }}</span>
                    @if ($canEdit ?? false)
                        <button type="button"
                                @click="openEdit('no_resi')"
                                class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-brand-600"
                                title="Edit No. resi / AWB">
                            <i class="bi bi-pencil text-xs"></i>
                        </button>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="flex items-center justify-between gap-2 text-xs text-slate-400">
                    <span>Required delivery</span>
                    @if ($canEdit ?? false)
                        <button type="button" @click="openEdit('required_delivery')" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-brand-600" title="Edit required delivery">
                            <i class="bi bi-pencil text-xs"></i>
                        </button>
                    @endif
                </dt>
                <dd class="mt-0.5 font-medium text-slate-800">{{ $requiredDeliveryLabel ?: '—' }}</dd>
            </div>
        </dl>
    </x-card>
</div>

@if ($dates)
    <x-card :padding="false" class="mb-5">
        <div class="px-5 py-4">
            <h3 class="text-sm font-semibold text-slate-800">Riwayat tanggal</h3>
        </div>
        <div class="grid grid-cols-2 gap-px border-t border-slate-100 bg-slate-100 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($dates as $date)
                <div class="bg-white px-5 py-4">
                    <p class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                        <i class="bi {{ $date['icon'] }}"></i> {{ $date['label'] }}
                    </p>
                    <p class="mt-1 text-sm font-semibold text-slate-800">{{ $date['value'] }}</p>
                </div>
            @endforeach
        </div>
    </x-card>
@endif

<div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
    <x-card title="Dokumen & referensi">
        <dl class="space-y-3 text-sm">
            <div class="flex items-start justify-between gap-3">
                <dt class="text-slate-400">Nomor ref</dt>
                <dd class="flex items-center gap-2 text-right font-medium text-slate-800">
                    <span>{{ $nomorRef !== '' ? $nomorRef : '—' }}</span>
                    @if ($canEdit ?? false)
                        <button type="button" @click="openEdit('nomor_ref')" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-brand-600" title="Edit nomor referensi">
                            <i class="bi bi-pencil text-xs"></i>
                        </button>
                    @endif
                </dd>
            </div>
            <div class="flex items-start justify-between gap-3">
                <dt class="text-slate-400">PO AGC</dt>
                <dd class="flex items-center gap-2 text-right font-medium text-slate-800">
                    <span>{{ $poAgcNumber !== '' ? $poAgcNumber : '—' }}</span>
                    @if ($canEditPoNumber)
                        <button type="button" @click="openEdit('po_agc')" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-brand-600" title="Edit PO AGC">
                            <i class="bi bi-pencil text-xs"></i>
                        </button>
                    @endif
                </dd>
            </div>
            <div class="flex items-start justify-between gap-3">
                <dt class="text-slate-400">PO customer</dt>
                <dd class="flex items-center gap-2 text-right font-medium text-slate-800">
                    @php $poFileUrl = $detail['po_file'] ?? data_get($salesOrder->agc_payload, 'po_file'); @endphp
                    @if (! empty($poFileUrl))
                        <a href="{{ $poFileUrl }}" target="_blank" class="text-brand-600 hover:underline">Lihat file</a>
                    @else
                        <span>—</span>
                    @endif
                    @if ($canEdit ?? false)
                        <button type="button" @click="openEdit('po_file')" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-brand-600" title="Upload file PO customer">
                            <i class="bi bi-pencil text-xs"></i>
                        </button>
                    @endif
                </dd>
            </div>
            <div class="flex items-start justify-between gap-3">
                <dt class="text-slate-400">Invoice</dt>
                <dd class="flex items-start gap-2 text-right font-medium text-slate-800">
                    <span>
                        {{ $detail['invoice_no'] ?: '—' }}
                        @if (! empty($detail['invoice_dt']))
                            <span class="block text-xs font-normal text-slate-400">{{ $fmtDate($detail['invoice_dt'], 'd M Y') }}</span>
                        @endif
                    </span>
                    @if ($canEditInvoice)
                        <button type="button" @click="openEdit('invoice_no')" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-brand-600" title="Edit invoice">
                            <i class="bi bi-pencil text-xs"></i>
                        </button>
                    @endif
                </dd>
            </div>
            <div class="flex items-start justify-between gap-3">
                <dt class="text-slate-400">Faktur</dt>
                <dd class="flex items-center gap-2 text-right font-medium text-slate-800">
                    @php $fakturUrl = $detail['faktur_pajak'] ?? data_get($salesOrder->agc_payload, 'faktur_pajak'); @endphp
                    @if (! empty($fakturUrl))
                        <a href="{{ $fakturUrl }}" target="_blank" class="text-brand-600 hover:underline">Lihat file</a>
                    @else
                        <span>—</span>
                    @endif
                    @if ($canEdit ?? false)
                        <button type="button" @click="openEdit('faktur_pajak')" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-brand-600" title="Upload Faktur">
                            <i class="bi bi-pencil text-xs"></i>
                        </button>
                    @endif
                </dd>
            </div>
            <div class="flex items-start justify-between gap-3">
                <dt class="text-slate-400">BAST</dt>
                <dd class="flex items-center gap-2 text-right font-medium text-slate-800">
                    @php $bastUrl = $detail['foto_serah_terima'] ?? data_get($salesOrder->agc_payload, 'foto_serah_terima'); @endphp
                    @if (! empty($bastUrl))
                        <a href="{{ $bastUrl }}" target="_blank" class="text-brand-600 hover:underline">Lihat file</a>
                    @else
                        <span>—</span>
                    @endif
                    @if ($canEdit ?? false)
                        <button type="button" @click="openEdit('foto_serah_terima')" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-brand-600" title="Upload BAST">
                            <i class="bi bi-pencil text-xs"></i>
                        </button>
                    @endif
                </dd>
            </div>
            <div class="flex items-start justify-between gap-3">
                <dt class="text-slate-400">File DO</dt>
                <dd class="flex items-center gap-2 text-right font-medium text-slate-800">
                    @php $doUrl = $detail['file_do'] ?? data_get($salesOrder->agc_payload, 'file_do'); @endphp
                    @if (! empty($doUrl))
                        <a href="{{ $doUrl }}" target="_blank" class="text-brand-600 hover:underline">Lihat file</a>
                    @else
                        <span>—</span>
                    @endif
                    @if ($canCompleteDelivery)
                        <button type="button" @click="openEdit('file_do')" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-brand-600" title="Upload file DO">
                            <i class="bi bi-pencil text-xs"></i>
                        </button>
                    @endif
                </dd>
            </div>
            @if ($detail['unique_code'] !== null && $detail['unique_code'] !== '')
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-400">Kode unik</dt>
                    <dd class="text-right font-mono font-medium text-slate-800">{{ $detail['unique_code'] }}</dd>
                </div>
            @endif
            <div class="flex items-start justify-between gap-3">
                <dt class="text-slate-400">Email</dt>
                <dd class="text-right font-medium text-slate-800">{{ $detail['email'] ?: ($salesOrder->email ?: '—') }}</dd>
            </div>
        </dl>
        <div class="mt-4 border-t border-slate-100 pt-4">
            <div class="mb-1 flex items-center justify-between gap-2">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Catatan</p>
                @if ($canEdit ?? false)
                    <button type="button" @click="openEdit('note')" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-brand-600" title="Edit catatan">
                        <i class="bi bi-pencil text-xs"></i>
                    </button>
                @endif
            </div>
            <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $noteText !== '' ? $noteText : '—' }}</p>
        </div>
    </x-card>

    <x-card title="Histori CRM">
        <dl class="space-y-3 text-sm">
            <div class="flex items-start justify-between gap-3">
                <dt class="text-slate-400">Dibuat di CRM oleh</dt>
                <dd class="text-right font-medium text-slate-800">{{ optional($salesOrder->creator)->display_name ?: '—' }}</dd>
            </div>
            <div class="flex items-start justify-between gap-3">
                <dt class="text-slate-400">Waktu dibuat di CRM</dt>
                <dd class="text-right font-medium text-slate-800">{{ $salesOrder->created_at?->translatedFormat('d M Y H:i') ?: '—' }}</dd>
            </div>
            <div class="flex items-start justify-between gap-3">
                <dt class="text-slate-400">Opportunity</dt>
                <dd class="text-right font-medium text-slate-800">
                    <a href="{{ route('opportunities.show', $opportunity) }}" class="text-brand-600 hover:underline">{{ $opportunity->name }}</a>
                </dd>
            </div>
            <div class="flex items-start justify-between gap-3">
                <dt class="text-slate-400">QO terakhir</dt>
                <dd class="text-right font-medium text-slate-800">
                    @if ($opportunity->quotation)
                        <a href="{{ route('quotations.show', $opportunity->quotation) }}" class="text-brand-600 hover:underline">
                            {{ $opportunity->quotation->number ?: $qoLast ?: '—' }}
                        </a>
                    @else
                        —
                    @endif
                </dd>
            </div>
        </dl>
    </x-card>
</div>

@if ($canEdit ?? false)
    <div x-show="editOpen" x-cloak x-ref="editModalRoot"
         class="fixed inset-0 z-[65] flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-slate-900/50" @click="editOpen = false"></div>
        <div class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-2xl" @click.stop>
            <div class="border-b border-slate-100 px-5 py-4">
                <h3 class="font-semibold text-slate-800">Edit Sales Order</h3>
                <p class="mt-0.5 text-sm text-slate-500">Perubahan disimpan di CRM. Produk dan TOP tidak bisa diubah di sini.</p>
            </div>
            <form @submit.prevent="saveEdit" class="space-y-4 px-5 py-4">
                <p x-show="editError" x-cloak class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="editError"></p>

                <div x-show="focusField === 'all' || focusField === 'po_number'">
                    <label class="crm-label">No. PO customer</label>
                    <input type="text" x-model="form.po_number" maxlength="100" class="crm-field">
                </div>

                @if (auth()->user()?->isSuperAdmin())
                    <div x-show="focusField === 'all' || focusField === 'po_agc'">
                        <label class="crm-label">PO AGC</label>
                        <input type="text" x-model="form.po_agc" maxlength="100" class="crm-field">
                    </div>
                    <div x-show="focusField === 'all' || focusField === 'invoice_no'">
                        <label class="crm-label">Invoice</label>
                        <input type="text" x-model="form.invoice_no" maxlength="100" class="crm-field">
                        <p class="mt-1 text-xs text-slate-400">Saat pertama kali diisi, Invoice Date akan otomatis terset.</p>
                    </div>
                    <div x-show="focusField === 'all' || focusField === 'invoice_no' || focusField === 'invoice_dt'">
                        <label class="crm-label">Invoice Date</label>
                        <input type="date" x-model="form.invoice_dt" max="{{ now()->format('Y-m-d') }}" class="crm-field">
                    </div>
                @endif

                <div x-show="focusField === 'all' || focusField === 'nomor_ref'">
                    <label class="crm-label">Nomor referensi</label>
                    <input type="text" x-model="form.nomor_ref" maxlength="100" class="crm-field">
                </div>

                <div x-show="focusField === 'all' || focusField === 'required_delivery'">
                    <label class="crm-label">Required delivery</label>
                    <input type="date" x-model="form.required_delivery" class="crm-field">
                </div>

                <div x-show="focusField === 'payment_paid'">
                    <label class="crm-label">Update Pembayaran</label>
                    <p class="text-sm text-slate-600">Klik simpan untuk mengubah status pembayaran menjadi <strong>paid</strong>.</p>
                </div>

                <div x-show="focusField === 'so_complete'">
                    <label class="crm-label">Update Status SO</label>
                    <p class="text-sm text-slate-600">Klik simpan untuk mengubah status SO menjadi <strong>completed</strong>. Pastikan pembayaran sudah paid dan pengiriman sudah complete.</p>
                </div>

                <div x-show="focusField === 'all' || focusField === 'no_resi'">
                    <label class="crm-label">No. resi / AWB</label>
                    <input type="text" x-model="form.no_resi" maxlength="100" class="crm-field">
                </div>

                <div x-show="focusField === 'all' || focusField === 'shipping_method'">
                    <label class="crm-label">Metode pengiriman</label>
                    <input type="text" x-model="form.shipping_method" maxlength="150" class="crm-field" placeholder="Contoh: JNE, ambil sendiri">
                </div>

                <div x-show="focusField === 'all' || focusField === 'po_file'">
                    <label class="crm-label">Upload file PO customer</label>
                    <input type="file" x-ref="poFileInput" @change="setFile($event, 'po_file')" accept=".pdf,.jpg,.jpeg,.png,.webp" class="crm-field">
                    <p class="mt-1 text-xs text-slate-400">Maks 5MB. PDF, JPG, JPEG, PNG, atau WEBP.</p>
                </div>

                <div x-show="focusField === 'all' || focusField === 'faktur_pajak'">
                    <label class="crm-label">Upload Faktur</label>
                    <input type="file" x-ref="fakturFileInput" @change="setFile($event, 'faktur_pajak_file')" accept=".pdf,.jpg,.jpeg,.png,.webp" class="crm-field">
                    <p class="mt-1 text-xs text-slate-400">Maks 5MB. PDF, JPG, JPEG, PNG, atau WEBP.</p>
                </div>

                <div x-show="focusField === 'all' || focusField === 'foto_serah_terima'">
                    <label class="crm-label">Upload BAST</label>
                    <input type="file" x-ref="bastFileInput" @change="setFile($event, 'foto_serah_terima_file')" accept=".pdf,.jpg,.jpeg,.png,.webp" class="crm-field">
                    <p class="mt-1 text-xs text-slate-400">Maks 5MB. PDF, JPG, JPEG, PNG, atau WEBP.</p>
                </div>

                <div x-show="focusField === 'file_do'">
                    <label class="crm-label">Upload file DO</label>
                    <input type="file" x-ref="doFileInput" @change="setFile($event, 'file_do_file')" accept=".pdf,.jpg,.jpeg,.png,.webp" class="crm-field">
                    <p class="mt-1 text-xs text-slate-400">Wajib. Jika pembayaran sudah paid/settlement, pengiriman akan otomatis complete.</p>
                </div>

                <div x-show="focusField === 'all' || focusField === 'note'">
                    <label class="crm-label">Catatan</label>
                    <textarea x-model="form.note" rows="3" maxlength="2000" class="crm-field"></textarea>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" @click="editOpen = false"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                        Batal
                    </button>
                    <button type="submit" :disabled="saving"
                            class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">
                        <span x-text="saving ? 'Menyimpan…' : 'Simpan'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
</div>
@endsection

@if ($canEdit ?? false)
@push('scripts')
<script>
function salesOrderDetail(cfg) {
    const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

    return {
        editOpen: false,
        editError: '',
        saving: false,
        focusField: 'all',
        form: {
            po_number: cfg.editForm.poNumber || '',
            po_agc: cfg.editForm.poAgc || '',
            invoice_no: cfg.editForm.invoiceNo || '',
            invoice_dt: cfg.editForm.invoiceDt || '',
            nomor_ref: cfg.editForm.nomorRef || '',
            required_delivery: cfg.editForm.requiredDelivery || '',
            no_resi: cfg.editForm.noResi || '',
            shipping_method: cfg.editForm.shippingMethod || '',
            po_file: null,
            faktur_pajak_file: null,
            foto_serah_terima_file: null,
            file_do_file: null,
            note: cfg.editForm.note || '',
        },
        cfg,

        init() {},

        async openEdit(field) {
            this.focusField = field || 'all';
            this.editError = '';
            this.editOpen = true;
            this.form.po_file = null;
            this.form.faktur_pajak_file = null;
            this.form.foto_serah_terima_file = null;
            this.form.file_do_file = null;
            await this.$nextTick();
            if (this.$refs.poFileInput) this.$refs.poFileInput.value = '';
            if (this.$refs.fakturFileInput) this.$refs.fakturFileInput.value = '';
            if (this.$refs.bastFileInput) this.$refs.bastFileInput.value = '';
            if (this.$refs.doFileInput) this.$refs.doFileInput.value = '';
        },

        setFile(event, key) {
            const file = event?.target?.files?.[0] || null;
            this.form[key] = file;
        },

        buildPayload() {
            const payload = {};
            const fields = ['po_number', 'po_agc', 'invoice_no', 'invoice_dt', 'nomor_ref', 'required_delivery', 'no_resi', 'shipping_method', 'note'];
            fields.forEach((key) => {
                const shouldInclude = this.focusField === 'all'
                    || this.focusField === key
                    || (this.focusField === 'invoice_no' && key === 'invoice_dt');
                if (shouldInclude) {
                    payload[key] = this.form[key] || null;
                }
            });
            if (this.focusField === 'file_do') {
                payload.mark_complete = '1';
            }
            if (this.focusField === 'payment_paid') {
                payload.mark_paid = '1';
            }
            if (this.focusField === 'so_complete') {
                payload.mark_so_complete = '1';
            }
            return payload;
        },

        async saveEdit() {
            this.saving = true;
            this.editError = '';
            try {
                const poFile = this.form.po_file || this.$refs.poFileInput?.files?.[0] || null;
                const fakturFile = this.form.faktur_pajak_file || this.$refs.fakturFileInput?.files?.[0] || null;
                const bastFile = this.form.foto_serah_terima_file || this.$refs.bastFileInput?.files?.[0] || null;
                const doFile = this.form.file_do_file || this.$refs.doFileInput?.files?.[0] || null;
                if (this.focusField === 'po_file' && ! poFile) {
                    this.editError = 'Pilih file PO customer.';
                    this.saving = false;
                    return;
                }
                if (this.focusField === 'file_do' && ! doFile) {
                    this.editError = 'Upload file DO wajib untuk update ke complete.';
                    this.saving = false;
                    return;
                }
                const payload = this.buildPayload();
                const fd = new FormData();
                fd.append('_method', 'PUT');
                Object.entries(payload).forEach(([k, v]) => fd.append(k, v ?? ''));
                if (poFile) fd.append('po_file', poFile);
                if (fakturFile) fd.append('faktur_pajak_file', fakturFile);
                if (bastFile) fd.append('foto_serah_terima_file', bastFile);
                if (doFile) fd.append('file_do_file', doFile);

                const res = await fetch(cfg.editForm.updateUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf(),
                    },
                    body: fd,
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok || !json.success) {
                    this.editError = json.message || 'Gagal memperbarui Sales Order.';
                    return;
                }
                window.location.reload();
            } catch (e) {
                this.editError = e?.message || 'Gagal memperbarui Sales Order.';
            } finally {
                this.saving = false;
            }
        },
    };
}
</script>
@endpush
@endif
