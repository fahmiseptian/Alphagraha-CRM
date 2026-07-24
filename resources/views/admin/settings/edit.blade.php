@extends('layouts.app')
@section('title', 'Tax Settings')

@section('content')
@php
    $ppn = (float) old('ppn_percent', $ppnPercent);
    $pphNwJasa = (float) old('pph_non_wapu_jasa', $pphNonWapuJasa);
    $pphWBarang = (float) old('pph_wapu_barang', $pphWapuBarang);
    $pphWJasa = (float) old('pph_wapu_jasa', $pphWapuJasa);
    $pnbp = (float) old('pnbp_percent', $pnbpPercent);
    $pph29 = (float) old('pph29_percent', $pph29Percent);
@endphp

<div class="mb-4">
    <h2 class="text-lg font-semibold text-slate-800">Pengaturan Pajak</h2>
    <p class="text-sm text-slate-500">PPN, PPH per kategori (Non Wapu / Wapu / Inaproc), PNBP, dan PPH Pasal 29.</p>
</div>

<div class="grid max-w-6xl gap-5 lg:grid-cols-5"
     x-data="taxSettingsPage({
         ppn: {{ $ppn }},
         pphNonWapuJasa: {{ $pphNwJasa }},
         pphWapuBarang: {{ $pphWBarang }},
         pphWapuJasa: {{ $pphWJasa }},
         pnbpPercent: {{ $pnbp }},
         pph29Percent: {{ $pph29 }},
     })">
    <form method="POST" action="{{ route('settings.update') }}" class="space-y-5 lg:col-span-3">
        @csrf
        @method('PUT')

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <x-card title="PPN">
            <div class="max-w-xs">
                <label class="mb-1.5 block text-sm font-medium text-slate-700">PPN (%) <span class="text-red-500">*</span></label>
                <input type="number" name="ppn_percent" step="0.01" min="0" max="100" required x-model.number="ppn"
                       class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                <p class="mt-1 text-xs text-slate-400">Konversi harga exclude ↔ include &amp; tax quotation.</p>
            </div>
        </x-card>

        <x-card title="PPH — Non Wapu">
            <p class="mb-4 text-xs text-slate-500">Barang: PPN saja. Jasa: PPN + PPH di bawah.</p>
            <div class="max-w-xs">
                <label class="mb-1.5 block text-sm font-medium text-slate-700">PPH Jasa (%) <span class="text-red-500">*</span></label>
                <input type="number" name="pph_non_wapu_jasa" step="0.01" min="0" max="100" required x-model.number="pphNonWapuJasa"
                       class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                <p class="mt-1 text-xs text-slate-400">Default 2%.</p>
            </div>
        </x-card>

        <x-card title="PPH — Wapu / Inaproc">
            <p class="mb-4 text-xs text-slate-500">Rate ini dipakai Wapu dan Inaproc (Barang / Jasa).</p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">PPH Barang (%) <span class="text-red-500">*</span></label>
                    <input type="number" name="pph_wapu_barang" step="0.01" min="0" max="100" required x-model.number="pphWapuBarang"
                           class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    <p class="mt-1 text-xs text-slate-400">Default 1.5%.</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">PPH Jasa (%) <span class="text-red-500">*</span></label>
                    <input type="number" name="pph_wapu_jasa" step="0.01" min="0" max="100" required x-model.number="pphWapuJasa"
                           class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    <p class="mt-1 text-xs text-slate-400">Default 2%.</p>
                </div>
            </div>
        </x-card>

        <x-card title="Inaproc — PNBP &\ PPH Pasal 29">
            <p class="mb-4 text-xs text-slate-500">Hanya kategori Inaproc. PNBP dari basis jual; PPH 29 dari margin kotor.</p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">PNBP (%) <span class="text-red-500">*</span></label>
                    <input type="number" name="pnbp_percent" step="0.01" min="0" max="100" required x-model.number="pnbpPercent"
                           class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    <p class="mt-1 text-xs text-slate-400">Default 0.4%.</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">PPH 29 (%) <span class="text-red-500">*</span></label>
                    <input type="number" name="pph29_percent" step="0.01" min="0" max="100" required x-model.number="pph29Percent"
                           class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    <p class="mt-1 text-xs text-slate-400">Default 22% (dari margin kotor).</p>
                </div>
            </div>
        </x-card>

        <div class="flex items-center gap-2">
            <button class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">Save</button>
        </div>
    </form>

    <div class="lg:col-span-2">
        <x-card title="Kalkulator Pajak">
            <p class="mb-4 text-xs text-slate-500">Simulasi live memakai rate di kiri (belum perlu Save).</p>

            <div class="space-y-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Kategori</label>
                    <select x-model="taxCategory" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm">
                        <option value="non_wapu">Non Wapu</option>
                        <option value="wapu">Wapu</option>
                        <option value="inaproc">Inaproc</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Jenis</label>
                    <select x-model="itemKind" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm">
                        <option value="barang">Barang</option>
                        <option value="jasa">Jasa</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Harga jual exclude</label>
                    <input type="number" step="0.01" min="0" x-model.number="sellExclude"
                           class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm text-right">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Modal / cost exclude</label>
                    <input type="number" step="0.01" min="0" x-model.number="costExclude"
                           class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm text-right">
                </div>
            </div>

            <p class="mt-4 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600" x-text="ruleSummary"></p>

            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between gap-3 border-b border-slate-100 pb-2">
                    <dt class="text-slate-500">Include (PPN <span x-text="ppn"></span>%)</dt>
                    <dd class="font-medium text-slate-800" x-text="format(sellInclude)"></dd>
                </div>
                <div class="flex justify-between gap-3" x-show="pph > 0">
                    <dt class="text-slate-500">PPH <span x-text="pphPct"></span>%</dt>
                    <dd class="font-medium text-slate-800" x-text="format(pph)"></dd>
                </div>
                <div class="flex justify-between gap-3" x-show="taxCategory === 'inaproc'">
                    <dt class="text-slate-500">PNBP <span x-text="pnbpPercent"></span>%</dt>
                    <dd class="font-medium text-slate-800" x-text="format(pnbp)"></dd>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 pb-2">
                    <dt class="text-slate-500">Margin kotor</dt>
                    <dd class="font-medium text-slate-800" x-text="format(grossMargin)"></dd>
                </div>
                <div class="flex justify-between gap-3" x-show="taxCategory === 'inaproc'">
                    <dt class="text-slate-500">PPH 29 <span x-text="pph29Percent"></span>% (dari kotor)</dt>
                    <dd class="font-medium text-slate-800" x-text="format(pph29)"></dd>
                </div>
                <div class="flex justify-between gap-3 rounded-lg bg-emerald-50 px-3 py-2">
                    <dt class="font-semibold text-emerald-800">Margin bersih</dt>
                    <dd class="font-bold text-emerald-800" x-text="format(netMargin)"></dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">Margin %</dt>
                    <dd class="font-medium text-slate-800" x-text="marginPercentLabel"></dd>
                </div>
            </dl>
        </x-card>
    </div>
