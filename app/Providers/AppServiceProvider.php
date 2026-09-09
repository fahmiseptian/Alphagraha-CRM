<?php

namespace App\Providers;

use App\Models\Espo\Account;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
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
                    'crmMissingIndustryCount' => 0,
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

            $missingIndustryCount = 0;
            if ($user->isSales() && Schema::hasTable('account')) {
                try {
                    $missingIndustryCount = Account::query()
                        ->where('assigned_user_id', $user->id)
                        ->where(function ($q) {
                            $q->whereNull('industry')->orWhere('industry', '');
                        })
                        ->count();
                } catch (\Throwable $e) {
                    $missingIndustryCount = 0;
                }
            }

            $view->with([
                'crmUnreadNotificationCount' => $service->unreadCount($user->id),
                'crmRecentNotifications' => $service->recent($user->id),
                'crmPopupNotifications' => $service->unreadPopups($user->id),
                'crmMissingIndustryCount' => $missingIndustryCount,
            ]);
        });
    }
}
