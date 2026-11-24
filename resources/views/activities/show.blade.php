@extends('layouts.app')
@section('title', 'Activity Detail')

@section('content')
<div class="mb-5 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="{{ route('activities.index') }}" class="text-sm text-slate-500 hover:text-slate-700"><i class="bi bi-arrow-left"></i> Kembali ke Activities</a>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $activity->subject }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $activity->typeLabel() }} · {{ $activity->statusLabel() }}</p>
    </div>
    @unless (auth()->user()->isFinance())
        <div class="flex gap-2">
            <a href="{{ $activity->googleCalendarUrl() }}" target="_blank" rel="noopener" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"><i class="bi bi-calendar-plus"></i> Google Calendar</a>
            <x-btn href="{{ route('activities.edit', $activity) }}" icon="bi-pencil">Edit</x-btn>
        </div>
    @endunless
</div>

@if ($activity->isEventTraining())
    @php
        $approvalBanner = match ($activity->approval_status) {
            App\Models\Activity::APPROVAL_APPROVED => 'border-green-200 bg-green-50 text-green-800',
            App\Models\Activity::APPROVAL_REJECTED => 'border-red-200 bg-red-50 text-red-800',
            default => 'border-amber-200 bg-amber-50 text-amber-800',
        };
    @endphp
    <div class="mb-5 rounded-lg border px-4 py-3 text-sm {{ $approvalBanner }}">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="font-medium"><i class="bi bi-calendar-event mr-1"></i> Event/Training — {{ $activity->approvalLabel() }}</p>
                @if ($activity->isEventApprovalPending())
                    <p class="mt-1 text-xs">Menunggu approval Superadmin sebelum ke event.</p>
                @elseif ($activity->approver)
                    <p class="mt-1 text-xs">{{ $activity->isEventApproved() ? 'Disetujui' : 'Diputuskan' }} {{ $activity->approver->display_name }}
                        @if ($activity->approved_at) · {{ $activity->approved_at->translatedFormat('d M Y H:i') }} @endif
                    </p>
                @elseif ($activity->isEventApproved() && $activity->eventDueHasPassed())
                    <p class="mt-1 text-xs">Otomatis approved karena tanggal sudah lewat.</p>
                @endif
                @if ($activity->approval_note)
                    <p class="mt-1 text-xs">Catatan: {{ $activity->approval_note }}</p>
                @endif
            </div>
            @if ($activity->isEventApprovalPending() && ! auth()->user()->isFinance() && auth()->user()->canApproveEventTraining())
                <div class="flex w-full max-w-sm shrink-0 flex-col gap-2">
                    <form method="POST" action="{{ route('activities.approve', $activity) }}" class="space-y-2">
                        @csrf
                        <input type="text" name="note" placeholder="Catatan approve (opsional)" class="w-full rounded-lg border border-green-200 bg-white px-2 py-1.5 text-xs text-slate-700">
                        <x-btn type="submit" icon="bi-check-lg" class="!border-transparent !bg-green-600 !text-white hover:!bg-green-700">Approve</x-btn>
                    </form>
                    <form method="POST" action="{{ route('activities.reject', $activity) }}" class="space-y-2">
                        @csrf
                        <input type="text" name="note" placeholder="Catatan reject (opsional)" class="w-full rounded-lg border border-red-200 bg-white px-2 py-1.5 text-xs text-slate-700">
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50"><i class="bi bi-x-lg"></i> Reject</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endif

<div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
    <div class="space-y-5 lg:col-span-2">
        <x-card>
            <h2 class="mb-4 text-base font-semibold text-slate-800">Informasi Activity</h2>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Acara</dt><dd class="mt-1 font-medium text-slate-800">{{ $activity->subject }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Jenis</dt><dd class="mt-1 text-slate-700">{{ $activity->typeLabel() }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Customer</dt><dd class="mt-1 text-slate-700">{{ $activity->account?->name ?? '—' }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">PIC Customer</dt>
                    <dd class="mt-1 font-medium text-slate-800">{{ $activity->contact?->full_name ?? '—' }}</dd>
                    @if ($activity->contact?->job_role)<dd class="text-sm text-slate-500">{{ $activity->contact->job_role }}</dd>@endif
                    @if ($activity->contact?->phone)<dd class="text-sm text-slate-500"><i class="bi bi-telephone"></i> {{ $activity->contact->phone }}</dd>@endif
                    @if ($activity->contact?->email)<dd class="text-sm text-slate-500"><i class="bi bi-envelope"></i> {{ $activity->contact->email }}</dd>@endif
                </div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Lokasi</dt><dd class="mt-1 text-slate-700">{{ $activity->location ?: 'Belum diisi' }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">PIC Internal</dt><dd class="mt-1 text-slate-700">{{ $activity->assignee?->display_name ?? $activity->assignee?->name ?? '—' }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">From · Mulai</dt><dd class="mt-1 text-slate-700">{{ $activity->starts_at?->translatedFormat('d M Y, H:i') ?? '—' }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">To · Selesai</dt><dd class="mt-1 text-slate-700">{{ $activity->ends_at?->translatedFormat('d M Y, H:i') ?? $activity->due_at?->translatedFormat('d M Y, H:i') ?? '—' }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Prioritas</dt><dd class="mt-1 text-slate-700">{{ ucfirst($activity->priority) }}</dd></div>
                @if ($activity->isEventTraining())
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Approval</dt><dd class="mt-1 text-slate-700">{{ $activity->approvalLabel() }}</dd></div>
                @endif
                @if ($activity->lead)<div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Lead terkait</dt><dd class="mt-1 text-slate-700">{{ $activity->lead->full_name }}</dd></div>@endif
            </dl>
            <div class="mt-5 border-t border-slate-100 pt-4">
                <h3 class="text-xs font-medium uppercase tracking-wide text-slate-400">Catatan</h3>
                <div class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $activity->description ?: 'Tidak ada catatan.' }}</div>
            </div>
        </x-card>
    </div>

    <aside class="space-y-5">
        @foreach (App\Models\Activity::MEDIA_COLLECTIONS as $collection => $label)
            @php $items = $mediaByCollection[$collection] ?? collect(); @endphp
            <x-card>
                <h2 class="mb-3 text-sm font-semibold text-slate-800">{{ $label }} <span class="font-normal text-slate-400">({{ $items->count() }})</span></h2>
                @if ($items->isNotEmpty())
                    <div class="space-y-3">
                        @foreach ($items as $media)
                            @if (str_starts_with($media->mime_type ?? '', 'image/'))
                                <a href="{{ $media->getUrl() }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                    <img src="{{ $media->getUrl() }}" alt="{{ $media->file_name }}" class="max-h-80 w-full object-contain">
                                </a>
                            @else
                                <a href="{{ $media->getUrl() }}" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-lg border border-slate-200 p-3 text-sm text-brand-700 hover:bg-slate-50"><i class="bi bi-file-earmark-text text-xl"></i><span class="min-w-0 truncate">{{ $media->file_name }}</span><i class="bi bi-box-arrow-up-right ml-auto"></i></a>
                            @endif
                            <p class="break-all text-xs text-slate-500">{{ $media->file_name }}</p>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-slate-400">Belum ada {{ strtolower($label) }}.</p>
                @endif
            </x-card>
        @endforeach
    </aside>
</div>
@endsection
