@php
    $entertainmentErrors = $errors->hasAny([
        'entertainment_name',
        'entertainment_description',
        'entertainment_amount',
        'entertainment_photo',
    ]);
    $currency = $opportunity->amount_currency ?: 'IDR';
    $canCompleteEntertainment = (bool) auth()->user()?->canCompleteEntertainment();
@endphp

<x-card id="entertainments" :padding="false" x-data="{ adding: {{ $entertainmentErrors ? 'true' : 'false' }} }"
        x-init="$watch('adding', value => { if (value && window.CrmNumber) window.CrmNumber.enhance($el) })">
    <div class="flex items-center justify-between px-5 pt-4">
        <h3 class="text-sm font-semibold text-slate-800">Entertainment</h3>
        <div class="flex items-center gap-1">
            @if (auth()->user()?->canViewEntertainmentReport() && $opportunity->entertainments->count())
                <a href="{{ route('opportunities.entertainments.report', $opportunity) }}"
                   class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100"
                   title="Laporan entertainment">
                    <i class="bi bi-file-earmark-bar-graph"></i> Detail
                </a>
            @endif
            <button type="button" @click="adding = !adding" class="inline-flex items-center gap-1 rounded-lg bg-brand-50 px-3 py-1.5 text-sm font-medium text-brand-600 hover:bg-brand-100" title="Add entertainment">
                <i class="bi bi-plus-lg"></i>
            </button>
        </div>
    </div>

    <div x-show="adding" x-cloak class="border-b border-slate-100 px-5 py-3">
        <form method="POST" action="{{ route('opportunities.entertainments.store', $opportunity) }}" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <div>
                <label class="crm-label">Nama <span class="text-red-500">*</span></label>
                <input type="text" name="entertainment_name" value="{{ old('entertainment_name') }}" required maxlength="255"
                       placeholder="Mis. Makan klien, parkir, struk hotel"
                       class="crm-field @error('entertainment_name') border-red-300 @enderror">
                @error('entertainment_name')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="crm-label">Deskripsi</label>
                <textarea name="entertainment_description" rows="2" maxlength="2000" placeholder="Opsional"
                          class="crm-field @error('entertainment_description') border-red-300 @enderror">{{ old('entertainment_description') }}</textarea>
                @error('entertainment_description')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="crm-label">Nominal <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm font-medium text-slate-500">Rp</span>
                        <input type="text" inputmode="numeric" name="entertainment_amount" required data-crm-number data-decimals="0"
                               value="{{ old('entertainment_amount') }}"
                               placeholder="0"
                               class="crm-field pl-10 text-right tabular-nums @error('entertainment_amount') border-red-300 @enderror">
                    </div>
                    @error('entertainment_amount')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="crm-label">Foto / struk</label>
                    <input type="file" name="entertainment_photo" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf"
                           class="block w-full text-xs text-slate-600 file:mr-2 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-brand-600 hover:file:bg-brand-100">
                    <p class="mt-1 text-[11px] text-slate-400">Opsional. JPG, PNG, WebP, atau PDF. Maks. 10 MB.</p>
                    @error('entertainment_photo')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            <div class="flex items-center gap-2">
                <x-btn type="submit" variant="primary" icon="bi-plus-lg">Simpan</x-btn>
                <button type="button" @click="adding = false" class="text-sm text-slate-500 hover:text-slate-700">Cancel</button>
            </div>
        </form>
    </div>

    @if ($opportunity->entertainments->count())
        <ul class="mt-2 divide-y divide-slate-50">
            @foreach ($opportunity->entertainments as $item)
                @php $photoUrl = $item->photoUrl(); @endphp
                <li class="flex items-start gap-2 px-5 py-3 hover:bg-slate-50">
                    @if ($photoUrl && $item->photoIsImage())
                        <a href="{{ $photoUrl }}" target="_blank" rel="noopener" class="mt-0.5 block h-10 w-10 shrink-0 overflow-hidden rounded border border-slate-200 bg-slate-50">
                            <img src="{{ $photoUrl }}" alt="" class="h-full w-full object-cover">
                        </a>
                    @else
                        <i class="bi {{ $photoUrl ? 'bi-paperclip' : 'bi-receipt' }} mt-0.5 shrink-0 text-slate-400"></i>
                    @endif
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate text-sm font-medium text-slate-800">{{ $item->name }}</p>
                            <x-badge :color="$item->statusColor()">{{ $item->statusLabel() }}</x-badge>
                        </div>
                        <p class="mt-0.5 text-sm font-semibold text-slate-700">{{ money($item->amount, $currency) }}</p>
                        @if ($item->description)
                            <p class="mt-1 whitespace-pre-line text-xs text-slate-500">{{ $item->description }}</p>
                        @endif
                        @if ($photoUrl && ! $item->photoIsImage())
                            <a href="{{ $photoUrl }}" target="_blank" rel="noopener" class="mt-1 inline-block text-xs font-medium text-brand-600 hover:underline">Lihat file</a>
                        @endif
                        <p class="mt-1 text-xs text-slate-400">
                            {{ optional($item->creator)->display_name ?: '—' }}
                            &middot;
                            {{ $item->created_at?->translatedFormat('d M Y H:i') }}
                            @if ($item->isComplete())
                                &middot; complete oleh {{ optional($item->completedByUser)->display_name ?: '—' }}
                                {{ $item->completed_at?->translatedFormat('d M Y H:i') }}
                            @endif
                        </p>
                    </div>
                    <div class="flex shrink-0 items-start gap-1">
                        @if ($item->isPending() && $canCompleteEntertainment)
                            <form method="POST" action="{{ route('opportunities.entertainments.complete', [$opportunity, $item]) }}"
                                  onsubmit="return confirm('Tandai entertainment ini complete?')">
                                @csrf
                                <button type="submit" class="rounded p-1 text-green-600 hover:bg-green-50" title="Tandai complete">
                                    <i class="bi bi-check-circle text-sm"></i>
                                </button>
                            </form>
                        @endif
                        @if (auth()->user()->isAdmin() || auth()->user()->isFinance() || $item->created_by === auth()->id())
                            <form method="POST" action="{{ route('opportunities.entertainments.destroy', [$opportunity, $item]) }}"
                                  onsubmit="return confirm('Hapus entertainment ini?')" class="shrink-0">
                                @csrf @method('DELETE')
                                <button type="submit" class="rounded p-1 text-red-500 hover:bg-red-50" title="Hapus">
                                    <i class="bi bi-trash text-sm"></i>
                                </button>
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <div class="px-5 py-8 text-center text-sm text-slate-400">
            No entertainment yet. Click <i class="bi bi-plus-lg"></i> to add a receipt.
        </div>
    @endif
</x-card>
