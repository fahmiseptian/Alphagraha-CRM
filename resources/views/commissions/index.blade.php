@extends('layouts.app')
@section('title', 'Komisi Sales')

@section('content')
<x-page-header title="Komisi Sales" description="Perhitungan GP komisi per periode target (Q)">
    <x-slot:actions>
        <form method="GET" action="{{ route('commissions.index') }}" class="flex flex-wrap items-center gap-2">
            <div class="crm-search min-w-[220px]">
                <i class="bi bi-search"></i>
                <input type="text" name="q" value="{{ $search ?? '' }}"
                       placeholder="Cari nama sales..."
                       class="crm-field py-1.5 text-sm" autocomplete="off">
            </div>
            <select name="year" class="crm-field py-1.5 text-sm" onchange="this.form.submit()">
                @for ($y = now()->year; $y >= now()->year - 4; $y--)
                    <option value="{{ $y }}" @selected((int) $year === $y)>Tahun {{ $y }}</option>
                @endfor
            </select>
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">
                <i class="bi bi-search"></i> Cari
            </button>
            @if (! empty($search))
                <a href="{{ route('commissions.index', ['year' => $year]) }}"
                   class="text-sm font-medium text-slate-500 hover:text-slate-700">Reset</a>
            @endif
        </form>
    </x-slot:actions>
</x-page-header>

<x-card :padding="false">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Sales</th>
                    <th class="px-4 py-3">Target / Periode</th>
                    <th class="px-4 py-3">Q Aktif</th>
                    <th class="px-4 py-3 text-right">SO Complete</th>
                    <th class="px-4 py-3 text-right">Gross Profit</th>
                    <th class="px-4 py-3 text-right">Biaya Tambahan</th>
                    <th class="px-4 py-3 text-right">Nett Profit</th>
                    <th class="px-4 py-3 text-right">Komisi</th>
                    <th class="px-4 py-3 text-right">Pencapaian</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    @php $q = $row['quarter']; @endphp
                    <tr class="hover:bg-slate-50/80">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $row['sales']->display_name }}</td>
                        <td class="px-4 py-3 text-slate-600">
                            <div>{{ money($row['target']) }}</div>
                            <div class="text-xs text-slate-400">{{ $row['target_period_label'] }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $q['label'] ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ number_format($row['so_count']) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ money_signed($row['gp_total']) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-amber-700">{{ money($row['deduction_total']) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-semibold text-green-700">{{ money_signed($row['net_gp']) }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="font-semibold tabular-nums text-slate-800">{{ money($row['commission'] ?? 0) }}</div>
                            <div class="text-[11px] text-slate-400">{{ rtrim(rtrim(number_format((float) ($row['commission_rate_percent'] ?? 0), 2, ',', '.'), '0'), ',') }}%</div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <span class="font-semibold tabular-nums {{ ($row['target_met'] ?? false) ? 'text-green-700' : 'text-rose-600' }}">
                                {{ number_format((float) ($row['target_progress'] ?? 0), 1, ',', '.') }}%
                            </span>
                            <div class="text-[11px] {{ ($row['target_met'] ?? false) ? 'text-green-600' : 'text-slate-400' }}">
                                {{ ($row['target_met'] ?? false) ? 'Tercapai' : 'Belum tercapai' }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('commissions.show', ['user' => $row['sales']->id, 'year' => $year, 'period' => $q['key'] ?? null]) }}"
                               class="text-sm font-medium text-brand-600 hover:text-brand-700">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-8 text-center text-slate-400">
                            @if (! empty($search))
                                Tidak ada sales yang cocok dengan “{{ $search }}”.
                            @else
                                Belum ada user sales.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
@endsection
