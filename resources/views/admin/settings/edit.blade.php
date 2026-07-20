@extends('layouts.app')
@section('title', 'Tax Settings')

@section('content')
@php
    $ppn = (float) old('ppn_percent', $ppnPercent);
    $pph = (float) old('pph_percent', $pphPercent);
@endphp

<div class="mb-4">
    <h2 class="text-lg font-semibold text-slate-800">Pengaturan Pajak</h2>
    <p class="text-sm text-slate-500">Atur PPN &amp; PPH agar perhitungan opportunity dan quotation mengikuti peraturan terbaru. Gunakan contoh di kanan untuk memverifikasi rumus sebelum menyimpan.</p>
</div>

<div class="grid grid-cols-1 gap-5 xl:grid-cols-2"
     x-data="taxSettingsExample({
         ppn: {{ \Illuminate\Support\Js::from($ppn) }},
         pph: {{ \Illuminate\Support\Js::from($pph) }},
     })">
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
                       x-model.number="ppnPercent"
                       value="{{ $ppn }}"
                       class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                <p class="mt-1 text-xs text-slate-400">Konversi harga exclude ↔ include &amp; tax quotation. Contoh: 11 atau 12.</p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">PPH (%) <span class="text-red-500">*</span></label>
                <input type="number" name="pph_percent" step="0.01" min="0" max="100" required
                       x-model.number="pphPercent"
                       value="{{ $pph }}"
                       class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                <p class="mt-1 text-xs text-slate-400">Dipakai pada margin opportunity (Wapu / Jasa). Non Wapu + Barang: PPH tidak dipotong. Contoh: 2.</p>
            </div>

            <div class="rounded-lg border border-slate-100 bg-slate-50 px-4 py-3 text-xs text-slate-500">
                Perubahan berlaku untuk opportunity &amp; quotation baru / perhitungan ulang. Dokumen quotation yang sudah tersimpan tetap memakai <em>tax_percent</em> yang tersimpan di dokumen tersebut.
            </div>

            <div class="flex items-center gap-2 border-t border-slate-100 pt-4">
                <button class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">Save</button>
            </div>
        </form>
    </x-card>

    <x-card title="Contoh Perhitungan (Live Test)">
        <p class="mb-4 text-xs text-slate-500">
            Angka di bawah memakai <strong>tarif yang sedang Anda ketik</strong> (belum perlu Save). Bandingkan hasilnya dengan kalkulator manual / Excel.
        </p>

        <div class="mb-4 grid gap-3 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-[11px] font-medium text-slate-500">Harga Jual Exclude</label>
                <input type="number" step="1" min="0" x-model.number="sellExclude"
                       class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
            </div>
            <div>
                <label class="mb-1 block text-[11px] font-medium text-slate-500">Modal / Beli Exclude</label>
                <input type="number" step="1" min="0" x-model.number="costExclude"
                       class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
            </div>
            <div>
                <label class="mb-1 block text-[11px] font-medium text-slate-500">Diskon Item (harga net, 0 = tidak ada)</label>
                <input type="number" step="1" min="0" x-model.number="itemDiscount"
                       class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="mb-1 block text-[11px] font-medium text-slate-500">Kategori</label>
                    <select x-model="taxCategory" class="w-full rounded-lg border border-slate-300 py-2 px-2 text-sm">
                        <option value="non_wapu">Non Wapu</option>
                        <option value="wapu">Wapu</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-medium text-slate-500">Jenis</label>
                    <select x-model="itemKind" class="w-full rounded-lg border border-slate-300 py-2 px-2 text-sm">
                        <option value="barang">Barang</option>
                        <option value="jasa">Jasa</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="mb-3 flex flex-wrap gap-2">
            <button type="button" @click="loadPreset('barang')"
                    class="rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-600 hover:bg-slate-50">
                Preset: Non Wapu Barang
            </button>
            <button type="button" @click="loadPreset('jasa')"
                    class="rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-600 hover:bg-slate-50">
                Preset: Wapu Jasa
            </button>
            <button type="button" @click="loadPreset('diskon')"
                    class="rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-600 hover:bg-slate-50">
                Preset: Ada Diskon Item
            </button>
        </div>

        {{-- PPN --}}
        <div class="mb-4 overflow-hidden rounded-lg border border-slate-200">
            <div class="border-b border-slate-100 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700">1. PPN — Exclude ↔ Include</div>
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex justify-between gap-3 px-3 py-2">
                    <dt class="text-slate-500">Rumus Include</dt>
                    <dd class="text-right font-mono text-xs text-slate-700">Exclude × (1 + PPN/100)</dd>
                </div>
                <div class="flex justify-between gap-3 px-3 py-2">
                    <dt class="text-slate-500">Harga Jual Include</dt>
                    <dd class="font-semibold text-slate-800" x-text="money(sellInclude)"></dd>
                </div>
                <div class="flex justify-between gap-3 px-3 py-2">
                    <dt class="text-slate-500">Modal Include</dt>
                    <dd class="font-semibold text-slate-800" x-text="money(costInclude)"></dd>
                </div>
                <div class="flex justify-between gap-3 px-3 py-2 bg-slate-50/80">
                    <dt class="text-slate-500">Cek balik Exclude dari Include jual</dt>
                    <dd class="font-medium text-slate-700" x-text="money(excludeFromInclude(sellInclude))"></dd>
                </div>
            </dl>
        </div>

        {{-- PPH + Margin --}}
        <div class="overflow-hidden rounded-lg border border-slate-200">
            <div class="border-b border-slate-100 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700">2. PPH &amp; Margin Opportunity</div>
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex justify-between gap-3 px-3 py-2">
                    <dt class="text-slate-500">Basis harga (margin)</dt>
                    <dd class="text-right">
                        <span class="font-semibold text-slate-800" x-text="money(effectiveSell)"></span>
                        <span class="mt-0.5 block text-[10px] text-slate-400" x-text="itemDiscount > 0 ? 'Diskon item > 0 → pakai harga net' : 'Diskon = 0 → pakai harga jual'"></span>
                    </dd>
                </div>
                <div class="flex justify-between gap-3 px-3 py-2">
                    <dt class="text-slate-500">PPH berlaku?</dt>
                    <dd>
                        <span x-show="appliesPph" class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-800">Ya</span>
                        <span x-show="!appliesPph" class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">Tidak (Non Wapu + Barang)</span>
                    </dd>
                </div>
                <div class="flex justify-between gap-3 px-3 py-2">
                    <dt class="text-slate-500" x-text="'PPH ' + pphPercent + '%'"></dt>
                    <dd class="font-semibold text-slate-800" x-text="money(pphAmount)"></dd>
                </div>
                <div class="flex justify-between gap-3 px-3 py-2">
                    <dt class="text-slate-500">Rumus margin</dt>
                    <dd class="text-right font-mono text-[11px] text-slate-600" x-text="marginFormula"></dd>
                </div>
                <div class="flex justify-between gap-3 px-3 py-2.5 bg-green-50">
                    <dt class="font-medium text-green-800">Margin</dt>
                    <dd class="text-right">
                        <span class="font-bold text-green-800" x-text="money(margin)"></span>
                        <span class="ml-1 text-xs text-green-700" x-text="'(' + formatPct(marginPercent) + ')'"></span>
                    </dd>
                </div>
            </dl>
        </div>

        <div class="mt-4 rounded-lg border border-brand-100 bg-brand-50/60 px-3 py-2.5 text-[11px] leading-relaxed text-brand-900">
            <p class="font-semibold">Cara memastikan benar</p>
            <ol class="mt-1 list-decimal space-y-0.5 pl-4 text-brand-800/90">
                <li>Ubah PPN/PPH di kiri — hasil contoh ikut berubah tanpa Save.</li>
                <li>Klik preset, lalu hitung manual: Include = Exclude × <span x-text="(1 + ppnPercent/100).toFixed(4)"></span>.</li>
                <li>Untuk Non Wapu + Barang: PPH harus 0, margin = Basis − Modal.</li>
                <li>Untuk Wapu/Jasa: PPH = Basis × <span x-text="(pphPercent/100).toFixed(4)"></span>, margin = Basis − PPH − Modal.</li>
                <li>Bila puas, klik <strong>Save</strong> agar tarif dipakai di opportunity &amp; QO baru.</li>
            </ol>
        </div>
    </x-card>
