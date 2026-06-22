<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Activity;
use App\Models\Espo\EspoUser;
use App\Models\Espo\Lead;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    use ScopesToUser;

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

        // Ringkasan jumlah per status (mengikuti scope user).
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

    public function show(string $id)
    {
        $lead = $this->scopeAssigned(Lead::query())->with('assignedUser')->findOrFail($id);

        $activities = $lead->activities()->with('assignee')->latest()->get();
        $salesUsers = EspoUser::query()->activeRegular()->orderBy('name')->get();

        return view('leads.show', compact('lead', 'activities', 'salesUsers'));
    }

    public function update(Request $request, string $id)
    {
        $lead = $this->scopeAssigned(Lead::query())->findOrFail($id);

        $data = $request->validate([
            'status' => ['required', Rule::in(Lead::STATUSES)],
            'assigned_user_id' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        $lead->status = $data['status'];
        $lead->description = $data['description'] ?? $lead->description;

        // Hanya admin yang boleh memindahkan assignment.
        if ($this->isAdmin() && array_key_exists('assigned_user_id', $data)) {
            $lead->assigned_user_id = $data['assigned_user_id'] ?: null;
        }

        $lead->modified_at = now();
        $lead->save();

        // Catat perubahan sebagai aktivitas otomatis.
        Activity::create([
            'type' => 'note',
            'subject' => 'Status lead diperbarui menjadi "' . $data['status'] . '"',
            'lead_id' => $lead->id,
            'status' => 'completed',
            'completed_at' => now(),
            'assigned_to' => auth()->id(),
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Data lead berhasil diperbarui.');
    }
}
