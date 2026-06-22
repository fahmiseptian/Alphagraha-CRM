<x-card>
    <form method="POST" action="{{ $action }}" class="space-y-5">
        @csrf
        @if (($method ?? 'POST') === 'PUT')@method('PUT')@endif

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Tipe Aktivitas <span class="text-red-500">*</span></label>
                <select name="type" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    @foreach ($types as $key => $label)
                        <option value="{{ $key }}" @selected(old('type', $activity->type) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Prioritas</label>
                <select name="priority" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    @foreach (['low'=>'Rendah','normal'=>'Normal','high'=>'Tinggi'] as $key => $label)
                        <option value="{{ $key }}" @selected(old('priority', $activity->priority) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Judul <span class="text-red-500">*</span></label>
            <input type="text" name="subject" value="{{ old('subject', $activity->subject) }}" required
                   class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Catatan</label>
            <textarea name="description" rows="3" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">{{ old('description', $activity->description) }}</textarea>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Pelanggan terkait</label>
                <select name="account_id" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    <option value="">— Tidak ada —</option>
                    @foreach ($accounts as $acc)
                        <option value="{{ $acc->id }}" @selected(old('account_id', $activity->account_id ?? ($presetAccount ?? null)) === $acc->id)>{{ $acc->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Ditugaskan ke</label>
                <select name="assigned_to" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected((int) old('assigned_to', $activity->assigned_to ?? auth()->id()) === $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Tenggat (Jadwal)</label>
                <input type="datetime-local" name="due_at"
                       value="{{ old('due_at', optional($activity->due_at)->format('Y-m-d\TH:i')) }}"
                       class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Reminder</label>
                <input type="datetime-local" name="reminder_at"
                       value="{{ old('reminder_at', optional($activity->reminder_at)->format('Y-m-d\TH:i')) }}"
                       class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Status</label>
                <select name="status" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}" @selected(old('status', $activity->status) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <input type="hidden" name="lead_id" value="{{ old('lead_id', $activity->lead_id ?? ($presetLead ?? '')) }}">

        <div class="flex items-center gap-3 border-t border-slate-100 pt-4">
            <button class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">Simpan</button>
            <a href="{{ route('activities.index') }}" class="rounded-lg border border-slate-300 px-5 py-2 text-sm text-slate-600 hover:bg-slate-50">Batal</a>
        </div>
    </form>
</x-card>
