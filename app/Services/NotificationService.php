<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\CrmNotification;
use App\Models\Espo\Opportunity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class NotificationService
{
    public function notify(
        string $userId,
        string $type,
        string $title,
        ?string $body = null,
        ?string $link = null,
        bool $showPopup = false,
        ?string $uniqueKey = null,
        ?array $data = null,
    ): CrmNotification {
        if ($uniqueKey) {
            $existing = CrmNotification::query()
                ->where('user_id', $userId)
                ->where('unique_key', $uniqueKey)
                ->first();

            if ($existing) {
                // Jangan reset status baca — cukup perbarui isi jika masih unread.
                if ($existing->isUnread()) {
                    $existing->fill([
                        'type' => $type,
                        'title' => $title,
                        'body' => $body,
                        'link' => $link,
                        'show_popup' => $showPopup || $existing->show_popup,
                        'data' => $data,
                    ])->save();
                }

                return $existing->fresh();
            }
        }

        return CrmNotification::query()->create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'link' => $link,
            'unique_key' => $uniqueKey,
            'data' => $data,
            'show_popup' => $showPopup,
        ]);
    }

    public function notifyDiscountApproved(Opportunity $opportunity, float $amount, bool $revised = false, ?string $note = null): void
    {
        $userIds = collect([
            $opportunity->assigned_user_id,
            $opportunity->crm_discount_requested_by,
        ])->filter()->unique()->values();

        $currency = $opportunity->amount_currency ?: 'IDR';
        $formatted = function_exists('money') ? money($amount, $currency) : number_format($amount, 0, ',', '.');
        $pct = $opportunity->discountPercent();
        $pctLabel = $pct !== null ? ' ('.number_format($pct, 2, ',', '.').'% dari margin)' : '';

        $type = $revised
            ? CrmNotification::TYPE_DISCOUNT_REVISED
            : CrmNotification::TYPE_DISCOUNT_APPROVED;

        $title = $revised
            ? 'Diskon disesuaikan & disetujui'
            : 'Diskon disetujui';

        $body = $revised
            ? 'Superadmin menyesuaikan diskon Opportunity "'.$opportunity->name.'" menjadi '.$formatted.$pctLabel.'.'
            : 'Diskon Opportunity "'.$opportunity->name.'" sebesar '.$formatted.$pctLabel.' telah disetujui.';

        if ($note) {
            $body .= ' Catatan: '.$note;
        }

        $link = route('opportunities.show', $opportunity);

        foreach ($userIds as $userId) {
            $this->notify(
                $userId,
                $type,
                $title,
                $body,
                $link,
                showPopup: true,
                uniqueKey: 'discount:'.$opportunity->id.':'.uniqid('', true),
                data: [
                    'opportunity_id' => $opportunity->id,
                    'amount' => $amount,
                    'revised' => $revised,
                ],
            );
        }
    }

    /**
     * Popup + bell untuk semua Superadmin saat sales request diskon tambahan.
     */
    public function notifyDiscountRequested(Opportunity $opportunity): void
    {
        $amount = (float) ($opportunity->crm_discount_amount ?? 0);
        if ($amount <= 0) {
            return;
        }

        $currency = $opportunity->amount_currency ?: 'IDR';
        $formatted = function_exists('money') ? money($amount, $currency) : number_format($amount, 0, ',', '.');
        $pct = $opportunity->discountPercent();
        $pctLabel = $pct !== null ? ' ('.number_format($pct, 2, ',', '.').'% dari margin)' : '';

        $requesterId = $opportunity->crm_discount_requested_by;
        $requester = $requesterId
            ? User::query()->whereKey($requesterId)->first()
            : null;
        $requesterName = $requester?->display_name ?? 'Sales';

        $link = route('opportunities.show', $opportunity);
        $title = 'Request diskon tambahan';
        $body = $requesterName.' mengajukan diskon '.$formatted.$pctLabel.' pada Opportunity "'.$opportunity->name.'".';

        $superAdminIds = User::query()
            ->where('deleted', 0)
            ->where('is_active', 1)
            ->whereHas('profile', fn ($q) => $q->where('app_role', User::ROLE_SUPERADMIN))
            ->pluck('id')
            ->filter(fn ($id) => $id !== $requesterId)
            ->values();

        foreach ($superAdminIds as $userId) {
            $this->notify(
                $userId,
                CrmNotification::TYPE_DISCOUNT_REQUESTED,
                $title,
                $body,
                $link,
                showPopup: true,
                uniqueKey: 'discount_request:'.$opportunity->id.':'.uniqid('', true),
                data: [
                    'opportunity_id' => $opportunity->id,
                    'amount' => $amount,
                    'requested_by' => $requesterId,
                ],
            );
        }
    }

    /**
     * Notifikasi ke sales saat Superadmin menolak request diskon (dengan nominal counter-offer).
     */
    public function notifyDiscountRejected(
        Opportunity $opportunity,
        float $approvedAmount,
        ?string $note = null,
        ?float $requestedAmount = null
    ): void {
        $userIds = collect([
            $opportunity->assigned_user_id,
            $opportunity->crm_discount_requested_by,
        ])->filter()->unique()->values();

        $currency = $opportunity->amount_currency ?: 'IDR';
        $formattedApproved = function_exists('money')
            ? money($approvedAmount, $currency)
            : number_format($approvedAmount, 0, ',', '.');

        $title = 'Diskon ditolak';
        if ($approvedAmount > 0) {
            $body = 'Permintaan diskon Opportunity "'.$opportunity->name.'" ditolak. Nominal yang disetujui: '.$formattedApproved.'.';
        } else {
            $body = 'Permintaan diskon Opportunity "'.$opportunity->name.'" ditolak.';
        }

        if ($requestedAmount !== null && $requestedAmount > 0 && abs($requestedAmount - $approvedAmount) > 0.009) {
            $formattedRequested = function_exists('money')
                ? money($requestedAmount, $currency)
                : number_format($requestedAmount, 0, ',', '.');
            $body .= ' (diajukan: '.$formattedRequested.')';
        }

        if ($note) {
            $body .= ' Catatan: '.$note;
        }

        $link = route('opportunities.show', $opportunity);

        foreach ($userIds as $userId) {
            $this->notify(
                $userId,
                CrmNotification::TYPE_DISCOUNT_REJECTED,
                $title,
                $body,
                $link,
                showPopup: true,
                uniqueKey: 'discount_reject:'.$opportunity->id.':'.uniqid('', true),
                data: [
                    'opportunity_id' => $opportunity->id,
                    'approved_amount' => $approvedAmount,
                    'requested_amount' => $requestedAmount,
                ],
            );
        }
    }

    /**
     * Notifikasi ke sales saat Superadmin mengembalikan ke pending.
     */
    public function notifyDiscountReverted(Opportunity $opportunity, ?string $note = null): void
    {
        $userIds = collect([
            $opportunity->assigned_user_id,
            $opportunity->crm_discount_requested_by,
        ])->filter()->unique()->values();

        $title = 'Diskon dikembalikan ke pending';
        $body = 'Keputusan diskon Opportunity "'.$opportunity->name.'" dikembalikan ke menunggu approval.';
        if ($note) {
            $body .= ' Catatan: '.$note;
        }

        $link = route('opportunities.show', $opportunity);

        foreach ($userIds as $userId) {
            $this->notify(
                $userId,
                CrmNotification::TYPE_DISCOUNT_REVERTED,
                $title,
                $body,
                $link,
                showPopup: true,
                uniqueKey: 'discount_revert:'.$opportunity->id.':'.uniqid('', true),
                data: ['opportunity_id' => $opportunity->id],
            );
        }
    }

    /**
     * Sinkronkan notifikasi deadline/acara mendekati untuk user.
     */
    public function syncUpcomingForUser(User $user): void
    {
        $until = Carbon::now()->addDays(config('crm.deadline_alert_days', 7));

        $this->syncOpportunityDeadlines($user, $until);
        $this->syncActivityDeadlines($user, $until);
    }

    protected function syncOpportunityDeadlines(User $user, Carbon $until): void
    {
        $query = Opportunity::query()
            ->whereIn('stage', Opportunity::OPEN_STAGES)
            ->whereNotNull('close_date')
            ->where('close_date', '<=', $until->toDateString());

        if ($user->isSales()) {
            $query->where('assigned_user_id', $user->id);
        } elseif (! $user->canViewAllOpportunities()) {
            return;
        } else {
            // Admin/superadmin: hanya yang assigned ke mereka (opsional), skip mass notify.
            $query->where('assigned_user_id', $user->id);
        }

        $query->orderBy('close_date')->limit(30)->get()->each(function (Opportunity $opp) use ($user) {
            $date = Carbon::parse($opp->close_date)->startOfDay();
            $label = $this->deadlineLabel($date);
            $overdue = $date->isPast();

            $this->notify(
                $user->id,
                CrmNotification::TYPE_OPPORTUNITY_DEADLINE,
                ($overdue ? 'Deadline lewat: ' : 'Deadline mendekati: ').$opp->name,
                'Close date '.$date->translatedFormat('d M Y').' — '.$label,
                route('opportunities.show', $opp),
                showPopup: $overdue || $date->isToday() || $date->isTomorrow(),
                uniqueKey: 'opp_deadline:'.$opp->id.':'.$date->toDateString(),
                data: ['opportunity_id' => $opp->id, 'close_date' => $date->toDateString()],
            );
        });
    }

    protected function syncActivityDeadlines(User $user, Carbon $until): void
    {
        Activity::query()
            ->where('assigned_to', $user->id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $until)
            ->orderBy('due_at')
            ->limit(30)
            ->get()
            ->each(function (Activity $activity) use ($user) {
                $date = $activity->due_at->copy()->startOfDay();
                $label = $this->deadlineLabel($date);
                $typeLabel = Activity::TYPES[$activity->type] ?? $activity->type;
                $overdue = $activity->isOverdue();

                $this->notify(
                    $user->id,
                    CrmNotification::TYPE_ACTIVITY_DUE,
                    ($overdue ? 'Acara/tugas lewat: ' : 'Acara/tugas mendekati: ').$activity->subject,
                    $typeLabel.' — '.$activity->due_at->translatedFormat('d M Y H:i').' ('.$label.')',
                    route('activities.edit', $activity),
                    showPopup: $overdue || $date->isToday() || $date->isTomorrow(),
                    uniqueKey: 'activity_due:'.$activity->id.':'.$date->toDateString(),
                    data: ['activity_id' => $activity->id, 'due_at' => $activity->due_at->toIso8601String()],
                );
            });
    }

    public function unreadCount(string $userId): int
    {
        return CrmNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function recent(string $userId, int $limit = 12): Collection
    {
        return CrmNotification::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function unreadPopups(string $userId): Collection
    {
        return CrmNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->where('show_popup', true)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();
    }

    public function markAllRead(string $userId): void
    {
        CrmNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function markPopupsRead(string $userId): void
    {
        CrmNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->where('show_popup', true)
            ->update(['read_at' => now()]);
    }

    protected function deadlineLabel(Carbon $date): string
    {
        $days = (int) now()->startOfDay()->diffInDays($date, false);

        if ($days < 0) {
            return 'Terlambat '.abs($days).' hari';
        }
        if ($days === 0) {
            return 'Hari ini';
        }
        if ($days === 1) {
            return 'Besok';
        }

        return $days.' hari lagi';
    }
}
