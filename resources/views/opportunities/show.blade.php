@extends('layouts.app')
@section('title', 'Opportunity Detail')

@section('content')
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
    <div>
        <a href="{{ route('opportunities.index') }}" class="crm-back"><i class="bi bi-arrow-left"></i> Back to list</a>
        <h2 class="crm-page-title">{{ $opportunity->name }}</h2>
        <p class="crm-page-desc">{{ optional($opportunity->account)->name ?: $opportunity->company ?: 'Opportunity' }}</p>
    </div>
    <div class="flex shrink-0 flex-wrap items-center gap-2">
        @if ($nextStage)
            <form method="POST" action="{{ route('opportunities.stage', $opportunity) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="stage" value="{{ $nextStage }}">
                <x-btn type="submit" icon="bi-arrow-right-circle">Move to {{ $nextStage }}</x-btn>
            </form>
        @elseif (! empty($closingStages))
            @foreach ($closingStages as $stage)
                <form method="POST" action="{{ route('opportunities.stage', $opportunity) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="stage" value="{{ $stage }}">
                    @if ($stage === \App\Models\Espo\Opportunity::WON_STAGE)
                        <x-btn type="submit" icon="bi-trophy" class="!border-transparent !bg-green-600 !text-white hover:!bg-green-700">Closed Won</x-btn>
                    @else
                        <x-btn type="submit" variant="secondary" icon="bi-x-circle" class="!border-red-200 !text-red-600 hover:!bg-red-50">Closed Lost</x-btn>
                    @endif
                </form>
            @endforeach
        @endif
        <x-btn href="{{ route('opportunities.edit', $opportunity) }}" variant="secondary" icon="bi-pencil">Edit</x-btn>
    </div>
</div>

