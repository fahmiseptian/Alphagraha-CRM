@extends('layouts.app')
@section('title', 'Penawaran')

@section('content')
<div class="mb-5 flex items-center justify-between">
    <div>
        <h2 class="text-lg font-semibold text-slate-800">Penawaran (Quotation)</h2>
        <p class="text-sm text-slate-500">{{ number_format($quotations->total()) }} penawaran</p>
    </div>
    <a href="{{ route('quotations.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
        <i class="bi bi-plus-lg"></i> Penawaran Baru
    </a>
</div>

<x-card class="mb-4" :padding="false">
    <form method="GET" class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="text" name="q" value="{{ $search }}" placeholder="Cari nomor atau nama pelanggan..."
                   class="w-full rounded-lg border border-slate-300 py-2 pl-10 pr-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
        </div>
        <select name="status" class="rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
            <option value="">Semua status</option>
            @foreach ($statuses as $key => $label)
                <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><i class="bi bi-funnel"></i> Filter</button>
        @if ($search || $status)
            <a href="{{ route('quotations.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-center text-sm text-slate-600 hover:bg-slate-50">Reset</a>
        @endif
    </form>
</x-card>

<x-card :padding="false">
    @if ($quotations->count())
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                        <th class="px-5 py-3 font-medium">Nomor</th>
                        <th class="px-5 py-3 font-medium">Pelanggan</th>
                        <th class="px-5 py-3 font-medium">Tanggal</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 text-right font-medium">Total</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach ($quotations as $quo)
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3">
                                <a href="{{ route('quotations.show', $quo) }}" class="font-medium text-brand-600 hover:text-brand-700">{{ $quo->number }}</a>
                                <span class="block text-xs text-slate-400">Rev. {{ $quo->revision }}</span>
                            </td>
                            <td class="px-5 py-3">
                                <span class="block text-slate-800">{{ $quo->customer_name }}</span>
                                <span class="block text-xs text-slate-400">{{ $quo->company_name }}</span>
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ $quo->quotation_date?->translatedFormat('d M Y') }}</td>
                            <td class="px-5 py-3"><x-badge :color="$quo->statusColor()">{{ $quo->statusLabel() }}</x-badge></td>
                            <td class="px-5 py-3 text-right font-semibold text-slate-700">{{ money($quo->total, $quo->currency) }}</td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('quotations.preview', $quo) }}" target="_blank" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" title="Preview"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('quotations.pdf', $quo) }}" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" title="PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                                    <a href="{{ route('quotations.show', $quo) }}" class="rounded-lg p-2 text-brand-600 hover:bg-brand-50" title="Detail"><i class="bi bi-arrow-right"></i></a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-5 py-3">{{ $quotations->links() }}</div>
    @else
        <x-empty-state icon="bi-file-earmark-text" title="Belum ada penawaran" message="Buat penawaran pertama Anda.">
            <x-slot:action>
                <a href="{{ route('quotations.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><i class="bi bi-plus-lg"></i> Penawaran Baru</a>
            </x-slot:action>
        </x-empty-state>
    @endif
</x-card>
@endsection
