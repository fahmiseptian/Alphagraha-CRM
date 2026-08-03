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
        @if (auth()->user()->canEditOpportunityFully())
            @if ($nextStage)
                <form method="POST" action="{{ route('opportunities.stage', $opportunity) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="stage" value="{{ $nextStage }}">
                    <x-btn type="submit" icon="bi-arrow-right-circle">Move to {{ $nextStage }}</x-btn>
                </form>
            @elseif (! empty($closingStages))
                @foreach ($closingStages as $stage)
                    @if ($stage === \App\Models\Espo\Opportunity::WON_STAGE)
                        <form method="POST" action="{{ route('opportunities.stage', $opportunity) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="stage" value="{{ $stage }}">
                            <x-btn type="submit" icon="bi-trophy" class="!border-transparent !bg-green-600 !text-white hover:!bg-green-700">Closed Won</x-btn>
                        </form>
                    @else
                        <x-opportunity-closed-lost-form :opportunity="$opportunity" :open-on-error="true">
                            <x-slot:trigger>
                                <x-btn type="button" variant="secondary" icon="bi-x-circle" class="!border-red-200 !text-red-600 hover:!bg-red-50">Closed Lost</x-btn>
                            </x-slot:trigger>
                        </x-opportunity-closed-lost-form>
                    @endif
                @endforeach
            @endif
        @endif
        @if (auth()->user()->canEditOpportunityFully() || (auth()->user()->isPurchasing() && $opportunity->stage === \App\Models\Espo\Opportunity::WON_STAGE))
            <x-btn href="{{ route('opportunities.edit', $opportunity) }}" variant="secondary" icon="bi-pencil">Edit</x-btn>
        @endif
        @if (auth()->user()->canDeleteOpportunity())
            <form method="POST" action="{{ route('opportunities.destroy', $opportunity) }}"
                  onsubmit="return confirm('Hapus opportunity ini? Tindakan tidak bisa dibatalkan.')">
                @csrf @method('DELETE')
                <x-btn type="submit" variant="secondary" icon="bi-trash" class="!border-red-200 !text-red-600 hover:!bg-red-50">Hapus</x-btn>
            </form>
        @endif
    </div>
</div>

@if ($opportunity->stage === \App\Models\Espo\Opportunity::LOST_STAGE && $opportunity->crm_lost_reason)
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
        <p class="font-semibold text-red-800">
            <i class="bi bi-journal-text mr-1"></i> Catatan kekalahan
        </p>
        <p class="mt-1 whitespace-pre-line">{{ $opportunity->crm_lost_reason }}</p>
    </div>
@endif

