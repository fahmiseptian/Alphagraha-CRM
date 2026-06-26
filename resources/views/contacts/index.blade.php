@extends('layouts.app')
@section('title', 'Contacts')

@section('content')
<x-page-header title="Contacts" :description="number_format($contacts->total()) . ' contact persons'">
    <x-slot:actions>
        <x-btn href="{{ route('contacts.create') }}" icon="bi-plus-lg">New Contact</x-btn>
    </x-slot:actions>
</x-page-header>

<x-card class="mb-4" :padding="false">
    <form method="GET" action="{{ route('contacts.index') }}" class="crm-filter-form">
        <div class="min-w-0 flex-1">
            <label class="crm-label">Nama Contact</label>
            <div class="crm-search">
                <i class="bi bi-search"></i>
                <input type="text" name="q" value="{{ $search }}" placeholder="Ketik nama contact atau customer..." class="crm-field" autocomplete="off">
            </div>
        </div>
        <x-btn type="submit" variant="primary" icon="bi-search">Cari</x-btn>
        @if ($search)
            <x-btn href="{{ route('contacts.index') }}" variant="ghost">Reset</x-btn>
        @endif
    </form>
</x-card>

<x-card :padding="false">
    @if ($contacts->count())
        <div class="crm-table-wrap">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th>Contact</th>
                        <th>Customer</th>
                        <th>Email / Phone</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($contacts as $contact)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="crm-avatar">{{ initials($contact->full_name) }}</span>
                                    <span class="font-medium text-slate-800">{{ $contact->full_name }}</span>
                                </div>
                            </td>
                            <td>
                                @if ($contact->account)
                                    <a href="{{ route('customers.show', $contact->account->id) }}" class="text-brand-600 hover:underline">
                                        {{ $contact->account->name }}
                                    </a>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="block text-slate-600">{{ $contact->email ?: '—' }}</span>
                                @if ($contact->phone)
                                    <span class="block text-xs text-slate-400">{{ $contact->phone }}</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @if ($contact->account)
                                    <a href="{{ route('customers.show', $contact->account->id) }}" class="crm-icon-btn crm-icon-btn--brand" title="View customer">
                                        <i class="bi bi-arrow-right"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="crm-table-footer">{{ $contacts->links() }}</div>
    @else
        <x-empty-state icon="bi-person-lines-fill" title="No contacts found" message="Add a contact person linked to a customer.">
            <x-slot:action>
                <x-btn href="{{ route('contacts.create') }}" icon="bi-plus-lg">New Contact</x-btn>
            </x-slot:action>
        </x-empty-state>
    @endif
</x-card>
@endsection
