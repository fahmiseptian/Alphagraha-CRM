@extends('layouts.app')
@section('title', 'Log Sales Order')

@section('content')
<x-page-header title="Log Sales Order" description="Riwayat buat, ubah, dan arsip SO. Hanya Superadmin. SO tidak dihapus permanen.">
    <x-slot:actions>
        <x-btn href="{{ route('sales-orders.index') }}" variant="secondary" icon="bi-receipt">Daftar SO aktif</x-btn>
    </x-slot:actions>
</x-page-header>

<div class="mb-4 flex flex-wrap items-center gap-2">
    @php
        $filters = ['all' => 'Semua'] + \App\Models\SalesOrderLog::ACTIONS;
    @endphp
    @foreach ($filters as $key => $label)
        <a href="{{ route('sales-order-logs.index', ['action' => $key, 'q' => $search]) }}"
           class="rounded-full px-4 py-1.5 text-sm font-medium transition {{ $action === $key ? 'bg-brand-600 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

<x-card class="mb-4" :padding="false">
    <form method="GET" action="{{ route('sales-order-logs.index') }}" class="crm-filter-form">
        <input type="hidden" name="action" value="{{ $action }}">
        <div class="min-w-0 flex-1">
            <label class="crm-label">Pencarian</label>
            <div class="crm-search">
                <i class="bi bi-search"></i>
                <input type="text" name="q" value="{{ $search }}"
                       placeholder="No. SO, customer, opportunity, actor..."
                       class="crm-field" autocomplete="off">
            </div>
        </div>
        <div class="flex items-end gap-2">
            <x-btn type="submit" variant="primary" icon="bi-search">Cari</x-btn>
            @if ($search)
                <x-btn href="{{ route('sales-order-logs.index', ['action' => $action]) }}" variant="ghost">Reset</x-btn>
            @endif
        </div>
    </form>
</x-card>

<x-card :padding="false">
    <div class="crm-table-wrap">
        <table class="crm-table">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Aksi</th>
                    <th>No. SO</th>
                    <th>Opportunity / Customer</th>
                    <th>Oleh</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap text-slate-600">
                            {{ $log->created_at?->translatedFormat('d M Y H:i') }}
                        </td>
                        <td>
                            <x-badge :color="$log->actionBadgeColor()">{{ $log->actionLabel() }}</x-badge>
                        </td>
                        <td>
                            <span class="font-medium text-slate-800">{{ $log->number ?: '—' }}</span>
                            @if ($log->snapshotValue('pso_number'))
                                <div class="text-xs text-slate-400">PSO {{ $log->snapshotValue('pso_number') }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="font-medium text-slate-800">{{ $log->snapshotValue('opportunity_name') ?: ($log->opportunity?->name ?: '—') }}</span>
                            <div class="text-xs text-slate-400">{{ $log->snapshotValue('customer') ?: '—' }}</div>
                        </td>
                        <td class="text-slate-600">
                            {{ $log->actor?->display_name ?: ($log->actor_name ?: '—') }}
                        </td>
                        <td class="text-right">
                            <a href="{{ route('sales-order-logs.show', $log) }}" class="crm-icon-btn" title="Detail log">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-10 text-center text-slate-500">
                            @if ($search || $action !== 'all')
                                Tidak ada log sesuai filter.
                            @else
                                Belum ada log Sales Order.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">
        {{ $logs->links() }}
    </div>
</x-card>
@endsection
