@extends('layouts.app')

@php
    $sales = $summary['sales'];
    $quarter = $summary['quarter'];
    $quarters = $summary['quarters'] ?? [];
    $canManage = $canManage ?? auth()->user()->canManageCommissions();
    $isOwn = (string) auth()->id() === (string) $sales->id;
    $baseTitle = $isOwn && ! $canManage ? 'Komisi Saya' : 'Komisi · '.$sales->display_name;
    $pageTitle = $sectionTitle.' · '.$baseTitle;
    $periodQuery = ['year' => $year, 'period' => $quarter['key'] ?? null];
    $backUrl = route('commissions.show', array_merge(['user' => $sales->id], $periodQuery));
@endphp

@section('title', $pageTitle)

@section('content')
<div class="mb-6">
    <a href="{{ $backUrl }}" class="crm-back"><i class="bi bi-arrow-left"></i> Kembali ke ringkasan</a>
</div>

<x-page-header :title="$sectionTitle"
               :description="($quarter['label'] ?? '—').' · '.$sales->display_name">
    <x-slot:actions>
        <form method="GET" action="{{ route('commissions.detail', [$sales->id, $section]) }}" class="flex flex-wrap items-center gap-2">
            <select name="year" class="crm-field py-1.5 text-sm" onchange="this.form.submit()">
                @for ($y = now()->year; $y >= now()->year - 4; $y--)
                    <option value="{{ $y }}" @selected((int) $year === $y)>{{ $y }}</option>
                @endfor
            </select>
            <select name="period" class="crm-field py-1.5 text-sm" onchange="this.form.submit()">
                @foreach ($quarters as $q)
                    <option value="{{ $q['key'] }}" @selected(($quarter['key'] ?? '') === $q['key'])>{{ $q['label'] }}</option>
                @endforeach
            </select>
        </form>
    </x-slot:actions>
</x-page-header>

@if (! $quarter)
    <x-card>
        <p class="text-sm text-slate-500">Periode komisi tidak ditemukan.</p>
    </x-card>
