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
}
