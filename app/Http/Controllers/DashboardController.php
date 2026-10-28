<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Activity;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Espo\Account;
use App\Models\Espo\EspoUser;
use App\Models\Espo\Lead;
use App\Models\Espo\Opportunity;
use App\Models\OpportunityEntertainment;
use App\Models\OpportunitySalesOrder;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\CommissionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    use ScopesToUser;

    public function __invoke(Request $request)
    {
        $user = auth()->user();

        $period = $request->get('period', 'year');
        if (! in_array($period, ['year', 'month', '3months', '6months', 'alltime'], true)) {
            $period = 'year';
        }
        $periodRange = $this->periodDateRange($period);
        $periodLabel = $this->periodLabel($period);

        if ($user->isPurchasing()) {
            return $this->purchasingDashboard($period, $periodRange, $periodLabel);
        }

        if ($user->isFinance()) {
            return $this->financeDashboard($period, $periodRange, $periodLabel);
        }

        if ($user->isInvoice()) {
            return $this->invoiceDashboard($period, $periodRange, $periodLabel);
        }

        $leaderboardPeriod = $request->get('leaderboard_period', $period);
        if (! in_array($leaderboardPeriod, ['alltime', 'month', 'year', '3months', '6months'], true)) {
            $leaderboardPeriod = $period;
        }

        $leaderboardSort = $request->get('leaderboard_sort', 'total');
        if (! in_array($leaderboardSort, ['total', 'margin', 'percent'], true)) {
            $leaderboardSort = 'total';
        }
        if ($leaderboardSort === 'percent' && ! $user->isSuperAdmin() && ! $user->isSales()) {
            $leaderboardSort = 'total';
        }

        $catalogSort = $request->get('catalog_sort', 'total');
        if (! in_array($catalogSort, ['total', 'count'], true)) {
            $catalogSort = 'total';
        }

        if ($user->isSales()) {
            $leaderboardSort = 'percent';
        }

        $selectedSalesId = $request->get('sales');
        if ($user->isSales()) {
            $selectedSalesId = $user->id;
        } elseif ($selectedSalesId && ! is_string($selectedSalesId)) {
            $selectedSalesId = null;
        }

        $selectedSales = null;
        if ($selectedSalesId) {
            $selectedSales = EspoUser::query()->find($selectedSalesId);
            if (! $selectedSales) {
                $selectedSalesId = null;
            }
        }

        $salesLeaderboard = $this->buildSalesLeaderboard($leaderboardPeriod, $leaderboardSort);

        $brandLeaderboard = collect();
        $categoryLeaderboard = collect();
        if ($user->isSuperAdmin()) {
            $catalogLeaderboards = $this->buildCatalogLeaderboards(
                $leaderboardPeriod,
                $catalogSort,
                $selectedSalesId
            );
            $brandLeaderboard = $catalogLeaderboards['brands'];
            $categoryLeaderboard = $catalogLeaderboards['categories'];
        }

        $customersCount = $this->scopeAssigned(Account::query())->count();
        $leadsCount = $this->scopeAssigned(Lead::query())->count();

        $quotationQuery = Quotation::query();
        if ($user->isSales()) {
            $quotationQuery->where('created_by', $user->id);
        } elseif ($selectedSalesId) {
            $quotationQuery->where('created_by', $selectedSalesId);
        }
        $this->applyPeriodToDateColumn($quotationQuery, 'quotation_date', $periodRange);
        $quotationsCount = (clone $quotationQuery)->count();
        $activeQuotations = (clone $quotationQuery)
            ->where('status', 'sent')
            ->with('items')
            ->get();
        $quotationsValue = $activeQuotations->sum('total');
        $quotationsMargin = $activeQuotations->sum(fn (Quotation $quotation) => $quotation->totalItemsMargin());
        $sentCount = (clone $quotationQuery)->where('status', 'sent')->count();

        // Open pipeline — ikut periode + filter sales (jika dipilih).
        $openPipelineQuery = $this->scopeAssigned(Opportunity::query())
            ->whereIn('stage', Opportunity::OPEN_STAGES);
        $this->applySalesFilter($openPipelineQuery, $selectedSalesId);
        $this->applyPeriodToOpenOpportunities($openPipelineQuery, $periodRange);
        $openPipeline = $openPipelineQuery->sum('amount');

        $wonThisMonth = $this->scopeAssigned(Opportunity::query())
            ->where('stage', Opportunity::WON_STAGE)
            ->whereBetween('close_date', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
        $this->applySalesFilter($wonThisMonth, $selectedSalesId);
        $wonThisMonth = $wonThisMonth->sum('amount');

        $wonQuery = $this->scopeAssigned(Opportunity::query())
            ->where('stage', Opportunity::WON_STAGE);
        $this->applySalesFilter($wonQuery, $selectedSalesId);
        $this->applyPeriodToDateColumn($wonQuery, 'close_date', $periodRange);
        $wonTotal = (clone $wonQuery)->sum('amount');
        $wonMargin = (float) (clone $wonQuery)->sum('crm_won_margin');

        // Distribusi stage — ikut periode + filter sales.
        $stageDistribution = $this->buildStageDistribution($periodRange, $selectedSalesId);

        // Detail pipeline per deal (saat sales dipilih, atau selalu ringkas).
        $pipelineDetails = $this->buildPipelineDetails($periodRange, $selectedSalesId);

        // Distribusi status penawaran.
        $quotationStatus = (clone $quotationQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        // Detail Dashboard: rekapan harian penjualan & margin.
        $detailStart = $request->get('detail_start')
            ? Carbon::parse($request->get('detail_start'))->startOfDay()
            : ($periodRange[0] ?? Carbon::now()->startOfMonth());
        $detailEnd = $request->get('detail_end')
            ? Carbon::parse($request->get('detail_end'))->endOfDay()
            : ($periodRange[1] ?? Carbon::now()->endOfDay());
        if ($detailStart->gt($detailEnd)) {
            [$detailStart, $detailEnd] = [$detailEnd->copy()->startOfDay(), $detailStart->copy()->endOfDay()];
        }
        $showDetail = $request->boolean('detail');
        $dailyRecap = $showDetail
            ? $this->buildDailyRecap($detailStart, $detailEnd, $selectedSalesId)
            : collect();

        // Aktivitas: tugas mendatang & terlambat milik user.
        Activity::approvePastPendingEvents();
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
        $recentQuotations = Quotation::query()
            ->when($user->isSales(), fn ($q) => $q->where('created_by', $user->id))
            ->when(! $user->isSales() && $selectedSalesId, fn ($q) => $q->where('created_by', $selectedSalesId))
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

        $pipelineTitle = $selectedSales
            ? 'Sales Pipeline - '.$selectedSales->display_name
            : 'Sales Pipeline';

        return view('dashboard', compact(
            'customersCount', 'leadsCount', 'quotationsCount', 'quotationsValue', 'quotationsMargin',
            'sentCount', 'openPipeline', 'wonThisMonth', 'wonTotal', 'wonMargin', 'stageDistribution',
            'quotationStatus', 'upcomingActivities', 'overdueCount',
            'recentQuotations', 'recentCustomers', 'deadlineAlerts', 'showDeadlinePopup',
            'salesLeaderboard', 'leaderboardPeriod', 'leaderboardSort',
            'brandLeaderboard', 'categoryLeaderboard', 'catalogSort',
            'period', 'periodLabel',
            'selectedSalesId', 'selectedSales', 'pipelineTitle', 'pipelineDetails',
            'showDetail', 'detailStart', 'detailEnd', 'dailyRecap'
        ));
    }

    /**
     * Dashboard purchasing: hanya Closed Won yang perlu dikerjakan (PO).
     */
    protected function purchasingDashboard(string $period, ?array $periodRange, string $periodLabel)
    {
        $wonBase = Opportunity::query()->where('stage', Opportunity::WON_STAGE);
        $this->applyPeriodToDateColumn($wonBase, 'close_date', $periodRange);

        $needsPoQuery = (clone $wonBase)->whereDoesntHave('purchaseOrders');
        $needsPoCount = (clone $needsPoQuery)->count();
        $needsPo = $needsPoQuery
            ->with(['account', 'assignedUser'])
            ->orderByDesc('close_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $wonPeriodCount = (clone $wonBase)->count();
        $wonWithPoCount = (clone $wonBase)->whereHas('purchaseOrders')->count();

        $poPeriodQuery = PurchaseOrder::query();
        $this->applyPeriodToDateColumn($poPeriodQuery, 'created_at', $periodRange);
        $poPeriodCount = (clone $poPeriodQuery)->count();
        $recentPos = (clone $poPeriodQuery)
            ->with(['opportunity.account', 'vendor', 'items.vendorQuotes'])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return view('dashboard-purchasing', compact(
            'period',
            'periodLabel',
            'needsPo',
            'needsPoCount',
            'wonPeriodCount',
            'wonWithPoCount',
            'poPeriodCount',
            'recentPos'
        ));
    }

    /**
     * Dashboard Finance: fokus invoice, pembayaran, DO, dan entertainment.
     */
    protected function financeDashboard(string $period, ?array $periodRange, string $periodLabel)
    {
        $soBase = $this->closedWonSalesOrdersQuery($periodRange);
        $commissions = app(CommissionService::class);

        $needsInvoiceQuery = $this->scopeSalesOrdersMissingInvoice(clone $soBase);
        $needsInvoiceCount = (clone $needsInvoiceQuery)->count();
        $needsInvoice = $needsInvoiceQuery
            ->with(['opportunity.account', 'opportunity.assignedUser', 'creator'])
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();

        $unpaidQuery = $this->scopeSalesOrdersUnpaid(clone $soBase);
        $unpaidCount = (clone $unpaidQuery)->count();
        $unpaidOrders = $unpaidQuery
            ->with(['opportunity.account', 'opportunity.assignedUser', 'creator'])
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();

        $deliveryPendingCount = $this->scopeSalesOrdersDeliveryPending(clone $soBase)->count();
        $paidCount = $this->scopeSalesOrdersPaid(clone $soBase)->count();
        $soPeriodCount = (clone $soBase)->count();

        // Antrian approval: semua pending (tidak dibatasi periode) agar finance tidak melewatkan request.
        $entertainmentQuery = OpportunityEntertainment::query()
            ->where('status', OpportunityEntertainment::STATUS_PENDING)
            ->whereHas('opportunity');
        $entertainmentPendingCount = (clone $entertainmentQuery)->count();
        $entertainmentPendingAmount = (float) (clone $entertainmentQuery)->sum('amount');
        $entertainmentPending = (clone $entertainmentQuery)
            ->with(['opportunity.account', 'opportunity.assignedUser', 'creator'])
            ->orderByDesc('entertainment_date')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function (OpportunityEntertainment $item) use ($commissions) {
                $salesId = (string) ($item->opportunity?->assigned_user_id ?? '');
                $periodDate = $commissions->entertainmentPeriodDate($item) ?? now();
                $periodKey = null;
                if ($salesId !== '') {
                    $profile = UserProfile::query()->where('user_id', $salesId)->first()
                        ?: UserProfile::ensureForUser($salesId, true, User::ROLE_SALES);
                    $periodKey = $commissions->resolveQuarterFromDate($profile, $periodDate)['key'] ?? null;
                }

                $item->setAttribute('commission_sales_id', $salesId !== '' ? $salesId : null);
                $item->setAttribute('commission_year', (int) $periodDate->year);
                $item->setAttribute('commission_period', $periodKey);
                $item->setAttribute(
                    'commission_url',
                    $salesId !== ''
                        ? route('commissions.show', array_filter([
                            'user' => $salesId,
                            'year' => (int) $periodDate->year,
                            'period' => $periodKey,
                        ]))
                        : null
                );

                return $item;
            });

        $salesUsersCount = User::query()
            ->where('deleted', 0)
            ->whereHas('profile', fn ($q) => $q->where('app_role', User::ROLE_SALES))
            ->count();

        $topApprovalPending = Opportunity::query()
            ->where('deleted', 0)
            ->where('crm_top_status', Opportunity::TOP_PENDING)
            ->with(['account', 'assignedUser'])
            ->orderByDesc('crm_top_requested_at')
            ->limit(20)
            ->get()
            ->filter(fn (Opportunity $opp) => $opp->topNeedsApproval())
            ->values();

        return view('dashboard-finance', compact(
            'topApprovalPending',
            'period',
            'periodLabel',
            'needsInvoice',
            'needsInvoiceCount',
            'unpaidOrders',
            'unpaidCount',
            'deliveryPendingCount',
            'paidCount',
            'soPeriodCount',
            'entertainmentPending',
            'entertainmentPendingCount',
            'entertainmentPendingAmount',
            'salesUsersCount',
        ));
    }

    /**
     * Dashboard Invoice: fokus isi invoice, follow-up unpaid, dan DO.
     */
    protected function invoiceDashboard(string $period, ?array $periodRange, string $periodLabel)
    {
        $soBase = $this->closedWonSalesOrdersQuery($periodRange);

        $needsInvoiceQuery = $this->scopeSalesOrdersMissingInvoice(clone $soBase);
        $needsInvoiceCount = (clone $needsInvoiceQuery)->count();
        $needsInvoice = $needsInvoiceQuery
            ->with(['opportunity.account', 'opportunity.assignedUser', 'creator'])
            ->orderByDesc('created_at')
            ->limit(40)
            ->get();

        $unpaidInvoicedQuery = $this->scopeSalesOrdersUnpaid(clone $soBase)
            ->whereNotNull('agc_payload->invoice_no')
            ->where('agc_payload->invoice_no', '!=', '');
        $unpaidInvoicedCount = (clone $unpaidInvoicedQuery)->count();
        $unpaidInvoiced = $unpaidInvoicedQuery
            ->with(['opportunity.account', 'opportunity.assignedUser', 'creator'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $deliveryPendingQuery = $this->scopeSalesOrdersDeliveryPending(clone $soBase);
        $deliveryPendingCount = (clone $deliveryPendingQuery)->count();
        $deliveryPending = $deliveryPendingQuery
            ->with(['opportunity.account', 'opportunity.assignedUser', 'creator'])
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        $paidCount = $this->scopeSalesOrdersPaid(clone $soBase)->count();
        $soPeriodCount = (clone $soBase)->count();
        $invoicedCount = (clone $soBase)
            ->whereNotNull('agc_payload->invoice_no')
            ->where('agc_payload->invoice_no', '!=', '')
            ->count();

        return view('dashboard-invoice', compact(
            'period',
            'periodLabel',
            'needsInvoice',
            'needsInvoiceCount',
            'unpaidInvoiced',
            'unpaidInvoicedCount',
            'deliveryPending',
            'deliveryPendingCount',
            'paidCount',
            'soPeriodCount',
            'invoicedCount',
        ));
    }

    protected function closedWonSalesOrdersQuery(?array $periodRange): Builder
    {
        $query = OpportunitySalesOrder::query()
            ->whereHas('opportunity', fn (Builder $q) => $q->where('stage', Opportunity::WON_STAGE));

        $this->applyPeriodToDateColumn($query, 'created_at', $periodRange);
        $this->scopeSalesOrdersActive($query);

        return $query;
    }

    protected function scopeSalesOrdersActive(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $q) {
                $q->whereNull('agc_payload->so_status')
                    ->orWhere('agc_payload->so_status', '!=', 'cancelled');
            })
            ->where(function (Builder $q) {
                $q->whereNull('agc_payload->cancel_status')
                    ->orWhere('agc_payload->cancel_status', '!=', OpportunitySalesOrder::CANCEL_APPROVED);
            });
    }

    protected function scopeSalesOrdersMissingInvoice(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('agc_payload->invoice_no')
                ->orWhere('agc_payload->invoice_no', '');
        });
    }

    protected function scopeSalesOrdersUnpaid(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('agc_payload->payment_status')
                ->orWhere('agc_payload->payment_status', '')
                ->orWhere('agc_payload->payment_status', 'unpaid')
                ->orWhere('agc_payload->payment_status', 'pending');
        });
    }

    protected function scopeSalesOrdersPaid(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('agc_payload->payment_status', 'paid')
                ->orWhere('agc_payload->payment_status', 'settlement');
        });
    }

    protected function scopeSalesOrdersDeliveryPending(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('agc_payload->delivery_status')
                ->orWhere('agc_payload->delivery_status', '')
                ->orWhere('agc_payload->delivery_status', 'pending');
        });
    }

    /**
     * Index brand & kategori Closed Won (semua entri, bukan hanya top leaderboard).
     * Khusus admin / superadmin.
     */
    public function catalogIndex(Request $request)
    {
        if (! auth()->user()?->canViewCatalogDashboard()) {
            abort(403, 'Hanya admin yang dapat melihat dashboard brand & kategori.');
        }

        $type = (string) $request->get('type', 'brand');
        if (! in_array($type, ['brand', 'category'], true)) {
            $type = 'brand';
        }

        $period = (string) $request->get('leaderboard_period', $request->get('period', 'year'));
        if (! in_array($period, ['alltime', 'month', 'year', '3months', '6months'], true)) {
            $period = 'year';
        }

        $sort = (string) $request->get('catalog_sort', 'total');
        if (! in_array($sort, ['name', 'count', 'won', 'items', 'total', 'margin'], true)) {
            $sort = 'total';
        }

        $sortDir = strtolower((string) $request->get('dir', $sort === 'name' ? 'asc' : 'desc'));
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = $sort === 'name' ? 'asc' : 'desc';
        }

        $search = trim((string) $request->get('q', ''));
        $includeEmpty = $request->boolean('include_empty', true);
        $perPage = 25;
        $page = max(1, (int) $request->get('page', 1));

        // Sort awal di builder cukup total; urutan final ditentukan di bawah (termasuk dir).
        $catalog = $this->buildCatalogDirectory($period, 'total', null, $includeEmpty);
        $entries = $type === 'brand' ? $catalog['brands'] : $catalog['categories'];

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $entries = $entries->filter(
                fn (array $entry) => str_contains(mb_strtolower($entry['name']), $needle)
            )->values();
        }

        $entries = $entries
            ->sort(function (array $a, array $b) use ($sort, $sortDir) {
                $cmp = match ($sort) {
                    'name' => strcasecmp($a['name'], $b['name']),
                    'count' => $a['oppti_count'] <=> $b['oppti_count'],
                    'won' => $a['won_count'] <=> $b['won_count'],
                    'items' => $a['item_count'] <=> $b['item_count'],
                    'margin' => $a['won_margin'] <=> $b['won_margin'],
                    default => $a['won_total'] <=> $b['won_total'],
                };
                if ($cmp === 0 && $sort !== 'name') {
                    $cmp = strcasecmp($a['name'], $b['name']);
                }

                return $sortDir === 'asc' ? $cmp : -$cmp;
            })
            ->values()
            ->map(fn (array $entry, int $index) => array_merge($entry, ['rank' => $index + 1]));

        $total = $entries->count();
        $pageItems = $entries->forPage($page, $perPage)->values();
        $paginator = new LengthAwarePaginator(
            $pageItems,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $typeLabel = $type === 'brand' ? 'Brand' : 'Category';
        $periodLabel = $this->periodLabel($period);

        return view('dashboard.catalog-index', [
            'type' => $type,
            'typeLabel' => $typeLabel,
            'period' => $period,
            'periodLabel' => $periodLabel,
            'sort' => $sort,
            'sortDir' => $sortDir,
            'search' => $search,
            'includeEmpty' => $includeEmpty,
            'entries' => $paginator,
            'totalCount' => $total,
        ]);
    }

    /**
     * Daftar opportunity Closed Won yang memakai brand / category tertentu.
     */
    public function catalog(Request $request)
    {
        if (! auth()->user()?->canViewCatalogDashboard()) {
            abort(403, 'Hanya admin yang dapat melihat daftar ini.');
        }

        $type = (string) $request->get('type', '');
        if (! in_array($type, ['brand', 'category'], true)) {
            abort(404);
        }

        $name = trim((string) $request->get('name', ''));
        if ($name === '') {
            abort(404);
        }

        $period = (string) $request->get('leaderboard_period', $request->get('period', 'year'));
        if (! in_array($period, ['alltime', 'month', 'year', '3months', '6months'], true)) {
            $period = 'year';
        }

        $selectedSalesId = $request->get('sales');
        if ($selectedSalesId && ! is_string($selectedSalesId)) {
            $selectedSalesId = null;
        }

        $selectedSales = null;
        if ($selectedSalesId) {
            $selectedSales = EspoUser::query()->find($selectedSalesId);
            if (! $selectedSales) {
                $selectedSalesId = null;
            }
        }

        $needle = mb_strtolower($name);
        $canonicalNames = $type === 'brand'
            ? Brand::query()->pluck('name')
            : Category::query()->pluck('name');
        $displayName = $canonicalNames
            ->first(fn ($label) => mb_strtolower(trim((string) $label)) === $needle) ?: $name;

        $field = $type === 'brand' ? 'brand' : 'category';
        $column = $type === 'brand' ? 'crm_item_brand' : 'crm_item_category';
        $like = '%'.addcslashes($needle, '%_\\').'%';

        $periodRange = $this->periodDateRange($period);

        // Semua opportunity di periode (created_at) + Closed Won by close_date → tabel + status
        $query = Opportunity::query()
            ->with(['account', 'assignedUser'])
            ->where(function ($q) use ($periodRange) {
                if ($periodRange === null) {
                    return;
                }
                [$start, $end] = $periodRange;
                $q->whereBetween('created_at', [$start, $end])
                    ->orWhere(function ($won) use ($start, $end) {
                        $won->where('stage', Opportunity::WON_STAGE)
                            ->whereBetween('close_date', [
                                $start->toDateString(),
                                $end->toDateString(),
                            ]);
                    });
            });
        $this->applySalesFilter($query, $selectedSalesId);
        $query->whereRaw('LOWER(COALESCE('.$column.', "")) LIKE ?', [$like]);

        $deals = collect();
        foreach ($query->orderByDesc('close_date')->orderByDesc('created_at')->orderByDesc('id')->get() as $opportunity) {
            $matched = $opportunity->products
                ->filter(fn (array $product) => mb_strtolower(trim((string) ($product[$field] ?? ''))) === $needle)
                ->values();

            if ($matched->isEmpty()) {
                continue;
            }

            $isWon = (string) $opportunity->stage === Opportunity::WON_STAGE;
            $deals->push([
                'opportunity' => $opportunity,
                'products' => $matched,
                'item_count' => $matched->count(),
                'is_won' => $isWon,
                'won_total' => $isWon
                    ? (float) $matched->sum(fn (array $product) => (float) ($product['subtotal'] ?? 0))
                    : 0.0,
                'won_margin' => $isWon
                    ? (float) $matched->sum(fn (array $product) => (float) ($product['margin'] ?? 0))
                    : 0.0,
            ]);
        }

        $wonDeals = $deals->where('is_won', true);
        $totals = [
            'oppti_count' => $deals->count(),
            'won_count' => $wonDeals->count(),
            'deal_count' => $wonDeals->count(),
            'item_count' => (int) $wonDeals->sum('item_count'),
            'won_total' => (float) $wonDeals->sum('won_total'),
            'won_margin' => (float) $wonDeals->sum('won_margin'),
        ];

        $typeLabel = $type === 'brand' ? 'Brand' : 'Category';
        $periodLabel = $this->periodLabel($period);
        $dashboardQuery = array_filter([
            'period' => $period,
            'leaderboard_period' => $period,
            'sales' => $selectedSalesId,
        ], fn ($value) => filled($value));
        $indexQuery = array_filter([
            'type' => $type,
            'leaderboard_period' => $period,
            'period' => $period,
            'sales' => $selectedSalesId,
            'catalog_sort' => $request->get('catalog_sort'),
            'q' => $request->get('q'),
        ], fn ($value) => filled($value));

        return view('dashboard.catalog', compact(
            'type', 'typeLabel', 'name', 'displayName',
            'period', 'periodLabel', 'selectedSalesId', 'selectedSales',
            'deals', 'totals', 'dashboardQuery', 'indexQuery'
        ));
    }

    protected function applySalesFilter($query, ?string $salesId): void
    {
        if ($salesId) {
            $query->where('assigned_user_id', $salesId);
        }
    }

    protected function applyPeriodToOpenOpportunities($query, ?array $range): void
    {
        if ($range === null) {
            return;
        }

        [$start, $end] = $range;
        $query->whereBetween('created_at', [$start, $end]);
    }

    protected function buildStageDistribution(?array $periodRange, ?string $salesId): array
    {
        $query = $this->scopeAssigned(Opportunity::query());
        $this->applySalesFilter($query, $salesId);
        $this->applyPeriodToStageQuery($query, $periodRange);

        $rows = $query
            ->selectRaw('stage, COUNT(*) as total, COALESCE(SUM(amount), 0) as value')
            ->groupBy('stage')
            ->get()
            ->keyBy('stage');

        $distribution = [];
        foreach (Opportunity::STAGES as $stage) {
            if ($rows->has($stage)) {
                $distribution[$stage] = (int) $rows->get($stage)->total;
            }
        }

        // Stage lain (jika ada di data) tetap ditampilkan di akhir.
        foreach ($rows as $stage => $row) {
            if (! array_key_exists($stage, $distribution)) {
                $distribution[$stage] = (int) $row->total;
            }
        }

        return $distribution;
    }

    protected function applyPeriodToStageQuery($query, ?array $range): void
    {
        if ($range === null) {
            return;
        }

        [$start, $end] = $range;
        $closed = [Opportunity::WON_STAGE, Opportunity::LOST_STAGE];

        $query->where(function ($q) use ($start, $end, $closed) {
            $q->where(function ($q2) use ($start, $end, $closed) {
                $q2->whereIn('stage', $closed)
                    ->whereBetween('close_date', [$start->toDateString(), $end->toDateString()]);
            })->orWhere(function ($q2) use ($start, $end, $closed) {
                $q2->whereNotIn('stage', $closed)
                    ->whereBetween('created_at', [$start, $end]);
            });
        });
    }

    protected function buildPipelineDetails(?array $periodRange, ?string $salesId): Collection
    {
        if (! $salesId && ! auth()->user()?->isSales()) {
            // Tanpa sales terpilih: tampilkan ringkas top deal open saja.
            $query = $this->scopeAssigned(Opportunity::query())
                ->with('account')
                ->whereIn('stage', Opportunity::OPEN_STAGES)
                ->orderByDesc('amount')
                ->limit(8);
            $this->applyPeriodToOpenOpportunities($query, $periodRange);

            return $query->get();
        }

        $query = $this->scopeAssigned(Opportunity::query())
            ->with('account')
            ->orderByRaw("FIELD(stage, 'Prospecting','Qualification','Proposal','Negotiation','Closed Won','Closed Lost')")
            ->orderByDesc('amount')
            ->limit(40);
        $this->applySalesFilter($query, $salesId);
        $this->applyPeriodToStageQuery($query, $periodRange);

        return $query->get();
    }

    protected function buildDailyRecap(Carbon $start, Carbon $end, ?string $salesId): Collection
    {
        $query = $this->scopeAssigned(Opportunity::query())
            ->where('stage', Opportunity::WON_STAGE)
            ->whereBetween('close_date', [$start->toDateString(), $end->toDateString()]);
        $this->applySalesFilter($query, $salesId);

        return $query
            ->selectRaw('DATE(close_date) as day, COUNT(*) as deal_count, COALESCE(SUM(amount), 0) as won_total, COALESCE(SUM(crm_won_margin), 0) as won_margin')
            ->groupByRaw('DATE(close_date)')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => [
                'day' => Carbon::parse($row->day),
                'deal_count' => (int) $row->deal_count,
                'won_total' => (float) $row->won_total,
                'won_margin' => (float) $row->won_margin,
            ]);
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
            ->get()
            ->keyBy('assigned_user_id');

        // Sertakan sales yang punya deal open di periode, agar nama tetap muncul.
        $openQuery = Opportunity::query()
            ->whereIn('stage', Opportunity::OPEN_STAGES)
            ->whereNotNull('assigned_user_id');
        $openRange = $this->periodDateRange($period);
        $this->applyPeriodToOpenOpportunities($openQuery, $openRange);
        $openRows = $openQuery
            ->selectRaw('assigned_user_id, COUNT(*) as open_count, COALESCE(SUM(amount), 0) as open_total')
            ->groupBy('assigned_user_id')
            ->get()
            ->keyBy('assigned_user_id');

        $userIds = $wonRows->keys()->merge($openRows->keys())->unique()->values();

        if ($userIds->isEmpty()) {
            return collect();
        }

        $users = EspoUser::query()
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        $profiles = UserProfile::query()
            ->whereIn('user_id', $userIds)
            ->get()
            ->keyBy('user_id');

        $entries = $userIds->map(function ($userId) use ($users, $profiles, $wonRows, $openRows) {
            $user = $users->get($userId);
            $profile = $profiles->get($userId);
            $won = $wonRows->get($userId);
            $open = $openRows->get($userId);
            $salesTarget = $profile?->resolvedSalesTarget()
                ?? UserProfile::DEFAULT_SALES_TARGET;
            $wonTotal = (float) ($won->won_total ?? 0);
            $wonMargin = (float) ($won->won_margin ?? 0);
            // Target sales diukur dari margin Closed Won (bukan amount).
            $targetWonMargin = $profile
                ? $this->wonMarginForTargetPeriod($userId, $profile)
                : $wonMargin;
            $deadline = $profile?->resolvedSalesTargetDeadline();
            $targetRemaining = max(0, $salesTarget - $targetWonMargin);
            $targetMet = $targetWonMargin >= $salesTarget && $salesTarget > 0;

            return [
                'user_id' => $userId,
                'name' => $user?->display_name ?? 'Unknown',
                'won_count' => (int) ($won->won_count ?? 0),
                'won_total' => $wonTotal,
                'won_margin' => $wonMargin,
                'open_count' => (int) ($open->open_count ?? 0),
                'open_total' => (float) ($open->open_total ?? 0),
                'sales_target' => $salesTarget,
                'target_period' => $profile?->resolvedSalesTargetPeriod() ?? UserProfile::TARGET_PERIOD_1_YEAR,
                'target_period_label' => $profile?->salesTargetPeriodLabel()
                    ?? UserProfile::TARGET_PERIODS[UserProfile::TARGET_PERIOD_1_YEAR],
                'target_deadline' => $deadline,
                'target_deadline_label' => $deadline?->format('d M Y'),
                'target_won_total' => $targetWonMargin,
                'target_remaining' => $targetRemaining,
                'target_met' => $targetMet,
                'target_progress' => $salesTarget > 0
                    ? round(($targetWonMargin / $salesTarget) * 100, 1)
                    : 0,
            ];
        });

        $sorted = $entries->sort(function (array $a, array $b) use ($sort) {
            return match ($sort) {
                'margin' => $b['won_margin'] <=> $a['won_margin']
                    ?: $b['won_total'] <=> $a['won_total']
                    ?: $b['open_total'] <=> $a['open_total'],
                'percent' => $b['target_progress'] <=> $a['target_progress']
                    ?: $b['won_total'] <=> $a['won_total']
                    ?: $b['open_total'] <=> $a['open_total'],
                default => $b['won_total'] <=> $a['won_total']
                    ?: $b['won_margin'] <=> $a['won_margin']
                    ?: $b['open_total'] <=> $a['open_total'],
            };
        })->values()->take(10);

        return $sorted->map(fn (array $entry, int $index) => array_merge($entry, [
            'rank' => $index + 1,
        ]));
    }

    /**
     * Ranking brand & category dari baris produk Closed Won.
     *
     * @return array{brands: Collection, categories: Collection}
     */
    protected function buildCatalogLeaderboards(string $period, string $sort, ?string $salesId): array
    {
        $query = Opportunity::query()->where('stage', Opportunity::WON_STAGE);
        $this->applyLeaderboardPeriodToCloseDate($query, $period);
        $this->applySalesFilter($query, $salesId);

        $brands = [];
        $categories = [];

        $query->lazyById(50)->each(function (Opportunity $opportunity) use (&$brands, &$categories) {
            foreach ($opportunity->products as $product) {
                $total = (float) ($product['subtotal'] ?? 0);
                $margin = (float) ($product['margin'] ?? 0);

                $this->accumulateCatalogEntry($brands, (string) ($product['brand'] ?? ''), $total, $margin, (string) $opportunity->id);
                $this->accumulateCatalogEntry($categories, (string) ($product['category'] ?? ''), $total, $margin, (string) $opportunity->id);
            }
        });

        return [
            'brands' => $this->rankCatalogEntries(
                $brands,
                Brand::query()->pluck('name'),
                $sort,
                limit: 10
            ),
            'categories' => $this->rankCatalogEntries(
                $categories,
                Category::query()->pluck('name'),
                $sort,
                limit: 10
            ),
        ];
    }

    /**
     * Semua brand & kategori (termasuk yang tidak masuk top leaderboard).
     *
     * @return array{brands: Collection, categories: Collection}
     */
    protected function buildCatalogDirectory(
        string $period,
        string $sort,
        ?string $salesId,
        bool $includeEmpty = true
    ): array {
        $brands = [];
        $categories = [];
        $periodRange = $this->periodDateRange($period);

        // Semua opportunity (periode by created_at) → Jumlah Oppti
        $allQuery = Opportunity::query();
        $this->applyPeriodToDateColumn($allQuery, 'created_at', $periodRange);
        $this->applySalesFilter($allQuery, $salesId);
        $allQuery->lazyById(50)->each(function (Opportunity $opportunity) use (&$brands, &$categories) {
            $seenBrand = [];
            $seenCategory = [];
            foreach ($opportunity->products as $product) {
                $brand = trim((string) ($product['brand'] ?? ''));
                $category = trim((string) ($product['category'] ?? ''));
                $brandKey = mb_strtolower($brand);
                $categoryKey = mb_strtolower($category);

                if ($brand !== '' && ! isset($seenBrand[$brandKey])) {
                    $this->registerCatalogOpportunity($brands, $brand, (string) $opportunity->id);
                    $seenBrand[$brandKey] = true;
                }
                if ($category !== '' && ! isset($seenCategory[$categoryKey])) {
                    $this->registerCatalogOpportunity($categories, $category, (string) $opportunity->id);
                    $seenCategory[$categoryKey] = true;
                }
            }
        });

        // Closed Won (periode by close_date) → Jumlah Close Won + item/total/margin
        $wonQuery = Opportunity::query()->where('stage', Opportunity::WON_STAGE);
        $this->applyLeaderboardPeriodToCloseDate($wonQuery, $period);
        $this->applySalesFilter($wonQuery, $salesId);
        $wonQuery->lazyById(50)->each(function (Opportunity $opportunity) use (&$brands, &$categories) {
            foreach ($opportunity->products as $product) {
                $total = (float) ($product['subtotal'] ?? 0);
                $margin = (float) ($product['margin'] ?? 0);

                $this->accumulateCatalogEntry($brands, (string) ($product['brand'] ?? ''), $total, $margin, (string) $opportunity->id);
                $this->accumulateCatalogEntry($categories, (string) ($product['category'] ?? ''), $total, $margin, (string) $opportunity->id);
            }
        });

        $brandNames = Brand::query()->orderBy('name')->pluck('name');
        $categoryNames = Category::query()->orderBy('name')->pluck('name');

        if ($includeEmpty) {
            $this->seedEmptyCatalogEntries($brands, $brandNames);
            $this->seedEmptyCatalogEntries($categories, $categoryNames);
        }

        return [
            'brands' => $this->rankCatalogEntries($brands, $brandNames, $sort, limit: null),
            'categories' => $this->rankCatalogEntries($categories, $categoryNames, $sort, limit: null),
        ];
    }

    /**
     * @param  array<string, array{name: string, won_total: float, won_margin: float, item_count: int, deal_ids: array<string, true>, oppti_ids: array<string, true>}>  $bucket
     */
    protected function ensureCatalogBucket(array &$bucket, string $label): ?string
    {
        $label = trim($label);
        if ($label === '') {
            return null;
        }

        $key = mb_strtolower($label);
        if (! isset($bucket[$key])) {
            $bucket[$key] = [
                'name' => $label,
                'won_total' => 0.0,
                'won_margin' => 0.0,
                'item_count' => 0,
                'deal_ids' => [],
                'oppti_ids' => [],
            ];
        } elseif (! isset($bucket[$key]['oppti_ids'])) {
            $bucket[$key]['oppti_ids'] = [];
        }

        return $key;
    }

    /**
     * Catat opportunity (semua stage) ke Jumlah Oppti.
     *
     * @param  array<string, array{name: string, won_total: float, won_margin: float, item_count: int, deal_ids: array<string, true>, oppti_ids: array<string, true>}>  $bucket
     */
    protected function registerCatalogOpportunity(array &$bucket, string $label, string $opportunityId): void
    {
        $key = $this->ensureCatalogBucket($bucket, $label);
        if ($key === null || $opportunityId === '') {
            return;
        }

        $bucket[$key]['oppti_ids'][$opportunityId] = true;
    }

    /**
     * @param  array<string, array{name: string, won_total: float, won_margin: float, item_count: int, deal_ids: array<string, true>, oppti_ids?: array<string, true>}>  $bucket
     */
    protected function accumulateCatalogEntry(array &$bucket, string $label, float $total, float $margin, string $opportunityId): void
    {
        $key = $this->ensureCatalogBucket($bucket, $label);
        if ($key === null) {
            return;
        }

        $bucket[$key]['won_total'] += $total;
        $bucket[$key]['won_margin'] += $margin;
        $bucket[$key]['item_count']++;
        if ($opportunityId !== '') {
            $bucket[$key]['deal_ids'][$opportunityId] = true;
            $bucket[$key]['oppti_ids'][$opportunityId] = true;
        }
    }

    /**
     * Pastikan master brand/kategori muncul meski belum ada Closed Won.
     *
     * @param  array<string, array{name: string, won_total: float, won_margin: float, item_count: int, deal_ids: array<string, true>, oppti_ids?: array<string, true>}>  $bucket
     */
    protected function seedEmptyCatalogEntries(array &$bucket, Collection $canonicalNames): void
    {
        foreach ($canonicalNames as $label) {
            $this->ensureCatalogBucket($bucket, (string) $label);
        }
    }

    /**
     * @param  array<string, array{name: string, won_total: float, won_margin: float, item_count: int, deal_ids?: array<string, true>, oppti_ids?: array<string, true>}>  $bucket
     */
    protected function rankCatalogEntries(
        array $bucket,
        Collection $canonicalNames,
        string $sort,
        ?int $limit = 10
    ): Collection {
        $canonical = $canonicalNames
            ->filter(fn ($name) => filled(trim((string) $name)))
            ->mapWithKeys(fn ($name) => [mb_strtolower(trim((string) $name)) => trim((string) $name)]);

        $ranked = collect($bucket)
            ->map(function (array $entry, string $key) use ($canonical) {
                $entry['name'] = $canonical->get($key, $entry['name']);
                $entry['key'] = $key;
                $entry['won_count'] = count($entry['deal_ids'] ?? []);
                $entry['deal_count'] = $entry['won_count'];
                $entry['oppti_count'] = count($entry['oppti_ids'] ?? $entry['deal_ids'] ?? []);
                unset($entry['deal_ids'], $entry['oppti_ids']);

                return $entry;
            })
            ->sort(function (array $a, array $b) use ($sort) {
                return match ($sort) {
                    'count' => $b['oppti_count'] <=> $a['oppti_count']
                        ?: $b['won_total'] <=> $a['won_total'],
                    'won' => $b['won_count'] <=> $a['won_count']
                        ?: $b['won_total'] <=> $a['won_total'],
                    'margin' => $b['won_margin'] <=> $a['won_margin']
                        ?: $b['won_total'] <=> $a['won_total'],
                    default => $b['won_total'] <=> $a['won_total']
                        ?: $b['won_count'] <=> $a['won_count'],
                };
            })
            ->values();

        if ($limit !== null) {
            $ranked = $ranked->take($limit)->values();
        }

        return $ranked->map(fn (array $entry, int $index) => array_merge($entry, [
            'rank' => $index + 1,
        ]));
    }

    protected function wonMarginForTargetPeriod(string $userId, UserProfile $profile): float
    {
        [$start, $end] = $profile->salesTargetDateRange();

        return (float) Opportunity::query()
            ->where('stage', Opportunity::WON_STAGE)
            ->where('assigned_user_id', $userId)
            ->whereBetween('close_date', [$start->toDateString(), $end->toDateString()])
            ->sum('crm_won_margin');
    }

    protected function applyLeaderboardPeriodToCloseDate($query, string $period): void
    {
        $this->applyPeriodToDateColumn($query, 'close_date', $this->periodDateRange($period));
    }

    /**
     * @return array{0: Carbon, 1: Carbon}|null
     */
    protected function periodDateRange(string $period): ?array
    {
        $now = Carbon::now();

        return match ($period) {
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            '3months' => [$now->copy()->subMonthsNoOverflow(3)->startOfDay(), $now->copy()->endOfDay()],
            '6months' => [$now->copy()->subMonthsNoOverflow(6)->startOfDay(), $now->copy()->endOfDay()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'alltime' => null,
            default => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
        };
    }

    protected function periodLabel(string $period): string
    {
        return match ($period) {
            'month' => 'bulan ini',
            '3months' => '3 bulan terakhir',
            '6months' => '6 bulan terakhir',
            'year' => 'tahun ini',
            'alltime' => 'semua waktu',
            default => 'tahun ini',
        };
    }

    protected function applyPeriodToDateColumn($query, string $column, ?array $range): void
    {
        if ($range === null) {
            return;
        }

        [$start, $end] = $range;
        $query->whereBetween($column, [$start, $end]);
    }

    protected function deadlineAlertsForUser($user): Collection
    {
        $until = Carbon::now()->addDays(config('crm.deadline_alert_days', 7));
        $now = Carbon::now();

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

        // Activity hanya muncul jika reminder sudah jatuh tempo.
        $followups = Activity::with('account')
            ->where('assigned_to', $user->id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('reminder_at')
            ->where('reminder_at', '<=', $now)
            ->orderBy('reminder_at')
            ->get()
            ->map(function (Activity $activity) {
                $date = $activity->reminder_at->copy();

                return [
                    'kind' => 'followup',
                    'title' => $activity->subject,
                    'subtitle' => optional($activity->account)->name,
                    'date' => $date,
                    'date_label' => 'Reminder jatuh tempo',
                    'overdue' => true,
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
