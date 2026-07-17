@extends('layouts.app')
@section('title', 'Tax Settings')

@section('content')
<div class="mb-4">
    <h2 class="text-lg font-semibold text-slate-800">Pengaturan Pajak</h2>
    <p class="text-sm text-slate-500">Atur PPN &amp; PPH agar perhitungan opportunity dan quotation mengikuti peraturan terbaru.</p>
</div>

<div class="max-w-xl">
    <x-card title="Tarif Pajak">
        <form method="POST" action="{{ route('settings.update') }}" class="space-y-5">
            @csrf
            @method('PUT')

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">PPN (%) <span class="text-red-500">*</span></label>
                <input type="number" name="ppn_percent" step="0.01" min="0" max="100" required
                       value="{{ old('ppn_percent', $ppnPercent) }}"
                       class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                <p class="mt-1 text-xs text-slate-400">Dipakai untuk konversi harga exclude ↔ include dan tax quotation. Contoh: 11 atau 12.</p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">PPH (%) <span class="text-red-500">*</span></label>
                <input type="number" name="pph_percent" step="0.01" min="0" max="100" required
                       value="{{ old('pph_percent', $pphPercent) }}"
                       class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                <p class="mt-1 text-xs text-slate-400">Dipakai pada perhitungan margin opportunity (Wapu / Jasa). Contoh: 2.</p>
            </div>

            <div class="rounded-lg border border-slate-100 bg-slate-50 px-4 py-3 text-xs text-slate-500">
                Perubahan berlaku untuk opportunity &amp; quotation baru / perhitungan ulang. Dokumen quotation yang sudah tersimpan tetap memakai <em>tax_percent</em> yang tersimpan di dokumen tersebut.
            </div>

            <div class="flex items-center gap-2 border-t border-slate-100 pt-4">
                <button class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">Save</button>
            </div>
        </form>
    </x-card>
</div>
@endsection
