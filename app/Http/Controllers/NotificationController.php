<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\CrmNotification;
use App\Models\Espo\EspoUser;
use App\Models\Espo\Opportunity;
use App\Models\Quotation;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class NotificationController extends Controller
{
    public function __construct(protected NotificationService $notifications)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $this->notifications->syncUpcomingForUser($user);

        $status = (string) $request->get('status', 'action');
        if (! in_array($status, ['all', 'unread', 'read', 'action'], true)) {
            $status = 'action';
        }

        $dateFrom = $this->parseDate($request->get('date_from'));
        $dateTo = $this->parseDate($request->get('date_to'));
        $salesId = trim((string) $request->get('sales_id', ''));
        $search = trim((string) $request->get('q', ''));
        if (mb_strlen($search) > 200) {
            $search = mb_substr($search, 0, 200);
        }

        $canFilterSales = (bool) $user?->isAdmin();
        $salesUsers = collect();
        if ($canFilterSales) {
            $salesUsers = EspoUser::query()
                ->activeSales()
                ->orderBy('name')
                ->get(['id', 'name', 'first_name', 'last_name', 'user_name']);

            if ($salesId !== '' && ! $salesUsers->contains(fn ($u) => $u->id === $salesId)) {
                $salesId = '';
            }
        } else {
            $salesId = '';
        }

        $query = CrmNotification::query()
            ->where('user_id', $user->id);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('body', 'like', $like);
            });
        }

        match ($status) {
            'unread' => $query->whereNull('read_at'),
            'read' => $query->whereNotNull('read_at'),
            'action' => $query->whereNull('read_at')->whereIn('type', [
                CrmNotification::TYPE_DISCOUNT_REQUESTED,
                CrmNotification::TYPE_MARGIN_REQUESTED,
                CrmNotification::TYPE_ACTIVITY_DUE,
                CrmNotification::TYPE_EVENT_APPROVAL_REQUESTED,
                CrmNotification::TYPE_OPPORTUNITY_DEADLINE,
                CrmNotification::TYPE_SALES_ORDER_CREATED,
                CrmNotification::TYPE_SALES_ORDER_CANCEL_REQUESTED,
                CrmNotification::TYPE_PURCHASE_ORDER_APPROVAL_REQUESTED,
            ]),
            default => null,
        };

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom->toDateString());
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo->toDateString());
        }

        if ($salesId !== '') {
            $this->applySalesFilter($query, $salesId);
        }

        $items = $query->orderByDesc('created_at')->paginate(30)->withQueryString();

        $hasFilters = $status !== 'action' || $dateFrom || $dateTo || $salesId !== '' || $search !== '';

        return view('notifications.index', [
            'notifications' => $items,
            'status' => $status,
            'dateFrom' => $dateFrom?->toDateString(),
            'dateTo' => $dateTo?->toDateString(),
            'salesId' => $salesId,
            'salesUsers' => $salesUsers,
            'canFilterSales' => $canFilterSales,
            'hasFilters' => $hasFilters,
            'search' => $search,
        ]);
    }

    public function markRead(CrmNotification $notification)
    {
        abort_unless($notification->user_id === auth()->id(), 403);

        // Yang butuh aksi: buka link saja, status tetap unread sampai aksi bisnis selesai.
        if (! $notification->requiresAction()) {
            $notification->markAsRead();
        } elseif ($notification->show_popup) {
            $notification->forceFill(['show_popup' => false])->save();
        }

        if (request()->wantsJson()) {
            return response()->json([
                'ok' => true,
                'read' => ! $notification->fresh()->isUnread(),
            ]);
        }

        if ($notification->link) {
            return redirect($notification->link);
        }

        return back();
    }

    public function markAllRead()
    {
        $this->notifications->markAllRead(auth()->id());

        if (request()->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Notifikasi informatif ditandai sudah dibaca. Yang menunggu aksi tetap aktif.');
    }

    public function destroySelected(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $deleted = $this->notifications->deleteForUser(auth()->id(), $data['ids']);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'deleted' => $deleted]);
        }

        return back()->with('success', $deleted.' notifikasi dihapus.');
    }

    public function dismissPopups()
    {
        $this->notifications->dismissPopups(auth()->id());

        return response()->json(['ok' => true]);
    }

    protected function parseDate(mixed $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Filter notifikasi yang terkait sales tertentu (request, opportunity, QO, activity).
     */
    protected function applySalesFilter($query, string $salesId): void
    {
        $opportunityIds = Opportunity::query()
            ->where('assigned_user_id', $salesId)
            ->pluck('id');

        $quotationIds = Quotation::query()
            ->where('created_by', $salesId)
            ->pluck('id');

        $activityIds = Activity::query()
            ->where('assigned_to', $salesId)
            ->pluck('id');

        $query->where(function ($q) use ($salesId, $opportunityIds, $quotationIds, $activityIds) {
            $q->where('data->requested_by', $salesId)
                ->orWhere('data->sales_user_id', $salesId);

            if ($opportunityIds->isNotEmpty()) {
                $q->orWhereIn('data->opportunity_id', $opportunityIds->all());
            }
            if ($quotationIds->isNotEmpty()) {
                $q->orWhereIn('data->quotation_id', $quotationIds->all());
            }
            if ($activityIds->isNotEmpty()) {
                $q->orWhereIn('data->activity_id', $activityIds->all());
            }
        });
    }
}
