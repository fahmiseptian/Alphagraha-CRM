<?php

namespace App\Providers;

use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Pagination\Paginator::defaultView('vendor.pagination.crm');

        $fontDir = storage_path('fonts');
        if (! is_dir($fontDir)) {
            mkdir($fontDir, 0755, true);
        }

        View::composer('layouts.app', function ($view) {
            $user = Auth::user();
            if (! $user) {
                $view->with([
                    'crmUnreadNotificationCount' => 0,
                    'crmRecentNotifications' => collect(),
                    'crmPopupNotifications' => collect(),
                ]);

                return;
            }

            $service = app(NotificationService::class);

            $syncKey = 'crm_notif_synced_at';
            $lastSync = (int) session($syncKey, 0);
            if ($lastSync < now()->subMinutes(15)->timestamp) {
                try {
                    $service->syncUpcomingForUser($user);
                    session([$syncKey => now()->timestamp]);
                } catch (\Throwable $e) {
                    // Jangan gagalkan halaman jika sync notifikasi error.
                    report($e);
                }
            }

            $view->with([
                'crmUnreadNotificationCount' => $service->unreadCount($user->id),
                'crmRecentNotifications' => $service->recent($user->id),
                'crmPopupNotifications' => $service->unreadPopups($user->id),
            ]);
        });
    }
}
