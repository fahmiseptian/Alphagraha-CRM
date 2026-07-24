@extends('layouts.app')
@section('title', 'Wilayah Indonesia')

@section('content')
<div class="mb-4 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h2 class="text-lg font-semibold text-slate-800">Master Wilayah</h2>
        <p class="text-sm text-slate-500">
            Provinsi → Kota/Kabupaten → Kecamatan. Data dari
            <a href="https://wilayah.id" target="_blank" class="text-brand-600 hover:underline">wilayah.id</a>
            disimpan di DB; bisa ditambah manual.
        </p>
        <p class="mt-1 text-xs text-slate-400">
            Total: {{ number_format($counts['provinces']) }} provinsi ·
            {{ number_format($counts['regencies']) }} kota/kab ·
            {{ number_format($counts['districts']) }} kecamatan
            @if ($lastSyncedAt)
                · Sync terakhir: {{ $lastSyncedAt }}
            @endif
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        <form method="POST" action="{{ route('settings.wilayah.sync') }}"
              onsubmit="return confirm('Sync provinsi + kota/kab dari API? (kecamatan diisi saat dipakai)')">
            @csrf
            <input type="hidden" name="mode" value="basic">
            <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
                <i class="bi bi-cloud-download"></i> Sync Provinsi + Kota
            </button>
        </form>
        <form method="POST" action="{{ route('settings.wilayah.sync') }}"
              onsubmit="return confirm('Full sync termasuk semua kecamatan? Proses bisa sangat lama.')">
            @csrf
            <input type="hidden" name="mode" value="full">
            <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Sync Penuh
            </button>
        </form>
    </div>
</div>

@if ($counts['provinces'] === 0)
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Belum ada data wilayah. Klik <strong>Sync Provinsi + Kota</strong> sekali di awal.
    </div>
@endif

