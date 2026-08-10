@extends('layouts.app')
@section('title', 'Tax Settings')

@section('content')
@php
    $ppn = (float) old('ppn_percent', $ppnPercent);
    $pphNwJasa = (float) old('pph_non_wapu_jasa', $pphNonWapuJasa);
    $pphWBarang = (float) old('pph_wapu_barang', $pphWapuBarang);
    $pphWJasa = (float) old('pph_wapu_jasa', $pphWapuJasa);
    $pph29 = (float) old('pph29_percent', $pph29Percent);
    $royalty = (float) old('royalty_percent', $royaltyPercent ?? 20);
    $tiersOld = old('pnbp_tiers');
    $tiers = collect(is_array($tiersOld) ? $tiersOld : ($pnbpTiers ?? []))->map(fn ($t) => [
        'max' => $t['max'] ?? null,
        'rate_percent' => (float) ($t['rate_percent'] ?? 0),
        'cap' => (float) ($t['cap'] ?? 0),
    ])->values()->all();
    if ($tiers === []) {
        $tiers = \App\Support\OpportunityProductPricing::defaultPnbpTiers();
    }
    $zinitOld = old('zinit_tiers');
    $zinitTiersForm = collect(is_array($zinitOld) ? $zinitOld : ($zinitTiers ?? []))->map(fn ($t) => [
        'max' => $t['max'] ?? null,
        'platform_fee' => (float) ($t['platform_fee'] ?? 0),
        'rate_percent' => (float) ($t['rate_percent'] ?? 0),
        'cap' => $t['cap'] ?? null,
    ])->values()->all();
    if ($zinitTiersForm === []) {
        $zinitTiersForm = \App\Support\OpportunityProductPricing::defaultZinitTiers();
    }
@endphp

<div class="mb-4">
    <h2 class="text-lg font-semibold text-slate-800">Pengaturan Pajak</h2>
    <p class="text-sm text-slate-500">PPN, PPH per kategori, PNBP, PPH 29, Rate Scale Zinit, dan Royalti (opsional per item).</p>
</div>

