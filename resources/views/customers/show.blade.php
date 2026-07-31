@extends('layouts.app')
@section('title', 'Customer Detail')

@section('content')
<div class="mb-6">
    <a href="{{ route('customers.index') }}" class="crm-back"><i class="bi bi-arrow-left"></i> Back to list</a>
</div>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    {{-- Profile --}}
    <div class="lg:col-span-1 space-y-4">
        <x-card>
            <div class="flex items-center gap-4">
                <span class="flex h-14 w-14 items-center justify-center rounded-xl bg-brand-100 text-lg font-bold text-brand-700">
                    {{ initials($account->name) }}
                </span>
                <div>
                    <h2 class="text-lg font-semibold text-slate-800">{{ $account->name }}</h2>
                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                        @if ($account->type)<x-badge color="slate">{{ $account->type }}</x-badge>@endif
                        @php
                            $levelColor = match ($account->paymentLevel()) {
                                'lancar' => 'green',
                                'mandek' => 'amber',
                                'jelek' => 'rose',
                                'suspend' => 'red',
                                default => 'slate',
                            };
                        @endphp
                        <x-badge :color="$levelColor">{{ $account->paymentLevelLabel() }}</x-badge>
                        <x-badge color="slate">{{ $account->topLabel() }}</x-badge>
                    </div>
                </div>
            </div>

            <dl class="mt-5 space-y-3 text-sm">
                <div class="flex gap-3"><dt class="w-24 shrink-0 text-slate-400"><i class="bi bi-envelope mr-1"></i>Email</dt><dd class="text-slate-700">{{ $account->email ?: '—' }}</dd></div>
                <div class="flex gap-3"><dt class="w-24 shrink-0 text-slate-400"><i class="bi bi-telephone mr-1"></i>Phone</dt><dd class="text-slate-700">{{ $account->phone ?: '—' }}</dd></div>
                <div class="flex gap-3"><dt class="w-24 shrink-0 text-slate-400"><i class="bi bi-globe mr-1"></i>Website</dt><dd class="text-slate-700">{{ $account->website ?: '—' }}</dd></div>
                <div class="flex gap-3"><dt class="w-24 shrink-0 text-slate-400"><i class="bi bi-building mr-1"></i>Industry</dt><dd class="text-slate-700">{{ $account->industry ?: '—' }}</dd></div>
                <div class="flex gap-3"><dt class="w-24 shrink-0 text-slate-400"><i class="bi bi-geo-alt mr-1"></i>Address</dt><dd class="text-slate-700">{{ $account->billing_address ?: '—' }}</dd></div>
                <div class="flex gap-3"><dt class="w-24 shrink-0 text-slate-400"><i class="bi bi-person mr-1"></i>Sales</dt><dd class="text-slate-700">{{ optional($account->assignedUser)->display_name ?: '—' }}</dd></div>
                <div class="flex gap-3"><dt class="w-24 shrink-0 text-slate-400"><i class="bi bi-cash-coin mr-1"></i>Level</dt><dd class="text-slate-700">{{ $account->paymentLevelLabel() }}@if ($account->minMarginPercent() !== null) <span class="text-xs text-slate-400">(min margin {{ number_format($account->minMarginPercent(), 0) }}%)</span>@endif</dd></div>
                <div class="flex gap-3"><dt class="w-24 shrink-0 text-slate-400"><i class="bi bi-calendar2-check mr-1"></i>TOP</dt><dd class="text-slate-700">{{ $account->topLabel() }}</dd></div>
            </dl>

            <div class="mt-5 flex flex-wrap gap-2">
                <a href="{{ route('customers.edit', $account->id) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-center text-sm font-medium text-slate-600 hover:bg-slate-50">
                    <i class="bi bi-pencil"></i> Edit
                </a>
                <a href="{{ route('opportunities.create', ['account_id' => $account->id]) }}" class="flex-1 rounded-lg bg-brand-600 px-3 py-2 text-center text-sm font-medium text-white hover:bg-brand-700">
                    <i class="bi bi-briefcase"></i> Opportunity
                </a>
                @if (! $account->isPaymentSuspended())
                    <a href="{{ route('quotations.create', ['account_id' => $account->id]) }}" class="flex-1 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-center text-sm font-medium text-brand-700 hover:bg-brand-100">
                        <i class="bi bi-file-earmark-plus"></i> Quotation
                    </a>
                @else
                    <span class="flex-1 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-center text-sm font-medium text-red-700" title="Customer Suspend">
                        <i class="bi bi-lock"></i> Suspend
                    </span>
                @endif
                <a href="{{ route('activities.create', ['account_id' => $account->id]) }}" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-center text-sm font-medium text-slate-600 hover:bg-slate-50">
                    <i class="bi bi-calendar-plus"></i> Activity
                </a>
                @if (auth()->user()->canDeleteCustomer())
                    <form method="POST" action="{{ route('customers.destroy', $account->id) }}"
                          onsubmit="return confirm('Hapus customer ini? Tindakan tidak bisa dibatalkan.')"
                          class="w-full">
                        @csrf @method('DELETE')
                        <button type="submit" class="w-full rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-center text-sm font-medium text-red-700 hover:bg-red-100">
                            <i class="bi bi-trash"></i> Hapus
                        </button>
                    </form>
                @endif
            </div>
        </x-card>

        <x-card title="Contact Persons (PIC)">
        @if ($contacts->count())
            <ul class="space-y-3" x-data="{ editingId: @js(old('editing_contact_id')) }">
                @foreach ($contacts as $contact)
                    <li class="rounded-lg border border-slate-100 p-3">
                        <div class="flex items-start gap-3" x-show="editingId !== '{{ $contact->id }}'">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">{{ initials($contact->full_name) }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-slate-800">{{ $contact->full_name }}</p>
                                @if ($contact->job_role)
                                    <p class="text-xs font-medium text-brand-600">{{ $contact->job_role }}</p>
                                @endif
                                <p class="text-xs text-slate-400">{{ $contact->email ?: '—' }}</p>
                                @if ($contact->phone)
                                    <p class="text-xs text-slate-400"><i class="bi bi-telephone mr-0.5"></i>{{ $contact->phone }}</p>
                                @endif
                            </div>
                            @if (auth()->user()->canEditCustomerContact())
                                <button type="button" @click="editingId = '{{ $contact->id }}'" class="crm-icon-btn shrink-0" title="Edit PIC">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            @endif
                        </div>

                        @if (auth()->user()->canEditCustomerContact())
                            <form x-show="editingId === '{{ $contact->id }}'" x-cloak
                                  method="POST" action="{{ route('customers.contacts.update', [$account->id, $contact->id]) }}"
                                  class="space-y-3">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="editing_contact_id" value="{{ $contact->id }}">
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="crm-label">First Name <span class="text-red-500">*</span></label>
                                        <input type="text" name="first_name"
                                               value="{{ old('editing_contact_id') === $contact->id ? old('first_name', $contact->first_name) : $contact->first_name }}"
                                               required class="crm-field">
                                    </div>
                                    <div>
                                        <label class="crm-label">Last Name</label>
                                        <input type="text" name="last_name"
                                               value="{{ old('editing_contact_id') === $contact->id ? old('last_name', $contact->last_name) : $contact->last_name }}"
                                               class="crm-field">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="crm-label">Job Role</label>
                                        <input type="text" name="job_role"
                                               value="{{ old('editing_contact_id') === $contact->id ? old('job_role', $contact->job_role) : $contact->job_role }}"
                                               placeholder="Contoh: Purchasing, IT Manager" class="crm-field">
                                    </div>
                                    <div>
                                        <label class="crm-label">Email</label>
                                        <input type="email" name="email"
                                               value="{{ old('editing_contact_id') === $contact->id ? old('email', $contact->email) : $contact->email }}"
                                               class="crm-field">
                                    </div>
                                    <div>
                                        <label class="crm-label">Phone</label>
                                        <input type="text" name="phone"
                                               value="{{ old('editing_contact_id') === $contact->id ? old('phone', $contact->phone) : $contact->phone }}"
                                               class="crm-field">
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <x-btn type="submit" variant="primary" icon="bi-save">Simpan PIC</x-btn>
                                    <button type="button" @click="editingId = null" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Batal</button>
                                </div>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-sm text-slate-400">No contacts yet.</p>
        @endif

        @if (auth()->user()->canCreateCustomerContact())
        <div class="mt-4 border-t border-slate-100 pt-4">
            @if (auth()->user()->isSuperAdmin())
                <x-btn href="{{ route('contacts.create', ['account_id' => $account->id]) }}" variant="secondary" icon="bi-person-plus" class="w-full justify-center sm:w-auto">Add Contact</x-btn>
            @else
                <form method="POST" action="{{ route('customers.contacts.store', $account->id) }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="crm-label">First Name <span class="text-red-500">*</span></label>
                            <input type="text" name="first_name" value="{{ old('editing_contact_id') ? '' : old('first_name') }}" required class="crm-field">
                        </div>
                        <div>
                            <label class="crm-label">Last Name</label>
                            <input type="text" name="last_name" value="{{ old('editing_contact_id') ? '' : old('last_name') }}" class="crm-field">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="crm-label">Job Role</label>
                            <input type="text" name="job_role" value="{{ old('editing_contact_id') ? '' : old('job_role') }}" placeholder="Contoh: Purchasing, IT Manager" class="crm-field">
                        </div>
                        <div>
                            <label class="crm-label">Email</label>
                            <input type="email" name="email" value="{{ old('editing_contact_id') ? '' : old('email') }}" class="crm-field">
                        </div>
                        <div>
                            <label class="crm-label">Phone</label>
                            <input type="text" name="phone" value="{{ old('editing_contact_id') ? '' : old('phone') }}" class="crm-field">
                        </div>
                    </div>
                    <x-btn type="submit" variant="secondary" icon="bi-person-plus">Add Contact</x-btn>
                </form>
            @endif
        </div>
        @endif
        </x-card>
    </div>

    {{-- Content tabs --}}
    <div class="lg:col-span-2" x-data="{ tab: 'opportunities' }">
        <x-card :padding="false">
            <div class="flex gap-1 border-b border-slate-100 px-4 pt-3 text-sm">
                <button @click="tab='opportunities'" :class="tab==='opportunities' ? 'border-brand-600 text-brand-600' : 'border-transparent text-slate-500'" class="border-b-2 px-3 py-2.5 font-medium">Deals ({{ $opportunities->count() }})</button>
                <button @click="tab='quotations'" :class="tab==='quotations' ? 'border-brand-600 text-brand-600' : 'border-transparent text-slate-500'" class="border-b-2 px-3 py-2.5 font-medium">Quotations ({{ $quotations->count() }})</button>
                <button @click="tab='activities'" :class="tab==='activities' ? 'border-brand-600 text-brand-600' : 'border-transparent text-slate-500'" class="border-b-2 px-3 py-2.5 font-medium">Activity History</button>
            </div>

            {{-- Opportunities --}}
            <div x-show="tab==='opportunities'">
                @forelse ($opportunities as $opp)
                    <a href="{{ route('opportunities.show', $opp) }}" class="flex items-center justify-between border-b border-slate-50 px-5 py-3 last:border-0 hover:bg-slate-50">
                        <div>
                            <p class="text-sm font-medium text-slate-800">{{ $opp->name }}</p>
                            <p class="text-xs text-slate-400">{{ $opp->close_date ? \Illuminate\Support\Carbon::parse($opp->close_date)->translatedFormat('d M Y') : '—' }} &middot; {{ optional($opp->assignedUser)->display_name }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-slate-700">{{ money($opp->amount, $opp->amount_currency ?: 'IDR') }}</p>
                            @php $c = in_array($opp->stage, ['Closed Won']) ? 'green' : (in_array($opp->stage, ['Closed Lost']) ? 'red' : 'blue'); @endphp
                            <x-badge :color="$c">{{ $opp->stage }}</x-badge>
                        </div>
                    </a>
                @empty
                    <x-empty-state icon="bi-briefcase" title="No deals yet" />
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
                    <x-empty-state icon="bi-file-earmark-text" title="No quotations yet" />
                @endforelse
            </div>

            {{-- Activities --}}
            <div x-show="tab==='activities'" x-cloak>
                @forelse ($activities as $activity)
                    <div class="flex gap-3 border-b border-slate-50 px-5 py-3 last:border-0">
                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                            <i class="bi {{ ['call'=>'bi-telephone','meeting'=>'bi-people','email'=>'bi-envelope','task'=>'bi-check2-square','followup'=>'bi-arrow-repeat','note'=>'bi-sticky','event_training'=>'bi-calendar-event'][$activity->type] ?? 'bi-dot' }}"></i>
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
                    <x-empty-state icon="bi-clock-history" title="No activities yet" message="Create an activity for this customer." />
                @endforelse
            </div>
        </x-card>
    </div>
</div>
@endsection
