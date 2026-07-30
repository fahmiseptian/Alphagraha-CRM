@extends('layouts.app')
@section('title', 'Margin Settings')

@section('content')
@php
    $mLancar = (float) old('margin_lancar', $marginLancar);
    $mMandek = (float) old('margin_mandek', $marginMandek);
    $mJelek = (float) old('margin_jelek', $marginJelek);
    $nUmum = (float) old('nominal_umum', $nominalUmum);
    $nOngkir = (float) old('nominal_ongkir_pribadi', $nominalOngkirPribadi);
@endphp

<div class="mb-4">
    <h2 class="text-lg font-semibold text-slate-800">Pengaturan Margin</h2>
    <p class="text-sm text-slate-500">
        Atur ambang batas margin opportunity (persentase per level pembayaran &amp; nominal).
        Bila margin di bawah minimal saat membuat Quotation, dokumen menunggu approval Superadmin.
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

    <x-card title="Threshold Margin (%)">
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
            Perubahan threshold berlaku untuk Quotation baru / yang di-update setelah disimpan.
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

        <div class="mt-5 rounded-lg border border-slate-100 bg-slate-50 px-4 py-3 text-xs text-slate-500">
            Parameter tersimpan untuk aturan margin nominal (penerapan ke alur approval/ongkir menyusul).
        </div>
    </x-card>

    <div class="flex items-center gap-2">
        <button class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">Save</button>
    </div>
</form>
@endsection
