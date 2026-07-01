<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Activity;
use App\Models\Espo\EspoUser;
use App\Models\Espo\Lead;
use App\Services\EspoEntityWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    use ScopesToUser;

    public function __construct(
        protected EspoEntityWriter $writer
    ) {}

    public function index(Request $request)
    {
        $search = trim((string) $request->get('q'));
        $status = $request->get('status');

        $query = $this->scopeAssigned(Lead::query())->with('assignedUser');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('account_name', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $leads = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        $statusCounts = $this->scopeAssigned(Lead::query())
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return view('leads.index', [
            'leads' => $leads,
            'search' => $search,
            'status' => $status,
            'statuses' => Lead::STATUSES,
            'statusCounts' => $statusCounts,
        ]);
    }

    public function create()
    {
        $lead = new Lead([
            'status' => 'New',
            'assigned_user_id' => auth()->id(),
        ]);

        return view('leads.create', $this->formData() + compact('lead'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $now = Carbon::now()->format('Y-m-d H:i:s');

        $lead = new Lead();
        $lead->id = $this->writer->generateId();
        $lead->deleted = 0;
        $lead->created_at = $now;
        $lead->modified_at = $now;
        $lead->created_by_id = auth()->id();
        $this->applyValidatedData($lead, $data, isNew: true);
        $lead->save();

        $this->writer->syncPrimaryEmail($lead->id, 'Lead', $data['email'] ?? null);
        $this->writer->syncPrimaryPhone($lead->id, 'Lead', $data['phone'] ?? null);

        return redirect()->route('leads.show', $lead->id)
            ->with('success', 'Lead created successfully.');
    }

    public function show(string $id)
    {
        $lead = $this->scopeAssigned(Lead::query())
            ->with(['assignedUser', 'emailAddresses', 'phoneNumbers'])
            ->findOrFail($id);

        $activities = $lead->activities()->with('assignee')->latest()->get();
        $salesUsers = EspoUser::query()->activeRegular()->orderBy('name')->get();

        return view('leads.show', [
            'lead' => $lead,
            'activities' => $activities,
            'salesUsers' => $salesUsers,
            'statuses' => Lead::STATUSES,
        ]);
    }

    public function edit(string $id)
    {
        $lead = $this->scopeAssigned(Lead::query())
            ->with(['emailAddresses', 'phoneNumbers'])
            ->findOrFail($id);

        return view('leads.edit', $this->formData() + compact('lead'));
    }

    public function update(Request $request, string $id)
    {
        $lead = $this->scopeAssigned(Lead::query())->findOrFail($id);
        $data = $this->validateData($request);

        $this->applyValidatedData($lead, $data);
        $lead->modified_at = Carbon::now()->format('Y-m-d H:i:s');
        $lead->save();

        $this->writer->syncPrimaryEmail($lead->id, 'Lead', $data['email'] ?? null);
        $this->writer->syncPrimaryPhone($lead->id, 'Lead', $data['phone'] ?? null);

        return redirect()->route('leads.show', $lead->id)
            ->with('success', 'Lead updated successfully.');
    }

    public function quickUpdate(Request $request, string $id)
    {
        $lead = $this->scopeAssigned(Lead::query())->findOrFail($id);

        $data = $request->validate([
            'status' => ['required', Rule::in(Lead::STATUSES)],
            'assigned_user_id' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        $lead->status = $data['status'];
        $lead->description = $data['description'] ?? $lead->description;

        if ($this->isAdmin() && array_key_exists('assigned_user_id', $data)) {
            $lead->assigned_user_id = $data['assigned_user_id'] ?: null;
        }

        $lead->modified_at = now();
        $lead->save();

        Activity::create([
            'type' => 'note',
            'subject' => 'Lead status updated to "' . $data['status'] . '"',
            'lead_id' => $lead->id,
            'status' => 'completed',
            'completed_at' => now(),
            'assigned_to' => auth()->id(),
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Lead updated successfully.');
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'salutation_name' => ['nullable', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:100'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(Lead::STATUSES)],
            'source' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address_street' => ['nullable', 'string', 'max:255'],
            'address_city' => ['nullable', 'string', 'max:100'],
            'address_state' => ['nullable', 'string', 'max:100'],
            'address_country' => ['nullable', 'string', 'max:100'],
            'address_postal_code' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'assigned_user_id' => ['nullable', 'string', Rule::exists('user', 'id')->where('deleted', 0)],
        ]);
    }

    protected function applyValidatedData(Lead $lead, array $data, bool $isNew = false): void
    {
        $first = trim($data['first_name'] ?? '');
        $last = trim($data['last_name'] ?? '');

        $lead->fill([
            'salutation_name' => $data['salutation_name'] ?? null,
            'first_name' => $first,
            'last_name' => $last ?: null,
            'name' => trim($first . ' ' . $last) ?: $first,
            'title' => $data['title'] ?? null,
            'account_name' => $data['account_name'] ?? null,
            'status' => $data['status'],
            'source' => $data['source'] ?? null,
            'industry' => $data['industry'] ?? null,
            'website' => $data['website'] ?? null,
            'address_street' => $data['address_street'] ?? null,
            'address_city' => $data['address_city'] ?? null,
            'address_state' => $data['address_state'] ?? null,
            'address_country' => $data['address_country'] ?? null,
            'address_postal_code' => $data['address_postal_code'] ?? null,
            'description' => $data['description'] ?? null,
        ]);

        if ($this->isAdmin()) {
            $lead->assigned_user_id = ($data['assigned_user_id'] ?? null) ?: null;
        } elseif ($isNew) {
            $lead->assigned_user_id = auth()->id();
        }
    }

    protected function formData(): array
    {
        return [
            'statuses' => Lead::STATUSES,
            'salesUsers' => EspoUser::query()->activeRegular()->orderBy('name')->get(),
        ];
    }
}
