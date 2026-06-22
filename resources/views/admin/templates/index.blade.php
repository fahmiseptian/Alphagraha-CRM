@extends('layouts.app')
@section('title', 'Template Penawaran')

@section('content')
<div class="mb-5 flex items-center justify-between">
    <div>
        <h2 class="text-lg font-semibold text-slate-800">Template Penawaran</h2>
        <p class="text-sm text-slate-500">Standarisasi dokumen penawaran perusahaan</p>
    </div>
    <a href="{{ route('templates.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><i class="bi bi-plus-lg"></i> Template Baru</a>
</div>

<x-card :padding="false">
    @if ($templates->count())
        <ul class="divide-y divide-slate-50">
            @foreach ($templates as $template)
                <li class="flex items-center gap-4 px-5 py-4 hover:bg-slate-50">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><i class="bi bi-file-earmark-richtext text-lg"></i></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <p class="font-medium text-slate-800">{{ $template->name }}</p>
                            @if ($template->is_default)<x-badge color="green">Default</x-badge>@endif
                            @if (!$template->is_active)<x-badge color="slate">Nonaktif</x-badge>@endif
                        </div>
                        <p class="truncate text-xs text-slate-400">{{ $template->description ?: 'Tanpa deskripsi' }}</p>
                    </div>
                    <div class="flex items-center gap-1">
                        <a href="{{ route('templates.edit', $template) }}" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" title="Edit"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="{{ route('templates.destroy', $template) }}" onsubmit="return confirm('Hapus template ini?')">@csrf @method('DELETE')
                            <button class="rounded-lg p-2 text-red-500 hover:bg-red-50" title="Hapus"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <x-empty-state icon="bi-file-earmark-richtext" title="Belum ada template" message="Buat template HTML pertama Anda." />
    @endif
</x-card>

<x-card class="mt-4" title="Placeholder yang Didukung">
    <p class="mb-3 text-sm text-slate-500">Gunakan placeholder berikut dalam template HTML. Sistem akan menggantinya otomatis saat penawaran dibuat.</p>
    <div class="grid grid-cols-2 gap-2 text-xs sm:grid-cols-3 lg:grid-cols-4">
        @foreach (['customer_name','company_name','customer_email','customer_phone','customer_address','quotation_number','quotation_date','valid_until','currency','subtotal','discount','tax_percent','tax_amount','total_price','notes','terms','sales_name','revision','items_table','items_rows'] as $ph)
            <code class="rounded bg-slate-100 px-2 py-1 text-brand-700">@{{ {{ $ph }} }}</code>
        @endforeach
    </div>
</x-card>
@endsection
