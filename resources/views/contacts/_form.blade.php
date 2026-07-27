<form method="POST" action="{{ $action }}">
    @csrf
    @if (($method ?? 'POST') === 'PUT')@method('PUT')@endif

    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-5">
        <div>
            <label class="crm-label">Customer <span class="text-red-500">*</span></label>
            <select name="account_id" required class="select2 select2-search w-full" data-placeholder="— Select customer —">
                <option value="">— Select customer —</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}" @selected(old('account_id', $selectedAccountId ?? null) === $account->id)>
                        {{ $account->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <label class="crm-label">First Name <span class="text-red-500">*</span></label>
                <input type="text" name="first_name" value="{{ old('first_name', $contact->first_name ?? '') }}" required class="crm-field">
            </div>
            <div>
                <label class="crm-label">Last Name</label>
                <input type="text" name="last_name" value="{{ old('last_name', $contact->last_name ?? '') }}" class="crm-field">
            </div>
            <div class="sm:col-span-2">
                <label class="crm-label">Job Role</label>
                <input type="text" name="job_role" value="{{ old('job_role', $contact->job_role ?? '') }}" placeholder="Contoh: Purchasing, IT Manager" class="crm-field">
            </div>
            <div>
                <label class="crm-label">Email</label>
                <input type="email" name="email" value="{{ old('email', $contact->email ?? '') }}" class="crm-field">
            </div>
            <div>
                <label class="crm-label">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $contact->phone ?? '') }}" class="crm-field">
            </div>
        </div>

        <div class="flex flex-wrap gap-2 border-t border-slate-100 pt-5">
            <x-btn type="submit" icon="bi-save">Save Contact</x-btn>
            <x-btn href="{{ $cancelUrl }}" variant="secondary">Cancel</x-btn>
        </div>
    </div>
</form>