<div class="grid max-w-6xl gap-5 lg:grid-cols-3">
    {{-- Provinsi --}}
    <x-card title="Provinsi">
        <form method="GET" action="{{ route('settings.wilayah.index') }}" class="mb-3">
            <select name="province" class="select2 select2-search w-full" data-auto-submit
                    data-placeholder="— Pilih / filter —">
                <option value="">— Pilih / filter —</option>
                @foreach ($provinces as $p)
                    <option value="{{ $p->code }}" @selected($provinceCode === $p->code)>{{ $p->name }} ({{ $p->code }})</option>
                @endforeach
            </select>
        </form>

        <form method="POST" action="{{ route('settings.wilayah.provinces.store') }}" class="space-y-2 rounded-lg border border-dashed border-slate-200 p-3">
            @csrf
            <p class="text-xs font-semibold text-slate-600">Tambah manual</p>
            <input type="text" name="code" required placeholder="Kode (mis. 99)" maxlength="10"
                   class="w-full rounded-lg border border-slate-300 py-1.5 px-2 text-sm">
            <input type="text" name="name" required placeholder="Nama provinsi" maxlength="150"
                   class="w-full rounded-lg border border-slate-300 py-1.5 px-2 text-sm">
            <button class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white">Tambah</button>
        </form>

        <ul class="mt-3 max-h-64 space-y-1 overflow-y-auto text-sm">
            @forelse ($provinces as $p)
                <li class="flex items-center justify-between gap-2 rounded px-2 py-1 {{ $provinceCode === $p->code ? 'bg-brand-50' : 'hover:bg-slate-50' }}">
                    <a href="{{ route('settings.wilayah.index', ['province' => $p->code]) }}" class="min-w-0 flex-1 truncate text-slate-700">
                        {{ $p->name }}
                    </a>
                    <span class="shrink-0 text-[10px] uppercase text-slate-400">{{ $p->source }}</span>
                </li>
            @empty
                <li class="py-4 text-center text-xs text-slate-400">Kosong</li>
            @endforelse
        </ul>
    </x-card>

    {{-- Kota/Kab --}}
    <x-card title="Kota / Kabupaten">
        @if (! $provinceCode)
            <p class="py-6 text-center text-sm text-slate-400">Pilih provinsi dulu.</p>
        @else
            <form method="GET" action="{{ route('settings.wilayah.index') }}" class="mb-3">
                <input type="hidden" name="province" value="{{ $provinceCode }}">
                <select name="regency" class="select2 select2-search w-full" data-auto-submit
                        data-placeholder="— Pilih kota/kab —">
                    <option value="">— Pilih kota/kab —</option>
                    @foreach ($regencies as $r)
                        <option value="{{ $r->code }}" @selected($regencyCode === $r->code)>{{ $r->name }}</option>
                    @endforeach
                </select>
            </form>

            <form method="POST" action="{{ route('settings.wilayah.regencies.store') }}" class="space-y-2 rounded-lg border border-dashed border-slate-200 p-3">
                @csrf
                <input type="hidden" name="province_code" value="{{ $provinceCode }}">
                <p class="text-xs font-semibold text-slate-600">Tambah manual</p>
                <input type="text" name="code" required placeholder="Kode (mis. {{ $provinceCode }}.99)" maxlength="10"
                       class="w-full rounded-lg border border-slate-300 py-1.5 px-2 text-sm">
                <input type="text" name="name" required placeholder="Nama kota/kab" maxlength="150"
                       class="w-full rounded-lg border border-slate-300 py-1.5 px-2 text-sm">
                <button class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white">Tambah</button>
            </form>

            <ul class="mt-3 max-h-64 space-y-1 overflow-y-auto text-sm">
                @forelse ($regencies as $r)
                    <li class="flex items-center justify-between gap-2 rounded px-2 py-1 {{ $regencyCode === $r->code ? 'bg-brand-50' : 'hover:bg-slate-50' }}">
                        <a href="{{ route('settings.wilayah.index', ['province' => $provinceCode, 'regency' => $r->code]) }}"
                           class="min-w-0 flex-1 truncate text-slate-700">{{ $r->name }}</a>
                        <span class="shrink-0 text-[10px] uppercase text-slate-400">{{ $r->source }}</span>
                    </li>
                @empty
                    <li class="py-4 text-center text-xs text-slate-400">Belum ada kota di provinsi ini</li>
                @endforelse
            </ul>
        @endif
    </x-card>

    {{-- Kecamatan --}}
    <x-card title="Kecamatan">
        @if (! $regencyCode)
            <p class="py-6 text-center text-sm text-slate-400">Pilih kota/kab dulu.</p>
        @else
            <div class="mb-3 flex flex-wrap gap-2">
                <form method="POST" action="{{ route('settings.wilayah.districts.sync') }}">
                    @csrf
                    <input type="hidden" name="regency_code" value="{{ $regencyCode }}">
                    <input type="hidden" name="province" value="{{ $provinceCode }}">
                    <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                        <i class="bi bi-cloud-download"></i> Sync Kecamatan (API)
                    </button>
                </form>
                <span class="self-center text-xs text-slate-400">{{ $districts->count() }} data</span>
            </div>

            <form method="POST" action="{{ route('settings.wilayah.districts.store') }}" class="space-y-2 rounded-lg border border-dashed border-slate-200 p-3">
                @csrf
                <input type="hidden" name="regency_code" value="{{ $regencyCode }}">
                <p class="text-xs font-semibold text-slate-600">Tambah manual</p>
                <input type="text" name="code" required placeholder="Kode (mis. {{ $regencyCode }}.99)" maxlength="15"
                       class="w-full rounded-lg border border-slate-300 py-1.5 px-2 text-sm">
                <input type="text" name="name" required placeholder="Nama kecamatan" maxlength="150"
                       class="w-full rounded-lg border border-slate-300 py-1.5 px-2 text-sm">
                <button class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white">Tambah</button>
            </form>

            <ul class="mt-3 max-h-64 space-y-1 overflow-y-auto text-sm">
                @forelse ($districts as $d)
                    <li class="flex items-center justify-between gap-2 rounded px-2 py-1 hover:bg-slate-50">
                        <span class="truncate text-slate-700">{{ $d->name }}</span>
                        <span class="shrink-0 text-[10px] uppercase text-slate-400">{{ $d->source }}</span>
                    </li>
                @empty
                    <li class="py-4 text-center text-xs text-slate-400">Belum ada — sync dari API atau tambah manual</li>
                @endforelse
            </ul>
        @endif
    </x-card>
</div>
@endsection
