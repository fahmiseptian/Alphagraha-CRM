@extends('layouts.app')
@section('title', 'Margin Settings')

@section('content')
@php
    $mLancar = (float) old('margin_lancar', $marginLancar);
    $mMandek = (float) old('margin_mandek', $marginMandek);
    $mJelek = (float) old('margin_jelek', $marginJelek);
    $nUmum = (float) old('nominal_umum', $nominalUmum);
    $nOngkir = (float) old('nominal_ongkir_pribadi', $nominalOngkirPribadi);
    $mMax = (float) old('margin_max_percent', $marginMaxPercent ?? 90);
@endphp

<div class="mb-4">
    <h2 class="text-lg font-semibold text-slate-800">Pengaturan Margin</h2>
    <p class="text-sm text-slate-500">
        Atur ambang batas margin opportunity (persentase per level pembayaran, per TOP customer, batas atas %, &amp; nominal bawah).
        Threshold % efektif (bawah) = nilai tertinggi antara level pembayaran dan TOP.
        Bila margin di bawah minimal atau di atas batas atas, dokumen menunggu approval Superadmin.
        Customer <strong>Suspend</strong> tidak boleh membuat Quotation.
    </p>
</div>

<form method="POST" action="{{ route('settings.margin.update') }}" class="max-w-3xl space-y-5">
    @csrf
    @method('PUT')

    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <x-card title="Threshold Margin per Level Pembayaran (%)">
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">
                    Lancar <span class="text-red-500">*</span>
                </label>
                <input type="text" inputmode="decimal" name="margin_lancar" required data-crm-number data-decimals="2"
                       value="{{ $mLancar }}"
                       class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm tabular-nums focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                <p class="mt-1 text-xs text-slate-400">Default 5%</p>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">
                    Mandek <span class="text-red-500">*</span>
                </label>
                <input type="text" inputmode="decimal" name="margin_mandek" required data-crm-number data-decimals="2"
                       value="{{ $mMandek }}"
                       class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm tabular-nums focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                <p class="mt-1 text-xs text-slate-400">Default 8%</p>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">
                    Jelek <span class="text-red-500">*</span>
                </label>
                <input type="text" inputmode="decimal" name="margin_jelek" required data-crm-number data-decimals="2"
                       value="{{ $mJelek }}"
                       class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm tabular-nums focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                <p class="mt-1 text-xs text-slate-400">Default 15%</p>
            </div>
        </div>

        <div class="mt-5 rounded-lg border border-slate-100 bg-slate-50 px-4 py-3 text-xs text-slate-500">
            Leveling customer diubah oleh Finance / Superadmin di halaman Customer.
        </div>
    </x-card>

    <x-card title="Threshold Margin per TOP Customer (%)">
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($topLabels as $topKey => $topLabel)
                @php
                    $field = 'margin_top_'.$topKey;
                    $value = (float) old($field, $topMargins[$topKey] ?? 0);
                    $defaultHint = \App\Support\CustomerTop::DEFAULT_MARGINS[$topKey] ?? 0;
                @endphp
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">
                        {{ $topLabel }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text" inputmode="decimal" name="{{ $field }}" required data-crm-number data-decimals="2"
                           value="{{ $value }}"
                           class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm tabular-nums focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    <p class="mt-1 text-xs text-slate-400">Default {{ rtrim(rtrim(number_format($defaultHint, 2, ',', '.'), '0'), ',') }}%</p>
                </div>
            @endforeach
        </div>

        <div class="mt-5 rounded-lg border border-slate-100 bg-slate-50 px-4 py-3 text-xs text-slate-500">
            TOP diatur per customer. Threshold % efektif memakai nilai tertinggi antara level pembayaran dan TOP.
            Contoh: Lancar 5% + TOP 60 hari 10% → minimal 10%.
        </div>
    </x-card>

    <x-card title="Batas Atas Margin (%)">
        <p class="mb-4 text-xs text-slate-500">
            Hanya persentase. Bila margin opportunity di atas nilai ini, dokumen menunggu approval Superadmin
            (kecuali stage Prospecting / Qualification).
        </p>
        <div class="max-w-xs">
            <label class="mb-1.5 block text-sm font-medium text-slate-700">
                Maksimal Margin <span class="text-red-500">*</span>
            </label>
            <input type="text" inputmode="decimal" name="margin_max_percent" required data-crm-number data-decimals="2"
                   value="{{ $mMax }}"
                   class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm tabular-nums focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
            <p class="mt-1 text-xs text-slate-400">Default 90%.</p>
        </div>
    </x-card>

    <x-card title="Margin Nominal (Rp)">
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">
                    Nominal Umum <span class="text-red-500">*</span>
                </label>
                <input type="text" inputmode="decimal" name="nominal_umum" required data-crm-number data-decimals="0"
                       value="{{ $nUmum }}"
                       class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm tabular-nums focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                <p class="mt-1 text-xs text-slate-400">Ambang margin nominal umum (Rp).</p>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">
                    Nominal dengan Ongkir BODETABEK <span class="text-red-500">*</span>
                </label>
                <input type="text" inputmode="decimal" name="nominal_ongkir_pribadi" required data-crm-number data-decimals="0"
                       value="{{ $nOngkir }}"
                       class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm tabular-nums focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                <p class="mt-1 text-xs text-slate-400">Ambang margin nominal bila memakai ongkir pribadi (Rp).</p>
            </div>
        </div>
    </x-card>

    <div class="flex items-center gap-2">
        <button class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">Save</button>
    </div>
</form>
@endsection
