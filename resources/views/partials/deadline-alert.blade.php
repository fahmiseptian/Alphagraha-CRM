@if ($showDeadlinePopup ?? false)
    <div
        x-data="{ open: true }"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="deadline-alert-title"
    >
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-slate-900/50" @click="open = false"></div>

        <div
            x-show="open"
            x-transition
            class="crm-deadline-modal relative flex max-h-[85vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"
        >
            <div class="border-b border-amber-100 bg-amber-50 px-5 py-4">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                        <i class="bi bi-alarm text-xl"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 id="deadline-alert-title" class="text-base font-semibold text-slate-800">
                            Pengingat Follow-up Deadline
                        </h3>
                        <p class="mt-0.5 text-sm text-slate-500">
                            {{ $deadlineAlerts->count() }} pengingat: deadline opportunity atau reminder activity yang sudah jatuh tempo.
                        </p>
                    </div>
                    <button type="button" @click="open = false" class="text-slate-400 hover:text-slate-600">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto px-5 py-3">
                <ul class="space-y-2">
                    @foreach ($deadlineAlerts as $alert)
                        <li>
                            <a href="{{ $alert['url'] }}"
                               class="flex items-start gap-3 rounded-xl border border-slate-100 px-3 py-3 transition hover:border-slate-200 hover:bg-slate-50">
                                <span @class([
                                    'mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-sm',
                                    'bg-red-50 text-red-600' => $alert['overdue'],
                                    'bg-amber-50 text-amber-600' => ! $alert['overdue'],
                                ])>
                                    <i class="bi {{ $alert['kind'] === 'opportunity' ? 'bi-briefcase' : 'bi-arrow-repeat' }}"></i>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-slate-800">{{ $alert['title'] }}</p>
                                    <p class="truncate text-xs text-slate-400">
                                        {{ $alert['kind'] === 'opportunity' ? 'Opportunity' : 'Activity' }}
                                        @if ($alert['subtitle'])
                                            &middot; {{ $alert['subtitle'] }}
                                        @endif
                                    </p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-xs font-medium {{ $alert['overdue'] ? 'text-red-600' : 'text-amber-600' }}">
                                        {{ $alert['date_label'] }}
                                    </p>
                                    <p class="text-[11px] text-slate-400">{{ $alert['date']->translatedFormat('d M Y') }}</p>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="flex items-center justify-between gap-3 border-t border-slate-100 bg-slate-50 px-5 py-4">
                <a href="{{ route('opportunities.index') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">
                    Lihat pipeline <i class="bi bi-arrow-right"></i>
                </a>
                <x-btn type="button" variant="primary" @click="open = false">Mengerti</x-btn>
            </div>
        </div>
    </div>
@endif
