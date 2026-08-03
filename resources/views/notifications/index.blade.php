@extends('layouts.app')
@section('title', 'Notifikasi')

@section('content')
<x-page-header title="Notifikasi" description="Diskon, deadline opportunity, acara &amp; follow-up">
    <x-slot:actions>
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <x-btn type="submit" variant="secondary" icon="bi-check2-all">Tandai informatif dibaca</x-btn>
        </form>
    </x-slot:actions>
</x-page-header>

@php
    $pageIds = $notifications->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
@endphp

{{-- Filter --}}
<x-card class="mb-4" :padding="false">
    <form method="GET" action="{{ route('notifications.index') }}" class="crm-opp-filters">
        <div @class([
            'crm-opp-filters__grid',
            'crm-opp-filters__grid--admin' => $canFilterSales,
        ])>
            <div class="crm-opp-filters__field">
                <label class="crm-label">Cari</label>
                <div class="crm-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="q" value="{{ $search }}" placeholder="Judul atau isi notifikasi..."
                           class="crm-field" autocomplete="off">
                </div>
            </div>

            @if ($canFilterSales)
                <div class="crm-opp-filters__field">
                    <label class="crm-label">Sales</label>
                    <select name="sales_id" class="select2 select2-search w-full" data-placeholder="Semua sales">
                        <option value="">Semua sales</option>
                        @foreach ($salesUsers as $su)
                            <option value="{{ $su->id }}" @selected($salesId === $su->id)>{{ $su->display_name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="crm-opp-filters__field">
                <label class="crm-label">Dari tanggal</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="crm-field">
            </div>

            <div class="crm-opp-filters__field">
                <label class="crm-label">Sampai tanggal</label>
                <input type="date" name="date_to" value="{{ $dateTo }}" class="crm-field">
            </div>

            <div class="crm-opp-filters__field">
                <label class="crm-label">Status</label>
                <select name="status" class="select2 select2-compact w-full" data-placeholder="Semua status">
                    <option value="all" @selected($status === 'all')>Semua</option>
                    <option value="unread" @selected($status === 'unread')>Belum dibaca</option>
                    <option value="read" @selected($status === 'read')>Sudah dibaca</option>
                    <option value="action" @selected($status === 'action')>Perlu aksi</option>
                </select>
            </div>
        </div>

        <div class="crm-opp-filters__actions">
            <div class="crm-opp-filters__buttons">
                <x-btn type="submit" icon="bi-funnel">Filter</x-btn>
                @if ($hasFilters)
                    <a href="{{ route('notifications.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Reset
                    </a>
                @endif
            </div>
            <p class="crm-opp-filters__total">
                <strong>{{ $notifications->total() }}</strong> notifikasi
                @if ($hasFilters)
                    <span>· terfilter</span>
                @endif
            </p>
        </div>
    </form>
</x-card>

<x-card :padding="false">
    @if ($notifications->count())
        <div
            x-data="{
                pageIds: @js($pageIds),
                selected: [],
                get allSelected() {
                    return this.pageIds.length > 0 && this.selected.length === this.pageIds.length;
                },
                toggleSelectAll(checked) {
                    this.selected = checked ? [...this.pageIds] : [];
                },
                submitDelete() {
                    if (this.selected.length === 0) {
                        return;
                    }
                    if (! confirm('Hapus notifikasi terpilih? Tindakan ini tidak bisa dibatalkan.')) {
                        return;
                    }
                    this.$refs.bulkDeleteForm.submit();
                },
            }"
        >
            <form x-ref="bulkDeleteForm" method="POST" action="{{ route('notifications.destroy-selected') }}" class="hidden">
                @csrf
                @method('DELETE')
                <template x-for="id in selected" :key="id">
                    <input type="hidden" name="ids[]" :value="id">
                </template>
            </form>

            <div class="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-5 py-3">
                <label class="flex cursor-pointer items-center gap-2.5 text-sm font-medium text-slate-700">
                    <input
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                        :checked="allSelected"
                        @change="toggleSelectAll($event.target.checked)"
                    >
                    <span>Pilih semua</span>
                </label>

                <button
                    type="button"
                    x-show="selected.length > 0"
                    x-cloak
                    @click="submitDelete()"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50"
                >
                    <i class="bi bi-trash"></i>
                    <span x-text="'Hapus (' + selected.length + ')'"></span>
                </button>
            </div>

            <ul class="divide-y divide-slate-100">
                @foreach ($notifications as $n)
                    @php
                        $needsAction = $n->isUnread() && $n->requiresAction();
                        $unread = $n->isUnread();
                    @endphp
                    <li @class([
                        'relative flex border-l-4 border-amber-500 bg-amber-50' => $needsAction,
                        'relative flex border-l-4 border-brand-500 bg-brand-50' => $unread && ! $needsAction,
                        'relative flex border-l-4 border-transparent' => ! $unread,
                    ])>
                        <label class="flex shrink-0 cursor-pointer items-start px-4 py-4">
                            <input
                                type="checkbox"
                                value="{{ (string) $n->id }}"
                                x-model="selected"
                                class="mt-1 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                @click.stop
                            >
                        </label>

                        <form method="POST" action="{{ route('notifications.read', $n) }}" class="min-w-0 flex-1">
                            @csrf
                            <button type="submit" @class([
                                'flex w-full items-start gap-3 py-4 pr-5 text-left transition',
                                'hover:bg-amber-100/70' => $needsAction,
                                'hover:bg-brand-100/50' => $unread && ! $needsAction,
                                'hover:bg-slate-50' => ! $unread,
                            ])>
                                <span @class([
                                    'mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ring-2',
                                    'ring-amber-300 '.$n->colorClass() => $needsAction,
                                    'ring-brand-300 '.$n->colorClass() => $unread && ! $needsAction,
                                    $n->colorClass() => ! $unread,
                                ])>
                                    <i class="bi {{ $n->icon() }} text-base"></i>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p @class([
                                            'text-sm font-bold text-slate-900' => $unread,
                                            'text-sm font-semibold text-slate-700' => ! $unread,
                                        ])>{{ $n->title }}</p>
                                        @if ($needsAction)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-500 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white shadow-sm">
                                                <i class="bi bi-exclamation-circle-fill text-[10px]"></i>
                                                Perlu aksi
                                            </span>
                                        @elseif ($unread)
                                            <span class="inline-flex items-center rounded-full bg-brand-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white shadow-sm">
                                                Baru
                                            </span>
                                        @endif
                                    </div>
                                    @if ($n->body)
                                        <p @class([
                                            'mt-1 text-sm text-slate-800' => $unread,
                                            'mt-1 text-sm text-slate-500' => ! $unread,
                                        ])>{{ $n->body }}</p>
                                    @endif
                                    <p class="mt-1.5 text-xs font-medium {{ $unread ? 'text-slate-500' : 'text-slate-400' }}">
                                        {{ $n->created_at?->translatedFormat('d M Y H:i') }}
                                        <span class="text-slate-300">·</span>
                                        {{ $n->created_at?->diffForHumans() }}
                                    </p>
                                </div>
                                @if ($unread)
                                    <span @class([
                                        'mt-2 h-3 w-3 shrink-0 rounded-full ring-4',
                                        'bg-amber-500 ring-amber-200' => $needsAction,
                                        'bg-brand-500 ring-brand-200' => ! $needsAction,
                                    ])></span>
                                @endif
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <div class="px-5 py-16 text-center text-slate-400">
            @if ($hasFilters)
                Tidak ada notifikasi sesuai filter.
                <a href="{{ route('notifications.index') }}" class="ml-1 text-brand-600 hover:underline">Reset filter</a>
            @else
                Belum ada notifikasi.
            @endif
        </div>
    @endif

    <div class="crm-table-footer">{{ $notifications->links() }}</div>
</x-card>
@endsection
