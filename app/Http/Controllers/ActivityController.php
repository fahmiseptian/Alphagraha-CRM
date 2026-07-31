<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Activity;
use App\Models\Espo\Account;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    use ScopesToUser;

    public function index(Request $request)
    {
        $filter = $request->get('filter', 'upcoming');
        $type = $request->get('type');

        $query = Activity::with(['account', 'lead', 'assignee']);

        // Sales hanya melihat aktivitas miliknya.
        if (! $this->isAdmin()) {
            $query->where('assigned_to', auth()->id());
        }

        if ($type) {
            $query->where('type', $type);
        }

        match ($filter) {
            'overdue' => $query->whereNotIn('status', ['completed', 'cancelled'])
                ->whereNotNull('due_at')->where('due_at', '<', now()),
            'completed' => $query->where('status', 'completed'),
            'all' => null,
            default => $query->whereNotIn('status', ['completed', 'cancelled']),
        };

        $activities = $query->orderByRaw('due_at IS NULL, due_at ASC')
            ->paginate(20)->withQueryString();

        return view('activities.index', [
            'activities' => $activities,
            'filter' => $filter,
            'type' => $type,
            'types' => Activity::TYPES,
        ]);
    }

    public function create(Request $request)
    {
        return view('activities.create', $this->formData() + [
            'activity' => new Activity(['type' => 'followup', 'priority' => 'normal', 'status' => 'planned']),
            'presetAccount' => $request->get('account_id'),
            'presetLead' => $request->get('lead_id'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['created_by'] = auth()->id();
        $data['assigned_to'] = $data['assigned_to'] ?? auth()->id();

        $activity = Activity::create($data);
        $calendarUrl = $activity->googleCalendarUrl();
        $editUrl = route('activities.edit', $activity);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'google_calendar_url' => $calendarUrl,
                'redirect' => $editUrl,
                'message' => 'Activity berhasil dibuat.',
            ]);
        }

        return redirect()
            ->route('activities.edit', $activity)
            ->with('success', 'Activity berhasil dibuat. Jika tab Calendar tidak terbuka, klik tombol di bawah.')
            ->with('google_calendar_url', $calendarUrl);
    }

    public function edit(Activity $activity)
    {
        $this->authorizeOwnership($activity);

        return view('activities.edit', $this->formData() + [
            'activity' => $activity,
            'mediaByCollection' => collect(Activity::MEDIA_COLLECTIONS)
                ->mapWithKeys(fn ($label, $key) => [$key => $activity->getMedia($key)]),
        ]);
    }

    public function update(Request $request, Activity $activity)
    {
        $this->authorizeOwnership($activity);

        $data = $this->validateData($request);
        $activity->update($data);

        if (in_array($activity->status, ['completed', 'cancelled'], true)) {
            app(\App\Services\NotificationService::class)->markActivityDueActioned($activity);
        }

        return redirect()->route('activities.edit', $activity)->with('success', 'Activity updated successfully.');
    }

    public function destroy(Activity $activity)
    {
        $this->authorizeOwnership($activity);
        $activity->delete();

        return back()->with('success', 'Activity deleted.');
    }

    public function complete(Activity $activity)
    {
        $this->authorizeOwnership($activity);
        $activity->update(['status' => 'completed', 'completed_at' => now()]);

        app(\App\Services\NotificationService::class)->markActivityDueActioned($activity);

        return back()->with('success', 'Activity marked as completed.');
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(array_keys(Activity::TYPES))],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'account_id' => ['nullable', 'string'],
            'lead_id' => ['nullable', 'string'],
            'status' => ['required', Rule::in(array_keys(Activity::STATUSES))],
            'priority' => ['required', Rule::in(['low', 'normal', 'high'])],
            'due_at' => ['nullable', 'date'],
            'reminder_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', Rule::exists('user', 'id')->where('deleted', 0)],
        ]);
    }

    protected function formData(): array
    {
        $accounts = $this->scopeAssigned(Account::query())
            ->orderBy('name')->get(['id', 'name']);

        $users = $this->isAdmin()
            ? User::internalActive()->orderBy('name')->get(['id', 'name'])
            : User::whereKey(auth()->id())->get(['id', 'name']);

        return [
            'accounts' => $accounts,
            'users' => $users,
            'types' => Activity::TYPES,
            'statuses' => Activity::STATUSES,
        ];
    }

    protected function authorizeOwnership(Activity $activity): void
    {
        if (! $this->isAdmin() && $activity->assigned_to !== auth()->id() && $activity->created_by !== auth()->id()) {
            abort(403, 'You do not have access to this activity.');
        }
    }
}
