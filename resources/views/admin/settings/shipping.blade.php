@extends('layouts.app')
@section('title', 'Free Ongkir Settings')

@section('content')
@php
    $selected = collect(old('regency_codes', $selectedCodes))->map(fn ($c) => (string) $c)->values()->all();
    $cities = collect($availableRegencies)->map(fn ($r) => [
        'code' => (string) $r['code'],
        'name' => (string) $r['name'],
        'province_name' => (string) ($r['province_name'] ?? ''),
    ])->values()->all();
@endphp

<div class="mb-4">
    <h2 class="text-lg font-semibold text-slate-800">Kawasan Free Ongkir</h2>
    <p class="text-sm text-slate-500">
        Pilih <strong>kota/kabupaten</strong> dari master wilayah yang mendapat free ongkir.
        Sumber: Settings → Wilayah (bukan teks bebas customer).
    </p>
</div>

@if (count($cities) === 0)
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Belum ada data kota. Sync dulu di
        <a href="{{ route('settings.wilayah.index') }}" class="font-semibold underline">Settings → Wilayah</a>.
    </div>
@endif

<form method="POST" action="{{ route('settings.shipping.update') }}" class="max-w-6xl space-y-5"
      x-data="freeShippingForm({{ \Illuminate\Support\Js::from([
          'cities' => $cities,
          'selected' => $selected,
      ]) }})">
    @csrf
    @method('PUT')

    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-2">
        {{-- Kiri: daftar semua kota --}}
        <x-card title="Semua Kota">
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <input type="search" x-model="query" placeholder="Cari kota atau provinsi…"
                       class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                <button type="button" @click="selectAllFiltered()"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50">
                    Pilih terfilter
                </button>
                <span class="text-xs text-slate-400" x-text="filtered.length + ' ditampilkan'"></span>
            </div>

            @if (count($cities) === 0)
                <p class="rounded-lg border border-dashed border-slate-200 py-8 text-center text-sm text-slate-400">
                    Tidak ada kota di master wilayah.
                </p>
            @else
                <div class="max-h-[28rem] overflow-y-auto rounded-lg border border-slate-200">
                    <ul class="divide-y divide-slate-100">
                        <template x-for="city in filtered" :key="city.code">
                            <li class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50"
                                :class="isSelected(city.code) && 'bg-brand-50/60'">
                                <input type="checkbox" :value="city.code" x-model="selected"
                                       class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm text-slate-700" x-text="city.name"></span>
                                    <span class="block text-xs text-slate-400" x-text="city.province_name"></span>
                                </span>
                            </li>
                        </template>
                    </ul>
                    <p x-show="filtered.length === 0" class="px-3 py-6 text-center text-sm text-slate-400">Tidak ada kota yang cocok.</p>
                </div>
            @endif
        </x-card>

        {{-- Kanan: kota terpilih --}}
        <x-card>
            <div class="mb-3 flex items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-semibold text-slate-800">Sudah dipilih</h3>
                    <p class="text-xs text-slate-400" x-text="selectedCities.length + ' kota free ongkir'"></p>
                </div>
                <button type="button" x-show="selected.length > 0" @click="clearAll()"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
                    Hapus semua
                </button>
            </div>

            <div class="max-h-[28rem] overflow-y-auto rounded-lg border border-slate-200 bg-slate-50/50">
                <ul class="divide-y divide-slate-100" x-show="selectedCities.length > 0">
                    <template x-for="city in selectedCities" :key="'sel-' + city.code">
                        <li class="flex items-center gap-3 bg-white px-3 py-2">
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium text-slate-800" x-text="city.name"></span>
                                <span class="block text-xs text-slate-400" x-text="city.province_name"></span>
                            </span>
                            <button type="button" @click="remove(city.code)"
                                    class="shrink-0 rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                    title="Hapus">
                                <i class="bi bi-x-lg text-xs"></i>
                            </button>
                        </li>
                    </template>
                </ul>
                <p x-show="selectedCities.length === 0" class="px-3 py-10 text-center text-sm text-slate-400">
                    Belum ada kota dipilih.<br>
                    Centang kota di kiri untuk menambah.
                </p>
            </div>
        </x-card>
    </div>

    <template x-for="code in selected" :key="'hidden-' + code">
        <input type="hidden" name="regency_codes[]" :value="code">
    </template>

    @if (! empty($orphanSelected))
        <p class="text-xs text-amber-600">
            Ada {{ count($orphanSelected) }} kode kota tersimpan yang tidak ada di master
            ({{ implode(', ', $orphanSelected) }}). Hilang otomatis jika tidak dipilih lagi.
        </p>
    @endif

    <div class="flex items-center gap-2">
        <button class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">Save</button>
    </div>
</form>

<script>
    function freeShippingForm(config) {
        return {
            cities: config.cities || [],
            selected: (config.selected || []).slice(),
            query: '',
            get filtered() {
                const q = (this.query || '').trim().toLowerCase();
                if (!q) return this.cities;
                return this.cities.filter((c) => {
                    const hay = ((c.name || '') + ' ' + (c.province_name || '') + ' ' + (c.code || '')).toLowerCase();
                    return hay.includes(q);
                });
            },
            get selectedCities() {
                const map = {};
                this.cities.forEach((c) => { map[c.code] = c; });
                return this.selected
                    .map((code) => map[code] || { code, name: code, province_name: '(tidak ada di master)' })
                    .sort((a, b) => String(a.name).localeCompare(String(b.name), 'id'));
            },
            isSelected(code) {
                return this.selected.includes(code);
            },
            remove(code) {
                this.selected = this.selected.filter((c) => c !== code);
            },
            selectAllFiltered() {
                const set = new Set(this.selected);
                this.filtered.forEach((city) => {
                    if (!set.has(city.code)) {
                        this.selected.push(city.code);
                        set.add(city.code);
                    }
                });
            },
            clearAll() {
                this.selected = [];
            },
        };
    }
</script>
@endsection
