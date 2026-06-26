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
        <div class="sm:col-span-2">
            <label class="crm-label">Customer Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $account->name) }}" required
                   class="crm-field">
        </div>
        <div>
            <label class="crm-label">Type</label>
            <select name="type" class="select2 w-full" data-placeholder="— None —">
                <option value="">— None —</option>
                @foreach ($types as $t)
                    <option value="{{ $t }}" @selected(old('type', $account->type) === $t)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="crm-label">Industry</label>
            <input type="text" name="industry" value="{{ old('industry', $account->industry) }}"
                   class="crm-field">
        </div>
        <div>
            <label class="crm-label">Email</label>
            <input type="email" name="email" value="{{ old('email') }}"
                   class="crm-field">
        </div>
        <div>
            <label class="crm-label">Phone</label>
            <input type="text" name="phone" value="{{ old('phone') }}"
                   class="crm-field">
        </div>
        <div class="sm:col-span-2">
            <label class="crm-label">Website</label>
            <input type="text" name="website" value="{{ old('website', $account->website) }}"
                   class="crm-field">
        </div>
        <div class="sm:col-span-2">
            <label class="crm-label">Street Address</label>
            <input type="text" name="billing_address_street" value="{{ old('billing_address_street', $account->billing_address_street) }}"
                   class="crm-field">
        </div>
        <div>
            <label class="crm-label">City</label>
            <input type="text" name="billing_address_city" value="{{ old('billing_address_city', $account->billing_address_city) }}"
                   class="crm-field">
        </div>
        <div>
            <label class="crm-label">State</label>
            <input type="text" name="billing_address_state" value="{{ old('billing_address_state', $account->billing_address_state) }}"
                   class="crm-field">
        </div>
        <div>
            <label class="crm-label">Postal Code</label>
            <input type="text" name="billing_address_postal_code" value="{{ old('billing_address_postal_code', $account->billing_address_postal_code) }}"
                   class="crm-field">
        </div>
        <div>
            <label class="crm-label">Country</label>
            <input type="text" name="billing_address_country" value="{{ old('billing_address_country', $account->billing_address_country) }}"
                   class="crm-field">
        </div>
        @if (auth()->user()->isAdmin())
            <div class="sm:col-span-2">
                <label class="crm-label">Assign to Sales</label>
                <select name="assigned_user_id" class="select2 w-full" data-placeholder="— Unassigned —">
                    <option value="">— Unassigned —</option>
                    @foreach ($salesUsers as $u)
                        <option value="{{ $u->id }}" @selected(old('assigned_user_id', $account->assigned_user_id) === $u->id)>{{ $u->display_name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="sm:col-span-2">
            <label class="crm-label">Description</label>
            <textarea name="description" rows="3" class="crm-field">{{ old('description', $account->description) }}</textarea>
        </div>
    </div>

    <div class="flex items-center gap-2 border-t border-slate-100 pt-4">
        <x-btn type="submit" icon="bi-save">Save</x-btn>
        <x-btn href="{{ $cancelUrl }}" variant="secondary">Cancel</x-btn>
    </div>
</form>
