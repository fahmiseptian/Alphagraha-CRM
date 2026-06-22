<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Espo\Account;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ScopesToUser;

    public function index(Request $request)
    {
        $search = trim((string) $request->get('q'));
        $type = $request->get('type');

        $query = $this->scopeAssigned(Account::query())
            ->with(['assignedUser', 'emailAddresses', 'phoneNumbers'])
            ->withCount('opportunities');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('website', 'like', "%{$search}%")
                    ->orWhere('billing_address_city', 'like', "%{$search}%");
            });
        }

        if ($type) {
            $query->where('type', $type);
        }

        $accounts = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        $types = $this->scopeAssigned(Account::query())
            ->whereNotNull('type')->where('type', '<>', '')
            ->distinct()->orderBy('type')->pluck('type');

        return view('customers.index', compact('accounts', 'search', 'type', 'types'));
    }

    public function show(string $id)
    {
        $account = $this->scopeAssigned(Account::query())
            ->with(['assignedUser', 'emailAddresses', 'phoneNumbers'])
            ->findOrFail($id);

        $contacts = $account->contacts()->with(['emailAddresses', 'phoneNumbers'])->get();

        $opportunities = $account->opportunities()
            ->with('assignedUser')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $quotations = $account->quotations()->latest()->get();

        $activities = $account->activities()
            ->with('assignee')
            ->orderByRaw('COALESCE(due_at, created_at) DESC')
            ->limit(30)
            ->get();

        return view('customers.show', compact('account', 'contacts', 'opportunities', 'quotations', 'activities'));
    }
}
