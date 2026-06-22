@extends('layouts.app')
@section('title', 'Detail Pelanggan')

@section('content')
<div class="mb-4">
    <a href="{{ route('customers.index') }}" class="text-sm text-slate-500 hover:text-slate-700"><i class="bi bi-arrow-left"></i> Kembali ke daftar</a>
</div>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    {{-- Profil --}}
    <div class="lg:col-span-1 space-y-4">
        <x-card>
            <div class="flex items-center gap-4">
                <span class="flex h-14 w-14 items-center justify-center rounded-xl bg-brand-100 text-lg font-bold text-brand-700">
                    {{ initials($account->name) }}
                </span>
                <div>
                    <h2 class="text-lg font-semibold text-slate-800">{{ $account->name }}</h2>
                    @if ($account->type)<x-badge color="slate">{{ $account->type }}</x-badge>@endif
                </div>
            </div>

            <dl class="mt-5 space-y-3 text-sm">
                <div class="flex gap-3"><dt class="w-24 shrink-0 text-slate-400"><i class="bi bi-envelope mr-1"></i>Email</dt><dd class="text-slate-700">{{ $account->email ?: '—' }}</dd></div>
                <div class="flex gap-3"><dt class="w-24 shrink-0 text-slate-400"><i class="bi bi-telephone mr-1"></i>Telepon</dt><dd class="text-slate-700">{{ $account->phone ?: '—' }}</dd></div>
                <div class="flex gap-3"><dt class="w-24 shrink-0 text-slate-400"><i class="bi bi-globe mr-1"></i>Website</dt><dd class="text-slate-700">{{ $account->website ?: '—' }}</dd></div>
                <div class="flex gap-3"><dt class="w-24 shrink-0 text-slate-400"><i class="bi bi-building mr-1"></i>Industri</dt><dd class="text-slate-700">{{ $account->industry ?: '—' }}</dd></div>
                <div class="flex gap-3"><dt class="w-24 shrink-0 text-slate-400"><i class="bi bi-geo-alt mr-1"></i>Alamat</dt><dd class="text-slate-700">{{ $account->billing_address ?: '—' }}</dd></div>
                <div class="flex gap-3"><dt class="w-24 shrink-0 text-slate-400"><i class="bi bi-person mr-1"></i>Sales</dt><dd class="text-slate-700">{{ optional($account->assignedUser)->display_name ?: '—' }}</dd></div>
            </dl>

            <div class="mt-5 flex gap-2">
                <a href="{{ route('quotations.create', ['account_id' => $account->id]) }}" class="flex-1 rounded-lg bg-brand-600 px-3 py-2 text-center text-sm font-medium text-white hover:bg-brand-700">
                    <i class="bi bi-file-earmark-plus"></i> Penawaran
                </a>
                <a href="{{ route('activities.create', ['account_id' => $account->id]) }}" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-center text-sm font-medium text-slate-600 hover:bg-slate-50">
                    <i class="bi bi-calendar-plus"></i> Aktivitas
                </a>
            </div>
        </x-card>

        @if ($contacts->count())
        <x-card title="Kontak Person">
            <ul class="space-y-3">
                @foreach ($contacts as $contact)
                    <li class="flex items-start gap-3">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">{{ initials($contact->full_name) }}</span>
                        <div>
                            <p class="text-sm font-medium text-slate-800">{{ $contact->full_name }}</p>
                            <p class="text-xs text-slate-400">{{ $contact->email ?: $contact->phone ?: '—' }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-card>
        @endif
    </div>

    {{-- Tab konten --}}
    <div class="lg:col-span-2" x-data="{ tab: 'opportunities' }">
        <x-card :padding="false">
            <div class="flex gap-1 border-b border-slate-100 px-4 pt-3 text-sm">
                <button @click="tab='opportunities'" :class="tab==='opportunities' ? 'border-brand-600 text-brand-600' : 'border-transparent text-slate-500'" class="border-b-2 px-3 py-2.5 font-medium">Deal ({{ $opportunities->count() }})</button>
                <button @click="tab='quotations'" :class="tab==='quotations' ? 'border-brand-600 text-brand-600' : 'border-transparent text-slate-500'" class="border-b-2 px-3 py-2.5 font-medium">Penawaran ({{ $quotations->count() }})</button>
                <button @click="tab='activities'" :class="tab==='activities' ? 'border-brand-600 text-brand-600' : 'border-transparent text-slate-500'" class="border-b-2 px-3 py-2.5 font-medium">Riwayat Aktivitas</button>
            </div>

            {{-- Opportunities --}}
            <div x-show="tab==='opportunities'">
                @forelse ($opportunities as $opp)
                    <div class="flex items-center justify-between border-b border-slate-50 px-5 py-3 last:border-0">
                        <div>
                            <p class="text-sm font-medium text-slate-800">{{ $opp->name }}</p>
                            <p class="text-xs text-slate-400">{{ $opp->close_date ? \Illuminate\Support\Carbon::parse($opp->close_date)->translatedFormat('d M Y') : '—' }} &middot; {{ optional($opp->assignedUser)->display_name }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-slate-700">{{ money($opp->amount, $opp->amount_currency ?: 'IDR') }}</p>
                            @php $c = in_array($opp->stage, ['Closed Won']) ? 'green' : (in_array($opp->stage, ['Closed Lost']) ? 'red' : 'blue'); @endphp
                            <x-badge :color="$c">{{ $opp->stage }}</x-badge>
                        </div>
                    </div>
                @empty
                    <x-empty-state icon="bi-briefcase" title="Belum ada deal" />
                @endforelse
            </div>

            {{-- Quotations --}}
            <div x-show="tab==='quotations'" x-cloak>
                @forelse ($quotations as $quo)
                    <a href="{{ route('quotations.show', $quo) }}" class="flex items-center justify-between border-b border-slate-50 px-5 py-3 last:border-0 hover:bg-slate-50">
                        <div>
                            <p class="text-sm font-medium text-slate-800">{{ $quo->number }}</p>
                            <p class="text-xs text-slate-400">{{ $quo->quotation_date?->translatedFormat('d M Y') }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-slate-700">{{ money($quo->total, $quo->currency) }}</p>
                            <x-badge :color="$quo->statusColor()">{{ $quo->statusLabel() }}</x-badge>
                        </div>
                    </a>
                @empty
                    <x-empty-state icon="bi-file-earmark-text" title="Belum ada penawaran" />
                @endforelse
            </div>

            {{-- Activities --}}
            <div x-show="tab==='activities'" x-cloak>
                @forelse ($activities as $activity)
                    <div class="flex gap-3 border-b border-slate-50 px-5 py-3 last:border-0">
                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                            <i class="bi {{ ['call'=>'bi-telephone','meeting'=>'bi-people','email'=>'bi-envelope','task'=>'bi-check2-square','followup'=>'bi-arrow-repeat','note'=>'bi-sticky'][$activity->type] ?? 'bi-dot' }}"></i>
                        </span>
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-slate-800">{{ $activity->subject }}</p>
                                <x-badge :color="$activity->status === 'completed' ? 'green' : ($activity->isOverdue() ? 'red' : 'slate')">{{ $activity->statusLabel() }}</x-badge>
                            </div>
                            @if ($activity->description)<p class="mt-1 text-xs text-slate-500">{{ $activity->description }}</p>@endif
                            <p class="mt-1 text-xs text-slate-400">{{ $activity->typeLabel() }} &middot; {{ optional($activity->due_at ?? $activity->created_at)->translatedFormat('d M Y H:i') }}</p>
                        </div>
                    </div>
                @empty
                    <x-empty-state icon="bi-clock-history" title="Belum ada aktivitas" message="Buat aktivitas untuk pelanggan ini." />
                @endforelse
            </div>
        </x-card>
    </div>
</div>
@endsection
