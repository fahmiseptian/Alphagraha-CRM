@extends('layouts.app')
@section('title', 'Pelanggan')

@section('content')
<div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h2 class="text-lg font-semibold text-slate-800">Data Pelanggan</h2>
        <p class="text-sm text-slate-500">{{ number_format($accounts->total()) }} pelanggan ditemukan</p>
    </div>
</div>

{{-- Pencarian & filter --}}
<x-card class="mb-4" :padding="false">
    <form method="GET" class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama, website, atau kota..."
                   class="w-full rounded-lg border border-slate-300 py-2 pl-10 pr-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
        </div>
        <select name="type" class="rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
            <option value="">Semua tipe</option>
            @foreach ($types as $t)
                <option value="{{ $t }}" @selected($type === $t)>{{ $t }}</option>
            @endforeach
        </select>
        <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <i class="bi bi-funnel"></i> Filter
        </button>
        @if ($search || $type)
            <a href="{{ route('customers.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-center text-sm text-slate-600 hover:bg-slate-50">Reset</a>
        @endif
    </form>
</x-card>

<x-card :padding="false">
    @if ($accounts->count())
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                        <th class="px-5 py-3 font-medium">Pelanggan</th>
                        <th class="px-5 py-3 font-medium">Kontak</th>
                        <th class="px-5 py-3 font-medium">Tipe</th>
                        <th class="px-5 py-3 font-medium">Sales</th>
                        <th class="px-5 py-3 text-center font-medium">Deal</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach ($accounts as $account)
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3">
                                <a href="{{ route('customers.show', $account->id) }}" class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-100 text-xs font-semibold text-brand-700">
                                        {{ initials($account->name) }}
                                    </span>
                                    <span>
                                        <span class="block font-medium text-slate-800">{{ $account->name }}</span>
                                        <span class="block text-xs text-slate-400">{{ $account->billing_address_city ?: ($account->website ?: '—') }}</span>
                                    </span>
                                </a>
                            </td>
                            <td class="px-5 py-3">
                                <span class="block text-slate-600">{{ $account->email ?: '—' }}</span>
                                <span class="block text-xs text-slate-400">{{ $account->phone ?: '' }}</span>
                            </td>
                            <td class="px-5 py-3">
                                @if ($account->type)
                                    <x-badge color="slate">{{ $account->type }}</x-badge>
                                @else <span class="text-slate-300">—</span> @endif
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ optional($account->assignedUser)->display_name ?: '—' }}</td>
                            <td class="px-5 py-3 text-center">
                                <span class="inline-flex h-6 min-w-[24px] items-center justify-center rounded-full bg-slate-100 px-2 text-xs font-medium text-slate-600">
                                    {{ $account->opportunities_count }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('customers.show', $account->id) }}" class="text-brand-600 hover:text-brand-700"><i class="bi bi-arrow-right"></i></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-5 py-3">{{ $accounts->links() }}</div>
    @else
        <x-empty-state icon="bi-people" title="Tidak ada pelanggan" message="Coba ubah kata kunci pencarian atau filter." />
    @endif
</x-card>
@endsection