<div class="grid max-w-6xl gap-5 lg:grid-cols-5"
     x-data="taxSettingsPage({
         ppn: {{ $ppn }},
         pphNonWapuJasa: {{ $pphNwJasa }},
         pphWapuBarang: {{ $pphWBarang }},
         pphWapuJasa: {{ $pphWJasa }},
         pph29Percent: {{ $pph29 }},
         royaltyPercent: {{ $royalty }},
         pnbpTiers: {{ \Illuminate\Support\Js::from($tiers) }},
         zinitTiers: {{ \Illuminate\Support\Js::from($zinitTiersForm) }},
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
                <input type="text" inputmode="decimal" required
                       x-effect="if (editingField !== 'ppn') $el.value = formatId(ppn, 2)"
                       @focus="editingField = 'ppn'"
                       @blur="editingField = null; $el.value = formatId(ppn, 2)"
                       @input="ppn = parseId($event.target.value)"
                       class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm tabular-nums focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                <input type="hidden" name="ppn_percent" :value="ppn">
                <p class="mt-1 text-xs text-slate-400">Konversi harga exclude ↔ include &amp; tax quotation.</p>
            </div>
        </x-card>

        <x-card title="PPH — Non Wapu">
            <p class="mb-4 text-xs text-slate-500">Barang: PPN saja. Jasa: PPN + PPH di bawah.</p>
            <div class="max-w-xs">
                <label class="mb-1.5 block text-sm font-medium text-slate-700">PPH Jasa (%) <span class="text-red-500">*</span></label>
                <input type="text" inputmode="decimal" required
                       x-effect="if (editingField !== 'pphNw') $el.value = formatId(pphNonWapuJasa, 2)"
                       @focus="editingField = 'pphNw'"
                       @blur="editingField = null; $el.value = formatId(pphNonWapuJasa, 2)"
                       @input="pphNonWapuJasa = parseId($event.target.value)"
                       class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm tabular-nums focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                <input type="hidden" name="pph_non_wapu_jasa" :value="pphNonWapuJasa">
                <p class="mt-1 text-xs text-slate-400">Default 2%.</p>
            </div>
        </x-card>

        <x-card title="PPH — Wapu / Inaproc">
            <p class="mb-4 text-xs text-slate-500">Rate ini dipakai Wapu dan Inaproc (Barang / Jasa).</p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">PPH Barang (%) <span class="text-red-500">*</span></label>
                    <input type="text" inputmode="decimal" required
                           x-effect="if (editingField !== 'pphWb') $el.value = formatId(pphWapuBarang, 2)"
                           @focus="editingField = 'pphWb'"
                           @blur="editingField = null; $el.value = formatId(pphWapuBarang, 2)"
                           @input="pphWapuBarang = parseId($event.target.value)"
                           class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm tabular-nums focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    <input type="hidden" name="pph_wapu_barang" :value="pphWapuBarang">
                    <p class="mt-1 text-xs text-slate-400">Default 1.5%.</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">PPH Jasa (%) <span class="text-red-500">*</span></label>
                    <input type="text" inputmode="decimal" required
                           x-effect="if (editingField !== 'pphWj') $el.value = formatId(pphWapuJasa, 2)"
                           @focus="editingField = 'pphWj'"
                           @blur="editingField = null; $el.value = formatId(pphWapuJasa, 2)"
                           @input="pphWapuJasa = parseId($event.target.value)"
                           class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm tabular-nums focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    <input type="hidden" name="pph_wapu_jasa" :value="pphWapuJasa">
                    <p class="mt-1 text-xs text-slate-400">Default 2%.</p>
                </div>
            </div>
        </x-card>

        <x-card title="Inaproc — PNBP berjenjang">
            <p class="mb-3 text-xs text-slate-500">
                Basis: <strong>harga jual include</strong>. Rumus tiap jenjang:
                <code class="rounded bg-slate-100 px-1">MIN(jual_include × rate%, cap)</code>.
                Batas kosong = di atas semua batas sebelumnya. Urutan otomatis dari batas terkecil.
            </p>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-400">
                            <th class="py-2 pr-2 font-medium">Batas jual include (≤)</th>
                            <th class="py-2 pr-2 font-medium">Rate %</th>
                            <th class="py-2 pr-2 font-medium">Cap (maks PNBP)</th>
                            <th class="py-2 w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(tier, i) in pnbpTiers" :key="i">
                            <tr class="border-b border-slate-100">
                                <td class="py-2 pr-2">
                                    <input type="text" inputmode="decimal" placeholder="(di atas)"
                                           x-effect="if (editingField !== `tmax-${i}`) $el.value = (tier.max === '' || tier.max === null) ? '' : formatId(tier.max)"
                                           @focus="editingField = `tmax-${i}`"
                                           @blur="editingField = null; $el.value = (tier.max === '' || tier.max === null) ? '' : formatId(tier.max)"
                                           @input="tier.max = $event.target.value.trim() === '' ? '' : parseId($event.target.value)"
                                           class="w-full min-w-[9rem] rounded-lg border border-slate-300 py-1.5 px-2 text-right text-sm tabular-nums">
                                    <input type="hidden" :name="`pnbp_tiers[${i}][max]`" :value="tier.max === '' || tier.max === null ? '' : tier.max">
                                </td>
                                <td class="py-2 pr-2">
                                    <input type="text" inputmode="decimal" required
                                           x-effect="if (editingField !== `trate-${i}`) $el.value = formatId(tier.rate_percent, 4)"
                                           @focus="editingField = `trate-${i}`"
                                           @blur="editingField = null; $el.value = formatId(tier.rate_percent, 4)"
                                           @input="tier.rate_percent = parseId($event.target.value)"
                                           class="w-full min-w-[5rem] rounded-lg border border-slate-300 py-1.5 px-2 text-right text-sm tabular-nums">
                                    <input type="hidden" :name="`pnbp_tiers[${i}][rate_percent]`" :value="tier.rate_percent">
                                </td>
                                <td class="py-2 pr-2">
                                    <input type="text" inputmode="decimal" required
                                           x-effect="if (editingField !== `tcap-${i}`) $el.value = formatId(tier.cap)"
                                           @focus="editingField = `tcap-${i}`"
                                           @blur="editingField = null; $el.value = formatId(tier.cap)"
                                           @input="tier.cap = parseId($event.target.value)"
                                           class="w-full min-w-[8rem] rounded-lg border border-slate-300 py-1.5 px-2 text-right text-sm tabular-nums">
                                    <input type="hidden" :name="`pnbp_tiers[${i}][cap]`" :value="tier.cap">
                                </td>
                                <td class="py-2 text-right">
                                    <button type="button" @click="removePnbpTier(i)"
                                            class="rounded p-1 text-red-500 hover:bg-red-50" title="Hapus"
                                            :disabled="pnbpTiers.length <= 1">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <button type="button" @click="addPnbpTier()"
                    class="mt-3 inline-flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                <i class="bi bi-plus-lg"></i> Tambah jenjang
            </button>
        </x-card>

        <x-card title="Inaproc — PPH Pasal 29">
            <div class="max-w-xs">
                <label class="mb-1.5 block text-sm font-medium text-slate-700">PPH 29 (%) <span class="text-red-500">*</span></label>
                <input type="text" inputmode="decimal" required
                       x-effect="if (editingField !== 'pph29') $el.value = formatId(pph29Percent, 2)"
                       @focus="editingField = 'pph29'"
                       @blur="editingField = null; $el.value = formatId(pph29Percent, 2)"
                       @input="pph29Percent = parseId($event.target.value)"
                       class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm tabular-nums focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                <input type="hidden" name="pph29_percent" :value="pph29Percent">
                <p class="mt-1 text-xs text-slate-400">Default 22%. Rumus: (jual exclude − modal exclude) × % × qty.</p>
            </div>
        </x-card>

        <x-card title="Royalti">
            <p class="mb-3 text-xs text-slate-500">
                Pajak tambahan opsional via checkbox per item — berlaku di <strong>semua kategori</strong> (Non Wapu / Wapu / Inaproc / Zinit).
                Rumus: <code class="rounded bg-slate-100 px-1">modal excl × rate%</code>.
            </p>
            <div class="max-w-xs">
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Royalti (%) <span class="text-red-500">*</span></label>
                <input type="text" inputmode="decimal" required
                       x-effect="if (editingField !== 'royalty') $el.value = formatId(royaltyPercent, 2)"
                       @focus="editingField = 'royalty'"
                       @blur="editingField = null; $el.value = formatId(royaltyPercent, 2)"
                       @input="royaltyPercent = parseId($event.target.value)"
                       class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm tabular-nums focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                <input type="hidden" name="royalty_percent" :value="royaltyPercent">
                <p class="mt-1 text-xs text-slate-400">Default 20%.</p>
            </div>
        </x-card>

        <x-card title="Zinit — Rate Scale">
            <p class="mb-3 text-xs text-slate-500">
                Basis tier &amp; service fee: <strong>harga jual include</strong>.
                Fee Zinit = Platform Fee + <code class="rounded bg-slate-100 px-1">MIN(jual_include × rate%, cap)</code>.
                Margin = jual excl − Fee Zinit − modal excl.
                Batas kosong = di atas semua batas sebelumnya.
            </p>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-400">
                            <th class="py-2 pr-2 font-medium">Batas jual include (≤)</th>
                            <th class="py-2 pr-2 font-medium">Platform Fee</th>
                            <th class="py-2 pr-2 font-medium">Rate %</th>
                            <th class="py-2 pr-2 font-medium">Cap Service Fee</th>
                            <th class="py-2 w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(tier, i) in zinitTiers" :key="i">
                            <tr class="border-b border-slate-100">
                                <td class="py-2 pr-2">
                                    <input type="text" inputmode="decimal" placeholder="(di atas)"
                                           x-effect="if (editingField !== `zmax-${i}`) $el.value = (tier.max === '' || tier.max === null) ? '' : formatId(tier.max)"
                                           @focus="editingField = `zmax-${i}`"
                                           @blur="editingField = null; $el.value = (tier.max === '' || tier.max === null) ? '' : formatId(tier.max)"
                                           @input="tier.max = $event.target.value.trim() === '' ? '' : parseId($event.target.value)"
                                           class="w-full min-w-[9rem] rounded-lg border border-slate-300 py-1.5 px-2 text-right text-sm tabular-nums">
                                    <input type="hidden" :name="`zinit_tiers[${i}][max]`" :value="tier.max === '' || tier.max === null ? '' : tier.max">
                                </td>
                                <td class="py-2 pr-2">
                                    <input type="text" inputmode="decimal" required
                                           x-effect="if (editingField !== `zplat-${i}`) $el.value = formatId(tier.platform_fee)"
                                           @focus="editingField = `zplat-${i}`"
                                           @blur="editingField = null; $el.value = formatId(tier.platform_fee)"
                                           @input="tier.platform_fee = parseId($event.target.value)"
                                           class="w-full min-w-[8rem] rounded-lg border border-slate-300 py-1.5 px-2 text-right text-sm tabular-nums">
                                    <input type="hidden" :name="`zinit_tiers[${i}][platform_fee]`" :value="tier.platform_fee">
                                </td>
                                <td class="py-2 pr-2">
                                    <input type="text" inputmode="decimal" required
                                           x-effect="if (editingField !== `zrate-${i}`) $el.value = formatId(tier.rate_percent, 4)"
                                           @focus="editingField = `zrate-${i}`"
                                           @blur="editingField = null; $el.value = formatId(tier.rate_percent, 4)"
                                           @input="tier.rate_percent = parseId($event.target.value)"
                                           class="w-full min-w-[5rem] rounded-lg border border-slate-300 py-1.5 px-2 text-right text-sm tabular-nums">
                                    <input type="hidden" :name="`zinit_tiers[${i}][rate_percent]`" :value="tier.rate_percent">
                                </td>
                                <td class="py-2 pr-2">
                                    <input type="text" inputmode="decimal" placeholder="(tanpa cap)"
                                           x-effect="if (editingField !== `zcap-${i}`) $el.value = (tier.cap === '' || tier.cap === null) ? '' : formatId(tier.cap)"
                                           @focus="editingField = `zcap-${i}`"
                                           @blur="editingField = null; $el.value = (tier.cap === '' || tier.cap === null) ? '' : formatId(tier.cap)"
                                           @input="tier.cap = $event.target.value.trim() === '' ? '' : parseId($event.target.value)"
                                           class="w-full min-w-[8rem] rounded-lg border border-slate-300 py-1.5 px-2 text-right text-sm tabular-nums">
                                    <input type="hidden" :name="`zinit_tiers[${i}][cap]`" :value="tier.cap === '' || tier.cap === null ? '' : tier.cap">
                                </td>
                                <td class="py-2 text-right">
                                    <button type="button" @click="removeZinitTier(i)"
                                            class="rounded p-1 text-red-500 hover:bg-red-50" title="Hapus"
                                            :disabled="zinitTiers.length <= 1">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <button type="button" @click="addZinitTier()"
                    class="mt-3 inline-flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                <i class="bi bi-plus-lg"></i> Tambah jenjang
            </button>
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
                        <option value="zinit">Zinit</option>
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
                    <input type="text" inputmode="decimal"
                           x-effect="if (editingField !== 'calcSell') $el.value = formatId(sellExclude)"
                           @focus="editingField = 'calcSell'"
                           @blur="editingField = null; $el.value = formatId(sellExclude)"
                           @input="sellExclude = parseId($event.target.value)"
                           class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm text-right tabular-nums">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Modal / cost exclude</label>
                    <input type="text" inputmode="decimal"
                           x-effect="if (editingField !== 'calcCost') $el.value = formatId(costExclude)"
                           @focus="editingField = 'calcCost'"
                           @blur="editingField = null; $el.value = formatId(costExclude)"
                           @input="costExclude = parseId($event.target.value)"
                           class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm text-right tabular-nums">
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" x-model="hasRoyalty" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    <span>Royalti <span class="text-slate-400" x-text="'(' + royaltyPercent + '%)'"></span></span>
                </label>
            </div>

            <p class="mt-4 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600" x-text="ruleSummary"></p>

            <dl class="mt-4 space-y-2 text-sm" x-show="taxCategory !== 'zinit'">
                <div class="flex justify-between gap-3 border-b border-slate-100 pb-2">
                    <dt class="text-slate-500">Include (PPN <span x-text="ppn"></span>%)</dt>
                    <dd class="font-medium text-slate-800" x-text="format(sellInclude)"></dd>
                </div>
                <div class="flex justify-between gap-3" x-show="pph > 0">
                    <dt class="text-slate-500">PPH <span x-text="pphPct"></span>%</dt>
                    <dd class="font-medium text-slate-800" x-text="format(pph)"></dd>
                </div>
                <div class="flex justify-between gap-3" x-show="taxCategory === 'inaproc'">
                    <dt class="text-slate-500">
                        PNBP
                        <span class="text-xs text-slate-400" x-text="'· ' + matchedPnbpTier.rate_percent + '% cap ' + format(matchedPnbpTier.cap)"></span>
                    </dt>
                    <dd class="font-medium text-slate-800" x-text="format(pnbp)"></dd>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 pb-2">
                    <dt class="text-slate-500">Margin kotor <span class="text-xs text-slate-400" x-show="taxCategory === 'wapu' || taxCategory === 'inaproc'">(jual − PPH − modal include)</span></dt>
                    <dd class="font-medium text-slate-800" x-text="format(grossMargin)"></dd>
                </div>
                <div class="flex justify-between gap-3" x-show="taxCategory === 'inaproc'">
                    <dt class="text-slate-500">PPH 29 <span x-text="pph29Percent"></span>% <span class="text-xs text-slate-400">(jual − modal) exclude</span></dt>
                    <dd class="font-medium text-slate-800" x-text="format(pph29)"></dd>
                </div>
                <div class="flex justify-between gap-3" x-show="hasRoyalty">
                    <dt class="text-slate-500">Royalti <span x-text="royaltyPercent"></span>%</dt>
                    <dd class="font-medium text-slate-800" x-text="format(royalty)"></dd>
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

            <dl class="mt-4 space-y-2 text-sm" x-show="taxCategory === 'zinit'" x-cloak>
                <div class="flex justify-between gap-3 border-b border-slate-100 pb-2">
                    <dt class="text-slate-500">Include (PPN <span x-text="ppn"></span>%)</dt>
                    <dd class="font-medium text-slate-800" x-text="format(sellInclude)"></dd>
                </div>
                <div class="flex justify-between gap-3" x-show="pph > 0">
                    <dt class="text-slate-500">PPH <span x-text="pphPct"></span>%</dt>
                    <dd class="font-medium text-slate-800" x-text="format(pph)"></dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">
                        Platform Fee
                        <span class="text-xs text-slate-400" x-text="matchedZinitTier.max === null ? '· di atas' : ('· ≤ ' + format(matchedZinitTier.max))"></span>
                    </dt>
                    <dd class="font-medium text-slate-800" x-text="format(zinitPlatformFee)"></dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">
                        Service Fee
                        <span class="text-xs text-slate-400" x-text="'· include × ' + matchedZinitTier.rate_percent + '%' + (matchedZinitTier.cap ? (' cap ' + format(matchedZinitTier.cap)) : '')"></span>
                    </dt>
                    <dd class="font-medium text-slate-800" x-text="format(zinitServiceFee)"></dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">Fee Zinit</dt>
                    <dd class="font-medium text-slate-800" x-text="format(zinitSuccessFee)"></dd>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 pb-2">
                    <dt class="text-slate-500">Modal Pot Fee Zinit</dt>
                    <dd class="font-medium text-slate-800" x-text="format(zinitPotFee)"></dd>
                </div>
                <div class="flex justify-between gap-3" x-show="hasRoyalty">
                    <dt class="text-slate-500">Royalti <span x-text="royaltyPercent"></span>%</dt>
                    <dd class="font-medium text-slate-800" x-text="format(royalty)"></dd>
                </div>
                <div class="flex justify-between gap-3 rounded-lg bg-emerald-50 px-3 py-2">
                    <dt class="font-semibold text-emerald-800" x-text="zinitMarginLabel"></dt>
                    <dd class="font-bold text-emerald-800" x-text="format(zinitMargin)"></dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">Margin %</dt>
                    <dd class="font-medium text-slate-800" x-text="zinitMarginPercentLabel"></dd>
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
            pph29Percent: Number(initial.pph29Percent) || 22,
            royaltyPercent: Number(initial.royaltyPercent) || 20,
            pnbpTiers: (initial.pnbpTiers || []).map(t => ({
                max: t.max === null || t.max === undefined || t.max === '' ? '' : Number(t.max),
                rate_percent: Number(t.rate_percent) || 0,
                cap: Number(t.cap) || 0,
            })),
            zinitTiers: (initial.zinitTiers || []).map(t => ({
                max: t.max === null || t.max === undefined || t.max === '' ? '' : Number(t.max),
                platform_fee: Number(t.platform_fee) || 0,
                rate_percent: Number(t.rate_percent) || 0,
                cap: t.cap === null || t.cap === undefined || t.cap === '' ? '' : Number(t.cap),
            })),
            editingField: null,
            taxCategory: 'non_wapu',
            itemKind: 'barang',
            hasRoyalty: false,
            sellExclude: 1000000,
            costExclude: 800000,
            round(v) { return Math.round((Number(v) || 0) * 100) / 100; },
            formatId(value, decimals = 0) {
                if (window.CrmNumber) return window.CrmNumber.format(value, decimals);
                return (Number(value) || 0).toLocaleString('id-ID', { maximumFractionDigits: decimals });
            },
            parseId(str) {
                if (window.CrmNumber) return window.CrmNumber.parse(str);
                return Number(String(str).replace(/\./g, '').replace(',', '.')) || 0;
            },
            format(v) {
                return this.formatId(v, 2);
            },
            addPnbpTier() {
                this.pnbpTiers.push({ max: '', rate_percent: 0.05, cap: 0 });
            },
            removePnbpTier(i) {
                if (this.pnbpTiers.length <= 1) return;
                this.pnbpTiers.splice(i, 1);
            },
            addZinitTier() {
                this.zinitTiers.push({ max: '', platform_fee: 0, rate_percent: 0.1, cap: '' });
            },
            removeZinitTier(i) {
                if (this.zinitTiers.length <= 1) return;
                this.zinitTiers.splice(i, 1);
            },
            sortedPnbpTiers() {
                return [...this.pnbpTiers].map(t => ({
                    max: t.max === '' || t.max === null || t.max === undefined ? null : Number(t.max),
                    rate_percent: Number(t.rate_percent) || 0,
                    cap: Number(t.cap) || 0,
                })).sort((a, b) => {
                    if (a.max === null && b.max === null) return 0;
                    if (a.max === null) return 1;
                    if (b.max === null) return -1;
                    return a.max - b.max;
                });
            },
            sortedZinitTiers() {
                return [...this.zinitTiers].map(t => ({
                    max: t.max === '' || t.max === null || t.max === undefined ? null : Number(t.max),
                    platform_fee: Number(t.platform_fee) || 0,
                    rate_percent: Number(t.rate_percent) || 0,
                    cap: t.cap === '' || t.cap === null || t.cap === undefined ? null : Number(t.cap),
                })).sort((a, b) => {
                    if (a.max === null && b.max === null) return 0;
                    if (a.max === null) return 1;
                    if (b.max === null) return -1;
                    return a.max - b.max;
                });
            },
            get matchedPnbpTier() {
                const include = this.sellInclude;
                const tiers = this.sortedPnbpTiers();
                let fallback = tiers[tiers.length - 1] || { max: null, rate_percent: 0, cap: 0 };
                for (const tier of tiers) {
                    if (tier.max === null) return tier;
                    if (include <= tier.max) return tier;
                }
                return fallback;
            },
            get matchedZinitTier() {
                const volume = this.sellInclude;
                const tiers = this.sortedZinitTiers();
                let fallback = tiers[tiers.length - 1] || { max: null, platform_fee: 0, rate_percent: 0, cap: null };
                for (const tier of tiers) {
                    if (tier.max === null) return tier;
                    if (volume <= tier.max) return tier;
                }
                return fallback;
            },
            get pphPct() {
                if (this.taxCategory === 'zinit' && this.itemKind === 'jasa') return Number(this.pphNonWapuJasa) || 0;
                if (this.taxCategory === 'zinit') return 0;
                if (this.taxCategory === 'non_wapu' && this.itemKind === 'barang') return 0;
                if (this.taxCategory === 'non_wapu' && this.itemKind === 'jasa') return Number(this.pphNonWapuJasa) || 0;
                if (this.itemKind === 'barang') return Number(this.pphWapuBarang) || 0;
                return Number(this.pphWapuJasa) || 0;
            },
            get ruleSummary() {
                if (this.taxCategory === 'zinit') {
                    const t = this.matchedZinitTier;
                    let s = 'Zinit · ' + (this.itemKind === 'barang' ? 'Barang' : 'Jasa')
                        + ': Platform Fee ' + this.format(t.platform_fee)
                        + ' + Service Fee (include × ' + t.rate_percent + '%';
                    if (t.cap) s += ', cap ' + this.format(t.cap);
                    s += ') = Fee Zinit';
                    if (this.itemKind === 'jasa') {
                        s += ' + PPH ' + this.pphPct + '%';
                    }
                    s += '; Margin = jual excl'
                        + (this.itemKind === 'jasa' ? ' − PPH' : '')
                        + ' − fee − modal';
                    return s;
                }
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
                const t = this.matchedPnbpTier;
                return 'Inaproc · ' + (this.itemKind === 'barang' ? 'Barang' : 'Jasa')
                    + ': PPN ' + ppn + '% + PPH ' + this.pphPct
                    + '% + PNBP MIN(include×' + t.rate_percent + '%, ' + this.format(t.cap) + ')'
                    + ' + PPH 29 ' + this.pph29Percent + '%';
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
                const include = this.sellInclude;
                if (include <= 0) return 0;
                const t = this.matchedPnbpTier;
                let amount = include * ((Number(t.rate_percent) || 0) / 100);
                const cap = Number(t.cap) || 0;
                if (cap > 0) amount = Math.min(amount, cap);
                return this.round(amount);
            },
            get zinitPlatformFee() {
                if (this.taxCategory !== 'zinit') return 0;
                if ((Number(this.sellExclude) || 0) <= 0) return 0;
                return this.round(this.matchedZinitTier.platform_fee);
            },
            get zinitServiceFee() {
                if (this.taxCategory !== 'zinit') return 0;
                const include = this.sellInclude;
                if (include <= 0) return 0;
                const t = this.matchedZinitTier;
                let service = include * ((Number(t.rate_percent) || 0) / 100);
                if (t.cap !== null && t.cap > 0) service = Math.min(service, t.cap);
                return this.round(service);
            },
            get zinitSuccessFee() {
                return this.round(this.zinitPlatformFee + this.zinitServiceFee);
            },
            get zinitPotFee() {
                return this.round((Number(this.sellExclude) || 0) - this.zinitSuccessFee);
            },
            get royalty() {
                if (!this.hasRoyalty) return 0;
                return this.round((Number(this.costExclude) || 0) * ((Number(this.royaltyPercent) || 0) / 100));
            },
            get zinitMarginLabel() {
                let s = 'Margin (Pot Fee';
                if (this.itemKind === 'jasa') s += ' − PPH';
                if (this.hasRoyalty) s += ' − Royalti';
                s += ' − Modal)';
                return s;
            },
            get zinitMargin() {
                return this.round(this.zinitPotFee - this.pph - this.royalty - (Number(this.costExclude) || 0));
            },
            get zinitMarginPercentLabel() {
                const base = this.zinitPotFee;
                if (base <= 0) return '—';
                return this.round((this.zinitMargin / base) * 100).toLocaleString('id-ID', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                }) + '%';
            },
            get grossMargin() {
                const sell = Number(this.sellExclude) || 0;
                const cost = Number(this.costExclude) || 0;
                if (this.taxCategory === 'wapu' || this.taxCategory === 'inaproc') {
                    const costInclude = this.round(cost * (1 + (Number(this.ppn) || 0) / 100));
                    return this.round(sell - this.pph - costInclude);
                }
                return this.round(sell - this.pph - cost);
            },
            get pph29() {
                if (this.taxCategory !== 'inaproc') return 0;
                const spread = (Number(this.sellExclude) || 0) - (Number(this.costExclude) || 0);
                if (spread <= 0) return 0;
                return this.round(spread * (Number(this.pph29Percent) || 0) / 100);
            },
            get netMargin() {
                return this.round(this.grossMargin - this.pnbp - this.pph29 - this.royalty);
            },
            get marginPercentLabel() {
                const base = Number(this.sellExclude) || 0;
                if (base <= 0) return '—';
                const denom = (this.taxCategory === 'wapu' || this.taxCategory === 'inaproc')
                    ? (base - this.pph)
                    : base;

                if (denom <= 0) return '—';

                return this.round((this.netMargin / denom) * 100).toLocaleString('id-ID', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                }) + '%';
            },
        };
    }
</script>
@endsection
