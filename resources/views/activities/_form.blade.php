<x-card>
    <form method="POST" action="{{ $action }}" class="space-y-5">
        @csrf
        @if (($method ?? 'POST') === 'PUT')@method('PUT')@endif

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <label class="crm-label">Activity Type <span class="text-red-500">*</span></label>
                <select name="type" class="select2 w-full">
                    @foreach ($types as $key => $label)
                        <option value="{{ $key }}" @selected(old('type', $activity->type) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="crm-label">Priority</label>
                <select name="priority" class="select2 w-full">
                    @foreach (['low'=>'Low','normal'=>'Normal','high'=>'High'] as $key => $label)
                        <option value="{{ $key }}" @selected(old('priority', $activity->priority) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="crm-label">Subject <span class="text-red-500">*</span></label>
            <input type="text" name="subject" value="{{ old('subject', $activity->subject) }}" required
                   class="crm-field">
        </div>

        <div>
            <label class="crm-label">Notes</label>
            <textarea name="description" rows="3" class="crm-field">{{ old('description', $activity->description) }}</textarea>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <label class="crm-label">Related Customer</label>
                <select name="account_id" class="select2 w-full" data-placeholder="— None —">
                    <option value="">— None —</option>
                    @foreach ($accounts as $acc)
                        <option value="{{ $acc->id }}" @selected(old('account_id', $activity->account_id ?? ($presetAccount ?? null)) === $acc->id)>{{ $acc->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="crm-label">Assigned to</label>
                <select name="assigned_to" class="select2 w-full">
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected((int) old('assigned_to', $activity->assigned_to ?? auth()->id()) === $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div>
                <label class="crm-label">Due Date</label>
                <input type="datetime-local" name="due_at"
                       value="{{ old('due_at', optional($activity->due_at)->format('Y-m-d\TH:i')) }}"
                       class="crm-field">
            </div>
            <div>
                <label class="crm-label">Reminder</label>
                <input type="datetime-local" name="reminder_at"
                       value="{{ old('reminder_at', optional($activity->reminder_at)->format('Y-m-d\TH:i')) }}"
                       class="crm-field">
            </div>
            <div>
                <label class="crm-label">Status</label>
                <select name="status" class="select2 w-full">
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}" @selected(old('status', $activity->status) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <input type="hidden" name="lead_id" value="{{ old('lead_id', $activity->lead_id ?? ($presetLead ?? '')) }}">

        <div class="flex items-center gap-3 border-t border-slate-100 pt-4">
            <x-btn type="submit" icon="bi-save">Save</x-btn>
            <x-btn href="{{ route('activities.index') }}" variant="secondary">Cancel</x-btn>
        </div>
    </form>
</x-card>
