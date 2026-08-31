<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Activity;
use App\Models\Espo\Account;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    use ScopesToUser;

    public function index(Request $request)
    {
        Activity::approvePastPendingEvents();

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
            'pending' => $query->where('type', Activity::TYPE_EVENT_TRAINING)
                ->where('approval_status', Activity::APPROVAL_PENDING),
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

        $activity = new Activity($data);
        $activity->syncEventApproval(auth()->user());
        $activity->save();
        $this->syncEventApprovalNotifications($activity);

        $calendarUrl = $activity->googleCalendarUrl();
        $editUrl = route('activities.edit', $activity);
        $message = $activity->isEventApprovalPending()
            ? 'Event/Training tersimpan dan menunggu approval Superadmin.'
            : 'Activity berhasil dibuat.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'google_calendar_url' => $calendarUrl,
                'redirect' => $editUrl,
                'message' => $message,
            ]);
        }

        return redirect()
            ->route('activities.edit', $activity)
            ->with('success', $message.' Jika tab Calendar tidak terbuka, klik tombol di bawah.')
            ->with('google_calendar_url', $calendarUrl);
    }

    public function edit(Activity $activity)
    {
        $this->authorizeOwnership($activity);
        Activity::approvePastPendingEvents();
        $activity->refresh();
        $activity->loadMissing('approver');

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
        $wasPending = $activity->isEventApprovalPending();
        $activity->fill($data);
        $activity->syncEventApproval(auth()->user());
        $activity->save();
        $this->syncEventApprovalNotifications($activity, $wasPending);

        if (in_array($activity->status, ['completed', 'cancelled'], true)) {
            app(NotificationService::class)->markActivityDueActioned($activity);
        }

        $message = $activity->isEventApprovalPending()
            ? 'Activity diupdate. Event/Training masih menunggu approval Superadmin.'
            : 'Activity updated successfully.';

        return redirect()->route('activities.edit', $activity)->with('success', $message);
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

        app(NotificationService::class)->markActivityDueActioned($activity);

        return back()->with('success', 'Activity marked as completed.');
    }

    public function approve(Request $request, Activity $activity)
    {
        if (! auth()->user()?->canApproveEventTraining()) {
            abort(403);
        }
        $this->authorizeOwnership($activity);
        Activity::approvePastPendingEvents();
        $activity->refresh();

        if (! $activity->isEventTraining()) {
            return back()->with('error', 'Hanya Event/Training yang memerlukan approval.');
        }

        if (! $activity->isEventApprovalPending()) {
            return back()->with('error', 'Event/Training tidak dalam status menunggu approval.');
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $activity->approval_status = Activity::APPROVAL_APPROVED;
        $activity->approved_by = auth()->id();
        $activity->approved_at = now();
        $activity->approval_note = $data['note'] ?? null;
        $activity->save();

        $notifications = app(NotificationService::class);
        $notifications->notifyEventApproved($activity, $data['note'] ?? null);
        $notifications->markEventApprovalActioned($activity->id);

        return back()->with('success', 'Event/Training disetujui.');
    }

    public function reject(Request $request, Activity $activity)
    {
        if (! auth()->user()?->canApproveEventTraining()) {
            abort(403);
        }
        $this->authorizeOwnership($activity);

        if (! $activity->isEventTraining()) {
            return back()->with('error', 'Hanya Event/Training yang memerlukan approval.');
        }

        if (! $activity->isEventApprovalPending()) {
            return back()->with('error', 'Event/Training tidak dalam status menunggu approval.');
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $activity->approval_status = Activity::APPROVAL_REJECTED;
        $activity->approved_by = auth()->id();
        $activity->approved_at = now();
        $activity->approval_note = $data['note'] ?? null;
        $activity->save();

        $notifications = app(NotificationService::class);
        $notifications->notifyEventRejected($activity, $data['note'] ?? null);
        $notifications->markEventApprovalActioned($activity->id);

        return back()->with('success', 'Event/Training ditolak.');
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

    protected function syncEventApprovalNotifications(Activity $activity, bool $wasPending = false): void
    {
        $notifications = app(NotificationService::class);

        if ($activity->isEventApprovalPending()) {
            $notifications->notifyEventApprovalRequested($activity);

            return;
        }

        if ($wasPending) {
            $notifications->markEventApprovalActioned($activity->id);
        }
    }
}
