@extends('layouts.app')
@section('title', 'PO Settings')

@section('content')
@php
    $cash = (float) old('surcharge_cash_percent', $surchargeCash);
    $top = (float) old('surcharge_top_percent', $surchargeTop);
@endphp

<div class="mb-4">
    <h2 class="text-lg font-semibold text-slate-800">Biaya Tambahan Purchase Order</h2>
    <p class="text-sm text-slate-500">
        Atur persentase tambahan pada harga modal PO berdasarkan kondisi pembayaran (TOP / Cash).
        Nilai ini dipakai di form PO, total PO, dan laporan PDF.
    </p>
</div>

<form method="POST" action="{{ route('settings.po.update') }}" class="max-w-2xl space-y-5">
    @csrf
    @method('PUT')

    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <x-card title="Kondisi Pembayaran PO">
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">
                    Cash (%) <span class="text-red-500">*</span>
                </label>
                <input type="text" inputmode="decimal" name="surcharge_cash_percent" required data-crm-number data-decimals="2"
                       value="{{ $cash }}"
                       class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm tabular-nums focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                <p class="mt-1 text-xs text-slate-400">Default 1% — ditambahkan ke modal exclude &amp; include.</p>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">
                    TOP (%) <span class="text-red-500">*</span>
                </label>
                <input type="text" inputmode="decimal" name="surcharge_top_percent" required data-crm-number data-decimals="2"
                       value="{{ $top }}"
                       class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm tabular-nums focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                <p class="mt-1 text-xs text-slate-400">Default 0% — tanpa tambahan.</p>
            </div>
        </div>

        <div class="mt-5 rounded-lg border border-slate-100 bg-slate-50 px-4 py-3 text-xs text-slate-500">
            Contoh: modal Rp 1.000.000, Cash 1% → tambahan exclude Rp 10.000, jumlah exclude Rp 1.010.000.
            Perubahan berlaku untuk PO baru / yang di-update setelah disimpan.
        </div>
    </x-card>

    <div class="flex items-center gap-2">
        <button class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">Save</button>
    </div>
</form>
@endsection
