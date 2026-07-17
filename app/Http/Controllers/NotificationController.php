<?php

namespace App\Http\Controllers;

use App\Models\CrmNotification;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(protected NotificationService $notifications)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $this->notifications->syncUpcomingForUser($user);

        $items = CrmNotification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('notifications.index', [
            'notifications' => $items,
        ]);
    }

    public function markRead(CrmNotification $notification)
    {
        abort_unless($notification->user_id === auth()->id(), 403);
        $notification->markAsRead();

        if (request()->wantsJson()) {
            return response()->json(['ok' => true]);
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

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    public function dismissPopups()
    {
        $this->notifications->markPopupsRead(auth()->id());

        return response()->json(['ok' => true]);
    }
}
