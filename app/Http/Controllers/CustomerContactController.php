<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Espo\Account;
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

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $this->contacts->createForAccount($account, $data);

        return redirect()->route('customers.show', $account->id)
            ->with('success', 'Contact added successfully.');
    }
}
