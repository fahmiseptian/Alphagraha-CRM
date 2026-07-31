<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\CrmNotification;
use App\Models\Espo\Opportunity;
use App\Models\Quotation;
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

    public function notifyOpportunityMarginRequested(Opportunity $opportunity): void
    {
        $marginPct = $opportunity->crm_margin_percent;
        $pctThreshold = $opportunity->crm_margin_threshold;
        $marginNominal = $opportunity->crm_margin_nominal;
        $nominalThreshold = $opportunity->crm_margin_nominal_threshold;

        $pctLabel = $marginPct !== null ? number_format((float) $marginPct, 2, ',', '.').'%' : '—';
        $pctMinLabel = $pctThreshold !== null ? number_format((float) $pctThreshold, 2, ',', '.').'%' : '—';
        $nomLabel = $marginNominal !== null ? 'Rp '.number_format((float) $marginNominal, 0, ',', '.') : '—';
        $nomMinLabel = $nominalThreshold !== null ? 'Rp '.number_format((float) $nominalThreshold, 0, ',', '.') : '—';

        $requesterId = $opportunity->assigned_user_id ?: $opportunity->created_by_id;
        $requester = $requesterId
            ? User::query()->whereKey($requesterId)->first()
            : null;
        $requesterName = $requester?->display_name ?? 'Sales';

        $link = route('opportunities.show', $opportunity);
        $title = 'Approval margin opportunity';
        $body = $requesterName.' mengajukan Opportunity "'.$opportunity->name.'" dengan margin '
            .$pctLabel.' / '.$nomLabel.' (minimal '.$pctMinLabel.' / '.$nomMinLabel.').';

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
                CrmNotification::TYPE_MARGIN_REQUESTED,
                $title,
                $body,
                $link,
                showPopup: true,
                uniqueKey: 'opp_margin_request:'.$opportunity->id.':'.uniqid('', true),
                data: [
                    'opportunity_id' => $opportunity->id,
                    'margin_percent' => $marginPct,
                    'threshold' => $pctThreshold,
                    'margin_nominal' => $marginNominal,
                    'nominal_threshold' => $nominalThreshold,
                ],
            );
        }
    }

    public function notifyOpportunityMarginApproved(Opportunity $opportunity, ?string $note = null): void
    {
        $userId = $opportunity->assigned_user_id ?: $opportunity->created_by_id;
        if (! $userId) {
            return;
        }

        $body = 'Margin Opportunity "'.$opportunity->name.'" telah disetujui.';
        if ($note) {
            $body .= ' Catatan: '.$note;
        }

        $this->notify(
            $userId,
            CrmNotification::TYPE_MARGIN_APPROVED,
            'Margin opportunity disetujui',
            $body,
            route('opportunities.show', $opportunity),
            showPopup: true,
            uniqueKey: 'opp_margin_approved:'.$opportunity->id.':'.uniqid('', true),
            data: ['opportunity_id' => $opportunity->id],
        );
    }

    public function notifyOpportunityMarginRejected(Opportunity $opportunity, ?string $note = null): void
    {
        $userId = $opportunity->assigned_user_id ?: $opportunity->created_by_id;
        if (! $userId) {
            return;
        }

        $body = 'Margin Opportunity "'.$opportunity->name.'" ditolak. Perbaiki harga/margin atau hubungi Superadmin.';
        if ($note) {
            $body .= ' Catatan: '.$note;
        }

        $this->notify(
            $userId,
            CrmNotification::TYPE_MARGIN_REJECTED,
            'Margin opportunity ditolak',
            $body,
            route('opportunities.show', $opportunity),
            showPopup: true,
            uniqueKey: 'opp_margin_rejected:'.$opportunity->id.':'.uniqid('', true),
            data: ['opportunity_id' => $opportunity->id],
        );
    }

    public function notifyMarginRequested(Quotation $quotation): void
    {
        $margin = $quotation->crm_margin_percent;
        $threshold = $quotation->crm_margin_threshold;
        $marginNominal = $quotation->crm_margin_nominal;
        $nominalThreshold = $quotation->crm_margin_nominal_threshold;
        $marginLabel = $margin !== null ? number_format((float) $margin, 2, ',', '.').'%' : '—';
        $thresholdLabel = $threshold !== null ? number_format((float) $threshold, 2, ',', '.').'%' : '—';
        $nomLabel = $marginNominal !== null ? 'Rp '.number_format((float) $marginNominal, 0, ',', '.') : '—';
        $nomMinLabel = $nominalThreshold !== null ? 'Rp '.number_format((float) $nominalThreshold, 0, ',', '.') : '—';

        $requesterId = $quotation->created_by;
        $requester = $requesterId
            ? User::query()->whereKey($requesterId)->first()
            : null;
        $requesterName = $requester?->display_name ?? 'Sales';

        $link = route('quotations.show', $quotation);
        $title = 'Approval margin quotation';
        $body = $requesterName.' mengajukan Quotation "'.$quotation->number.'" dengan margin '
            .$marginLabel.' / '.$nomLabel.' (minimal '.$thresholdLabel.' / '.$nomMinLabel.').';

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
                CrmNotification::TYPE_MARGIN_REQUESTED,
                $title,
                $body,
                $link,
                showPopup: true,
                uniqueKey: 'margin_request:'.$quotation->id.':'.uniqid('', true),
                data: [
                    'quotation_id' => $quotation->id,
                    'margin_percent' => $margin,
                    'threshold' => $threshold,
                    'margin_nominal' => $marginNominal,
                    'nominal_threshold' => $nominalThreshold,
                ],
            );
        }
    }

    public function notifyMarginApproved(Quotation $quotation, ?string $note = null): void
    {
        $userId = $quotation->created_by;
        if (! $userId) {
            return;
        }

        $body = 'Margin Quotation "'.$quotation->number.'" telah disetujui. Preview/PDF dapat dilanjutkan.';
        if ($note) {
            $body .= ' Catatan: '.$note;
        }

        $this->notify(
            $userId,
            CrmNotification::TYPE_MARGIN_APPROVED,
            'Margin quotation disetujui',
            $body,
            route('quotations.show', $quotation),
            showPopup: true,
            uniqueKey: 'margin_approved:'.$quotation->id.':'.uniqid('', true),
            data: ['quotation_id' => $quotation->id],
        );
    }

    public function notifyMarginRejected(Quotation $quotation, ?string $note = null): void
    {
        $userId = $quotation->created_by;
        if (! $userId) {
            return;
        }

        $body = 'Margin Quotation "'.$quotation->number.'" ditolak. Dokumen terkunci hingga margin diperbaiki / di-approve.';
        if ($note) {
            $body .= ' Catatan: '.$note;
        }

        $this->notify(
            $userId,
            CrmNotification::TYPE_MARGIN_REJECTED,
            'Margin quotation ditolak',
            $body,
            route('quotations.show', $quotation),
            showPopup: true,
            uniqueKey: 'margin_rejected:'.$quotation->id.':'.uniqid('', true),
            data: ['quotation_id' => $quotation->id],
        );
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
        // Hanya muncul saat reminder sudah jatuh tempo (bukan "X hari lagi").
        $now = Carbon::now();

        Activity::query()
            ->where('assigned_to', $user->id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('reminder_at')
            ->where('reminder_at', '<=', $now)
            ->orderBy('reminder_at')
            ->limit(30)
            ->get()
            ->each(function (Activity $activity) use ($user) {
                $reminder = $activity->reminder_at->copy();
                $typeLabel = Activity::TYPES[$activity->type] ?? $activity->type;
                $dueLabel = $activity->due_at
                    ? 'Jadwal '.$activity->due_at->translatedFormat('d M Y H:i')
                    : null;

                $body = $typeLabel.' — Reminder '.$reminder->translatedFormat('d M Y H:i');
                if ($dueLabel) {
                    $body .= ' · '.$dueLabel;
                }

                $this->notify(
                    $user->id,
                    CrmNotification::TYPE_ACTIVITY_DUE,
                    'Reminder activity: '.$activity->subject,
                    $body,
                    route('activities.edit', $activity),
                    showPopup: true,
                    uniqueKey: 'activity_reminder:'.$activity->id.':'.$reminder->format('Y-m-d-H-i'),
                    data: [
                        'activity_id' => $activity->id,
                        'reminder_at' => $reminder->toIso8601String(),
                        'due_at' => $activity->due_at?->toIso8601String(),
                    ],
                );
            });

        // Hapus notifikasi activity yang masih unread tapi reminder belum jatuh / tidak relevan.
        $this->prunePrematureActivityNotifications($user);
    }

    /**
     * Bersihkan notifikasi activity lama yang dibuat sebelum waktunya (mis. "7 hari lagi").
     */
    protected function prunePrematureActivityNotifications(User $user): void
    {
        $now = Carbon::now();

        CrmNotification::query()
            ->where('user_id', $user->id)
            ->where('type', CrmNotification::TYPE_ACTIVITY_DUE)
            ->whereNull('read_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->each(function (CrmNotification $notification) use ($now) {
                $activityId = $notification->data['activity_id'] ?? null;
                if (! $activityId) {
                    $notification->delete();

                    return;
                }

                $activity = Activity::query()->find($activityId);
                if (! $activity
                    || in_array($activity->status, ['completed', 'cancelled'], true)
                    || ! $activity->reminder_at
                    || $activity->reminder_at->gt($now)
                ) {
                    $notification->delete();
                }
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
        // Hanya notifikasi informatif — yang butuh aksi tetap unread sampai diaksi.
        CrmNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->whereNotIn('type', [
                CrmNotification::TYPE_DISCOUNT_REQUESTED,
                CrmNotification::TYPE_MARGIN_REQUESTED,
                CrmNotification::TYPE_ACTIVITY_DUE,
                CrmNotification::TYPE_OPPORTUNITY_DEADLINE,
            ])
            ->update([
                'read_at' => now(),
                'show_popup' => false,
            ]);
    }

    /**
     * Tutup popup saja — status baca tidak berubah.
     */
    public function dismissPopups(string $userId): void
    {
        CrmNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->where('show_popup', true)
            ->update(['show_popup' => false]);
    }

    /**
     * @param  list<int>  $ids
     */
    public function deleteForUser(string $userId, array $ids): int
    {
        return CrmNotification::query()
            ->where('user_id', $userId)
            ->whereIn('id', $ids)
            ->delete();
    }

    /**
     * Tandai dibaca setelah aksi bisnis selesai (approve/reject/complete, dll).
     *
     * @param  array<string, mixed>  $dataMatch  kunci di kolom JSON `data`
     */
    public function markActioned(string $type, array $dataMatch): void
    {
        $query = CrmNotification::query()
            ->where('type', $type)
            ->whereNull('read_at');

        foreach ($dataMatch as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $query->where("data->{$key}", $value);
        }

        $query->update([
            'read_at' => now(),
            'show_popup' => false,
        ]);
    }

    public function markDiscountRequestActioned(Opportunity $opportunity): void
    {
        $this->markActioned(CrmNotification::TYPE_DISCOUNT_REQUESTED, [
            'opportunity_id' => $opportunity->id,
        ]);
    }

    public function markOpportunityMarginRequestActioned(Opportunity $opportunity): void
    {
        $this->markActioned(CrmNotification::TYPE_MARGIN_REQUESTED, [
            'opportunity_id' => $opportunity->id,
        ]);
    }

    public function markQuotationMarginRequestActioned(Quotation $quotation): void
    {
        $this->markActioned(CrmNotification::TYPE_MARGIN_REQUESTED, [
            'quotation_id' => $quotation->id,
        ]);
    }

    public function markActivityDueActioned(Activity $activity): void
    {
        $this->markActioned(CrmNotification::TYPE_ACTIVITY_DUE, [
            'activity_id' => $activity->id,
        ]);
    }

    public function markOpportunityDeadlineActioned(Opportunity $opportunity): void
    {
        $this->markActioned(CrmNotification::TYPE_OPPORTUNITY_DEADLINE, [
            'opportunity_id' => $opportunity->id,
        ]);
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
