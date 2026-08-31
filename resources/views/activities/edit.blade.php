@extends('layouts.app')
@section('title', 'Edit Activity')

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div>
        <a href="{{ route('activities.index') }}" class="text-sm text-slate-500 hover:text-slate-700"><i class="bi bi-arrow-left"></i> Back</a>
        <h2 class="mt-1 text-lg font-semibold text-slate-800">Edit Activity</h2>
    </div>
    <a href="{{ $activity->googleCalendarUrl() }}" target="_blank" rel="noopener"
       class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
        <i class="bi bi-calendar-plus text-brand-600"></i> Tambah ke Google Calendar
    </a>
</div>

<div class="max-w-3xl space-y-4">
    @if ($activity->isEventTraining())
        @php
            $approvalBanner = match ($activity->approval_status) {
                App\Models\Activity::APPROVAL_APPROVED => 'border-green-200 bg-green-50 text-green-800',
                App\Models\Activity::APPROVAL_REJECTED => 'border-red-200 bg-red-50 text-red-800',
                default => 'border-amber-200 bg-amber-50 text-amber-800',
            };
        @endphp
        <div class="rounded-lg border px-4 py-3 text-sm {{ $approvalBanner }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-medium">
                        <i class="bi bi-calendar-event mr-1"></i>
                        Event/Training — {{ $activity->approvalLabel() }}
                    </p>
                    <p class="mt-1 text-xs opacity-90">
                        @if ($activity->isEventApproved())
                            @if ($activity->approved_by && $activity->approver)
                                Disetujui {{ $activity->approver->display_name }}
                                @if ($activity->approved_at) · {{ $activity->approved_at->translatedFormat('d M Y H:i') }} @endif
                            @elseif ($activity->eventDueHasPassed())
                                Otomatis approved karena tanggal sudah lewat.
                            @else
                                Approved.
                            @endif
                        @elseif ($activity->isEventRejected())
                            Ditolak Superadmin.
                            @if ($activity->approval_note) Catatan: {{ $activity->approval_note }} @endif
                        @else
                            Menunggu approval Superadmin sebelum ke event.
                        @endif
                    </p>
                    @if ($activity->isEventApproved() && $activity->approval_note)
                        <p class="mt-1 text-xs">Catatan: {{ $activity->approval_note }}</p>
                    @endif
                </div>
                @if ($activity->isEventApprovalPending() && auth()->user()->canApproveEventTraining())
                    <div class="flex w-full max-w-sm shrink-0 flex-col gap-2">
                        <form method="POST" action="{{ route('activities.approve', $activity) }}" class="space-y-2">
                            @csrf
                            <input type="text" name="note" placeholder="Catatan approve (opsional)"
                                   class="w-full rounded-lg border border-green-200 bg-white px-2 py-1.5 text-xs text-slate-700">
                            <x-btn type="submit" icon="bi-check-lg" class="!border-transparent !bg-green-600 !text-white hover:!bg-green-700">Approve</x-btn>
                        </form>
                        <form method="POST" action="{{ route('activities.reject', $activity) }}" class="space-y-2">
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

    @include('activities._form', ['action' => route('activities.update', $activity), 'method' => 'PUT'])
    @include('activities._media')
</div>
@endsection
