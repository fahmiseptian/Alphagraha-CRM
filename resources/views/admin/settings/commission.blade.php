@extends('layouts.app')
@section('title', 'Komisi Settings')

@section('content')
@php
    $formatTier = function ($value): string {
        if ($value === '' || $value === null) {
            return '';
        }
        $formatted = rtrim(rtrim(number_format((float) $value, 4, ',', ''), '0'), ',');

        return $formatted === '' ? '0' : $formatted;
    };
    $tierOld = old('tiers');
    $source = is_array($tierOld) ? $tierOld : $tiers;
    $tiersForm = collect($source)->map(fn ($tier) => [
        'min_percent' => $formatTier($tier['min_percent'] ?? ''),
        'rate_percent' => $formatTier($tier['rate_percent'] ?? ''),
    ])->values()->all();
    if ($tiersForm === []) {
        $tiersForm = collect(\App\Services\CommissionService::defaultRateTiers())->map(fn ($tier) => [
            'min_percent' => $formatTier($tier['min_percent']),
            'rate_percent' => $formatTier($tier['rate_percent']),
        ])->all();
    }
    $roundValue = $formatTier(old('round_to', $roundTo));
@endphp

<div class="mb-4">
    <h2 class="text-lg font-semibold text-slate-800">Pengaturan Komisi</h2>
    <p class="text-sm text-slate-500">
        Tarif komisi dihitung dari Nett Profit dibanding target periode sales.
        GP harus lebih besar dari batas persen target. Di bawah jenjang terendah, tarifnya 0.
    </p>
</div>

<form method="POST" action="{{ route('settings.commission.update') }}" class="max-w-3xl space-y-5"
      x-data="{ tiers: {{ \Illuminate\Support\Js::from($tiersForm) }} }">
    @csrf
    @method('PUT')

    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <x-card title="Jenjang tarif">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="py-2 pr-3">Nett Profit di atas (% target)</th>
                        <th class="py-2 pr-3">Tarif (%)</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(tier, i) in tiers" :key="i">
                        <tr class="border-t border-slate-100">
                            <td class="py-2 pr-3">
                                <input type="text" inputmode="decimal" required
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm tabular-nums focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
                                       :name="`tiers[${i}][min_percent]`"
                                       x-model="tier.min_percent">
                            </td>
                            <td class="py-2 pr-3">
                                <input type="text" inputmode="decimal" required
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm tabular-nums focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
                                       :name="`tiers[${i}][rate_percent]`"
                                       x-model="tier.rate_percent">
                            </td>
                            <td class="py-2 text-right">
                                <button type="button" @click="tiers.splice(i, 1)"
                                        class="rounded p-1 text-red-500 hover:bg-red-50"
                                        :disabled="tiers.length <= 1"
                                        title="Hapus jenjang">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <button type="button" @click="tiers.push({ min_percent: '', rate_percent: '' })"
                class="mt-3 inline-flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
            <i class="bi bi-plus-lg"></i> Tambah jenjang
        </button>
        <p class="mt-3 text-xs text-slate-400">
            Contoh: batas 25 dan tarif 1,5 berarti GP di atas 25% target mendapat komisi 1,5%.
            Desimal boleh ditulis 1,5 atau 1.5.
        </p>
    </x-card>

    <x-card title="Pembulatan">
        <div class="max-w-xs">
            <label class="mb-1.5 block text-sm font-medium text-slate-700">
                Dibulatkan ke bawah per (Rp) <span class="text-red-500">*</span>
            </label>
            <input type="text" inputmode="decimal" name="round_to" required
                   value="{{ $roundValue }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm tabular-nums focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
            <p class="mt-1 text-xs text-slate-400">Default 500. Komisi Rp 918.426 menjadi Rp 918.000.</p>
        </div>
    </x-card>

    <div class="flex items-center gap-2">
        <button class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">Simpan</button>
    </div>
</form>
@endsection