</div>

<script>
    function taxSettingsPage(initial) {
        return {
            ppn: Number(initial.ppn) || 11,
            pphNonWapuJasa: Number(initial.pphNonWapuJasa) || 2,
            pphWapuBarang: Number(initial.pphWapuBarang) || 1.5,
            pphWapuJasa: Number(initial.pphWapuJasa) || 2,
            pnbpPercent: Number(initial.pnbpPercent) || 0.4,
            pph29Percent: Number(initial.pph29Percent) || 22,
            taxCategory: 'non_wapu',
            itemKind: 'barang',
            sellExclude: 1000000,
            costExclude: 800000,
            round(v) { return Math.round((Number(v) || 0) * 100) / 100; },
            format(v) {
                return (Number(v) || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
            },
            get pphPct() {
                if (this.taxCategory === 'non_wapu' && this.itemKind === 'barang') return 0;
                if (this.taxCategory === 'non_wapu' && this.itemKind === 'jasa') return Number(this.pphNonWapuJasa) || 0;
                if (this.itemKind === 'barang') return Number(this.pphWapuBarang) || 0;
                return Number(this.pphWapuJasa) || 0;
            },
            get ruleSummary() {
                const ppn = this.ppn;
                if (this.taxCategory === 'non_wapu' && this.itemKind === 'barang') {
                    return 'Non Wapu · Barang: PPN ' + ppn + '%';
                }
                if (this.taxCategory === 'non_wapu') {
                    return 'Non Wapu · Jasa: PPN ' + ppn + '% + PPH ' + this.pphPct + '%';
                }
                if (this.taxCategory === 'wapu') {
                    return 'Wapu · ' + (this.itemKind === 'barang' ? 'Barang' : 'Jasa')
                        + ': PPN ' + ppn + '% + PPH ' + this.pphPct + '%';
                }
                return 'Inaproc · ' + (this.itemKind === 'barang' ? 'Barang' : 'Jasa')
                    + ': PPN ' + ppn + '% + PPH ' + this.pphPct + '% + PNBP ' + this.pnbpPercent
                    + '% + PPH 29 ' + this.pph29Percent + '%';
            },
            get sellInclude() {
                return this.round(this.sellExclude * (1 + (Number(this.ppn) || 0) / 100));
            },
            get pph() {
                const rate = this.pphPct / 100;
                return rate > 0 ? this.round(this.sellExclude * rate) : 0;
            },
            get pnbp() {
                if (this.taxCategory !== 'inaproc') return 0;
                return this.round(this.sellExclude * (Number(this.pnbpPercent) || 0) / 100);
            },
            get grossMargin() {
                return this.round(this.sellExclude - this.pph - this.pnbp - (Number(this.costExclude) || 0));
            },
            get pph29() {
                if (this.taxCategory !== 'inaproc' || this.grossMargin <= 0) return 0;
                return this.round(this.grossMargin * (Number(this.pph29Percent) || 0) / 100);
            },
            get netMargin() {
                return this.round(this.grossMargin - this.pph29);
            },
            get marginPercentLabel() {
                const base = Number(this.sellExclude) || 0;
                if (base <= 0) return '—';
                return this.round((this.netMargin / base) * 100).toLocaleString('id-ID', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                }) + '%';
            },
        };
    }
</script>
@endsection
