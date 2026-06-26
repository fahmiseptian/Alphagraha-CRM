<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Activity;
use App\Models\Espo\Account;
use App\Models\Espo\Lead;
use App\Models\Espo\Opportunity;
use App\Models\Quotation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    use ScopesToUser;

    public function __invoke()
    {
        $user = auth()->user();

        $customersCount = $this->scopeAssigned(Account::query())->count();
        $leadsCount = $this->scopeAssigned(Lead::query())->count();

        $quotationQuery = Quotation::query();
        if ($user->isSales()) {
            $quotationQuery->where('created_by', $user->id);
        }
        $quotationsCount = (clone $quotationQuery)->count();
        $quotationsValue = (clone $quotationQuery)->whereIn('status', ['sent', 'accepted'])->sum('total');
        $acceptedCount = (clone $quotationQuery)->where('status', 'accepted')->count();

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
            'customersCount', 'leadsCount', 'quotationsCount', 'quotationsValue',
            'acceptedCount', 'openPipeline', 'wonThisMonth', 'stageDistribution',
            'quotationStatus', 'upcomingActivities', 'overdueCount',
            'recentQuotations', 'recentCustomers', 'deadlineAlerts', 'showDeadlinePopup'
        ));
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
