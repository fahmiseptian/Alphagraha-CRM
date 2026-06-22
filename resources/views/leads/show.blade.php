@extends('layouts.app')
@section('title', 'Detail Lead')

@section('content')
<div class="mb-4">
    <a href="{{ route('leads.index') }}" class="text-sm text-slate-500 hover:text-slate-700"><i class="bi bi-arrow-left"></i> Kembali ke daftar lead</a>
</div>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="lg:col-span-1 space-y-4">
        <x-card>
            <div class="flex items-center gap-4">
                <span class="flex h-14 w-14 items-center justify-center rounded-xl bg-purple-100 text-lg font-bold text-purple-700">{{ initials($lead->full_name) }}</span>
                <div>
                    <h2 class="text-lg font-semibold text-slate-800">{{ $lead->full_name }}</h2>
                    <p class="text-sm text-slate-500">{{ $lead->account_name ?: '—' }}</p>
                </div>
            </div>
            <dl class="mt-5 space-y-3 text-sm">
                <div class="flex gap-3"><dt class="w-24 shrink-0 text-slate-400">Email</dt><dd class="text-slate-700">{{ $lead->email ?: '—' }}</dd></div>
                <div class="flex gap-3"><dt class="w-24 shrink-0 text-slate-400">Telepon</dt><dd class="text-slate-700">{{ $lead->phone ?: '—' }}</dd></div>
                <div class="flex gap-3"><dt class="w-24 shrink-0 text-slate-400">Sumber</dt><dd class="text-slate-700">{{ $lead->source ?: '—' }}</dd></div>
                <div class="flex gap-3"><dt class="w-24 shrink-0 text-slate-400">Industri</dt><dd class="text-slate-700">{{ $lead->industry ?: '—' }}</dd></div>
                <div class="flex gap-3"><dt class="w-24 shrink-0 text-slate-400">Sales</dt><dd class="text-slate-700">{{ optional($lead->assignedUser)->display_name ?: '—' }}</dd></div>
            </dl>
        </x-card>

        {{-- Update status & assignment --}}
        <x-card title="Perbarui Lead">
            <form method="POST" action="{{ route('leads.update', $lead->id) }}" class="space-y-4">
                @csrf @method('PUT')
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Status</label>
                    <select name="status" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                        @foreach ($statuses as $st)
                            <option value="{{ $st }}" @selected($lead->status === $st)>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>
                @if (auth()->user()->isAdmin())
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Assign ke Sales</label>
                        <select name="assigned_user_id" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                            <option value="">— Belum ditugaskan —</option>
                            @foreach ($salesUsers as $u)
                                <option value="{{ $u->id }}" @selected($lead->assigned_user_id === $u->id)>{{ $u->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Catatan</label>
                    <textarea name="description" rows="3" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">{{ $lead->description }}</textarea>
                </div>
                <button class="w-full rounded-lg bg-brand-600 py-2 text-sm font-semibold text-white hover:bg-brand-700">Simpan Perubahan</button>
            </form>
        </x-card>
    </div>

    {{-- Follow-up & aktivitas --}}
    <div class="lg:col-span-2 space-y-4">
        <x-card title="Tambah Follow-up">
            <form method="POST" action="{{ route('activities.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="lead_id" value="{{ $lead->id }}">
                <input type="hidden" name="type" value="followup">
                <input type="hidden" name="status" value="planned">
                <input type="hidden" name="priority" value="normal">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <input type="text" name="subject" required placeholder="Judul follow-up (mis. Telepon penawaran)"
                           class="rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    <input type="datetime-local" name="due_at"
                           class="rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                </div>
                <textarea name="description" rows="2" placeholder="Catatan..." class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200"></textarea>
                <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700"><i class="bi bi-plus-lg"></i> Tambah Follow-up</button>
            </form>
        </x-card>

        <x-card :padding="false">
            <x-slot:title>Riwayat & Follow-up</x-slot:title>
            @forelse ($activities as $activity)
                <div class="flex gap-3 border-b border-slate-50 px-5 py-3 last:border-0">
                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                        <i class="bi {{ ['call'=>'bi-telephone','meeting'=>'bi-people','email'=>'bi-envelope','task'=>'bi-check2-square','followup'=>'bi-arrow-repeat','note'=>'bi-sticky'][$activity->type] ?? 'bi-dot' }}"></i>
                    </span>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-slate-800">{{ $activity->subject }}</p>
                            <div class="flex items-center gap-2">
                                <x-badge :color="$activity->status === 'completed' ? 'green' : ($activity->isOverdue() ? 'red' : 'slate')">{{ $activity->statusLabel() }}</x-badge>
                                @if ($activity->status !== 'completed')
                                    <form method="POST" action="{{ route('activities.complete', $activity) }}">@csrf @method('PATCH')
                                        <button class="text-green-600 hover:text-green-700" title="Tandai selesai"><i class="bi bi-check-circle"></i></button>
                                    </form>
                                @endif
                            </div>
                        </div>
                        @if ($activity->description)<p class="mt-1 text-xs text-slate-500">{{ $activity->description }}</p>@endif
                        <p class="mt-1 text-xs text-slate-400">{{ optional($activity->due_at ?? $activity->created_at)->translatedFormat('d M Y H:i') }}</p>
                    </div>
                </div>
            @empty
                <x-empty-state icon="bi-clock-history" title="Belum ada aktivitas" message="Tambahkan follow-up pertama untuk lead ini." />
            @endforelse
        </x-card>
    </div>
</div>
@endsection