@if ($duplicates->isNotEmpty())
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <p class="font-medium"><i class="bi bi-exclamation-triangle mr-1"></i> Deal duplikat terdeteksi</p>
        <p class="mt-1 text-amber-700">Ada {{ $duplicates->count() }} deal lain dengan nama, customer, dan nilai yang sama. Ini bisa menyebabkan kartu ganda di pipeline meskipun stage sudah diubah.</p>
        <ul class="mt-2 space-y-1">
            @foreach ($duplicates as $dup)
                <li class="flex flex-wrap items-center justify-between gap-2 rounded-md bg-white/70 px-3 py-2">
                    <span>
                        <x-badge color="blue">{{ $dup->stage }}</x-badge>
                        <span class="ml-1 text-amber-900">
                            {{ $dup->close_date ? \Illuminate\Support\Carbon::parse($dup->close_date)->translatedFormat('d M Y') : '—' }}
                        </span>
                    </span>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('opportunities.show', $dup) }}" class="text-xs font-medium text-brand-600 hover:underline">Lihat</a>
                        @if (! $dup->quotation)
                            <form method="POST" action="{{ route('opportunities.destroy', $dup) }}" onsubmit="return confirm('Hapus deal duplikat ini?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-red-600 hover:underline">Hapus duplikat</button>
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    {{-- Left column --}}
    <div class="space-y-4 lg:col-span-2">
        <x-card>
            <div class="mb-4 flex items-start justify-between">
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-400">Opportunity</p>
                    <h2 class="text-lg font-semibold text-slate-800">{{ $opportunity->name }}</h2>
                </div>
                <x-badge :color="$opportunity->stageColor()">{{ $opportunity->stage }}</x-badge>
            </div>

            <dl class="grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-400">Company</dt><dd class="mt-0.5 font-medium text-slate-700">{{ $opportunity->company ?: '—' }}</dd></div>
                <div><dt class="text-slate-400">Account / Customer</dt><dd class="mt-0.5 font-medium text-slate-700">{{ optional($opportunity->account)->name ?: '—' }}</dd></div>
                <div><dt class="text-slate-400">Type</dt><dd class="mt-0.5 font-medium text-slate-700">{{ $opportunity->type ?: '—' }}</dd></div>
                <div><dt class="text-slate-400">Amount</dt><dd class="mt-0.5 font-semibold text-slate-800">{{ money($opportunity->amount, $opportunity->amount_currency ?: 'IDR') }}</dd></div>
                <div><dt class="text-slate-400">Probability</dt><dd class="mt-0.5 font-medium text-slate-700">{{ $opportunity->probability !== null ? $opportunity->probability . '%' : '—' }}</dd></div>
                <div><dt class="text-slate-400">Close Date</dt><dd class="mt-0.5 font-medium text-slate-700">{{ $opportunity->close_date ? \Illuminate\Support\Carbon::parse($opportunity->close_date)->translatedFormat('d M Y') : '—' }}</dd></div>
                <div><dt class="text-slate-400">Contact</dt><dd class="mt-0.5 font-medium text-slate-700">{{ optional($opportunity->contact)->full_name ?: '—' }}</dd></div>
                <div><dt class="text-slate-400">Lead Source</dt><dd class="mt-0.5 font-medium text-slate-700">{{ $opportunity->lead_source ?: '—' }}</dd></div>
            </dl>

            @if ($opportunity->description)
                <div class="mt-4 border-t border-slate-100 pt-4 text-sm">
                    <dt class="text-slate-400">Description</dt>
                    <dd class="mt-1 whitespace-pre-line text-slate-700">{{ $opportunity->description }}</dd>
                </div>
            @endif
        </x-card>

        {{-- Product list --}}
        @php $products = $opportunity->products; @endphp
        <x-card :padding="false">
            <div class="flex items-center justify-between px-5 pt-4">
                <h3 class="text-sm font-semibold text-slate-800">Product List</h3>
                <span class="text-xs text-slate-400">{{ $products->count() }} item</span>
            </div>
            @if ($products->count())
                <div class="mt-3 overflow-x-auto">
                    <table class="crm-table min-w-[960px]">
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th>Jenis</th>
                                <th>Item</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Jual Excl</th>
                                <th class="text-right">Jual Incl</th>
                                <th class="text-right">Beli Excl</th>
                                <th class="text-right">Beli Incl</th>
                                <th class="text-right">PPH 2%</th>
                                <th class="text-right">Margin</th>
                                <th class="text-right">%</th>
                                <th>Vendor</th>
                                <th class="text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($products as $p)
                                <tr>
                                    <td class="text-slate-600">{{ $p['tax_category'] === 'wapu' ? 'Wapu' : 'Non Wapu' }}</td>
                                    <td class="text-slate-600">{{ ucfirst($p['item_kind']) }}</td>
                                    <td class="text-slate-700">{{ $p['name'] }}</td>
                                    <td class="text-right text-slate-600">{{ rtrim(rtrim(number_format($p['quantity'], 2, ',', '.'), '0'), ',') }}</td>
                                    <td class="text-right text-slate-600">{{ money($p['sell_exclude'], $opportunity->amount_currency ?: 'IDR') }}</td>
                                    <td class="text-right text-slate-600">{{ money($p['sell_include'], $opportunity->amount_currency ?: 'IDR') }}</td>
                                    <td class="text-right text-slate-400">{{ money($p['cost_exclude'], $opportunity->amount_currency ?: 'IDR') }}</td>
                                    <td class="text-right text-slate-400">{{ money($p['cost_include'], $opportunity->amount_currency ?: 'IDR') }}</td>
                                    <td class="text-right text-slate-600">
                                        @if ($p['pph_applicable'] ?? true)
                                            {{ money($p['pph'], $opportunity->amount_currency ?: 'IDR') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-right font-medium text-slate-700">{{ money($p['margin'], $opportunity->amount_currency ?: 'IDR') }}</td>
                                    <td class="text-right text-slate-500">{{ $p['margin_percent'] !== null ? number_format($p['margin_percent'], 2, ',', '.') . '%' : '—' }}</td>
                                    <td class="text-slate-600">{{ $p['vendor'] ?: '—' }}</td>
                                    <td class="text-right font-medium text-slate-700">{{ money($p['subtotal'], $opportunity->amount_currency ?: 'IDR') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-slate-50">
                                <td class="font-semibold text-slate-700" colspan="12">Total</td>
                                <td class="text-right font-bold text-slate-900">{{ money($products->sum('subtotal'), $opportunity->amount_currency ?: 'IDR') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="px-5 py-8 text-center text-sm text-slate-400">No products on this opportunity yet.</div>
            @endif
        </x-card>

        {{-- Quotation file card (1 opportunity : 1 quotation) --}}
        <x-card>
            <x-slot:title>Quotation File</x-slot:title>
            @if ($opportunity->quotation)
                @php $quo = $opportunity->quotation; @endphp
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-brand-100 text-brand-700"><i class="bi bi-file-earmark-text text-xl"></i></span>
                        <div>
                            <p class="font-semibold text-slate-800">{{ $quo->number }}</p>
                            <p class="text-xs text-slate-400">
                                {{ optional($quo->quotation_date)->translatedFormat('d M Y') }} &middot;
                                {{ money($quo->total, $quo->currency) }} &middot;
                                <span class="align-middle"><x-badge :color="$quo->statusColor()">{{ $quo->statusLabel() }}</x-badge></span>
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('quotations.show', $quo) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"><i class="bi bi-eye"></i> View</a>
                        <a href="{{ route('quotations.preview', $quo) }}" target="_blank" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"><i class="bi bi-window"></i> Preview</a>
                        <a href="{{ route('quotations.pdf', $quo) }}" class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700"><i class="bi bi-download"></i> Download PDF</a>
                    </div>
                </div>
            @else
                <div class="flex flex-col items-center justify-center gap-3 py-6 text-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400"><i class="bi bi-file-earmark-plus text-2xl"></i></span>
                    <p class="text-sm text-slate-500">No quotation for this opportunity yet.</p>
                    <a href="{{ route('quotations.create', ['opportunity_id' => $opportunity->id]) }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700"><i class="bi bi-plus-lg"></i> Create Quotation</a>
                </div>
            @endif
        </x-card>
    </div>

    {{-- Right column --}}
    <div class="space-y-4">
        <x-card title="Move Stage" :padding="false" x-data="{ open: false }">
            <x-slot:action>
                <button type="button" @click="open = !open" class="text-xs font-medium text-brand-600 hover:text-brand-700">
                    <span x-show="!open">Change</span>
                    <span x-show="open" x-cloak>Cancel</span>
                </button>
            </x-slot:action>

            <div class="px-5 py-3">
                <p class="text-sm text-slate-600">
                    Current: <span class="font-semibold text-slate-800">{{ $opportunity->stage }}</span>
                    @if ($opportunity->probability !== null)
                        <span class="text-slate-400">({{ $opportunity->probability }}%)</span>
                    @endif
                </p>
            </div>

            <div x-show="open" x-cloak class="border-t border-slate-100">
                @if ($opportunity->stage === 'Negotiation')
                    <p class="px-5 pt-3 text-xs text-slate-500">Pilih hasil akhir deal:</p>
                @endif
                <ul class="crm-stage-move">
                    @foreach (\App\Models\Espo\Opportunity::STAGES as $stage)
                        @php
                            $isCurrent = $opportunity->stage === $stage;
                            $isWon = $stage === \App\Models\Espo\Opportunity::WON_STAGE;
                            $isLost = $stage === \App\Models\Espo\Opportunity::LOST_STAGE;
                            $isClosingOption = $opportunity->stage === 'Negotiation' && ($isWon || $isLost);
                            $isNext = $nextStage === $stage;
                            $canSelect = ! $isCurrent && ($isNext || $isClosingOption || ! in_array($stage, [\App\Models\Espo\Opportunity::WON_STAGE, \App\Models\Espo\Opportunity::LOST_STAGE], true));
                        @endphp
                        <li>
                            @if ($isCurrent)
                                <span @class([
                                    'crm-stage-move__item crm-stage-move__item--current',
                                    'crm-stage-move__item--won' => $isWon,
                                    'crm-stage-move__item--lost' => $isLost,
                                ])>
                                    <i class="bi bi-check-circle-fill"></i> {{ $stage }}
                                </span>
                            @elseif ($canSelect)
                                <form method="POST" action="{{ route('opportunities.stage', $opportunity) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="stage" value="{{ $stage }}">
                                    <button type="submit" @class([
                                        'crm-stage-move__item crm-stage-move__item--action',
                                        'crm-stage-move__item--won' => $isWon,
                                        'crm-stage-move__item--lost' => $isLost,
                                    ])>
                                        {{ $stage }}
                                    </button>
                                </form>
                            @else
                                <span class="crm-stage-move__item text-slate-300">{{ $stage }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </x-card>

        <x-card title="Information">
            <dl class="space-y-3 text-sm">
                <div class="flex gap-3"><dt class="w-28 shrink-0 text-slate-400"><i class="bi bi-person mr-1"></i>Sales</dt><dd class="text-slate-700">{{ optional($opportunity->assignedUser)->display_name ?: '—' }}</dd></div>
                <div class="flex gap-3"><dt class="w-28 shrink-0 text-slate-400"><i class="bi bi-flag mr-1"></i>Stage</dt><dd class="text-slate-700">{{ $opportunity->stage }}</dd></div>
                <div class="flex gap-3"><dt class="w-28 shrink-0 text-slate-400"><i class="bi bi-people mr-1"></i>Teams</dt><dd class="text-slate-700">{{ $opportunity->teams->pluck('name')->implode(', ') ?: '—' }}</dd></div>
                <div class="flex gap-3"><dt class="w-28 shrink-0 text-slate-400"><i class="bi bi-calendar mr-1"></i>Created</dt><dd class="text-slate-700">{{ $opportunity->created_at ? \Illuminate\Support\Carbon::parse($opportunity->created_at)->translatedFormat('d M Y') : '—' }}</dd></div>
            </dl>
            @if (optional($opportunity->account)->id)
                <a href="{{ route('customers.show', $opportunity->account->id) }}" class="mt-4 block w-full rounded-lg border border-slate-300 py-2 text-center text-sm font-medium text-slate-600 hover:bg-slate-50"><i class="bi bi-building"></i> View Customer</a>
            @endif
        </x-card>

        {{-- Documents (legacy EspoCRM + new upload via Spatie Media) --}}
        <x-card :padding="false">
            <div class="flex items-center justify-between px-5 pt-4">
                <h3 class="text-sm font-semibold text-slate-800">Documents</h3>
                <form method="POST" action="{{ route('opportunities.documents.store', $opportunity) }}" enctype="multipart/form-data" class="flex items-center gap-2" id="doc-upload-form">
                    @csrf
                    <input type="file" name="file" id="doc-file-input" class="hidden" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.zip" onchange="this.form.submit()">
                    <button type="button" onclick="document.getElementById('doc-file-input').click()" class="inline-flex items-center gap-1 rounded-lg bg-brand-50 px-3 py-1.5 text-sm font-medium text-brand-600 hover:bg-brand-100" title="Upload document">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </form>
            </div>

            @php
                $legacyDocs = $opportunity->legacyDocuments;
                $hasDocs = $legacyDocs->count() > 0 || $mediaDocuments->count() > 0;
            @endphp

            @if ($hasDocs)
                <ul class="mt-2 divide-y divide-slate-50">
                    @foreach ($legacyDocs as $doc)
                        <li class="flex items-start gap-2 px-5 py-3 hover:bg-slate-50">
                            <i class="bi bi-paperclip mt-0.5 shrink-0 text-slate-400"></i>
                            <div class="min-w-0 flex-1">
                                @if ($url = $doc->legacyDownloadUrl())
                                    <a href="{{ $url }}" target="_blank" rel="noopener" class="block truncate text-sm font-medium text-brand-600 hover:underline" title="{{ $doc->name }}">{{ $doc->name }}</a>
                                @else
                                    <span class="block truncate text-sm text-slate-700" title="{{ $doc->name }}">{{ $doc->name }}</span>
                                @endif
                                <p class="mt-0.5 text-xs text-slate-400">
                                    {{ $doc->created_at ? \Illuminate\Support\Carbon::parse($doc->created_at)->translatedFormat('d M Y H:i') : '—' }}
                                </p>
                            </div>
                        </li>
                    @endforeach

                    @foreach ($mediaDocuments as $media)
                        <li class="flex items-start gap-2 px-5 py-3 hover:bg-slate-50">
                            <i class="bi bi-paperclip mt-0.5 shrink-0 text-slate-400"></i>
                            <div class="min-w-0 flex-1">
                                <a href="{{ $media->getUrl() }}" target="_blank" rel="noopener" class="block truncate text-sm font-medium text-brand-600 hover:underline" title="{{ $media->file_name }}">{{ $media->file_name }}</a>
                                <p class="mt-0.5 text-xs text-slate-400">{{ $media->created_at?->translatedFormat('d M Y H:i') }}</p>
                            </div>
                            <form method="POST" action="{{ route('opportunities.documents.destroy', [$opportunity, $media]) }}" onsubmit="return confirm('Delete this document?')" class="shrink-0">
                                @csrf @method('DELETE')
                                <button class="rounded p-1 text-red-500 hover:bg-red-50" title="Delete"><i class="bi bi-trash text-sm"></i></button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="px-5 py-8 text-center text-sm text-slate-400">
                    No documents yet. Click <i class="bi bi-plus-lg"></i> to upload a new file.
                </div>
            @endif
        </x-card>
    </div>
</div>
@endsection