@if ($opportunity->hasActiveDiscount())
    @php
        $discStatus = $opportunity->crm_discount_status;
        $discPct = $opportunity->discountPercent();
        $isPending = $opportunity->discountNeedsAttention();
        $isRejected = ! $opportunity->skipsApproval()
            && $discStatus === \App\Models\Espo\Opportunity::DISCOUNT_REJECTED;
        $isApproved = ! $opportunity->skipsApproval()
            && $discStatus === \App\Models\Espo\Opportunity::DISCOUNT_APPROVED;
        $currency = $opportunity->amount_currency ?: 'IDR';
        $approvalProducts = $opportunity->products;
        $marginTotal = $opportunity->totalProductsMargin();
        $discountBasisMargin = $opportunity->totalDiscountBasisMargin();
        $discountBasisIsGross = $approvalProducts->contains(
            fn ($p) => ($p['tax_category'] ?? '') === \App\Support\OpportunityProductPricing::TAX_INAPROC
        );
        $discountAmount = (float) $opportunity->crm_discount_amount;
    @endphp
    <div @class([
        'mb-4 rounded-lg border px-4 py-3 text-sm',
        'border-amber-200 bg-amber-50 text-amber-900' => $isPending,
        'border-red-200 bg-red-50 text-red-800' => $isRejected,
        'border-green-200 bg-green-50 text-green-800' => $isApproved,
        'border-slate-200 bg-slate-50 text-slate-700' => ! $isPending && ! $isRejected && ! $isApproved,
    ])>
        <div class="flex flex-col gap-3">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="font-semibold">
                        @if ($isPending)
                            <i class="bi bi-hourglass-split mr-1"></i> Diskon menunggu approval Superadmin
                        @elseif ($isRejected)
                            <i class="bi bi-x-circle mr-1"></i> Diskon ditolak
                            @if ($discountAmount > 0)
                                <span class="font-normal">— nominal disetujui {{ money($discountAmount, $currency) }}</span>
                            @endif
                        @elseif ($isApproved)
                            <i class="bi bi-check-circle mr-1"></i> Diskon disetujui
                        @else
                            Diskon tambahan
                        @endif
                    </p>
                    @if ($opportunity->crm_discount_note)
                        <p class="mt-1 text-xs opacity-80">Catatan: {{ $opportunity->crm_discount_note }}</p>
                    @endif
                    @if ($opportunity->discountRequesterName() || $opportunity->crm_discount_requested_at)
                        <p class="mt-1 text-xs opacity-80">
                            Diajukan
                            @if ($opportunity->discountRequesterName())
                                oleh <strong>{{ $opportunity->discountRequesterName() }}</strong>
                            @endif
                            @if ($opportunity->crm_discount_requested_at)
                                · {{ $opportunity->crm_discount_requested_at->timezone(config('app.timezone'))->format('d M Y H:i') }}
                            @endif
                        </p>
                    @endif
                    @if (($isApproved || $isRejected) && ($opportunity->discountReviewerName() || $opportunity->crm_discount_reviewed_at))
                        <p class="mt-1 text-xs opacity-80">
                            {{ $isApproved ? 'Disetujui' : 'Ditolak' }}
                            @if ($opportunity->discountReviewerName())
                                oleh <strong>{{ $opportunity->discountReviewerName() }}</strong>
                            @endif
                            @if ($opportunity->crm_discount_reviewed_at)
                                · {{ $opportunity->crm_discount_reviewed_at->timezone(config('app.timezone'))->format('d M Y H:i') }}
                            @endif
                        </p>
                    @endif
                </div>

                @if ($isPending && auth()->user()->canApproveDiscount())
                    <div class="flex w-full max-w-md shrink-0 flex-col gap-3"
                         x-data="{
                             adjustMode: false,
                             editingField: null,
                             marginTotal: {{ json_encode($discountBasisMargin) }},
                             discountPercent: {{ json_encode($discPct ?? 0) }},
                             discountAmount: {{ json_encode((float) old('discount_amount', $discountAmount)) }},
                             round(n) { return Math.round(n * 100) / 100; },
                             formatId(value, decimals = 0) {
                                 if (window.CrmNumber) return window.CrmNumber.format(value, decimals);
                                 return (Number(value) || 0).toLocaleString('id-ID', { maximumFractionDigits: decimals });
                             },
                             parseId(str) {
                                 if (window.CrmNumber) return window.CrmNumber.parse(str);
                                 return Number(String(str).replace(/\./g, '').replace(',', '.')) || 0;
                             },
                             onDiscountPercentChange() {
                                 const pct = Number(this.discountPercent) || 0;
                                 if (this.marginTotal > 0) {
                                     this.discountAmount = this.round(this.marginTotal * pct / 100);
                                 }
                             },
                             onDiscountAmountChange() {
                                 const disc = Number(this.discountAmount) || 0;
                                 if (this.marginTotal > 0 && disc >= 0) {
                                     this.discountPercent = disc > 0
                                         ? this.round((disc / this.marginTotal) * 100)
                                         : 0;
                                 }
                             },
                         }">
                        <div x-show="!adjustMode" x-cloak class="space-y-2">
                            <form method="POST" action="{{ route('opportunities.discount.approve', $opportunity) }}" class="space-y-2">
                                @csrf
                                <input type="text" name="note" placeholder="Catatan approve (opsional)"
                                       class="w-full rounded-lg border border-green-200 bg-white px-2 py-1.5 text-xs text-slate-700">
                                <div class="flex flex-wrap gap-2">
                                    <x-btn type="submit" icon="bi-check-lg" class="!border-transparent !bg-green-600 !text-white hover:!bg-green-700">Approve</x-btn>
                                    <button type="button" @click="adjustMode = true"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-white px-3 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-50">
                                        <i class="bi bi-sliders"></i> Sesuaikan
                                    </button>
                                </div>
                            </form>
                        </div>
                        <div x-show="adjustMode" x-cloak class="rounded-lg border border-green-200 bg-white p-3">
                            <p class="mb-2 text-xs font-semibold text-green-800">Sesuaikan diskon — isi nominal atau % margin</p>
                            <form method="POST" action="{{ route('opportunities.discount.approve', $opportunity) }}" class="space-y-2">
                                @csrf
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-0.5 block text-[11px] text-slate-500">
                                            % dari {{ $discountBasisIsGross ? 'margin kotor' : 'margin' }}
                                        </label>
                                        <div class="flex items-center gap-1.5">
                                            <input type="text" inputmode="decimal" placeholder="0"
                                                   x-effect="if (editingField !== 'pct') $el.value = formatId(discountPercent, 2)"
                                                   @focus="editingField = 'pct'"
                                                   @blur="editingField = null; $el.value = formatId(discountPercent, 2)"
                                                   @input="discountPercent = parseId($event.target.value); onDiscountPercentChange()"
                                                   class="w-full rounded-lg border border-green-200 bg-white px-2 py-1.5 text-sm tabular-nums text-slate-700">
                                            <span class="shrink-0 text-xs font-semibold text-slate-500">%</span>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="mb-0.5 block text-[11px] text-slate-500">Nominal diskon <span class="text-red-500">*</span></label>
                                        <input type="text" inputmode="decimal" required
                                               x-effect="if (editingField !== 'amt') $el.value = formatId(discountAmount)"
                                               @focus="editingField = 'amt'"
                                               @blur="editingField = null; $el.value = formatId(discountAmount)"
                                               @input="discountAmount = parseId($event.target.value); onDiscountAmountChange()"
                                               class="w-full rounded-lg border border-green-200 bg-white px-2 py-1.5 text-sm tabular-nums text-slate-700">
                                        <input type="hidden" name="discount_amount" :value="discountAmount">
                                    </div>
                                </div>
                                @unless ($discountBasisMargin > 0)
                                    <p class="text-[11px] text-amber-600">Margin belum tersedia; isi nominal langsung.</p>
                                @endunless
                                <p class="text-[11px] text-slate-400">Isi 0 lalu submit untuk menolak diskon sepenuhnya.</p>
                                <div>
                                    <label class="mb-0.5 block text-[11px] text-slate-500">Catatan ke sales</label>
                                    <input type="text" name="note" placeholder="Mis. diskon diturunkan sesuai kebijakan"
                                           value="{{ old('note') }}"
                                           class="w-full rounded-lg border border-green-200 bg-white px-2 py-1.5 text-xs text-slate-700">
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button type="submit"
                                            class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-semibold transition"
                                            :class="discountAmount > 0
                                                ? 'border-transparent bg-green-600 text-white hover:bg-green-700'
                                                : 'border-transparent bg-red-600 text-white hover:bg-red-700'">
                                        <i class="bi" :class="discountAmount > 0 ? 'bi-check-lg' : 'bi-x-lg'"></i>
                                        <span x-text="discountAmount > 0 ? 'Setujui' : 'Tolak sepenuhnya'"></span>
                                    </button>
                                    <button type="button" @click="adjustMode = false"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50">
                                        <i class="bi bi-arrow-left"></i> Kembali
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @elseif (($isApproved || $isRejected) && auth()->user()->canApproveDiscount() && $opportunity->hasActiveDiscount())
                    <form method="POST" action="{{ route('opportunities.discount.revert', $opportunity) }}"
                          onsubmit="return confirm('Kembalikan diskon ke status menunggu approval?')"
                          class="flex w-full max-w-md shrink-0 flex-col gap-2">
                        @csrf
                        <input type="text" name="note" placeholder="Catatan (opsional)"
                               class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-700">
                        <x-btn type="submit" variant="secondary" icon="bi-arrow-counterclockwise" class="w-full justify-center">
                            Kembalikan ke Pending
                        </x-btn>
                    </form>
                @endif
            </div>

            {{-- Ringkasan detail untuk Superadmin --}}
            @if (auth()->user()->canApproveDiscount() && $approvalProducts->isNotEmpty())
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg border border-white/70 bg-white/90 px-3 py-2">
                        <p class="text-[10px] uppercase tracking-wide text-slate-400">% Diskon</p>
                        <p class="text-sm font-semibold text-slate-800">
                            {{ $discPct !== null ? number_format($discPct, 2, ',', '.').'%' : '—' }}
                        </p>
                        <p class="text-[10px] text-slate-400">
                            {{ $discountBasisIsGross ? 'dari margin kotor' : 'dari total margin' }}
                        </p>
                    </div>
                    <div class="rounded-lg border border-white/70 bg-white/90 px-3 py-2">
                        <p class="text-[10px] uppercase tracking-wide text-slate-400">Nominal Diskon</p>
                        <p class="text-sm font-semibold text-slate-800">{{ money($discountAmount, $currency) }}</p>
                    </div>
                    <div class="rounded-lg border border-white/70 bg-white/90 px-3 py-2">
                        <p class="text-[10px] uppercase tracking-wide text-slate-400">
                            {{ $discountBasisIsGross ? 'Margin Kotor' : 'Total Margin' }}
                        </p>
                        <p class="text-sm font-semibold text-slate-800">{{ money($discountBasisMargin, $currency) }}</p>
                    </div>
                    <div class="rounded-lg border border-white/70 bg-white/90 px-3 py-2">
                        <p class="text-[10px] uppercase tracking-wide text-slate-400">Kategori deal</p>
                        <p class="text-sm font-semibold text-slate-800">
                            {{ \App\Support\OpportunityProductPricing::taxCategoryLabel((string) ($approvalProducts->first()['tax_category'] ?? 'non_wapu')) }}
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-lg border border-white/60 bg-white/80">
                    <table class="min-w-[960px] w-full text-left text-xs">
                        <thead class="bg-slate-100/80 text-[11px] uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2 font-semibold">Item</th>
                                <th class="px-3 py-2 font-semibold">Wapu / Non Wapu</th>
                                <th class="px-3 py-2 text-right font-semibold">Modal Excl</th>
                                <th class="px-3 py-2 text-right font-semibold">Modal Incl</th>
                                <th class="px-3 py-2 text-right font-semibold">Jual Excl</th>
                                <th class="px-3 py-2 text-right font-semibold">% Margin</th>
                                <th class="px-3 py-2 text-right font-semibold">Margin</th>
                                <th class="px-3 py-2 text-right font-semibold">% Diskon</th>
                                <th class="px-3 py-2 text-right font-semibold">Diskon</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ($approvalProducts as $p)
                                @php
                                    $qty = (float) ($p['quantity'] ?? 1);
                                    $taxCat = (string) ($p['tax_category'] ?? \App\Support\OpportunityProductPricing::TAX_NON_WAPU);
                                    $lineBasisMargin = $taxCat === \App\Support\OpportunityProductPricing::TAX_INAPROC
                                        ? round($qty * (float) ($p['gross_margin'] ?? $p['margin'] ?? 0), 2)
                                        : round($qty * (float) ($p['margin'] ?? 0), 2);
                                    $lineCostExcl = round($qty * (float) ($p['cost_exclude'] ?? 0), 2);
                                    $lineCostIncl = round($qty * (float) ($p['cost_include'] ?? 0), 2);
                                    $lineSellExcl = round($qty * (float) ($p['effective_sell_exclude'] ?? $p['sell_exclude'] ?? 0), 2);
                                    $lineDiscShare = ($discountBasisMargin > 0 && $lineBasisMargin > 0)
                                        ? round($discountAmount * ($lineBasisMargin / $discountBasisMargin), 2)
                                        : 0.0;
                                    $lineDiscPct = $lineBasisMargin > 0
                                        ? round(($lineDiscShare / $lineBasisMargin) * 100, 2)
                                        : null;
                                @endphp
                                <tr>
                                    <td class="px-3 py-2">
                                        <span class="font-medium text-slate-800">{{ $p['name'] }}</span>
                                        <span class="block text-[10px] text-slate-400">
                                            {{ ucfirst($p['item_kind'] ?? '') }} · Qty {{ rtrim(rtrim(number_format($qty, 2, ',', '.'), '0'), ',') }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ $p['tax_category_label'] ?? \App\Support\OpportunityProductPricing::taxCategoryLabel($taxCat) }}
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ money($lineCostExcl, $currency) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ money($lineCostIncl, $currency) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ money($lineSellExcl, $currency) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        {{ $p['margin_percent'] !== null ? number_format((float) $p['margin_percent'], 2, ',', '.').'%' : '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums font-medium">{{ money($lineBasisMargin, $currency) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        {{ $lineDiscPct !== null ? number_format($lineDiscPct, 2, ',', '.').'%' : '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ money($lineDiscShare, $currency) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-slate-200 bg-slate-50 font-semibold text-slate-800">
                            <tr>
                                <td class="px-3 py-2" colspan="5">Total</td>
                                <td class="px-3 py-2 text-right tabular-nums text-slate-400">—</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money($discountBasisMargin, $currency) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ $discPct !== null ? number_format($discPct, 2, ',', '.').'%' : '—' }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money($discountAmount, $currency) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @elseif (! auth()->user()->canApproveDiscount())
                <p class="text-xs">
                    Nominal: <strong>{{ money($discountAmount, $currency) }}</strong>
                    @if ($discPct !== null)
                        &nbsp;·&nbsp; Persentase: <strong>{{ number_format($discPct, 2, ',', '.') }}%</strong>
                        {{ $discountBasisIsGross ? 'dari margin kotor' : 'dari margin' }}
                    @endif
                    @if ($discountBasisMargin > 0)
                        &nbsp;·&nbsp; Basis: <strong>{{ money($discountBasisMargin, $currency) }}</strong>
                    @endif
                </p>
            @endif
        </div>
    </div>
@endif

@if ($opportunity->crm_margin_status && ! $opportunity->skipsApproval())
    @php
        $marginStatus = $opportunity->crm_margin_status;
        $isMarginPending = $opportunity->marginNeedsApproval();
        $isMarginRejected = $marginStatus === \App\Models\Espo\Opportunity::MARGIN_REJECTED;
        $isMarginApproved = $marginStatus === \App\Models\Espo\Opportunity::MARGIN_APPROVED;
        $marginCurrency = $opportunity->amount_currency ?: 'IDR';
    @endphp
    <div @class([
        'mb-4 rounded-lg border px-4 py-3 text-sm',
        'border-amber-200 bg-amber-50 text-amber-900' => $isMarginPending,
        'border-red-200 bg-red-50 text-red-800' => $isMarginRejected,
        'border-green-200 bg-green-50 text-green-800' => $isMarginApproved,
        'border-slate-200 bg-slate-50 text-slate-700' => ! $isMarginPending && ! $isMarginRejected && ! $isMarginApproved,
    ])>
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="font-semibold">
                    @if ($isMarginPending)
                        <i class="bi bi-hourglass-split mr-1"></i> Margin menunggu approval Superadmin
                    @elseif ($isMarginRejected)
                        <i class="bi bi-x-circle mr-1"></i> Margin ditolak
                    @elseif ($isMarginApproved)
                        <i class="bi bi-check-circle mr-1"></i> Margin di bawah minimal — disetujui
                    @else
                        {{ $opportunity->marginStatusLabel() }}
                    @endif
                </p>
                <p class="mt-1 text-xs opacity-90">
                    Margin:
                    <strong>{{ $opportunity->crm_margin_percent !== null ? number_format((float) $opportunity->crm_margin_percent, 2, ',', '.').'%' : '—' }}</strong>
                    / <strong>{{ money($opportunity->crm_margin_nominal, $marginCurrency) }}</strong>
                    &middot; Minimal:
                    <strong>{{ $opportunity->crm_margin_threshold !== null ? number_format((float) $opportunity->crm_margin_threshold, 2, ',', '.').'%' : '—' }}</strong>
                    / <strong>{{ money($opportunity->crm_margin_nominal_threshold, $marginCurrency) }}</strong>
                </p>
                @if ($opportunity->crm_has_shipping_charge)
                    <p class="mt-1 text-xs opacity-80">
                        Ongkir jual: {{ money($opportunity->crm_shipping_sell, $marginCurrency) }}
                        (checkbox ongkir aktif — Nominal Ongkir Pribadi tidak dipakai)
                    </p>
                @elseif ($opportunity->isCustomerFreeShipping())
                    <p class="mt-1 text-xs opacity-80">Kota customer free ongkir — threshold: Nominal Umum saja.</p>
                @else
                    <p class="mt-1 text-xs opacity-80">Kota customer bukan free ongkir — threshold: Nominal Umum + Ongkir Pribadi.</p>
                @endif
                @if ($opportunity->crm_margin_note)
                    <p class="mt-1 text-xs opacity-80">Catatan: {{ $opportunity->crm_margin_note }}</p>
                @endif
                @if (($isMarginApproved || $isMarginRejected) && ($opportunity->marginReviewerName() || $opportunity->crm_margin_reviewed_at))
                    <p class="mt-1 text-xs opacity-80">
                        {{ $isMarginApproved ? 'Disetujui' : 'Ditolak' }}
                        @if ($opportunity->marginReviewerName())
                            oleh <strong>{{ $opportunity->marginReviewerName() }}</strong>
                        @endif
                        @if ($opportunity->crm_margin_reviewed_at)
                            · {{ $opportunity->crm_margin_reviewed_at->timezone(config('app.timezone'))->format('d M Y H:i') }}
                        @endif
                    </p>
                @endif
                @if ($isMarginPending || $isMarginRejected)
                    <p class="mt-2 text-xs font-medium opacity-90">
                        <i class="bi bi-lock mr-1"></i>
                        Quotation tidak dapat dibuat selama margin {{ $isMarginPending ? 'menunggu approval' : 'ditolak' }}.
                        @if ($isMarginPending && $opportunity->stage === 'Negotiation')
                            Closed Won juga terkunci sampai approval selesai.
                        @endif
                    </p>
                @endif
            </div>
            @if ($isMarginPending && auth()->user()->canApproveMargin())
                <div class="flex w-full max-w-md shrink-0 flex-col gap-2">
                    <form method="POST" action="{{ route('opportunities.margin.approve', $opportunity) }}" class="space-y-2">
                        @csrf
                        <input type="text" name="note" placeholder="Catatan approve (opsional)"
                               class="w-full rounded-lg border border-green-200 bg-white px-2 py-1.5 text-xs text-slate-700">
                        <x-btn type="submit" icon="bi-check-lg" class="!border-transparent !bg-green-600 !text-white hover:!bg-green-700">Approve Margin</x-btn>
                    </form>
                    <form method="POST" action="{{ route('opportunities.margin.reject', $opportunity) }}" class="space-y-2">
                        @csrf
                        <input type="text" name="note" placeholder="Catatan reject (opsional)"
                               class="w-full rounded-lg border border-red-200 bg-white px-2 py-1.5 text-xs text-slate-700">
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50">
                            <i class="bi bi-x-lg"></i> Reject
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endif

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
                        @if (! $dup->quotation && auth()->user()->canDeleteOpportunity())
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
                @if ($opportunity->crm_has_shipping_charge)
                    <div>
                        <dt class="text-slate-400">Ongkir jual</dt>
                        <dd class="mt-0.5 font-medium text-slate-700">{{ money($opportunity->crm_shipping_sell, $opportunity->amount_currency ?: 'IDR') }}</dd>
                    </div>
                @endif
                @if ($opportunity->hasActiveDiscount())
                    <div>
                        <dt class="text-slate-400">Diskon tambahan</dt>
                        <dd class="mt-0.5 font-medium text-slate-700">
                            {{ money($opportunity->crm_discount_amount, $opportunity->amount_currency ?: 'IDR') }}
                            @if ($opportunity->discountPercent() !== null)
                                <span class="text-slate-500">({{ number_format($opportunity->discountPercent(), 2, ',', '.') }}%
                                    {{ $opportunity->products->contains(fn ($p) => ($p['tax_category'] ?? '') === \App\Support\OpportunityProductPricing::TAX_INAPROC) ? 'dari margin kotor' : 'dari margin' }})
                                </span>
                            @endif
                            <x-badge class="ml-1" :color="match($opportunity->crm_discount_status) {
                                'approved' => 'green',
                                'rejected' => 'red',
                                'pending' => 'amber',
                                default => 'slate',
                            }">{{ $opportunity->discountStatusLabel() }}</x-badge>
                        </dd>
                    </div>
                @endif
                @if ($opportunity->stage === \App\Models\Espo\Opportunity::WON_STAGE && $opportunity->crm_won_margin !== null)
                    <div><dt class="text-slate-400">Won Margin</dt><dd class="mt-0.5 font-semibold text-green-700">{{ money($opportunity->crm_won_margin, $opportunity->amount_currency ?: 'IDR') }}</dd></div>
                @endif
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
                    <table class="crm-table min-w-[1080px]">
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th>Jenis</th>
                                <th>Item</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Jual Excl</th>
                                <th class="text-right">Diskon Item</th>
                                <th class="text-right">Jual Incl</th>
                                <th class="text-right">Beli Excl</th>
                                <th class="text-right">Beli Incl</th>
                                <th class="text-right">Potongan</th>
                                <th class="text-right">Margin</th>
                                <th class="text-right">%</th>
                                <th>Vendor</th>
                                <th class="text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($products as $p)
                                <tr>
                                    <td class="text-slate-600">{{ $p['tax_category_label'] ?? \App\Support\OpportunityProductPricing::taxCategoryLabel((string) ($p['tax_category'] ?? 'non_wapu')) }}</td>
                                    <td class="text-slate-600">{{ ucfirst($p['item_kind']) }}</td>
                                    <td class="text-slate-700">{{ $p['name'] }}</td>
                                    <td class="text-right text-slate-600">{{ rtrim(rtrim(number_format($p['quantity'], 2, ',', '.'), '0'), ',') }}</td>
                                    <td class="text-right text-slate-600">{{ money($p['sell_exclude'], $opportunity->amount_currency ?: 'IDR') }}</td>
                                    <td class="text-right text-slate-600">
                                        @if (($p['discount_exclude'] ?? 0) > 0)
                                            {{ money($p['discount_exclude'], $opportunity->amount_currency ?: 'IDR') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-right text-slate-600">{{ money($p['effective_sell_include'] ?? $p['sell_include'], $opportunity->amount_currency ?: 'IDR') }}</td>
                                    <td class="text-right text-slate-400">{{ money($p['cost_exclude'], $opportunity->amount_currency ?: 'IDR') }}</td>
                                    <td class="text-right text-slate-400">{{ money($p['cost_include'], $opportunity->amount_currency ?: 'IDR') }}</td>
                                    <td class="text-right text-slate-600">
                                        @if (($p['pph_applicable'] ?? false) || ($p['pnbp_applicable'] ?? false) || ($p['pph29_applicable'] ?? false))
                                            @if ($p['pph_applicable'] ?? false)
                                                <span class="block">{{ money($p['pph'], $opportunity->amount_currency ?: 'IDR') }}</span>
                                                @if (($p['pph_percent'] ?? 0) > 0)
                                                    <span class="text-[10px] text-slate-400">PPH {{ rtrim(rtrim(number_format((float) $p['pph_percent'], 2, ',', '.'), '0'), ',') }}%</span>
                                                @endif
                                            @endif
                                            @if ($p['pnbp_applicable'] ?? false)
                                                <span class="mt-0.5 block text-[10px] text-slate-500">PNBP {{ money($p['pnbp'] ?? 0, $opportunity->amount_currency ?: 'IDR') }}</span>
                                            @endif
                                            @if ($p['pph29_applicable'] ?? false)
                                                <span class="mt-0.5 block text-[10px] text-slate-500">PPH29 {{ money($p['pph29'] ?? 0, $opportunity->amount_currency ?: 'IDR') }}</span>
                                            @endif
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
                                <td class="font-semibold text-slate-700" colspan="13">Total</td>
                                <td class="text-right font-bold text-slate-900">{{ money($products->sum('subtotal'), $opportunity->amount_currency ?: 'IDR') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="px-5 py-8 text-center text-sm text-slate-400">No products on this opportunity yet.</div>
            @endif
        </x-card>

        @include('opportunities._purchase_orders')
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
                    @if (! $opportunity->canMoveToClosedWon())
                        <p class="px-5 pt-2 text-xs text-amber-700">
                            <i class="bi bi-lock"></i> Closed Won terkunci sampai approval margin/diskon selesai.
                        </p>
                    @endif
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
                            if ($isWon && $isClosingOption && ! $opportunity->canMoveToClosedWon()) {
                                $canSelect = false;
                            }
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
                                @if ($isLost && $isClosingOption)
                                    <x-opportunity-closed-lost-form :opportunity="$opportunity" :open-on-error="true">
                                        <x-slot:trigger>
                                            <button type="button" class="crm-stage-move__item crm-stage-move__item--action crm-stage-move__item--lost w-full text-left">
                                                {{ $stage }}
                                            </button>
                                        </x-slot:trigger>
                                    </x-opportunity-closed-lost-form>
                                @else
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
                                @endif
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

        {{-- Notes (append-only, seperti dokumen) --}}
        <x-card :padding="false" x-data="{ adding: false }">
            <div class="flex items-center justify-between px-5 pt-4">
                <h3 class="text-sm font-semibold text-slate-800">Notes</h3>
                <button type="button" @click="adding = !adding" class="inline-flex items-center gap-1 rounded-lg bg-brand-50 px-3 py-1.5 text-sm font-medium text-brand-600 hover:bg-brand-100" title="Add note">
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>

            <div x-show="adding" x-cloak class="border-b border-slate-100 px-5 py-3">
                <form method="POST" action="{{ route('opportunities.notes.store', $opportunity) }}" class="space-y-3">
                    @csrf
                    <textarea name="body" rows="3" required placeholder="Tulis catatan..."
                              class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">{{ old('body') }}</textarea>
                    <div class="flex items-center gap-2">
                        <x-btn type="submit" variant="primary" icon="bi-plus-lg">Add Note</x-btn>
                        <button type="button" @click="adding = false" class="text-sm text-slate-500 hover:text-slate-700">Cancel</button>
                    </div>
                </form>
            </div>

            @if ($opportunity->notes->count())
                <ul class="mt-2 divide-y divide-slate-50">
                    @foreach ($opportunity->notes as $note)
                        <li class="flex items-start gap-2 px-5 py-3 hover:bg-slate-50">
                            <i class="bi bi-sticky mt-0.5 shrink-0 text-slate-400"></i>
                            <div class="min-w-0 flex-1">
                                <p class="whitespace-pre-line text-sm text-slate-700">{{ $note->body }}</p>
                                <p class="mt-1 text-xs text-slate-400">
                                    {{ optional($note->creator)->display_name ?: '—' }}
                                    &middot;
                                    {{ $note->created_at?->translatedFormat('d M Y H:i') }}
                                </p>
                            </div>
                            @if (auth()->user()->isAdmin() || $note->created_by === auth()->id())
                                <form method="POST" action="{{ route('opportunities.notes.destroy', [$opportunity, $note]) }}" onsubmit="return confirm('Delete this note?')" class="shrink-0">
                                    @csrf @method('DELETE')
                                    <button class="rounded p-1 text-red-500 hover:bg-red-50" title="Delete"><i class="bi bi-trash text-sm"></i></button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="px-5 py-8 text-center text-sm text-slate-400">
                    No notes yet. Click <i class="bi bi-plus-lg"></i> to add a note.
                </div>
            @endif
        </x-card>

        {{-- Quotation file card (1 opportunity : 1 quotation) --}}
        <x-card>
            <x-slot:title>Quotation File</x-slot:title>
            @if ($opportunity->quotation)
                @php $quo = $opportunity->quotation; @endphp
                <div class="flex flex-col gap-3">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-brand-100 text-brand-700"><i class="bi bi-file-earmark-text text-xl"></i></span>
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-slate-800">{{ $quo->number }}</p>
                            <p class="text-xs text-slate-400">
                                {{ optional($quo->quotation_date)->translatedFormat('d M Y') }} &middot;
                                {{ money($quo->total, $quo->currency) }}
                            </p>
                            <div class="mt-1"><x-badge :color="$quo->statusColor()">{{ $quo->statusLabel() }}</x-badge></div>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('quotations.show', $quo) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"><i class="bi bi-eye"></i> View</a>
                        @if ($quo->isMarginLocked() && ! auth()->user()->canApproveMargin())
                            <span class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800" title="Menunggu approval margin">
                                <i class="bi bi-lock"></i> Preview/PDF terkunci
                            </span>
                        @else
                            <a href="{{ route('quotations.preview', $quo) }}" target="_blank" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"><i class="bi bi-window"></i> Preview</a>
                            <a href="{{ route('quotations.pdf', $quo) }}" class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700"><i class="bi bi-download"></i> PDF</a>
                        @endif
                    </div>
                </div>
            @elseif ($opportunity->isMarginLocked() || $opportunity->discountNeedsAttention())
                <div class="flex flex-col items-center justify-center gap-3 py-4 text-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-amber-600"><i class="bi bi-lock text-2xl"></i></span>
                    <p class="text-sm text-slate-600">
                        @if ($opportunity->discountNeedsAttention())
                            Diskon tambahan menunggu approval Superadmin — Quotation belum bisa dibuat.
                        @elseif ($opportunity->crm_margin_status === \App\Models\Espo\Opportunity::MARGIN_PENDING)
                            Margin menunggu approval Superadmin — Quotation belum bisa dibuat.
                        @else
                            Margin ditolak — Quotation belum bisa dibuat. Perbarui opportunity atau minta approval ulang.
                        @endif
                    </p>
                </div>
            @else
                <div class="flex flex-col items-center justify-center gap-3 py-4 text-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400"><i class="bi bi-file-earmark-plus text-2xl"></i></span>
                    <p class="text-sm text-slate-500">No quotation for this opportunity yet.</p>
                    <a href="{{ route('quotations.create', ['opportunity_id' => $opportunity->id]) }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700"><i class="bi bi-plus-lg"></i> Create Quotation</a>
                </div>
            @endif
        </x-card>
    </div>
</div>
@endsection
