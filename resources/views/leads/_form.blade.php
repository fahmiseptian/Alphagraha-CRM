<form method="POST" action="{{ $action }}" class="space-y-5">
    @csrf
    @if (($method ?? 'POST') === 'PUT')@method('PUT')@endif

    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div>
            <label class="crm-label">First Name <span class="text-red-500">*</span></label>
            <input type="text" name="first_name" value="{{ old('first_name', $lead->first_name) }}" required
                   class="crm-field">
        </div>
        <div>
            <label class="crm-label">Last Name</label>
            <input type="text" name="last_name" value="{{ old('last_name', $lead->last_name) }}"
                   class="crm-field">
        </div>
        <div>
            <label class="crm-label">Title</label>
            <input type="text" name="title" value="{{ old('title', $lead->title) }}"
                   class="crm-field">
        </div>
        <div>
            <label class="crm-label">Company</label>
            <input type="text" name="account_name" value="{{ old('account_name', $lead->account_name) }}"
                   class="crm-field">
        </div>
        <div>
            <label class="crm-label">Status <span class="text-red-500">*</span></label>
            <select name="status" class="select2 w-full">
                @foreach ($statuses as $st)
                    <option value="{{ $st }}" @selected(old('status', $lead->status) === $st)>{{ $st }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="crm-label">Source</label>
            <input type="text" name="source" value="{{ old('source', $lead->source) }}"
                   class="crm-field">
        </div>
        <div>
            <label class="crm-label">Industry</label>
            <select name="industry" class="select2 w-full" data-placeholder="— Pilih industri —">
                <option value="">— None —</option>
                @foreach ($industries ?? [] as $industry)
                    <option value="{{ $industry->name }}" @selected(old('industry', $lead->industry) === $industry->name)>{{ $industry->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="crm-label">Website</label>
            <input type="text" name="website" value="{{ old('website', $lead->website) }}"
                   class="crm-field">
        </div>
        <div>
            <label class="crm-label">Email</label>
            <input type="email" name="email" value="{{ old('email', $lead->email ?? '') }}"
                   class="crm-field">
        </div>
        <div>
            <label class="crm-label">Phone</label>
            <input type="text" name="phone" value="{{ old('phone', $lead->phone ?? '') }}"
                   class="crm-field">
        </div>
        <div class="sm:col-span-2">
            <label class="crm-label">Street Address</label>
            <input type="text" name="address_street" value="{{ old('address_street', $lead->address_street) }}"
                   class="crm-field">
        </div>
        <div>
            <label class="crm-label">City</label>
            <input type="text" name="address_city" value="{{ old('address_city', $lead->address_city) }}"
                   class="crm-field">
        </div>
        <div>
            <label class="crm-label">State</label>
            <input type="text" name="address_state" value="{{ old('address_state', $lead->address_state) }}"
                   class="crm-field">
        </div>
        <div>
            <label class="crm-label">Postal Code</label>
            <input type="text" name="address_postal_code" value="{{ old('address_postal_code', $lead->address_postal_code) }}"
                   class="crm-field">
        </div>
        <div>
            <label class="crm-label">Country</label>
            <input type="text" name="address_country" value="{{ old('address_country', $lead->address_country) }}"
                   class="crm-field">
        </div>
        @if (auth()->user()->isAdmin())
            <div class="sm:col-span-2">
                <label class="crm-label">Assign to Sales</label>
                <select name="assigned_user_id" class="select2 w-full" data-placeholder="— Unassigned —">
                    <option value="">— Unassigned —</option>
                    @foreach ($salesUsers as $u)
                        <option value="{{ $u->id }}" @selected(old('assigned_user_id', $lead->assigned_user_id) === $u->id)>{{ $u->display_name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="sm:col-span-2">
            <label class="crm-label">Description</label>
            <textarea name="description" rows="3" class="crm-field">{{ old('description', $lead->description) }}</textarea>
        </div>
    </div>

    <div class="flex items-center gap-2 border-t border-slate-100 pt-4">
        <x-btn type="submit" icon="bi-save">Save</x-btn>
        <x-btn href="{{ $cancelUrl }}" variant="secondary">Cancel</x-btn>
    </div>
</form>