@elseif ($section === 'so-all')
    <x-card title="Daftar SO Keseluruhan" :padding="false">
        <p class="border-b border-slate-100 px-4 py-2 text-xs text-slate-500">
            Semua SO (kecuali cancelled) yang dibuat di {{ $quarter['label'] }}.
        </p>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">SO</th>
                        <th class="px-4 py-2">Customer</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2">Pembayaran</th>
                        <th class="px-4 py-2">Dibuat</th>
                        <th class="px-4 py-2 text-right">Nilai SO</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($summary['sales_orders_all'] ?? [] as $row)
                        <tr class="{{ ! empty($row['is_overdue']) ? 'bg-rose-50/70' : '' }}">
                            <td class="px-4 py-2 font-medium text-slate-800">
                                <a href="{{ route('opportunities.sales-orders.show', [$row['opportunity_id'], $row['id']]) }}"
                                   class="text-brand-600 hover:underline">{{ $row['number'] }}</a>
                                @if ($row['reassigned'] ?? false)
                                    <div class="text-[11px] font-normal text-amber-600">
                                        Dialihkan dari opportunity {{ $row['opportunity_owner'] ?: '—' }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-slate-600">{{ $row['customer'] ?: '—' }}</td>
                            <td class="px-4 py-2">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium
                                    {{ ($row['is_complete'] ?? false) ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $row['so_status'] ?? '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-slate-600">
                                {{ $row['payment_label'] ?? '—' }}
                                <div class="text-[11px] {{ ($row['is_unpaid'] ?? false) ? 'text-amber-600' : 'text-green-600' }}">
                                    {{ ($row['is_unpaid'] ?? false) ? 'Belum bayar' : 'Sudah bayar' }}
                                </div>
                            </td>
                            <td class="px-4 py-2 text-slate-500 tabular-nums">
                                {{ optional($row['created_at'] ?? null)->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums font-medium">{{ money($row['amount']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-400">Belum ada SO di periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if (($summary['so_all_count'] ?? 0) > 0)
                    <tfoot class="bg-slate-50 font-semibold">
                        <tr>
                            <td colspan="5" class="px-4 py-2 text-slate-600">Total · {{ number_format($summary['so_all_count']) }} SO</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ money($summary['so_all_total']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-card>

@elseif ($section === 'so-outstanding')
    <x-card title="Daftar SO Outstanding (Belum complete)" :padding="false">
        <p class="border-b border-slate-100 px-4 py-2 text-xs text-slate-500">
            SO yang dibuat di {{ $quarter['label'] }} dan status belum complete.
            @if (($summary['so_overdue_count'] ?? 0) > 0)
                · <span class="font-semibold text-rose-600">{{ number_format($summary['so_overdue_count']) }} lewat TOP</span>
            @endif
        </p>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">SO</th>
                        <th class="px-4 py-2">Customer</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2">Pembayaran</th>
                        <th class="px-4 py-2">Dibuat</th>
                        <th class="px-4 py-2 text-right">Nilai SO</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($summary['sales_orders_outstanding'] ?? [] as $row)
                        <tr class="{{ ! empty($row['is_overdue']) ? 'bg-rose-50/80' : '' }}">
                            <td class="px-4 py-2 font-medium text-slate-800">
                                <a href="{{ route('opportunities.sales-orders.show', [$row['opportunity_id'], $row['id']]) }}"
                                   class="text-brand-600 hover:underline">{{ $row['number'] }}</a>
                            </td>
                            <td class="px-4 py-2 text-slate-600">{{ $row['customer'] ?: '—' }}</td>
                            <td class="px-4 py-2">
                                <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700">
                                    {{ $row['so_status'] ?? 'pending' }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-slate-600">
                                {{ $row['payment_label'] ?? '—' }}
                                <div class="text-[11px] {{ ($row['is_unpaid'] ?? false) ? 'text-amber-600' : 'text-green-600' }}">
                                    {{ ($row['is_unpaid'] ?? false) ? 'Belum bayar' : 'Sudah bayar' }}
                                </div>
                            </td>
                            <td class="px-4 py-2 text-slate-500 tabular-nums">
                                {{ optional($row['created_at'] ?? null)->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums font-medium">{{ money($row['amount']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-400">Tidak ada SO outstanding di periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if (($summary['so_outstanding_count'] ?? 0) > 0)
                    <tfoot class="bg-slate-50 font-semibold">
                        <tr>
                            <td colspan="5" class="px-4 py-2 text-slate-600">
                                Total · {{ number_format($summary['so_outstanding_count']) }} outstanding
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums text-amber-700">{{ money($summary['so_outstanding_total']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-card>

@elseif ($section === 'so-complete')
    <x-card title="Daftar SO Complete" :padding="false">
        <p class="border-b border-slate-100 px-4 py-2 text-xs text-slate-500">
            SO sudah complete. Periodisasi berdasarkan tanggal dibuat SO (dibuat Q3 tetap Q3).
        </p>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">SO</th>
                        <th class="px-4 py-2">Customer</th>
                        <th class="px-4 py-2">Dibuat</th>
                        <th class="px-4 py-2">Tanggal Bayar</th>
                        <th class="px-4 py-2">Complete</th>
                        <th class="px-4 py-2 text-right">Nilai SO</th>
                        <th class="px-4 py-2 text-right">Margin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($summary['sales_orders'] as $row)
                        <tr>
                            <td class="px-4 py-2 font-medium text-slate-800">
                                <a href="{{ route('opportunities.sales-orders.show', [$row['opportunity_id'], $row['id']]) }}"
                                   class="text-brand-600 hover:underline">{{ $row['number'] }}</a>
                                @if ($row['reassigned'] ?? false)
                                    <div class="text-[11px] font-normal text-amber-600">
                                        Dialihkan dari opportunity {{ $row['opportunity_owner'] ?: '—' }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-slate-600">{{ $row['customer'] ?? '—' }}</td>
                            <td class="px-4 py-2 text-slate-600 tabular-nums">
                                {{ optional($row['created_at'] ?? null)->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-2 text-slate-600">{{ $row['paid_at']?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-4 py-2 text-slate-500">{{ $row['completed_at']?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ money($row['amount']) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums font-medium text-green-700">{{ money_signed($row['margin']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-slate-400">Belum ada SO complete di periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($summary['so_count'] > 0)
                    <tfoot class="bg-slate-50 font-semibold">
                        <tr>
                            <td colspan="5" class="px-4 py-2 text-slate-600">Total · {{ number_format($summary['so_count']) }} SO</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ money($summary['so_total']) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums text-green-700">{{ money_signed($summary['so_complete_margin_total'] ?? $summary['gp_total']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-card>

@elseif ($section === 'invoice-all')
    <x-card title="Daftar Invoice Keseluruhan" :padding="false">
        <p class="border-b border-slate-100 px-4 py-2 text-xs text-slate-500">
            Semua invoice dengan tanggal invoice di {{ $quarter['label'] }}.
        </p>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">SO</th>
                        <th class="px-4 py-2">Customer</th>
                        <th class="px-4 py-2">Tgl Invoice</th>
                        <th class="px-4 py-2">Pembayaran</th>
                        <th class="px-4 py-2 text-right">Nilai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($summary['invoices_all'] ?? [] as $row)
                        <tr>
                            <td class="px-4 py-2 font-medium text-slate-800">
                                <a href="{{ route('opportunities.sales-orders.show', [$row['opportunity_id'], $row['id']]) }}"
                                   class="text-brand-600 hover:underline">{{ $row['number'] }}</a>
                            </td>
                            <td class="px-4 py-2 text-slate-600">{{ $row['customer'] ?: '—' }}</td>
                            <td class="px-4 py-2 text-slate-500 tabular-nums">
                                {{ optional($row['invoice_at'] ?? null)->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-2">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium
                                    {{ ($row['is_unpaid'] ?? false) ? 'bg-amber-50 text-amber-700' : 'bg-green-50 text-green-700' }}">
                                    {{ ($row['is_unpaid'] ?? false) ? 'Belum bayar' : 'Sudah bayar' }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums font-medium">{{ money($row['amount']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-slate-400">Belum ada invoice di periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if (($summary['invoice_all_count'] ?? 0) > 0)
                    <tfoot class="bg-slate-50 font-semibold">
                        <tr>
                            <td colspan="4" class="px-4 py-2 text-slate-600">Total · {{ number_format($summary['invoice_all_count']) }} invoice</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ money($summary['invoice_all_total']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-card>

@elseif ($section === 'invoice-outstanding')
    <x-card title="Daftar Invoice Outstanding (Belum bayar)" :padding="false">
        <p class="border-b border-slate-100 px-4 py-2 text-xs text-slate-500">
            Invoice bertanggal di {{ $quarter['label'] }} yang belum paid/settlement.
        </p>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">SO</th>
                        <th class="px-4 py-2">Customer</th>
                        <th class="px-4 py-2">Tgl Invoice</th>
                        <th class="px-4 py-2">Jatuh tempo</th>
                        <th class="px-4 py-2 text-right">Nilai</th>
                        <th class="px-4 py-2">Status TOP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($summary['invoices_outstanding'] ?? [] as $row)
                        <tr class="{{ ! empty($row['is_overdue']) ? 'bg-rose-50/80' : '' }}">
                            <td class="px-4 py-2 font-medium text-slate-800">
                                <a href="{{ route('opportunities.sales-orders.show', [$row['opportunity_id'], $row['id']]) }}"
                                   class="text-brand-600 hover:underline">{{ $row['number'] }}</a>
                            </td>
                            <td class="px-4 py-2 text-slate-600">{{ $row['customer'] ?: '—' }}</td>
                            <td class="px-4 py-2 text-slate-500 tabular-nums">
                                {{ optional($row['invoice_at'] ?? null)->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-2 tabular-nums {{ ! empty($row['is_overdue']) ? 'font-semibold text-rose-700' : 'text-slate-600' }}">
                                {{ optional($row['due_at'] ?? null)->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums font-medium">{{ money($row['amount']) }}</td>
                            <td class="px-4 py-2">
                                @if (! empty($row['is_overdue']))
                                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-700">
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                        Lewat {{ number_format($row['overdue_days'] ?? 0) }} hari
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700">
                                        Belum bayar
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-400">Tidak ada invoice outstanding di periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if (($summary['invoice_outstanding_count'] ?? 0) > 0)
                    <tfoot class="bg-slate-50 font-semibold">
                        <tr>
                            <td colspan="4" class="px-4 py-2 text-slate-600">
                                Total · {{ number_format($summary['invoice_outstanding_count']) }} outstanding
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums text-rose-700">{{ money($summary['invoice_outstanding_total']) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-card>

@elseif ($section === 'invoice-complete')
    <x-card title="Daftar Invoice Complete (Sudah bayar)" :padding="false">
        <p class="border-b border-slate-100 px-4 py-2 text-xs text-slate-500">
            Invoice bertanggal di {{ $quarter['label'] }} yang sudah paid/settlement.
        </p>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">SO</th>
                        <th class="px-4 py-2">Customer</th>
                        <th class="px-4 py-2">Tgl Invoice</th>
                        <th class="px-4 py-2">Tanggal Bayar</th>
                        <th class="px-4 py-2 text-right">Nilai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($summary['invoices_complete'] ?? [] as $row)
                        <tr>
                            <td class="px-4 py-2 font-medium text-slate-800">
                                <a href="{{ route('opportunities.sales-orders.show', [$row['opportunity_id'], $row['id']]) }}"
                                   class="text-brand-600 hover:underline">{{ $row['number'] }}</a>
                            </td>
                            <td class="px-4 py-2 text-slate-600">{{ $row['customer'] ?: '—' }}</td>
                            <td class="px-4 py-2 text-slate-500 tabular-nums">
                                {{ optional($row['invoice_at'] ?? null)->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-2 text-slate-600 tabular-nums">
                                {{ optional($row['paid_at'] ?? null)->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums font-medium">{{ money($row['amount']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-slate-400">Belum ada invoice complete di periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if (($summary['invoice_complete_count'] ?? 0) > 0)
                    <tfoot class="bg-slate-50 font-semibold">
                        <tr>
                            <td colspan="4" class="px-4 py-2 text-slate-600">Total · {{ number_format($summary['invoice_complete_count']) }} invoice</td>
                            <td class="px-4 py-2 text-right tabular-nums text-green-700">{{ money($summary['invoice_complete_total']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-card>

@elseif ($section === 'gross-profit')
    <x-card title="Rincian Gross Profit per SO" :padding="false">
        <p class="border-b border-slate-100 px-4 py-2 text-xs text-slate-500">
            Margin setelah modal &amp; diskon. Periodisasi ikut <span class="font-semibold">tanggal invoice</span>.
        </p>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">SO</th>
                        <th class="px-4 py-2">Tgl Invoice</th>
                        <th class="px-4 py-2 text-right">Nilai SO</th>
                        <th class="px-4 py-2 text-right">Margin kotor</th>
                        <th class="px-4 py-2 text-right">Biaya terikat</th>
                        <th class="px-4 py-2 text-right">Gross Profit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($summary['gp_orders'] ?? $summary['sales_orders'] as $row)
                        <tr>
                            <td class="px-4 py-2 font-medium text-slate-800">
                                <a href="{{ route('opportunities.sales-orders.show', [$row['opportunity_id'], $row['id']]) }}"
                                   class="text-brand-600 hover:underline">{{ $row['number'] }}</a>
                            </td>
                            <td class="px-4 py-2 text-slate-500 tabular-nums">
                                {{ optional($row['invoice_at'] ?? null)->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ money($row['amount']) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ money_signed($row['margin_raw'] ?? $row['margin']) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums text-amber-700">
                                {{ ($row['linked_expense'] ?? 0) > 0 ? '− '.money($row['linked_expense']) : '—' }}
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums font-semibold text-green-700">{{ money_signed($row['margin']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-400">Belum ada data Gross Profit.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($summary['so_count'] > 0)
                    <tfoot class="bg-slate-50 font-semibold">
                        <tr>
                            <td colspan="5" class="px-4 py-2 text-slate-600">Total Gross Profit</td>
                            <td class="px-4 py-2 text-right tabular-nums text-green-700">{{ money_signed($summary['gp_total']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-card>

@elseif ($section === 'biaya-tambahan')
    @php
        $biayaRows = collect();
        foreach ($summary['entertainments'] as $item) {
            $biayaRows->push([
                'jenis' => 'Entertainment',
                'nama' => $item->name,
                'ref' => $item->opportunity?->name,
                'ref_url' => $item->opportunity ? route('opportunities.show', $item->opportunity).'#entertainments' : null,
                'tanggal' => optional($item->entertainment_date ?? $item->completed_at)->format('d/m/Y'),
                'amount' => (float) $item->amount,
            ]);
        }
        foreach ($summary['expenses'] as $expense) {
            $biayaRows->push([
                'jenis' => 'Biaya lain',
                'nama' => $expense->name,
                'ref' => $expense->account?->name ?: ($expense->sales_order_number ?: null),
                'ref_url' => $expense->account_id ? route('customers.show', $expense->account_id) : null,
                'tanggal' => optional($expense->expense_date)->format('d/m/Y'),
                'amount' => (float) $expense->amount,
                'file_url' => $expense->hasFile()
                    ? route('commissions.expenses.download', [$sales->id, $expense])
                    : null,
                'file_name' => $expense->original_name,
            ]);
        }
        $biayaTotal = (float) ($summary['entertainment_total'] + $summary['expense_total']);
    @endphp
    <x-card title="Daftar Biaya Tambahan" :padding="false">
        <p class="border-b border-slate-100 px-4 py-2 text-xs text-slate-500">
            Entertainment otomatis + biaya lain Finance di {{ $quarter['label'] }}.
        </p>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Jenis</th>
                        <th class="px-4 py-2">Nama</th>
                        <th class="px-4 py-2">Referensi</th>
                        <th class="px-4 py-2">Tanggal</th>
                        <th class="px-4 py-2 text-right">Nominal</th>
                        <th class="px-4 py-2">File</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($biayaRows as $row)
                        <tr>
                            <td class="px-4 py-2">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium
                                    {{ $row['jenis'] === 'Entertainment' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $row['jenis'] }}
                                </span>
                            </td>
                            <td class="px-4 py-2 font-medium text-slate-800">{{ $row['nama'] }}</td>
                            <td class="px-4 py-2 text-slate-600">
                                @if (! empty($row['ref_url']))
                                    <a href="{{ $row['ref_url'] }}" class="hover:underline">{{ $row['ref'] }}</a>
                                @else
                                    {{ $row['ref'] ?: '—' }}
                                @endif
                            </td>
                            <td class="px-4 py-2 text-slate-500 tabular-nums">{{ $row['tanggal'] ?? '—' }}</td>
                            <td class="px-4 py-2 text-right tabular-nums font-semibold text-amber-700">{{ money($row['amount']) }}</td>
                            <td class="px-4 py-2">
                                @if (! empty($row['file_url']))
                                    <a href="{{ $row['file_url'] }}" class="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700">
                                        <i class="bi bi-paperclip"></i>
                                        {{ \Illuminate\Support\Str::limit($row['file_name'] ?: 'Unduh', 20) }}
                                    </a>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-400">Belum ada biaya tambahan.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($biayaTotal > 0)
                    <tfoot class="bg-slate-50 font-semibold">
                        <tr>
                            <td colspan="4" class="px-4 py-2 text-slate-600">
                                Total · Entertainment {{ money($summary['entertainment_total']) }}
                                + Biaya {{ money($summary['expense_total']) }}
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums text-amber-700">{{ money($biayaTotal) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-card>

@elseif ($section === 'nett-profit')
    <div class="space-y-4">
        <x-card title="Komponen Nett Profit" :padding="false">
            <ul class="divide-y divide-slate-100 text-sm">
                <li class="flex items-center justify-between gap-3 px-4 py-3">
                    <span class="text-slate-600">Gross Profit (dari {{ number_format($summary['gp_order_count'] ?? $summary['so_count']) }} SO · by tgl invoice)</span>
                    <span class="font-semibold tabular-nums text-slate-800">{{ money_signed($summary['gp_total']) }}</span>
                </li>
                <li class="flex items-center justify-between gap-3 px-4 py-3">
                    <span class="text-slate-600">− Entertainment</span>
                    <span class="tabular-nums text-amber-700">{{ money($summary['entertainment_total']) }}</span>
                </li>
                <li class="flex items-center justify-between gap-3 px-4 py-3">
                    <span class="text-slate-600">− Biaya lain (umum)</span>
                    <span class="tabular-nums text-amber-700">{{ money($summary['expense_unlinked_total'] ?? 0) }}</span>
                </li>
                <li class="flex items-center justify-between gap-3 bg-slate-50 px-4 py-3">
                    <span class="font-semibold text-slate-800">Nett Profit</span>
                    <span class="text-lg font-bold tabular-nums text-green-700">{{ money_signed($summary['net_gp']) }}</span>
                </li>
            </ul>
        </x-card>

        <x-card title="Kontribusi Gross Profit per SO" :padding="false">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2">SO</th>
                            <th class="px-4 py-2 text-right">Gross Profit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($summary['gp_orders'] ?? $summary['sales_orders'] as $row)
                            <tr>
                                <td class="px-4 py-2">
                                    <a href="{{ route('opportunities.sales-orders.show', [$row['opportunity_id'], $row['id']]) }}"
                                       class="font-medium text-brand-600 hover:underline">{{ $row['number'] }}</a>
                                    <div class="text-[11px] text-slate-400 tabular-nums">
                                        Invoice {{ optional($row['invoice_at'] ?? null)->format('d/m/Y') ?? '—' }}
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums text-green-700">{{ money_signed($row['margin']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-4 py-6 text-center text-slate-400">Tidak ada SO ber-invoice di periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

@elseif ($section === 'komisi')
    @php
        $tiers = $summary['commission_tiers'] ?? [];
        $ratePercent = (float) ($summary['commission_rate_percent'] ?? 0);
        $roundTo = (float) ($summary['commission_round_to'] ?? 500);
    @endphp
    <div class="space-y-4">
        <x-card title="Hasil perhitungan" :padding="false">
            <ul class="divide-y divide-slate-100 text-sm">
                <li class="flex items-center justify-between gap-3 px-4 py-3">
                    <span class="text-slate-600">Nett Profit</span>
                    <span class="font-medium tabular-nums">{{ money_signed($summary['net_gp']) }}</span>
                </li>
                <li class="flex items-center justify-between gap-3 px-4 py-3">
                    <span class="text-slate-600">Tarif aktif</span>
                    <span class="font-medium tabular-nums">
                        {{ rtrim(rtrim(number_format($ratePercent, 2, ',', '.'), '0'), ',') }}%
                    </span>
                </li>
                <li class="flex items-center justify-between gap-3 px-4 py-3">
                    <span class="text-slate-600">Komisi sebelum pembulatan</span>
                    <span class="tabular-nums">{{ money($summary['commission_raw'] ?? 0) }}</span>
                </li>
                <li class="flex items-center justify-between gap-3 bg-slate-50 px-4 py-3">
                    <span class="font-semibold text-slate-800">
                        Komisi
                        <span class="mt-0.5 block text-[11px] font-normal text-slate-400">
                            Dibulatkan ke bawah per {{ money($roundTo) }}
                        </span>
                    </span>
                    <span class="text-lg font-bold tabular-nums text-brand-700">{{ money($summary['commission'] ?? 0) }}</span>
                </li>
            </ul>
        </x-card>

        <x-card title="Jenjang tarif komisi" :padding="false">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2">Nett Profit di atas (% target)</th>
                            <th class="px-4 py-2 text-right">Tarif</th>
                            <th class="px-4 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($tiers as $tier)
                            @php
                                $active = abs((float) $tier['rate_percent'] - $ratePercent) < 0.0001 && $ratePercent > 0;
                            @endphp
                            <tr class="{{ $active ? 'bg-brand-50/60' : '' }}">
                                <td class="px-4 py-2 text-slate-700">
                                    &gt; {{ rtrim(rtrim(number_format((float) $tier['min_percent'], 2, ',', '.'), '0'), ',') }}%
                                    target
                                    <span class="text-slate-400">
                                        ({{ money($summary['target'] * ((float) $tier['min_percent'] / 100)) }})
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums font-medium">
                                    {{ rtrim(rtrim(number_format((float) $tier['rate_percent'], 2, ',', '.'), '0'), ',') }}%
                                </td>
                                <td class="px-4 py-2">
                                    @if ($active)
                                        <span class="inline-flex rounded-full bg-brand-100 px-2 py-0.5 text-[11px] font-semibold text-brand-700">Aktif</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-slate-400">Jenjang tarif belum diatur.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

@elseif ($section === 'target')
    @php
        $targetProgress = (float) ($summary['target_progress'] ?? 0);
        $targetMet = (bool) ($summary['target_met'] ?? false);
        $remaining = max(0, (float) $summary['target'] - (float) $summary['net_gp']);
    @endphp
    <div class="space-y-4">
        <x-card title="Pencapaian target" :padding="false">
            <ul class="divide-y divide-slate-100 text-sm">
                <li class="flex items-center justify-between gap-3 px-4 py-3">
                    <span class="text-slate-600">Target {{ $summary['target_period_label'] }}</span>
                    <span class="font-medium tabular-nums">{{ money($summary['target']) }}</span>
                </li>
                <li class="flex items-center justify-between gap-3 px-4 py-3">
                    <span class="text-slate-600">Nett Profit periode ini</span>
                    <span class="font-medium tabular-nums text-green-700">{{ money_signed($summary['net_gp']) }}</span>
                </li>
                <li class="flex items-center justify-between gap-3 px-4 py-3">
                    <span class="text-slate-600">Sisa menuju target</span>
                    <span class="tabular-nums {{ $targetMet ? 'text-green-700' : 'text-rose-600' }}">
                        {{ $targetMet ? 'Tercapai' : money($remaining) }}
                    </span>
                </li>
                <li class="px-4 py-3">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <span class="font-semibold text-slate-800">Pencapaian</span>
                        <span class="font-bold tabular-nums {{ $targetMet ? 'text-green-700' : 'text-rose-600' }}">
                            {{ number_format($targetProgress, 1, ',', '.') }}%
                        </span>
                    </div>
                    <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full {{ $targetMet ? 'bg-green-500' : 'bg-brand-500' }}"
                             style="width: {{ min(100, max(0, $targetProgress)) }}%"></div>
                    </div>
                </li>
            </ul>
        </x-card>
    </div>
@endif
@endsection
