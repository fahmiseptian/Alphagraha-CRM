@extends('layouts.app')
@section('title', 'Notifikasi')

@section('content')
<x-page-header title="Notifikasi" description="Diskon, deadline opportunity, acara &amp; follow-up">
    <x-slot:actions>
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <x-btn type="submit" variant="secondary" icon="bi-check2-all">Tandai semua dibaca</x-btn>
        </form>
    </x-slot:actions>
</x-page-header>

<x-card :padding="false">
    <ul class="divide-y divide-slate-100">
        @forelse ($notifications as $n)
            <li @class(['bg-brand-50/40' => $n->isUnread()])>
                <form method="POST" action="{{ route('notifications.read', $n) }}" class="block">
                    @csrf
                    <button type="submit" class="flex w-full items-start gap-3 px-5 py-4 text-left transition hover:bg-slate-50">
                        <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $n->colorClass() }}">
                            <i class="bi {{ $n->icon() }}"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-800">{{ $n->title }}</p>
                                @if ($n->isUnread())
                                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-brand-500"></span>
                                @endif
                            </div>
                            @if ($n->body)
                                <p class="mt-0.5 text-sm text-slate-600">{{ $n->body }}</p>
                            @endif
                            <p class="mt-1 text-xs text-slate-400">{{ $n->created_at?->diffForHumans() }}</p>
                        </div>
                    </button>
                </form>
            </li>
        @empty
            <li class="px-5 py-16 text-center text-slate-400">Belum ada notifikasi.</li>
        @endforelse
    </ul>
    <div class="crm-table-footer">{{ $notifications->links() }}</div>
</x-card>
@endsection
