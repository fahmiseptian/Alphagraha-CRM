@php
    $popupNotifications = $crmPopupNotifications ?? collect();
@endphp
@if ($popupNotifications->isNotEmpty())
    <div
        x-data="{
            open: true,
            async dismiss() {
                this.open = false;
                try {
                    await fetch('{{ route('notifications.dismiss-popups') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                        },
                    });
                } catch (e) {}
            }
        }"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
    >
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-slate-900/50" @click="dismiss()"></div>

        <div x-show="open" x-transition
             class="relative flex max-h-[85vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="border-b border-brand-100 bg-brand-50 px-5 py-4">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-brand-600">
                        <i class="bi bi-bell-fill text-xl"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-base font-semibold text-slate-800">Notifikasi penting</h3>
                        <p class="mt-0.5 text-sm text-slate-500">
                            {{ $popupNotifications->count() }} pemberitahuan menunggu perhatian
                        </p>
                    </div>
                    <button type="button" @click="dismiss()" class="text-slate-400 hover:text-slate-600">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto px-5 py-3">
                <ul class="space-y-2">
                    @foreach ($popupNotifications as $n)
                        @php $needsAction = $n->requiresAction(); @endphp
                        <li>
                            <form method="POST" action="{{ route('notifications.read', $n) }}">
                                @csrf
                                <button type="submit"
                                        @class([
                                            'flex w-full items-start gap-3 rounded-xl border px-3 py-3 text-left transition',
                                            'border-amber-300 bg-amber-50 hover:bg-amber-100' => $needsAction,
                                            'border-brand-200 bg-brand-50 hover:bg-brand-100/70' => ! $needsAction,
                                        ])>
                                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $n->colorClass() }}">
                                        <i class="bi {{ $n->icon() }}"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <p class="text-sm font-bold text-slate-900">{{ $n->title }}</p>
                                            @if ($needsAction)
                                                <span class="rounded-full bg-amber-500 px-1.5 py-0.5 text-[9px] font-bold uppercase text-white">Perlu aksi</span>
                                            @endif
                                        </div>
                                        @if ($n->body)
                                            <p class="mt-0.5 text-xs text-slate-700">{{ $n->body }}</p>
                                        @endif
                                        <p class="mt-1 text-[11px] text-slate-500">{{ $n->created_at?->diffForHumans() }}</p>
                                    </div>
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50 px-5 py-4">
                <a href="{{ route('notifications.index') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">
                    Lihat semua <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
@endif
