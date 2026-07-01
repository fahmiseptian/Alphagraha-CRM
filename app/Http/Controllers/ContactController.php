<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Espo\Account;
use App\Models\Espo\Contact;
use App\Services\ContactService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    use ScopesToUser;

    public function __construct(
        protected ContactService $contacts
    ) {}

    public function index(Request $request)
    {
        $search = trim((string) $request->get('q'));

        $query = Contact::query()
            ->with(['account', 'emailAddresses', 'phoneNumbers'])
            ->whereIn('account_id', $this->scopeAssigned(Account::query())->select('id'));

        if ($search !== '') {
            $like = '%'.$search.'%';

            $query->where(function ($q) use ($like) {
                $q->where('contact.first_name', 'like', $like)
                    ->orWhere('contact.last_name', 'like', $like)
                    ->orWhere('contact.name', 'like', $like)
                    ->orWhereHas('account', fn ($aq) => $aq->where('account.name', 'like', $like))
                    ->orWhereHas('emailAddresses', fn ($eq) => $eq->where('name', 'like', $like))
                    ->orWhereHas('phoneNumbers', fn ($pq) => $pq->where('name', 'like', $like));
            });
        }

        $contacts = $query->orderByDesc('contact.created_at')->paginate(15)->withQueryString();

        return view('contacts.index', compact('contacts', 'search'));
    }

    public function create(Request $request)
    {
        $accountId = $request->get('account_id');
        if ($accountId && ! $this->scopeAssigned(Account::query())->where('id', $accountId)->exists()) {
            $accountId = null;
        }

        return view('contacts.create', [
            'accounts' => $this->scopeAssigned(Account::query())->orderBy('name')->get(['id', 'name']),
            'selectedAccountId' => $accountId,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'account_id' => ['required', 'string', Rule::exists('account', 'id')->where('deleted', 0)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $account = $this->scopeAssigned(Account::query())->findOrFail($data['account_id']);

        $this->contacts->createForAccount($account, $data);

        return redirect()->route('contacts.index')
            ->with('success', 'Contact added successfully.');
    }

    public function edit(string $id)
    {
        $contact = Contact::query()
            ->with(['account', 'emailAddresses', 'phoneNumbers'])
            ->whereIn('account_id', $this->scopeAssigned(Account::query())->select('id'))
            ->findOrFail($id);

        return view('contacts.edit', [
            'contact' => $contact,
            'accounts' => $this->scopeAssigned(Account::query())->orderBy('name')->get(['id', 'name']),
            'selectedAccountId' => $contact->account_id,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $contact = Contact::query()
            ->whereIn('account_id', $this->scopeAssigned(Account::query())->select('id'))
            ->findOrFail($id);

        $data = $request->validate([
            'account_id' => ['required', 'string', Rule::exists('account', 'id')->where('deleted', 0)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $account = $this->scopeAssigned(Account::query())->findOrFail($data['account_id']);

        $this->contacts->update($contact, $account, $data);

        return redirect()->route('contacts.index')
            ->with('success', 'Contact updated successfully.');
    }
}
