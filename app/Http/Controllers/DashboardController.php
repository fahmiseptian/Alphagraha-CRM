<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Activity;
use App\Models\Espo\Account;
use App\Models\Espo\EspoUser;
use App\Models\Espo\Lead;
use App\Models\Espo\Opportunity;
use App\Models\Quotation;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    use ScopesToUser;

    public function __invoke(Request $request)
    {
        $user = auth()->user();

        $leaderboardPeriod = $request->get('leaderboard_period', 'year');
        if (! in_array($leaderboardPeriod, ['alltime', 'month', 'year'], true)) {
            $leaderboardPeriod = 'year';
        }

        $leaderboardSort = $request->get('leaderboard_sort', 'total');
        if (! in_array($leaderboardSort, ['total', 'margin', 'percent'], true)) {
            $leaderboardSort = 'total';
        }

        if ($user->isSales()) {
            $leaderboardSort = 'percent';
        }

        $salesLeaderboard = $this->buildSalesLeaderboard($leaderboardPeriod, $leaderboardSort);

        $customersCount = $this->scopeAssigned(Account::query())->count();
        $leadsCount = $this->scopeAssigned(Lead::query())->count();

        $quotationQuery = Quotation::query();
        if ($user->isSales()) {
            $quotationQuery->where('created_by', $user->id);
        }
        $quotationsCount = (clone $quotationQuery)->count();
        $activeQuotations = (clone $quotationQuery)
            ->where('status', 'sent')
            ->with('items')
            ->get();
        $quotationsValue = $activeQuotations->sum('total');
        $quotationsMargin = $activeQuotations->sum(fn (Quotation $quotation) => $quotation->totalItemsMargin());
        $sentCount = (clone $quotationQuery)->where('status', 'sent')->count();

        // Pipeline opportunity (deal) yang masih terbuka.
        $openPipeline = $this->scopeAssigned(Opportunity::query())
            ->whereIn('stage', Opportunity::OPEN_STAGES)
            ->sum('amount');

        $wonThisMonth = $this->scopeAssigned(Opportunity::query())
            ->where('stage', Opportunity::WON_STAGE)
            ->whereBetween('close_date', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->sum('amount');

        // Distribusi stage opportunity untuk grafik sederhana.
        $stageDistribution = $this->scopeAssigned(Opportunity::query())
            ->selectRaw('stage, COUNT(*) as total, SUM(amount) as value')
            ->groupBy('stage')
            ->pluck('total', 'stage')
            ->toArray();

        // Distribusi status penawaran.
        $quotationStatus = (clone $quotationQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        // Aktivitas: tugas mendatang & terlambat milik user.
        $upcomingActivities = Activity::with(['account', 'lead'])
            ->where('assigned_to', $user->id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderByRaw('due_at IS NULL, due_at ASC')
            ->limit(6)
            ->get();

        $overdueCount = Activity::where('assigned_to', $user->id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        // Penawaran terbaru.
        $recentQuotations = (clone $quotationQuery)
            ->with('creator')
            ->latest()
            ->limit(5)
            ->get();

        // Pelanggan terbaru.
        $recentCustomers = $this->scopeAssigned(Account::query())
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $deadlineAlerts = $user->isSales()
            ? $this->deadlineAlertsForUser($user)
            : collect();

        $showDeadlinePopup = session('show_deadline_popup') && $deadlineAlerts->isNotEmpty();

        return view('dashboard', compact(
            'customersCount', 'leadsCount', 'quotationsCount', 'quotationsValue', 'quotationsMargin',
            'sentCount', 'openPipeline', 'wonThisMonth', 'stageDistribution',
            'quotationStatus', 'upcomingActivities', 'overdueCount',
            'recentQuotations', 'recentCustomers', 'deadlineAlerts', 'showDeadlinePopup',
            'salesLeaderboard', 'leaderboardPeriod', 'leaderboardSort'
        ));
    }

    protected function buildSalesLeaderboard(string $period, string $sort = 'total'): Collection
    {
        $wonQuery = Opportunity::query()
            ->where('stage', Opportunity::WON_STAGE)
            ->whereNotNull('assigned_user_id');

        $this->applyLeaderboardPeriodToCloseDate($wonQuery, $period);

        $wonRows = $wonQuery
            ->selectRaw('assigned_user_id, COUNT(*) as won_count, COALESCE(SUM(amount), 0) as won_total, COALESCE(SUM(crm_won_margin), 0) as won_margin')
            ->groupBy('assigned_user_id')
            ->get();

        if ($wonRows->isEmpty()) {
            return collect();
        }

        $users = EspoUser::query()
            ->whereIn('id', $wonRows->pluck('assigned_user_id'))
            ->get()
            ->keyBy('id');

        $profiles = UserProfile::query()
            ->whereIn('user_id', $wonRows->pluck('assigned_user_id'))
            ->get()
            ->keyBy('user_id');

        $entries = $wonRows->map(function ($row) use ($users, $profiles) {
            $user = $users->get($row->assigned_user_id);
            $profile = $profiles->get($row->assigned_user_id);
            $salesTarget = $profile?->resolvedSalesTarget()
                ?? UserProfile::DEFAULT_SALES_TARGET;
            $wonTotal = (float) $row->won_total;
            $targetWonTotal = $profile
                ? $this->wonTotalForTargetPeriod($row->assigned_user_id, $profile)
                : $wonTotal;
            $deadline = $profile?->resolvedSalesTargetDeadline();
            $targetRemaining = max(0, $salesTarget - $targetWonTotal);
            $targetMet = $targetWonTotal >= $salesTarget && $salesTarget > 0;

            return [
                'user_id' => $row->assigned_user_id,
                'name' => $user?->display_name ?? 'Unknown',
                'won_count' => (int) $row->won_count,
                'won_total' => $wonTotal,
                'won_margin' => (float) $row->won_margin,
                'sales_target' => $salesTarget,
                'target_period' => $profile?->resolvedSalesTargetPeriod() ?? UserProfile::TARGET_PERIOD_1_YEAR,
                'target_period_label' => $profile?->salesTargetPeriodLabel()
                    ?? UserProfile::TARGET_PERIODS[UserProfile::TARGET_PERIOD_1_YEAR],
                'target_deadline' => $deadline,
                'target_deadline_label' => $deadline?->format('d M Y'),
                'target_won_total' => $targetWonTotal,
                'target_remaining' => $targetRemaining,
                'target_met' => $targetMet,
                'target_progress' => $salesTarget > 0
                    ? round(($targetWonTotal / $salesTarget) * 100, 1)
                    : 0,
            ];
        });

        $sorted = $entries->sort(function (array $a, array $b) use ($sort) {
            return match ($sort) {
                'margin' => $b['won_margin'] <=> $a['won_margin']
                    ?: $b['won_total'] <=> $a['won_total'],
                'percent' => $b['target_progress'] <=> $a['target_progress']
                    ?: $b['won_total'] <=> $a['won_total'],
                default => $b['won_total'] <=> $a['won_total']
                    ?: $b['won_margin'] <=> $a['won_margin'],
            };
        })->values()->take(10);

        return $sorted->map(fn (array $entry, int $index) => array_merge($entry, [
            'rank' => $index + 1,
        ]));
    }

    protected function wonTotalForTargetPeriod(string $userId, UserProfile $profile): float
    {
        [$start, $end] = $profile->salesTargetDateRange();

        return (float) Opportunity::query()
            ->where('stage', Opportunity::WON_STAGE)
            ->where('assigned_user_id', $userId)
            ->whereBetween('close_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');
    }

    protected function applyLeaderboardPeriodToCloseDate($query, string $period): void
    {
        if ($period === 'month') {
            $query->whereBetween('close_date', [
                Carbon::now()->startOfMonth()->toDateString(),
                Carbon::now()->endOfMonth()->toDateString(),
            ]);
        } elseif ($period === 'year') {
            $query->whereBetween('close_date', [
                Carbon::now()->startOfYear()->toDateString(),
                Carbon::now()->endOfYear()->toDateString(),
            ]);
        }
    }

    protected function deadlineAlertsForUser($user): Collection
    {
        $until = Carbon::now()->addDays(config('crm.deadline_alert_days', 7));

        $opportunities = $this->scopeAssigned(Opportunity::query())
            ->with('account')
            ->whereIn('stage', Opportunity::OPEN_STAGES)
            ->whereNotNull('close_date')
            ->where('close_date', '<=', $until->toDateString())
            ->orderBy('close_date')
            ->get()
            ->map(function (Opportunity $opp) {
                $date = Carbon::parse($opp->close_date)->startOfDay();

                return [
                    'kind' => 'opportunity',
                    'title' => $opp->name,
                    'subtitle' => optional($opp->account)->name ?: $opp->company,
                    'date' => $date,
                    'date_label' => $this->deadlineLabel($date),
                    'overdue' => $date->isPast(),
                    'url' => route('opportunities.show', $opp),
                ];
            });

        $followups = Activity::with('account')
            ->where('assigned_to', $user->id)
            ->where('type', 'followup')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $until)
            ->orderBy('due_at')
            ->get()
            ->map(function (Activity $activity) {
                $date = $activity->due_at->copy()->startOfDay();

                return [
                    'kind' => 'followup',
                    'title' => $activity->subject,
                    'subtitle' => optional($activity->account)->name,
                    'date' => $date,
                    'date_label' => $this->deadlineLabel($date),
                    'overdue' => $activity->isOverdue(),
                    'url' => route('activities.edit', $activity),
                ];
            });

        return $opportunities
            ->concat($followups)
            ->sortBy('date')
            ->values();
    }

    protected function deadlineLabel(Carbon $date): string
    {
        $days = now()->startOfDay()->diffInDays($date, false);

        if ($days < 0) {
            return 'Terlambat '.abs($days).' hari';
        }

        if ($days === 0) {
            return 'Hari ini';
        }

        return $days.' hari lagi';
    }
}
