<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Activity;
use App\Models\Espo\Account;
use App\Models\Espo\Lead;
use App\Models\Espo\Opportunity;
use App\Models\Quotation;
use Illuminate\Support\Carbon;

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

        return view('dashboard', compact(
            'customersCount', 'leadsCount', 'quotationsCount', 'quotationsValue',
            'acceptedCount', 'openPipeline', 'wonThisMonth', 'stageDistribution',
            'quotationStatus', 'upcomingActivities', 'overdueCount',
            'recentQuotations', 'recentCustomers'
        ));
    }
}
