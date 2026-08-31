@php $isCreate = ($method ?? 'POST') === 'POST'; @endphp
<x-card>
    <form method="POST" action="{{ $action }}" class="space-y-5" @if ($isCreate) id="activity-create-form" @endif>
        @csrf
        @if (! $isCreate)@method('PUT')@endif

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <label class="crm-label">Activity Type <span class="text-red-500">*</span></label>
                <select name="type" class="select2 w-full" id="activity-type">
                    @foreach ($types as $key => $label)
                        <option value="{{ $key }}" @selected(old('type', $activity->type) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <p id="event-approval-hint" class="mt-1 hidden text-xs text-amber-600">
                    Event/Training memerlukan approval Superadmin. Jika tanggal sudah lewat, status otomatis Approved.
                </p>
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
                        <option value="{{ $u->id }}" @selected(old('assigned_to', $activity->assigned_to ?? auth()->id()) == $u->id)>{{ $u->display_name }}</option>
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

<script>
(function () {
    const select = document.getElementById('activity-type');
    const hint = document.getElementById('event-approval-hint');
    if (!select || !hint) return;

    const sync = function () {
        hint.classList.toggle('hidden', select.value !== 'event_training');
    };

    sync();
    if (window.jQuery) {
        window.jQuery(select).on('change', sync);
    } else {
        select.addEventListener('change', sync);
    }
})();
</script>

@if ($isCreate)
<script>
(function () {
    const form = document.getElementById('activity-create-form');
    if (!form) return;

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        // Buka tab baru saat klik Save (agar tidak diblokir popup blocker).
        const calendarTab = window.open('about:blank', '_blank');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (response.status === 422) {
                if (calendarTab) calendarTab.close();
                if (submitBtn) submitBtn.disabled = false;
                const errors = await response.json();
                const messages = errors.errors
                    ? Object.values(errors.errors).flat().join('\n')
                    : 'Validasi gagal.';
                alert(messages);
                return;
            }

            if (!response.ok) {
                if (calendarTab) calendarTab.close();
                if (submitBtn) submitBtn.disabled = false;
                const err = await response.json().catch(() => null);
                alert(err?.message || 'Gagal menyimpan activity.');
                return;
            }

            const data = await response.json();
            const calendarUrl = data.google_calendar_url || '';
            const redirectUrl = data.redirect || @json(route('activities.index'));

            if (calendarUrl) {
                if (calendarTab && !calendarTab.closed) {
                    calendarTab.location.href = calendarUrl;
                    // Tunggu sebentar agar tab Calendar sempat load sebelum halaman utama pindah.
                    setTimeout(function () {
                        window.location.href = redirectUrl;
                    }, 400);
                    return;
                }

                // Popup diblokir — buka di tab ini sebagai fallback, atau tampilkan link.
                const opened = window.open(calendarUrl, '_blank', 'noopener');
                if (!opened) {
                    alert('Activity tersimpan. Izinkan popup browser, atau buka Google Calendar dari tombol di halaman berikutnya.');
                }
            }

            window.location.href = redirectUrl;
        } catch (err) {
            if (calendarTab) calendarTab.close();
            if (submitBtn) submitBtn.disabled = false;
            alert('Terjadi kesalahan. Coba lagi.');
        }
    });
})();
</script>
@endif