</div>
@endsection

@push('scripts')
<script>
    function taxSettingsExample(config) {
        return {
            ppnPercent: Number(config.ppn) || 11,
            pphPercent: Number(config.pph) || 2,
            sellExclude: 11000000,
            costExclude: 10000000,
            itemDiscount: 0,
            taxCategory: 'non_wapu',
            itemKind: 'barang',

            round(n) {
                return Math.round((Number(n) || 0) * 100) / 100;
            },
            money(n) {
                const v = Number(n) || 0;
                return 'Rp ' + v.toLocaleString('id-ID', { maximumFractionDigits: 0 });
            },
            formatPct(n) {
                return (Number(n) || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
            },
            get ppnMultiplier() {
                return 1 + ((Number(this.ppnPercent) || 0) / 100);
            },
            get pphRate() {
                return (Number(this.pphPercent) || 0) / 100;
            },
            get sellInclude() {
                return this.round((Number(this.sellExclude) || 0) * this.ppnMultiplier);
            },
            get costInclude() {
                return this.round((Number(this.costExclude) || 0) * this.ppnMultiplier);
            },
            excludeFromInclude(include) {
                const m = this.ppnMultiplier;
                if (m <= 0) return 0;
                return this.round((Number(include) || 0) / m);
            },
            get appliesPph() {
                return !(this.taxCategory === 'non_wapu' && this.itemKind === 'barang');
            },
            get effectiveSell() {
                const disc = Number(this.itemDiscount) || 0;
                return disc > 0 ? disc : (Number(this.sellExclude) || 0);
            },
            get pphAmount() {
                if (!this.appliesPph) return 0;
                return this.round(this.effectiveSell * this.pphRate);
            },
            get margin() {
                const base = this.effectiveSell;
                const cost = Number(this.costExclude) || 0;
                if (!this.appliesPph) return this.round(base - cost);
                return this.round(base - this.pphAmount - cost);
            },
            get marginPercent() {
                const base = this.effectiveSell;
                if (base <= 0) return 0;
                return this.round((this.margin / base) * 100);
            },
            get marginFormula() {
                if ((Number(this.itemDiscount) || 0) > 0) {
                    return this.appliesPph ? 'Diskon − PPH − Modal' : 'Diskon − Modal';
                }
                return this.appliesPph ? 'Jual Exclude − PPH − Modal' : 'Jual Exclude − Modal';
            },
            loadPreset(type) {
                if (type === 'barang') {
                    this.taxCategory = 'non_wapu';
                    this.itemKind = 'barang';
                    this.sellExclude = 11000000;
                    this.costExclude = 10000000;
                    this.itemDiscount = 0;
                } else if (type === 'jasa') {
                    this.taxCategory = 'wapu';
                    this.itemKind = 'jasa';
                    this.sellExclude = 11000000;
                    this.costExclude = 10000000;
                    this.itemDiscount = 0;
                } else if (type === 'diskon') {
                    this.taxCategory = 'non_wapu';
                    this.itemKind = 'barang';
                    this.sellExclude = 11000000;
                    this.costExclude = 10000000;
                    this.itemDiscount = 10500000;
                }
            },
        };
    }
</script>
@endpush
