<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Espo\Account;
use App\Models\Espo\Contact;
use App\Services\ContactService;
use Illuminate\Http\Request;

class CustomerContactController extends Controller
{
    use ScopesToUser;

    public function __construct(
        protected ContactService $contacts
    ) {}

    public function store(Request $request, string $accountId)
    {
        if (! auth()->user()?->canCreateCustomerContact()) {
            abort(403, 'Anda tidak memiliki akses untuk menambah contact.');
        }

        $account = $this->scopeAssigned(Account::query())->findOrFail($accountId);

        $data = $this->validatedContact($request);

        $this->contacts->createForAccount($account, $data);

        return redirect()->route('customers.show', $account->id)
            ->with('success', 'Contact added successfully.');
    }

    public function update(Request $request, string $accountId, string $contactId)
    {
        if (! auth()->user()?->canEditCustomerContact()) {
            abort(403, 'Anda tidak memiliki akses untuk mengedit contact.');
        }

        $account = $this->scopeAssigned(Account::query())->findOrFail($accountId);

        $contact = Contact::query()
            ->where('account_id', $account->id)
            ->findOrFail($contactId);

        $data = $this->validatedContact($request);
        $data['account_id'] = $account->id;

        $this->contacts->update($contact, $account, $data);

        return redirect()->route('customers.show', $account->id)
            ->with('success', 'PIC customer berhasil diperbarui.');
    }

    protected function validatedContact(Request $request): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'job_role' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);
    }
}
