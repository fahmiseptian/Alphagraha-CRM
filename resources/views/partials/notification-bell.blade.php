{{-- Lonceng notifikasi di topbar --}}
@php
    $unreadCount = $crmUnreadNotificationCount ?? 0;
    $recentNotifications = $crmRecentNotifications ?? collect();
@endphp
<div x-data="{ open: false }" class="relative">
    <button type="button" @click="open = !open" class="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
            aria-label="Notifikasi">
        <i class="bi bi-bell text-xl"></i>
        @if ($unreadCount > 0)
            <span class="absolute right-1 top-1 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div x-show="open" x-cloak @click.outside="open = false"
         class="absolute right-0 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <p class="text-sm font-semibold text-slate-800">Notifikasi</p>
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="text-xs font-medium text-brand-600 hover:text-brand-700">Tandai informatif dibaca</button>
                </form>
            @endif
        </div>
        <ul class="max-h-80 overflow-y-auto divide-y divide-slate-50">
            @forelse ($recentNotifications as $n)
                @php
                    $needsAction = $n->isUnread() && $n->requiresAction();
                    $unread = $n->isUnread();
                @endphp
                <li @class([
                    'border-l-4 border-amber-500 bg-amber-50' => $needsAction,
                    'border-l-4 border-brand-500 bg-brand-50' => $unread && ! $needsAction,
                ])>
                    <form method="POST" action="{{ route('notifications.read', $n) }}">
                        @csrf
                        <button type="submit" class="flex w-full items-start gap-3 px-4 py-3 text-left hover:brightness-95">
                            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $n->colorClass() }}">
                                <i class="bi {{ $n->icon() }} text-sm"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <p @class([
                                        'truncate text-sm font-bold text-slate-900' => $unread,
                                        'truncate text-sm font-medium text-slate-700' => ! $unread,
                                    ])>{{ $n->title }}</p>
                                    @if ($needsAction)
                                        <span class="shrink-0 rounded-full bg-amber-500 px-1.5 py-0.5 text-[9px] font-bold uppercase text-white">Aksi</span>
                                    @elseif ($unread)
                                        <span class="shrink-0 rounded-full bg-brand-600 px-1.5 py-0.5 text-[9px] font-bold uppercase text-white">Baru</span>
                                    @endif
                                </div>
                                @if ($n->body)
                                    <p class="mt-0.5 line-clamp-2 text-xs {{ $unread ? 'text-slate-700' : 'text-slate-500' }}">{{ $n->body }}</p>
                                @endif
                                <p class="mt-1 text-[11px] text-slate-400">{{ $n->created_at?->diffForHumans() }}</p>
                            </div>
                            @if ($unread)
                                <span @class([
                                    'mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full',
                                    'bg-amber-500' => $needsAction,
                                    'bg-brand-500' => ! $needsAction,
                                ])></span>
                            @endif
                        </button>
                    </form>
                </li>
            @empty
                <li class="px-4 py-8 text-center text-sm text-slate-400">Tidak ada notifikasi</li>
            @endforelse
        </ul>
        <div class="border-t border-slate-100 bg-slate-50 px-4 py-2.5 text-center">
            <a href="{{ route('notifications.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Lihat semua</a>
        </div>
    </div>
</div>
