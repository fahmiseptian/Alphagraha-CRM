@extends('layouts.app')
@section('title', 'Quotation ' . $quotation->number)

@section('content')
@php
    $marginLocked = $quotation->isMarginLocked();
    $canApproveMargin = auth()->user()->canApproveMargin();
@endphp

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div>
        <a href="{{ route('quotations.index') }}" class="text-sm text-slate-500 hover:text-slate-700"><i class="bi bi-arrow-left"></i> Back to list</a>
        <div class="mt-1 flex flex-wrap items-center gap-3">
            <h2 class="text-xl font-bold text-slate-800">{{ $quotation->number }}</h2>
            <x-badge :color="$quotation->statusColor()">{{ $quotation->statusLabel() }}</x-badge>
            @if ($quotation->crm_margin_status)
                @php
                    $marginBadge = match ($quotation->crm_margin_status) {
                        'pending' => 'amber',
                        'approved' => 'green',
                        'rejected' => 'red',
                        default => 'slate',
                    };
                @endphp
                <x-badge :color="$marginBadge">{{ $quotation->marginStatusLabel() }}</x-badge>
            @endif
            <span class="text-xs text-slate-400">
                @if ($quotation->document_revision > 0)
                    Dokumen R{{ $quotation->document_revision }}
                @else
                    Quotation
                @endif
            </span>
        </div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        @if ($marginLocked && ! $canApproveMargin)
            <span class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800" title="Menunggu approval margin">
                <i class="bi bi-lock"></i> Preview/PDF terkunci
            </span>
        @else
            <a href="{{ route('quotations.preview', $quotation) }}" target="_blank" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50"><i class="bi bi-eye"></i> Preview</a>
            <a href="{{ route('quotations.pdf', $quotation) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a>
        @endif
        <a href="{{ route('quotations.edit', $quotation) }}" class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700"><i class="bi bi-pencil"></i> Edit</a>
        <div x-data="{ open: false }" class="relative">
            <button @click="open=!open" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50"><i class="bi bi-three-dots-vertical"></i></button>
            <div x-show="open" x-cloak @click.outside="open=false" class="absolute right-0 z-10 mt-1 w-44 rounded-xl border border-slate-200 bg-white py-1.5 shadow-lg">
                <form method="POST" action="{{ route('quotations.duplicate', $quotation) }}">@csrf
                    <button class="block w-full px-4 py-2 text-left text-sm hover:bg-slate-50"><i class="bi bi-files mr-2"></i> Duplicate</button>
                </form>
                @if (auth()->user()->canDeleteQuotation())
                <form method="POST" action="{{ route('quotations.destroy', $quotation) }}" onsubmit="return confirm('Hapus quotation ini?')">@csrf @method('DELETE')
                    <button class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50"><i class="bi bi-trash mr-2"></i> Hapus</button>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>

