@extends('layouts.app')
@section('title', 'Product')

@section('content')
<x-page-header title="Product" :description="$stocks->total().' data harga & status barang dari vendor'">
    <x-slot:actions>
        <x-btn href="{{ route('vendor-stocks.create') }}" icon="bi-plus-lg">Tambah ketersediaan</x-btn>
    </x-slot:actions>
</x-page-header>

<x-card class="mb-4" :padding="false">
    <form method="GET" action="{{ route('vendor-stocks.index') }}" class="crm-filter-form">
        <div class="min-w-0 flex-1">
            <label class="crm-label">Pencarian</label>
            <div class="crm-search">
                <i class="bi bi-search"></i>
                <input type="text" name="q" value="{{ $search }}"
                       placeholder="Nama produk, SKU, vendor..."
                       class="crm-field" autocomplete="off">
            </div>
        </div>
        <div class="w-full sm:w-56">
            <label class="crm-label">Vendor</label>
            <select name="vendor_id" class="select2 select2-search w-full" data-placeholder="Semua vendor">
                <option value="">Semua vendor</option>
                @foreach ($vendors as $vendor)
                    <option value="{{ $vendor->id }}" @selected($vendorId === (int) $vendor->id)>{{ $vendor->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-full sm:w-40">
            <label class="crm-label">Status</label>
            <select name="status" class="select2 w-full" data-placeholder="Semua status">
                <option value="">Semua status</option>
                @foreach (\App\Models\VendorStock::STATUSES as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <x-btn type="submit" variant="primary" icon="bi-search">Cari</x-btn>
            @if ($search || $vendorId || $status)
                <x-btn href="{{ route('vendor-stocks.index') }}" variant="ghost">Reset</x-btn>
            @endif
        </div>
    </form>
</x-card>

<x-card :padding="false">
    @if ($stocks->count())
        <div class="crm-table-wrap">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Vendor</th>
                        <th>Status</th>
                        <th class="text-right">Harga</th>
                        <th>Diperbarui</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stocks as $item)
                        <tr>
                            <td>
                                <span class="font-medium text-slate-800">{{ $item->product_name }}</span>
                                @if ($item->sku)
                                    <div class="text-xs text-slate-400">SKU {{ $item->sku }}</div>
                                @endif
                                @if ($item->note)
                                    <div class="mt-0.5 text-xs italic text-slate-400">{{ \Illuminate\Support\Str::limit($item->note, 80) }}</div>
                                @endif
                            </td>
                            <td class="text-slate-700">{{ $item->vendor?->name ?: '—' }}</td>
                            <td>
                                <x-badge :color="$item->badgeColor()">{{ $item->statusLabel() }}</x-badge>
                                @unless ($item->is_active)
                                    <x-badge color="slate">Nonaktif</x-badge>
                                @endunless
                            </td>
                            <td class="text-right tabular-nums font-medium text-slate-800">{{ money($item->price) }}</td>
                            <td class="whitespace-nowrap text-xs text-slate-500">
                                {{ $item->updated_at?->translatedFormat('d M Y H:i') }}
                                @if ($item->creator)
                                    <div class="text-slate-400">{{ $item->creator->display_name }}</div>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('vendor-stocks.edit', $item) }}" class="crm-icon-btn" title="Edit"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('vendor-stocks.destroy', $item) }}" class="inline"
                                      onsubmit="return confirm('Hapus ketersediaan produk ini?')">
                                    @csrf @method('DELETE')
                                    <button class="crm-icon-btn text-red-500 hover:bg-red-50" title="Hapus"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($stocks->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">
                {{ $stocks->links('vendor.pagination.crm') }}
            </div>
        @endif
    @else
        <x-empty-state icon="bi-boxes" title="Belum ada ketersediaan vendor" message="Tambah harga dan status ready/indent per produk, atau isi dari form Purchase Order.">
            <x-slot:action>
                <div class="flex flex-wrap items-center justify-center gap-2">
                    <x-btn href="{{ route('vendor-stocks.create') }}" icon="bi-plus-lg">Tambah ketersediaan</x-btn>
                    @if (auth()->user()?->canAccessAdministration())
                        <x-btn href="{{ route('vendors.index') }}" variant="secondary" icon="bi-truck">Kelola vendor</x-btn>
                    @endif
                </div>
            </x-slot:action>
        </x-empty-state>
    @endif
</x-card>
@endsection
