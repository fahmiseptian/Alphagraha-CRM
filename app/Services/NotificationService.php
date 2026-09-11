<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\CrmNotification;
use App\Models\Espo\Opportunity;
use App\Models\OpportunitySalesOrder;
use App\Models\Quotation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

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

    /**
     * Nama user untuk log/notifikasi approval (fallback: Superadmin).
     */
    protected function actorName(?string $userId = null): string
    {
        $userId = $userId ?: auth()->id();
        if (! $userId) {
            return 'Superadmin';
        }

        $user = User::query()->whereKey($userId)->first();

        return $user?->display_name ?: 'Superadmin';
    }

    protected function actorPayload(?string $userId = null): array
    {
        $userId = $userId ?: auth()->id();

        return [
            'reviewed_by' => $userId,
            'reviewed_by_name' => $this->actorName($userId),
        ];
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
        $actor = $this->actorName($opportunity->crm_discount_reviewed_by ?: auth()->id());

        $type = $revised
            ? CrmNotification::TYPE_DISCOUNT_REVISED
            : CrmNotification::TYPE_DISCOUNT_APPROVED;

        $title = $revised
            ? 'Diskon disesuaikan & disetujui'
            : 'Diskon disetujui';

        $body = $revised
            ? $actor.' menyesuaikan diskon Opportunity "'.$opportunity->name.'" menjadi '.$formatted.$pctLabel.'.'
            : 'Diskon Opportunity "'.$opportunity->name.'" sebesar '.$formatted.$pctLabel.' telah disetujui oleh '.$actor.'.';

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
                data: array_merge([
                    'opportunity_id' => $opportunity->id,
                    'amount' => $amount,
                    'revised' => $revised,
                ], $this->actorPayload($opportunity->crm_discount_reviewed_by ?: auth()->id())),
            );
        }
    }

    /**
     * Popup + bell untuk semua Superadmin saat sales request diskon tambahan.
     * Satu opportunity = satu notif pending per superadmin.
     * Edit nominal mengarsipkan request lama (tetap di histori, tanpa wajib aksi) lalu membuat notif baru.
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
        $uniqueKey = 'discount_request:'.$opportunity->id;

        $superAdminIds = User::query()
            ->where('deleted', 0)
            ->where('is_active', 1)
            ->whereHas('profile', fn ($q) => $q->where('app_role', User::ROLE_SUPERADMIN))
            ->pluck('id')
            ->filter(fn ($id) => $id !== $requesterId)
            ->values();

        foreach ($superAdminIds as $userId) {
            $this->replacePendingActionNotifications(
                $userId,
                CrmNotification::TYPE_DISCOUNT_REQUESTED,
                $uniqueKey,
                opportunityId: $opportunity->id,
            );

            $this->notify(
                $userId,
                CrmNotification::TYPE_DISCOUNT_REQUESTED,
                $title,
                $body,
                $link,
                showPopup: true,
                uniqueKey: $uniqueKey,
                data: [
                    'opportunity_id' => $opportunity->id,
                    'amount' => $amount,
                    'requested_by' => $requesterId,
                    'sales_user_id' => $requesterId ?: $opportunity->assigned_user_id,
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
        $actor = $this->actorName($opportunity->crm_discount_reviewed_by ?: auth()->id());
        if ($approvedAmount > 0) {
            $body = 'Permintaan diskon Opportunity "'.$opportunity->name.'" ditolak oleh '.$actor.'. Nominal yang disetujui: '.$formattedApproved.'.';
        } else {
            $body = 'Permintaan diskon Opportunity "'.$opportunity->name.'" ditolak oleh '.$actor.'.';
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
                data: array_merge([
                    'opportunity_id' => $opportunity->id,
                    'approved_amount' => $approvedAmount,
                    'requested_amount' => $requestedAmount,
                ], $this->actorPayload($opportunity->crm_discount_reviewed_by ?: auth()->id())),
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
        $actor = $this->actorName(auth()->id());
        $body = 'Keputusan diskon Opportunity "'.$opportunity->name.'" dikembalikan ke menunggu approval oleh '.$actor.'.';
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
                data: array_merge(['opportunity_id' => $opportunity->id], $this->actorPayload()),
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
            $uniqueKey = 'opp_margin_request:'.$opportunity->id;
            $this->replacePendingActionNotifications(
                $userId,
                CrmNotification::TYPE_MARGIN_REQUESTED,
                $uniqueKey,
                opportunityId: $opportunity->id,
            );

            $this->notify(
                $userId,
                CrmNotification::TYPE_MARGIN_REQUESTED,
                $title,
                $body,
                $link,
                showPopup: true,
                uniqueKey: $uniqueKey,
                data: [
                    'opportunity_id' => $opportunity->id,
                    'margin_percent' => $marginPct,
                    'threshold' => $pctThreshold,
                    'margin_nominal' => $marginNominal,
                    'nominal_threshold' => $nominalThreshold,
                    'sales_user_id' => $requesterId,
                    'requested_by' => $requesterId,
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

        $actor = $this->actorName($opportunity->crm_margin_reviewed_by ?: auth()->id());
        $body = 'Margin Opportunity "'.$opportunity->name.'" telah disetujui oleh '.$actor.'.';
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
            data: array_merge(
                ['opportunity_id' => $opportunity->id],
                $this->actorPayload($opportunity->crm_margin_reviewed_by ?: auth()->id())
            ),
        );
    }

    public function notifyOpportunityMarginRejected(Opportunity $opportunity, ?string $note = null): void
    {
        $userId = $opportunity->assigned_user_id ?: $opportunity->created_by_id;
        if (! $userId) {
            return;
        }

        $actor = $this->actorName($opportunity->crm_margin_reviewed_by ?: auth()->id());
        $body = 'Margin Opportunity "'.$opportunity->name.'" ditolak oleh '.$actor.'. Perbaiki harga/margin atau hubungi Superadmin.';
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
            data: array_merge(
                ['opportunity_id' => $opportunity->id],
                $this->actorPayload($opportunity->crm_margin_reviewed_by ?: auth()->id())
            ),
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
            $uniqueKey = 'margin_request:'.$quotation->id;
            $this->replacePendingActionNotifications(
                $userId,
                CrmNotification::TYPE_MARGIN_REQUESTED,
                $uniqueKey,
                quotationId: $quotation->id,
            );

            $this->notify(
                $userId,
                CrmNotification::TYPE_MARGIN_REQUESTED,
                $title,
                $body,
                $link,
                showPopup: true,
                uniqueKey: $uniqueKey,
                data: [
                    'quotation_id' => $quotation->id,
                    'margin_percent' => $margin,
                    'threshold' => $threshold,
                    'margin_nominal' => $marginNominal,
                    'nominal_threshold' => $nominalThreshold,
                    'sales_user_id' => $requesterId,
                    'requested_by' => $requesterId,
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

        $actor = $this->actorName($quotation->crm_margin_reviewed_by ?: auth()->id());
        $body = 'Margin Quotation "'.$quotation->number.'" telah disetujui oleh '.$actor.'. Preview/PDF dapat dilanjutkan.';
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
            data: array_merge(
                ['quotation_id' => $quotation->id],
                $this->actorPayload($quotation->crm_margin_reviewed_by ?: auth()->id())
            ),
        );
    }

    public function notifyMarginRejected(Quotation $quotation, ?string $note = null): void
    {
        $userId = $quotation->created_by;
        if (! $userId) {
            return;
        }

        $actor = $this->actorName($quotation->crm_margin_reviewed_by ?: auth()->id());
        $body = 'Margin Quotation "'.$quotation->number.'" ditolak oleh '.$actor.'. Dokumen terkunci hingga margin diperbaiki / di-approve.';
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
            data: array_merge(
                ['quotation_id' => $quotation->id],
                $this->actorPayload($quotation->crm_margin_reviewed_by ?: auth()->id())
            ),
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
        $this->dedupePendingRequestNotifications($user->id);
    }

    /**
     * Satukan notif request pending yang terduplikasi (mis. edit diskon sebelum diaksi).
     * Sisakan yang terbaru sebagai wajib aksi; yang lama tetap di histori tanpa aksi.
     */
    protected function dedupePendingRequestNotifications(string $userId): void
    {
        $this->dedupePendingByDataKey(
            $userId,
            CrmNotification::TYPE_DISCOUNT_REQUESTED,
            'opportunity_id',
            'discount_request:'
        );
        $this->dedupePendingByDataKey(
            $userId,
            CrmNotification::TYPE_MARGIN_REQUESTED,
            'opportunity_id',
            'opp_margin_request:'
        );
        $this->dedupePendingByDataKey(
            $userId,
            CrmNotification::TYPE_MARGIN_REQUESTED,
            'quotation_id',
            'margin_request:'
        );
        $this->dedupePendingByDataKey(
            $userId,
            CrmNotification::TYPE_EVENT_APPROVAL_REQUESTED,
            'activity_id',
            'event_approval:'
        );
    }

    protected function dedupePendingByDataKey(
        string $userId,
        string $type,
        string $dataKey,
        string $uniqueKeyPrefix,
    ): void {
        $items = CrmNotification::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->whereNull('read_at')
            ->whereNotNull("data->{$dataKey}")
            ->orderByDesc('id')
            ->get();

        if ($items->count() < 2) {
            return;
        }

        $grouped = $items->groupBy(fn (CrmNotification $n) => (string) data_get($n->data, $dataKey));

        foreach ($grouped as $entityId => $rows) {
            if ($entityId === '' || $rows->count() < 2) {
                continue;
            }

            $keep = $rows->first();
            $olderIds = $rows->slice(1)->pluck('id')->all();
            if ($olderIds !== []) {
                CrmNotification::query()
                    ->whereIn('id', $olderIds)
                    ->update([
                        'read_at' => now(),
                        'show_popup' => false,
                        'unique_key' => null,
                    ]);
            }

            $desiredKey = $uniqueKeyPrefix.$entityId;
            if ($keep->unique_key === $desiredKey) {
                continue;
            }

            $keyTaken = CrmNotification::query()
                ->where('user_id', $userId)
                ->where('unique_key', $desiredKey)
                ->where('id', '!=', $keep->id)
                ->exists();

            if ($keyTaken) {
                CrmNotification::query()
                    ->where('user_id', $userId)
                    ->where('unique_key', $desiredKey)
                    ->where('id', '!=', $keep->id)
                    ->update(['unique_key' => null]);
            }

            $keep->forceFill(['unique_key' => $desiredKey])->save();
        }
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
        // Popup maksimal sekali per hari — notif baru tetap masuk lonceng, tanpa buka modal lagi.
        if ($this->popupAlreadyShownToday($userId)) {
            return collect();
        }

        $items = CrmNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->where('show_popup', true)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        if ($items->isNotEmpty()) {
            $this->markPopupShownToday($userId);
        }

        return $items;
    }

    public function popupAlreadyShownToday(string $userId): bool
    {
        return Cache::has($this->popupDayCacheKey($userId));
    }

    public function markPopupShownToday(string $userId): void
    {
        Cache::put($this->popupDayCacheKey($userId), true, now()->endOfDay());
    }

    protected function popupDayCacheKey(string $userId): string
    {
        return 'crm_notif_popup_day:'.$userId;
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
                CrmNotification::TYPE_EVENT_APPROVAL_REQUESTED,
                CrmNotification::TYPE_OPPORTUNITY_DEADLINE,
                CrmNotification::TYPE_SALES_ORDER_CREATED,
                CrmNotification::TYPE_SALES_ORDER_CANCEL_REQUESTED,
                CrmNotification::TYPE_CUSTOMER_INDUSTRY_UPDATE,
            ])
            ->update([
                'read_at' => now(),
                'show_popup' => false,
            ]);
    }

    /**
     * Tutup popup saja — status baca tidak berubah.
     * Juga kunci agar modal tidak muncul lagi sampai hari berikutnya.
     */
    public function dismissPopups(string $userId): void
    {
        $this->markPopupShownToday($userId);

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
     * Arsipkan notifikasi aksi pending sebelumnya (tetap di histori, tidak wajib aksi).
     * Unique key dibersihkan agar notif aktif baru bisa memakai key stabil yang sama.
     */
    protected function replacePendingActionNotifications(
        string $userId,
        string $type,
        string $uniqueKey,
        ?string $opportunityId = null,
        int|string|null $quotationId = null,
        int|string|null $activityId = null,
        int|string|null $salesOrderId = null,
    ): void {
        CrmNotification::query()
            ->where('user_id', $userId)
            ->where(function ($q) use ($type, $uniqueKey, $opportunityId, $quotationId, $activityId, $salesOrderId) {
                $q->where('unique_key', $uniqueKey)
                    ->orWhere(function ($q2) use ($type, $opportunityId, $quotationId, $activityId, $salesOrderId) {
                        $q2->where('type', $type)
                            ->whereNull('read_at');

                        if ($opportunityId) {
                            $q2->where('data->opportunity_id', $opportunityId);
                        }
                        if ($quotationId) {
                            $q2->where('data->quotation_id', $quotationId);
                        }
                        if ($activityId) {
                            $q2->where('data->activity_id', $activityId);
                        }
                        if ($salesOrderId) {
                            $q2->where('data->sales_order_id', $salesOrderId);
                        }
                    });
            })
            ->update([
                'read_at' => now(),
                'show_popup' => false,
                'unique_key' => null,
            ]);
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

    /**
     * Tutup semua request approval (diskon + margin) untuk opportunity yang Closed Lost.
     */
    public function dismissApprovalRequestsForOpportunity(Opportunity $opportunity): void
    {
        $this->markDiscountRequestActioned($opportunity);
        $this->markOpportunityMarginRequestActioned($opportunity);

        $opportunity->loadMissing('quotation');
        if ($opportunity->quotation) {
            $this->markQuotationMarginRequestActioned($opportunity->quotation);
        }
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

    public function notifyEventApprovalRequested(Activity $activity): void
    {
        if (! $activity->isEventApprovalPending()) {
            return;
        }

        $requesterId = $activity->assigned_to ?: $activity->created_by;
        $requester = $requesterId
            ? User::query()->whereKey($requesterId)->first()
            : null;
        $requesterName = $requester?->display_name ?? 'Sales';
        $dueLabel = $activity->due_at
            ? $activity->due_at->translatedFormat('d M Y H:i')
            : 'tanpa tanggal';

        $link = route('activities.edit', $activity);
        $title = 'Approval Event/Training';
        $body = $requesterName.' mengajukan Event/Training "'.$activity->subject.'" ('.$dueLabel.').';

        $superAdminIds = User::query()
            ->where('deleted', 0)
            ->where('is_active', 1)
            ->whereHas('profile', fn ($q) => $q->where('app_role', User::ROLE_SUPERADMIN))
            ->pluck('id')
            ->filter(fn ($id) => $id !== $requesterId)
            ->values();

        foreach ($superAdminIds as $userId) {
            $uniqueKey = 'event_approval:'.$activity->id;
            $this->replacePendingActionNotifications(
                $userId,
                CrmNotification::TYPE_EVENT_APPROVAL_REQUESTED,
                $uniqueKey,
                activityId: $activity->id,
            );

            $this->notify(
                $userId,
                CrmNotification::TYPE_EVENT_APPROVAL_REQUESTED,
                $title,
                $body,
                $link,
                showPopup: true,
                uniqueKey: $uniqueKey,
                data: [
                    'activity_id' => $activity->id,
                    'sales_user_id' => $requesterId,
                    'requested_by' => $requesterId,
                ],
            );
        }
    }

    public function notifyEventApproved(Activity $activity, ?string $note = null): void
    {
        $userId = $activity->assigned_to ?: $activity->created_by;
        if (! $userId) {
            return;
        }

        $actor = $this->actorName($activity->approved_by ?: auth()->id());
        $body = 'Event/Training "'.$activity->subject.'" telah disetujui oleh '.$actor.'.';
        if ($note) {
            $body .= ' Catatan: '.$note;
        }

        $this->notify(
            $userId,
            CrmNotification::TYPE_EVENT_APPROVED,
            'Event/Training disetujui',
            $body,
            route('activities.edit', $activity),
            showPopup: true,
            uniqueKey: 'event_approved:'.$activity->id.':'.uniqid('', true),
            data: array_merge(
                ['activity_id' => $activity->id],
                $this->actorPayload($activity->approved_by ?: auth()->id())
            ),
        );
    }

    public function notifyEventRejected(Activity $activity, ?string $note = null): void
    {
        $userId = $activity->assigned_to ?: $activity->created_by;
        if (! $userId) {
            return;
        }

        $actor = $this->actorName($activity->approved_by ?: auth()->id());
        $body = 'Event/Training "'.$activity->subject.'" ditolak oleh '.$actor.'.';
        if ($note) {
            $body .= ' Catatan: '.$note;
        }

        $this->notify(
            $userId,
            CrmNotification::TYPE_EVENT_REJECTED,
            'Event/Training ditolak',
            $body,
            route('activities.edit', $activity),
            showPopup: true,
            uniqueKey: 'event_rejected:'.$activity->id.':'.uniqid('', true),
            data: array_merge(
                ['activity_id' => $activity->id],
                $this->actorPayload($activity->approved_by ?: auth()->id())
            ),
        );
    }

    public function markActivityDueActioned(Activity $activity): void
    {
        $this->markActioned(CrmNotification::TYPE_ACTIVITY_DUE, [
            'activity_id' => $activity->id,
        ]);
    }

    public function markEventApprovalActioned(int|string $activityId): void
    {
        $this->markActioned(CrmNotification::TYPE_EVENT_APPROVAL_REQUESTED, [
            'activity_id' => $activityId,
        ]);
    }

    public function markOpportunityDeadlineActioned(Opportunity $opportunity): void
    {
        $this->markActioned(CrmNotification::TYPE_OPPORTUNITY_DEADLINE, [
            'opportunity_id' => $opportunity->id,
        ]);
    }

    /**
     * Notifikasi ke Purchasing saat Sales membuat SO,
     * agar proses PO / kelanjutan deal bisa dilanjutkan.
     * Superadmin tidak menerima notifikasi ini.
     */
    public function notifySalesOrderCreated(OpportunitySalesOrder $salesOrder, Opportunity $opportunity): void
    {
        $creator = $salesOrder->creator
            ?? ($salesOrder->created_by ? User::query()->whereKey($salesOrder->created_by)->first() : null);
        $creatorName = $creator?->display_name ?: 'Sales';
        $soNumber = $salesOrder->displayNumber();
        $oppName = $opportunity->name ?: $opportunity->id;

        $title = 'Sales Order baru — lanjutkan proses';
        $body = $creatorName.' membuat SO '.$soNumber.' untuk Opportunity "'.$oppName.'". Silakan lanjutkan proses pembelian (PO / modal & vendor).';
        $link = route('opportunities.purchase-orders.index', $opportunity);
        $uniqueKey = 'sales_order_created:'.$salesOrder->id;

        $recipientIds = User::query()
            ->where('deleted', 0)
            ->where('is_active', 1)
            ->whereHas('profile', fn ($q) => $q->where('app_role', User::ROLE_PURCHASING))
            ->pluck('id')
            ->filter(fn ($id) => (string) $id !== (string) $salesOrder->created_by)
            ->values();

        foreach ($recipientIds as $userId) {
            $this->notify(
                $userId,
                CrmNotification::TYPE_SALES_ORDER_CREATED,
                $title,
                $body,
                $link,
                showPopup: true,
                uniqueKey: $uniqueKey.':'.$userId,
                data: [
                    'opportunity_id' => $opportunity->id,
                    'sales_order_id' => $salesOrder->id,
                    'sales_order_number' => $soNumber,
                    'created_by' => $salesOrder->created_by,
                    'created_by_name' => $creatorName,
                ],
            );
        }
    }

    public function markSalesOrderCreatedActioned(int|string $salesOrderId): void
    {
        $this->markActioned(CrmNotification::TYPE_SALES_ORDER_CREATED, [
            'sales_order_id' => $salesOrderId,
        ]);
    }

    /**
     * Popup + lonceng untuk Superadmin/Admin saat Sales request Cancel SO.
     */
    public function notifySalesOrderCancelRequested(
        OpportunitySalesOrder $salesOrder,
        Opportunity $opportunity,
        User $requester,
        string $reason
    ): void {
        $soNumber = $salesOrder->displayNumber();
        $oppName = $opportunity->name ?: $opportunity->id;
        $requesterName = $requester->display_name ?: 'Sales';
        $title = 'Request pembatalan Sales Order';
        $body = $requesterName.' mengajukan pembatalan SO '.$soNumber.' pada Opportunity "'.$oppName.'". Alasan: '.$reason;
        $link = route('opportunities.sales-orders.show', [$opportunity, $salesOrder]);
        $uniqueKey = 'so_cancel_request:'.$salesOrder->id;

        $recipientIds = User::query()
            ->where('deleted', 0)
            ->where('is_active', 1)
            ->whereHas('profile', fn ($q) => $q->whereIn('app_role', [
                User::ROLE_SUPERADMIN,
                User::ROLE_ADMIN,
            ]))
            ->pluck('id')
            ->filter(fn ($id) => (string) $id !== (string) $requester->id)
            ->values();

        foreach ($recipientIds as $userId) {
            $this->replacePendingActionNotifications(
                $userId,
                CrmNotification::TYPE_SALES_ORDER_CANCEL_REQUESTED,
                $uniqueKey,
                opportunityId: $opportunity->id,
                salesOrderId: $salesOrder->id,
            );

            $this->notify(
                $userId,
                CrmNotification::TYPE_SALES_ORDER_CANCEL_REQUESTED,
                $title,
                $body,
                $link,
                showPopup: true,
                uniqueKey: $uniqueKey,
                data: [
                    'opportunity_id' => $opportunity->id,
                    'sales_order_id' => $salesOrder->id,
                    'sales_order_number' => $soNumber,
                    'requested_by' => $requester->id,
                    'sales_user_id' => $requester->id,
                    'reason' => $reason,
                ],
            );
        }
    }

    public function notifySalesOrderCancelApproved(
        OpportunitySalesOrder $salesOrder,
        Opportunity $opportunity,
        ?string $note = null
    ): void {
        $soNumber = $salesOrder->displayNumber();
        $oppName = $opportunity->name ?: $opportunity->id;
        $actor = $this->actorName($salesOrder->payloadValue('cancel_reviewed_by') ?: auth()->id());
        $title = 'Sales Order dibatalkan';
        $body = $actor.' menyetujui pembatalan SO '.$soNumber.' pada Opportunity "'.$oppName.'".';
        if ($note) {
            $body .= ' Catatan: '.$note;
        }

        $this->notifySalesOrderCancelResult(
            $salesOrder,
            $opportunity,
            CrmNotification::TYPE_SALES_ORDER_CANCEL_APPROVED,
            $title,
            $body,
        );
    }

    public function notifySalesOrderCancelRejected(
        OpportunitySalesOrder $salesOrder,
        Opportunity $opportunity,
        ?string $note = null
    ): void {
        $soNumber = $salesOrder->displayNumber();
        $oppName = $opportunity->name ?: $opportunity->id;
        $actor = $this->actorName($salesOrder->payloadValue('cancel_reviewed_by') ?: auth()->id());
        $title = 'Pembatalan SO ditolak';
        $body = $actor.' menolak permintaan pembatalan SO '.$soNumber.' pada Opportunity "'.$oppName.'".';
        if ($note) {
            $body .= ' Catatan: '.$note;
        }

        $this->notifySalesOrderCancelResult(
            $salesOrder,
            $opportunity,
            CrmNotification::TYPE_SALES_ORDER_CANCEL_REJECTED,
            $title,
            $body,
        );
    }

    protected function notifySalesOrderCancelResult(
        OpportunitySalesOrder $salesOrder,
        Opportunity $opportunity,
        string $type,
        string $title,
        string $body,
    ): void {
        $requesterId = $salesOrder->payloadValue('cancel_requested_by')
            ?: $salesOrder->created_by
            ?: $opportunity->assigned_user_id;

        $userIds = collect([
            $requesterId,
            $opportunity->assigned_user_id,
            $salesOrder->created_by,
        ])->filter()->unique()->values();

        $link = route('opportunities.sales-orders.show', [$opportunity, $salesOrder]);

        foreach ($userIds as $userId) {
            if ((string) $userId === (string) auth()->id()) {
                continue;
            }

            $this->notify(
                $userId,
                $type,
                $title,
                $body,
                $link,
                showPopup: true,
                uniqueKey: $type.':'.$salesOrder->id.':'.uniqid('', true),
                data: array_merge([
                    'opportunity_id' => $opportunity->id,
                    'sales_order_id' => $salesOrder->id,
                    'sales_order_number' => $salesOrder->displayNumber(),
                    'requested_by' => $requesterId,
                    'sales_user_id' => $opportunity->assigned_user_id,
                ], $this->actorPayload()),
            );
        }
    }

    public function markSalesOrderCancelRequestActioned(int|string $salesOrderId): void
    {
        $this->markActioned(CrmNotification::TYPE_SALES_ORDER_CANCEL_REQUESTED, [
            'sales_order_id' => $salesOrderId,
        ]);
    }

    /**
     * Notifikasi ke sales baru saat Superadmin/Admin menunjuk atau mengalihkan opportunity.
     */
    public function notifyOpportunityAssigned(
        Opportunity $opportunity,
        ?string $previousUserId = null,
        ?string $assignedByUserId = null,
    ): void {
        $newUserId = $opportunity->assigned_user_id;
        if (! filled($newUserId)) {
            return;
        }

        if ($assignedByUserId && (string) $newUserId === (string) $assignedByUserId) {
            return;
        }

        $actor = $this->actorName($assignedByUserId);
        $previousUser = filled($previousUserId)
            ? User::query()->whereKey($previousUserId)->first()
            : null;
        $previousName = $previousUser?->display_name;

        $title = 'Opportunity ditunjuk ke Anda';
        if ($previousName) {
            $body = $actor.' mengalihkan Opportunity "'.$opportunity->name.'" kepada Anda (sebelumnya '.$previousName.').';
        } else {
            $body = $actor.' menunjukkan Opportunity "'.$opportunity->name.'" kepada Anda.';
        }

        $this->notify(
            $newUserId,
            CrmNotification::TYPE_OPPORTUNITY_ASSIGNED,
            $title,
            $body,
            route('opportunities.show', $opportunity),
            showPopup: true,
            uniqueKey: 'opp_assigned:'.$opportunity->id.':'.uniqid('', true),
            data: array_merge([
                'opportunity_id' => $opportunity->id,
                'previous_assigned_user_id' => $previousUserId,
                'previous_assigned_user_name' => $previousName,
                'assigned_user_id' => $newUserId,
            ], $this->actorPayload($assignedByUserId)),
        );
    }

    /**
     * Minta sales mengisi ulang industri customer sesuai master data.
     */
    public function notifySalesUpdateCustomerIndustry(): void
    {
        $title = 'Perbarui industri customer';
        $body = 'Daftar industri customer sudah distandarkan. Mohon perbarui data customer Anda sesuai industri yang tersedia.';
        $link = route('customers.index', ['missing_industry' => 1]);

        $salesIds = User::query()
            ->where('is_active', 1)
            ->whereHas('profile', fn ($q) => $q->where('app_role', User::ROLE_SALES))
            ->pluck('id');

        foreach ($salesIds as $userId) {
            $this->notify(
                $userId,
                CrmNotification::TYPE_CUSTOMER_INDUSTRY_UPDATE,
                $title,
                $body,
                $link,
                showPopup: true,
                uniqueKey: 'customer_industry_update:'.$userId,
                data: ['scope' => 'all'],
            );
        }
    }

    public function markCustomerIndustryUpdateActioned(string $userId): void
    {
        CrmNotification::query()
            ->where('user_id', $userId)
            ->where('type', CrmNotification::TYPE_CUSTOMER_INDUSTRY_UPDATE)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'show_popup' => false,
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