@if ($quotation->crm_margin_status)
    <div @class([
        'mb-4 rounded-xl border px-4 py-3',
        'border-amber-200 bg-amber-50' => $quotation->crm_margin_status === 'pending',
        'border-green-200 bg-green-50' => $quotation->crm_margin_status === 'approved',
        'border-red-200 bg-red-50' => $quotation->crm_margin_status === 'rejected',
    ])>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="text-sm">
                <p class="font-semibold text-slate-800">{{ $quotation->marginStatusLabel() }}</p>
                <p class="mt-1 text-slate-600">
                    Margin opportunity:
                    <strong>{{ $quotation->crm_margin_percent !== null ? number_format((float) $quotation->crm_margin_percent, 2, ',', '.').'%' : '—' }}</strong>
                    / <strong>{{ money($quotation->crm_margin_nominal) }}</strong>
                    &middot; Minimal:
                    <strong>{{ $quotation->crm_margin_threshold !== null ? number_format((float) $quotation->crm_margin_threshold, 2, ',', '.').'%' : '—' }}</strong>
                    / <strong>{{ money($quotation->crm_margin_nominal_threshold) }}</strong>
                </p>
                @if ($quotation->crm_margin_note)
                    <p class="mt-1 text-xs text-slate-500">Catatan: {{ $quotation->crm_margin_note }}</p>
                @endif
                @if (in_array($quotation->crm_margin_status, [\App\Models\Quotation::MARGIN_APPROVED, \App\Models\Quotation::MARGIN_REJECTED], true)
                    && ($quotation->marginReviewerName() || $quotation->crm_margin_reviewed_at))
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $quotation->crm_margin_status === \App\Models\Quotation::MARGIN_APPROVED ? 'Disetujui' : 'Ditolak' }}
                        @if ($quotation->marginReviewerName())
                            oleh <strong>{{ $quotation->marginReviewerName() }}</strong>
                        @endif
                        @if ($quotation->crm_margin_reviewed_at)
                            · {{ $quotation->crm_margin_reviewed_at->timezone(config('app.timezone'))->format('d M Y H:i') }}
                        @endif
                    </p>
                @endif
            </div>
            @if ($quotation->marginNeedsApproval() && $canApproveMargin)
                <div class="flex w-full max-w-md flex-col gap-2 sm:w-auto">
                    <form method="POST" action="{{ route('quotations.margin.approve', $quotation) }}" class="space-y-2">
                        @csrf
                        <input type="text" name="note" placeholder="Catatan approve (opsional)"
                               class="w-full rounded-lg border border-green-200 bg-white px-2 py-1.5 text-xs text-slate-700">
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-green-600 px-3 py-2 text-xs font-semibold text-white hover:bg-green-700">
                                <i class="bi bi-check-lg"></i> Approve
                            </button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('quotations.margin.reject', $quotation) }}" class="space-y-2">
                        @csrf
                        <input type="text" name="note" placeholder="Catatan reject (opsional)"
                               class="w-full rounded-lg border border-red-200 bg-white px-2 py-1.5 text-xs text-slate-700">
                        <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-white px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50">
                            <i class="bi bi-x-lg"></i> Reject
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endif

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">
        <x-card title="Quotation Items" :padding="false">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                            <th class="px-5 py-3 font-medium">Item</th>
                            <th class="px-5 py-3 text-center font-medium">Qty</th>
                            <th class="px-5 py-3 text-right font-medium">Harga Exclude</th>
                            <th class="px-5 py-3 text-right font-medium">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach ($quotation->items as $item)
                            <tr>
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800">{{ $item->name }}</p>
                                    @if ($item->description)
                                        <div class="quotation-item-spec mt-1 text-xs text-slate-500">
                                            {!! strip_tags($item->description, '<p><br><b><strong><i><em><u><ul><ol><li><a><span><div>') !!}
                                        </div>
                                    @endif
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
                    <div class="flex justify-between"><dt class="text-slate-500">Discount</dt><dd class="text-slate-700">{{ money($quotation->discount, $quotation->currency) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Tax ({{ rtrim(rtrim(number_format($quotation->tax_percent,2),'0'),'.') }}%)</dt><dd class="text-slate-700">{{ money($quotation->tax_amount, $quotation->currency) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-bold text-slate-900"><dt>Total</dt><dd class="text-brand-600">{{ money($quotation->total, $quotation->currency) }}</dd></div>
                </dl>
            </div>
        </x-card>

        @if ($quotation->notes || $quotation->terms)
        <x-card title="Notes & Terms">
            @if ($quotation->notes)<div class="mb-3"><p class="text-xs font-semibold uppercase text-slate-400">Notes</p><p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ $quotation->notes }}</p></div>@endif
            @if ($quotation->terms)
                @php
                    $termsContent = (string) $quotation->terms;
                    $termsIsHtml = (bool) preg_match('/<[^>]+>/', $termsContent);
                @endphp
                <div>
                    <p class="text-xs font-semibold uppercase text-slate-400">Terms & Conditions</p>
                    @if ($termsIsHtml)
                        <div class="mt-1 prose prose-sm max-w-none text-sm text-slate-600">{!! $termsContent !!}</div>
                    @else
                        <p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ $termsContent }}</p>
                    @endif
                </div>
            @endif
        </x-card>
        @endif

        <x-card title="{{ $quotation->hasBeenSent() ? 'Revision History' : 'Dokumen' }}" :padding="false">
            @forelse ($quotation->revisions as $rev)
                <div class="flex items-center justify-between border-b border-slate-50 px-5 py-3 last:border-0">
                    <div>
                        <p class="text-sm font-medium text-slate-800">
                            @php $revNumber = data_get($rev->snapshot, 'number', $quotation->number); @endphp
                            {{ $revNumber }}
                            <span class="text-xs font-normal text-slate-400">— {{ $rev->note }}</span>
                        </p>
                        <p class="text-xs text-slate-400">{{ $rev->created_at->translatedFormat('d M Y H:i') }} &middot; {{ optional($rev->creator)->name }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-slate-600">{{ money(data_get($rev->snapshot, 'total', 0), $quotation->currency) }}</span>
                        @if ($rev->rendered_html && ! ($marginLocked && ! $canApproveMargin))
                            <a href="{{ route('quotations.revisions.preview', [$quotation, $rev]) }}" target="_blank"
                               class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs text-brand-600 hover:bg-brand-50">
                                <i class="bi bi-eye"></i> Lihat
                            </a>
                        @elseif ($rev->rendered_html && $marginLocked)
                            <span class="rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs text-amber-700" title="Menunggu approval margin">
                                <i class="bi bi-lock"></i> Terkunci
                            </span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-5 py-6 text-center text-sm text-slate-400">Belum ada dokumen.</div>
            @endforelse
            @if (! $quotation->hasBeenSent())
                <p class="border-t border-slate-50 px-5 py-2 text-xs text-slate-400">
                    Revisi nomor (-R1, -R2, …) baru dibuat saat status <strong>Sent</strong> lalu isi dokumen diubah.
                    Setelah revisi, status kembali ke Draft — edit lagi tidak naik R sampai dikirim ulang.
                </p>
            @elseif ($quotation->status !== 'sent')
                <p class="border-t border-slate-50 px-5 py-2 text-xs text-slate-400">
                    Status Draft. Edit isi tidak menaikkan nomor R. Setelah di-<strong>Sent</strong> lagi, perubahan berikutnya baru menjadi R{{ (int) $quotation->document_revision + 1 }}.
                </p>
            @endif
        </x-card>
    </div>

    <div class="space-y-4">
        <x-card title="Customer">
            <p class="font-semibold text-slate-800">{{ $quotation->customer_name }}</p>
            <p class="text-sm text-slate-500">{{ $quotation->company_name }}</p>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex gap-2"><dt class="text-slate-400"><i class="bi bi-envelope"></i></dt><dd class="text-slate-600">{{ $quotation->customer_email ?: '—' }}</dd></div>
                <div class="flex gap-2"><dt class="text-slate-400"><i class="bi bi-telephone"></i></dt><dd class="text-slate-600">{{ $quotation->customer_phone ?: '—' }}</dd></div>
                <div class="flex gap-2"><dt class="text-slate-400"><i class="bi bi-geo-alt"></i></dt><dd class="text-slate-600">{{ $quotation->customer_address ?: '—' }}</dd></div>
            </dl>
            @if ($quotation->account_id)
                <a href="{{ route('customers.show', $quotation->account_id) }}" class="mt-3 inline-block text-sm text-brand-600 hover:text-brand-700">View customer profile <i class="bi bi-arrow-right"></i></a>
            @endif
        </x-card>

        <x-card title="Information">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-400">Date</dt><dd class="text-slate-700">{{ $quotation->quotation_date?->translatedFormat('d M Y') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Valid until</dt><dd class="text-slate-700">{{ $quotation->valid_until?->translatedFormat('d M Y') ?: '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Template</dt><dd class="text-slate-700">{{ optional($quotation->template)->name ?: 'Default' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Created by</dt><dd class="text-slate-700">{{ optional($quotation->creator)->name }}</dd></div>
            </dl>
        </x-card>

        <x-card title="Change Status">
            <form method="POST" action="{{ route('quotations.status', $quotation) }}" class="flex gap-2">
                @csrf @method('PATCH')
                <select name="status" class="select2 flex-1">
                    @foreach (\App\Models\Quotation::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected($quotation->status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Save</button>
            </form>
        </x-card>
    </div>
</div>
@endsection

@push('styles')
<style>
    .quotation-item-spec ul {
        list-style-type: disc !important;
        list-style-position: outside !important;
        padding-left: 1.25rem !important;
        margin: 0.25rem 0 !important;
    }
    .quotation-item-spec ol {
        list-style-type: decimal !important;
        list-style-position: outside !important;
        padding-left: 1.25rem !important;
        margin: 0.25rem 0 !important;
    }
    .quotation-item-spec li {
        display: list-item !important;
        margin: 0.1rem 0;
    }
</style>
@endpush
