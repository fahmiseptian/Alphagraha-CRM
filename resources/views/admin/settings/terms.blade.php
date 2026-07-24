@extends('layouts.app')
@section('title', 'Terms & Conditions')

@section('content')
@php
    $terms = old('default_terms', $defaultTerms);
    $ppnLabel = rtrim(rtrim(number_format((float) $ppnPercent, 2, ',', '.'), '0'), ',');
@endphp

<div class="mb-4">
    <h2 class="text-lg font-semibold text-slate-800">Terms &amp; Conditions (Quotation)</h2>
    <p class="text-sm text-slate-500">
        Teks default yang muncul saat membuat Quotation baru. Sales tetap bisa mengubah per dokumen.
        Placeholder <code class="rounded bg-slate-100 px-1 text-xs">@{{ppn}}</code> diganti otomatis dengan tarif PPN saat ini ({{ $ppnLabel }}%).
    </p>
</div>

<form method="POST" action="{{ route('settings.terms.update') }}" class="max-w-3xl space-y-5">
    @csrf
    @method('PUT')

    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <x-card title="Default Terms">
        <textarea name="default_terms" rows="10" required
                  class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
                  placeholder="1. ...">{{ $terms }}</textarea>
    </x-card>

    <div class="flex items-center gap-2">
        <button class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">Save</button>
    </div>
</form>
@endsection
