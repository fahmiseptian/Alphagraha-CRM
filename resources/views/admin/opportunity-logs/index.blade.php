@extends('layouts.app')
@section('title', 'Log Opportunity')

@section('content')
<x-page-header title="Log Opportunity" description="Riwayat buat, ubah harga/barang, stage, dan approval di seluruh Opportunity. Hanya Superadmin.">
    <x-slot:actions>
        <x-btn href="{{ route('opportunities.index') }}" variant="secondary" icon="bi-kanban">Daftar Opportunity</x-btn>
    </x-slot:actions>
</x-page-header>

<div class="mb-4 flex flex-wrap items-center gap-2">
    @php
        $quickFilters = [
            'all' => 'Semua',
            'created' => 'Dibuat',
            'updated' => 'Diubah',
            'products_updated' => 'Produk / harga',
            'stage_changed' => 'Stage',
            'discount_approved' => 'Diskon disetujui',
            'margin_approved' => 'Margin disetujui',
        ];
    @endphp
    @foreach ($quickFilters as $key => $label)
        <a href="{{ route('opportunity-logs.index', ['action' => $key, 'q' => $search]) }}"
           class="rounded-full px-4 py-1.5 text-sm font-medium transition {{ $action === $key ? 'bg-brand-600 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

<x-card class="mb-4" :padding="false">
    <form method="GET" action="{{ route('opportunity-logs.index') }}" class="crm-filter-form">
        <div class="w-full sm:w-56">
            <label class="crm-label">Aksi</label>
            <select name="action" class="crm-field">
                <option value="all" @selected($action === 'all')>Semua</option>
                @foreach (\App\Models\OpportunityLog::ACTIONS as $key => $label)
                    <option value="{{ $key }}" @selected($action === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-0 flex-1">
            <label class="crm-label">Pencarian</label>
            <div class="crm-search">
                <i class="bi bi-search"></i>
                <input type="text" name="q" value="{{ $search }}"
                       placeholder="Nama opportunity, customer, actor, ringkasan..."
                       class="crm-field" autocomplete="off">
            </div>
        </div>
        <div class="flex items-end gap-2">
            <x-btn type="submit" variant="primary" icon="bi-search">Cari</x-btn>
            @if ($search || $action !== 'all')
                <x-btn href="{{ route('opportunity-logs.index') }}" variant="ghost">Reset</x-btn>
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
                    <th>Opportunity</th>
                    <th>Ringkasan</th>
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
                            <span class="font-medium text-slate-800">{{ $log->snapshotValue('name') ?: ($log->opportunity?->name ?: '—') }}</span>
                            <div class="text-xs text-slate-400">{{ $log->snapshotValue('account_name') ?: '—' }}</div>
                        </td>
                        <td class="max-w-md text-sm text-slate-600">
                            <span class="line-clamp-2">{{ $log->summary ?: '—' }}</span>
                        </td>
                        <td class="text-slate-600">
                            {{ $log->actor?->display_name ?: ($log->actor_name ?: '—') }}
                        </td>
                        <td class="text-right">
                            <a href="{{ route('opportunity-logs.show', $log) }}" class="crm-icon-btn" title="Detail log">
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
                                Belum ada log Opportunity.
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
