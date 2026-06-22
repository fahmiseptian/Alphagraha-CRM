@extends('layouts.app')
@section('title', 'Penawaran ' . $quotation->number)

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div>
        <a href="{{ route('quotations.index') }}" class="text-sm text-slate-500 hover:text-slate-700"><i class="bi bi-arrow-left"></i> Daftar penawaran</a>
        <div class="mt-1 flex items-center gap-3">
            <h2 class="text-xl font-bold text-slate-800">{{ $quotation->number }}</h2>
            <x-badge :color="$quotation->statusColor()">{{ $quotation->statusLabel() }}</x-badge>
            <span class="text-xs text-slate-400">Revisi {{ $quotation->revision }}</span>
        </div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('quotations.preview', $quotation) }}" target="_blank" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50"><i class="bi bi-eye"></i> Preview</a>
        <a href="{{ route('quotations.pdf', $quotation) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50"><i class="bi bi-file-earmark-pdf"></i> Unduh PDF</a>
        <a href="{{ route('quotations.edit', $quotation) }}" class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700"><i class="bi bi-pencil"></i> Edit</a>
        <div x-data="{ open: false }" class="relative">
            <button @click="open=!open" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50"><i class="bi bi-three-dots-vertical"></i></button>
            <div x-show="open" x-cloak @click.outside="open=false" class="absolute right-0 z-10 mt-1 w-44 rounded-xl border border-slate-200 bg-white py-1.5 shadow-lg">
                <form method="POST" action="{{ route('quotations.duplicate', $quotation) }}">@csrf
                    <button class="block w-full px-4 py-2 text-left text-sm hover:bg-slate-50"><i class="bi bi-files mr-2"></i> Duplikat</button>
                </form>
                <form method="POST" action="{{ route('quotations.destroy', $quotation) }}" onsubmit="return confirm('Hapus penawaran ini?')">@csrf @method('DELETE')
                    <button class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50"><i class="bi bi-trash mr-2"></i> Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">
        <x-card title="Item Penawaran" :padding="false">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                            <th class="px-5 py-3 font-medium">Item</th>
                            <th class="px-5 py-3 text-center font-medium">Qty</th>
                            <th class="px-5 py-3 text-right font-medium">Harga</th>
                            <th class="px-5 py-3 text-right font-medium">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach ($quotation->items as $item)
                            <tr>
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800">{{ $item->name }}</p>
                                    @if ($item->description)<p class="text-xs text-slate-400">{{ $item->description }}</p>@endif
                                </td>
                                <td class="px-5 py-3 text-center text-slate-600">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }} {{ $item->unit }}</td>
                                <td class="px-5 py-3 text-right text-slate-600">{{ money($item->unit_price, $quotation->currency) }}</td>
                                <td class="px-5 py-3 text-right font-medium text-slate-700">{{ money($item->total, $quotation->currency) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-4">
                <dl class="ml-auto max-w-xs space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd class="text-slate-700">{{ money($quotation->subtotal, $quotation->currency) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Diskon</dt><dd class="text-slate-700">{{ money($quotation->discount, $quotation->currency) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Pajak ({{ rtrim(rtrim(number_format($quotation->tax_percent,2),'0'),'.') }}%)</dt><dd class="text-slate-700">{{ money($quotation->tax_amount, $quotation->currency) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-bold text-slate-900"><dt>Total</dt><dd class="text-brand-600">{{ money($quotation->total, $quotation->currency) }}</dd></div>
                </dl>
            </div>
        </x-card>

        @if ($quotation->notes || $quotation->terms)
        <x-card title="Catatan & Syarat">
            @if ($quotation->notes)<div class="mb-3"><p class="text-xs font-semibold uppercase text-slate-400">Catatan</p><p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ $quotation->notes }}</p></div>@endif
            @if ($quotation->terms)<div><p class="text-xs font-semibold uppercase text-slate-400">Syarat & Ketentuan</p><p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ $quotation->terms }}</p></div>@endif
        </x-card>
        @endif

        <x-card title="Riwayat Revisi" :padding="false">
            @forelse ($quotation->revisions as $rev)
                <div class="flex items-center justify-between border-b border-slate-50 px-5 py-3 last:border-0">
                    <div>
                        <p class="text-sm font-medium text-slate-800">Revisi {{ $rev->revision }} <span class="text-xs font-normal text-slate-400">— {{ $rev->note }}</span></p>
                        <p class="text-xs text-slate-400">{{ $rev->created_at->translatedFormat('d M Y H:i') }} &middot; {{ optional($rev->creator)->name }}</p>
                    </div>
                    <span class="text-sm text-slate-600">{{ money(data_get($rev->snapshot, 'total', 0), $quotation->currency) }}</span>
                </div>
            @empty
                <div class="px-5 py-6 text-center text-sm text-slate-400">Belum ada riwayat revisi.</div>
            @endforelse
        </x-card>
    </div>

    <div class="space-y-4">
        <x-card title="Pelanggan">
            <p class="font-semibold text-slate-800">{{ $quotation->customer_name }}</p>
            <p class="text-sm text-slate-500">{{ $quotation->company_name }}</p>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex gap-2"><dt class="text-slate-400"><i class="bi bi-envelope"></i></dt><dd class="text-slate-600">{{ $quotation->customer_email ?: '—' }}</dd></div>
                <div class="flex gap-2"><dt class="text-slate-400"><i class="bi bi-telephone"></i></dt><dd class="text-slate-600">{{ $quotation->customer_phone ?: '—' }}</dd></div>
                <div class="flex gap-2"><dt class="text-slate-400"><i class="bi bi-geo-alt"></i></dt><dd class="text-slate-600">{{ $quotation->customer_address ?: '—' }}</dd></div>
            </dl>
            @if ($quotation->account_id)
                <a href="{{ route('customers.show', $quotation->account_id) }}" class="mt-3 inline-block text-sm text-brand-600 hover:text-brand-700">Lihat profil pelanggan <i class="bi bi-arrow-right"></i></a>
            @endif
        </x-card>

        <x-card title="Informasi">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-400">Tanggal</dt><dd class="text-slate-700">{{ $quotation->quotation_date?->translatedFormat('d M Y') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Berlaku hingga</dt><dd class="text-slate-700">{{ $quotation->valid_until?->translatedFormat('d M Y') ?: '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Template</dt><dd class="text-slate-700">{{ optional($quotation->template)->name ?: 'Default' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Dibuat oleh</dt><dd class="text-slate-700">{{ optional($quotation->creator)->name }}</dd></div>
            </dl>
        </x-card>

        <x-card title="Ubah Status">
            <form method="POST" action="{{ route('quotations.status', $quotation) }}" class="flex gap-2">
                @csrf @method('PATCH')
                <select name="status" class="flex-1 rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    @foreach (\App\Models\Quotation::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected($quotation->status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Simpan</button>
            </form>
        </x-card>
    </div>
</div>
@endsection
