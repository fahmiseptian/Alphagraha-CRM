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
                <label class="crm-label">Customer <span class="text-red-500">*</span></label>
                <select name="account_id" id="activity-account" required class="select2 w-full" data-placeholder="Pilih customer">
                    <option value="">Pilih customer dahulu</option>
                    @foreach ($accounts as $acc)
                        <option value="{{ $acc->id }}" data-assigned-sales="{{ $acc->assigned_user_id }}" @selected(old('account_id', $activity->account_id ?? ($presetAccount ?? null)) === $acc->id)>{{ $acc->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <div class="mb-1 flex items-center justify-between gap-2">
                    <label class="crm-label mb-0">PIC Customer <span class="text-red-500">*</span></label>
                    @if (auth()->user()?->canCreateCustomerContact())
                        <a id="activity-add-contact" href="{{ route('contacts.create') }}" target="_blank" rel="noopener"
                           class="hidden whitespace-nowrap text-xs font-medium text-brand-600 hover:underline">
                            <i class="bi bi-person-plus"></i> Tambah PIC
                        </a>
                    @endif
                </div>
                <select name="contact_id" id="" required class="select2 w-full" data-placeholder="Pilih customer terlebih dahulu">
                    <option value="">Pilih customer terlebih dahulu</option>
                    @foreach ($contacts as $contact)
                        <option value="{{ $contact->id }}" data-account="{{ $contact->account_id }}"
                            @selected(old('contact_id', $activity->contact_id) === $contact->id)>
                            {{ $contact->full_name }}{{ $contact->job_role ? ' · '.$contact->job_role : '' }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-400">PIC mengikuti daftar Contact pada customer yang dipilih.</p>
            </div>
            <div>
                <label class="crm-label">Assigned to</label>
                <select name="assigned_to" id="activity-assigned-to" class="select2 w-full">
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected(old('assigned_to', $activity->assigned_to ?? auth()->id()) == $u->id)>{{ $u->display_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="crm-label">Lokasi Activity <span class="text-red-500">*</span></label>
            <input type="text" name="location" value="{{ old('location', $activity->location) }}" required maxlength="255" class="crm-field" placeholder="Nama tempat / alamat">
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <label class="crm-label">From <span class="text-red-500">*</span></label>
                <input type="datetime-local" name="starts_at" required
                       value="{{ old('starts_at', optional($activity->starts_at ?? $activity->due_at)->format('Y-m-d\TH:i')) }}"
                       class="crm-field">
            </div>
            <div>
                <label class="crm-label">To <span class="text-red-500">*</span></label>
                <input type="datetime-local" name="ends_at" required
                       value="{{ old('ends_at', optional($activity->ends_at ?? $activity->due_at)->format('Y-m-d\TH:i')) }}"
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

<script>
(function () {
    const account = document.getElementById('activity-account');
    const contact = document.getElementById('activity-contact');
    const addContact = document.getElementById('activity-add-contact');
    if (!account || !contact) return;
    const accountPlaceholder = account.options[0].cloneNode(true);
    const contactOptions = Array.from(contact.options).slice(1).map(option => option.cloneNode(true));

    const syncContacts = function () {
        const accountId = account.value;
        const selectedContactId = contact.value;
        const availableContacts = contactOptions.filter(option => !!accountId && option.dataset.account === accountId);
        contact.disabled = false;
        const placeholder = !accountId ? 'Pilih customer terlebih dahulu' : (availableContacts.length ? 'Pilih PIC customer' : 'Belum ada PIC untuk customer ini');
        const selected = availableContacts.some(option => option.value === selectedContactId) ? selectedContactId : '';
        if (addContact) {
            addContact.href = @json(route('contacts.create')) + (accountId ? '?account_id=' + encodeURIComponent(accountId) : '');
            addContact.classList.toggle('hidden', !accountId);
        }
        if (window.CrmSelect2) {
            window.CrmSelect2.setOptions(contact, availableContacts.map(option => ({
                id: option.value,
                text: option.textContent.trim(),
            })), selected, placeholder);
        } else {
            const placeholderOption = new Option(placeholder, '', false, false);
            contact.replaceChildren(placeholderOption, ...availableContacts.map(option => option.cloneNode(true)));
            contact.value = selected;
        }
    };

    if (window.jQuery) {
        window.jQuery(account).on('change', syncContacts);
    } else {
        account.addEventListener('change', syncContacts);
    }
    syncContacts();
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
