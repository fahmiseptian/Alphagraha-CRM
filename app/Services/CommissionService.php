<?php

namespace App\Services;

use App\Models\CommissionExpense;
use App\Models\CrmSetting;
use App\Models\Espo\Opportunity;
use App\Models\OpportunityEntertainment;
use App\Models\OpportunitySalesOrder;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\CustomerTop;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CommissionService
{
    /**
     * Jenjang komisi dari rasio GP terhadap target.
     * Syaratnya GP harus lebih besar dari kelipatan target (bukan lebih besar atau sama).
     *
     * @var list<array{multiple: float, rate: float}>
     */
    public const SETTING_TIERS = 'commission.rate_tiers';

    public const SETTING_ROUND_TO = 'commission.round_to';

    public const DEFAULT_ROUND_TO = 500;

    /**
     * @return list<array{min_percent: float, rate_percent: float}>
     */
    public static function defaultRateTiers(): array
    {
        return [
            ['min_percent' => 500, 'rate_percent' => 14],
            ['min_percent' => 300, 'rate_percent' => 11],
            ['min_percent' => 200, 'rate_percent' => 9],
            ['min_percent' => 100, 'rate_percent' => 7],
            ['min_percent' => 50, 'rate_percent' => 4],
            ['min_percent' => 25, 'rate_percent' => 1.5],
        ];
    }

    public function __construct(
        protected PurchaseOrderReportService $poReports
    ) {}

    /**
     * Jenjang aktif, urut dari batas tertinggi.
     *
     * @return list<array{min_percent: float, rate_percent: float}>
     */
    public function rateTiers(): array
    {
        $stored = CrmSetting::get(self::SETTING_TIERS);
        $tiers = $this->normalizeRateTiers(is_array($stored) ? $stored : self::defaultRateTiers());

        return $tiers !== [] ? $tiers : $this->normalizeRateTiers(self::defaultRateTiers());
    }

    public function roundUnit(): float
    {
        $unit = (float) CrmSetting::get(self::SETTING_ROUND_TO, self::DEFAULT_ROUND_TO);

        return $unit >= 1 ? $unit : (float) self::DEFAULT_ROUND_TO;
    }

    /**
     * @param  array<int, mixed>  $tiers
     * @return list<array{min_percent: float, rate_percent: float}>
     */
    public function normalizeRateTiers(array $tiers): array
    {
        $clean = [];
        foreach ($tiers as $tier) {
            if (! is_array($tier)) {
                continue;
            }
            $min = round((float) ($tier['min_percent'] ?? -1), 4);
            $rate = round((float) ($tier['rate_percent'] ?? -1), 4);
            if ($min < 0 || $rate < 0 || $rate > 100) {
                continue;
            }
            $clean[] = [
                'min_percent' => $min,
                'rate_percent' => $rate,
            ];
        }

        usort($clean, fn (array $a, array $b) => $b['min_percent'] <=> $a['min_percent']);

        return array_values($clean);
    }

    /**
     * Komisi = GP × tarif jenjang, dibulatkan ke bawah sesuai pengaturan.
     *
     * @return array{rate: float, rate_percent: float, raw: float, amount: float}
     */
    public function commissionFromGp(float $gp, float $target): array
    {
        $rate = 0.0;
        if ($target > 0 && $gp > 0) {
            foreach ($this->rateTiers() as $tier) {
                if ($gp > ($target * ($tier['min_percent'] / 100))) {
                    $rate = $tier['rate_percent'] / 100;
                    break;
                }
            }
        }

        $raw = $gp * $rate;
        $unit = $this->roundUnit();
        $amount = $raw > 0 ? (float) (floor($raw / $unit) * $unit) : 0.0;

        return [
            'rate' => $rate,
            'rate_percent' => $rate * 100,
            'raw' => $raw,
            'amount' => $amount,
        ];
    }

    /**
     * Daftar sales aktif yang punya target.
     *
     * @return Collection<int, User>
     */
    public function salesUsers(): Collection
    {
        return User::query()
            ->where('deleted', 0)
            ->where('is_active', 1)
            ->whereHas('profile', fn ($q) => $q->where('app_role', User::ROLE_SALES))
            ->with('profile')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{key: string, index: int, label: string, start: Carbon, end: Carbon}|null
     */
    public function resolveQuarter(UserProfile $profile, int $year, ?string $periodKey = null): ?array
    {
        $quarters = $profile->commissionQuarters($year);
        if ($quarters === []) {
            return null;
        }

        if ($periodKey) {
            foreach ($quarters as $quarter) {
                if ($quarter['key'] === $periodKey) {
                    return $quarter;
                }
            }
        }

        $now = Carbon::now();
        if ($year === (int) $now->year) {
            return $profile->currentCommissionQuarter($now) ?? $quarters[0];
        }

        return $quarters[0];
    }

    /**
     * Tentukan periode komisi (Q) dari tanggal biaya.
     *
     * @return array{key: string, index: int, label: string, start: Carbon, end: Carbon}|null
     */
    public function resolveQuarterFromDate(UserProfile $profile, Carbon $date): ?array
    {
        return $profile->currentCommissionQuarter($date);
    }

    /**
     * Ringkasan komisi satu sales untuk satu Q.
     *
     * @return array<string, mixed>
     */
    public function buildForSales(User $sales, int $year, ?string $periodKey = null, bool $detailed = true): array
    {
        $profile = $sales->profile ?: UserProfile::ensureForUser($sales->id, true, User::ROLE_SALES);
        $quarter = $this->resolveQuarter($profile, $year, $periodKey);
        if (! $quarter) {
            return [
                'sales' => $sales,
                'profile' => $profile,
                'year' => $year,
                'quarters' => [],
                'quarter' => null,
            ];
        }

        // SO Complete: periodisasi tanggal dibuat + status complete.
        $orders = $this->completedSalesOrders($sales->id, $quarter['start'], $quarter['end']);
        // GP & Invoice: periodisasi tanggal invoice.
        $gpOrders = $this->invoicedSalesOrdersInPeriod($sales->id, $quarter['start'], $quarter['end']);
        $allOrders = $detailed
            ? $this->salesOrdersInPeriod($sales->id, $quarter['start'], $quarter['end'])
            : collect();

        $expenses = CommissionExpense::query()
            ->with(['account:id,name', 'category:id,name'])
            ->where('user_id', $sales->id)
            ->where('year', $year)
            ->where('period_key', $quarter['key'])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();

        $linkedExpenseBySo = [];
        foreach ($expenses as $expense) {
            if ($expense->sales_order_id === null) {
                continue;
            }
            $soId = (string) $expense->sales_order_id;
            $linkedExpenseBySo[$soId] = ($linkedExpenseBySo[$soId] ?? 0) + (float) $expense->amount;
        }

        $orderRows = [];
        $gpRows = [];
        $gpTotal = 0.0;
        $soTotal = 0.0;
        $soCompleteMarginTotal = 0.0;
        /** @var array<string, array{amount: float, margin: float}> $financialCache */
        $financialCache = [];

        foreach ($orders as $salesOrder) {
            $row = $this->buildMarginRow($salesOrder, $linkedExpenseBySo, $financialCache);
            if ($row === null) {
                continue;
            }
            $soTotal += $row['amount'];
            $soCompleteMarginTotal += $row['margin'];
            if ($detailed) {
                $orderRows[] = $row;
            }
        }

        foreach ($gpOrders as $salesOrder) {
            $row = $this->buildMarginRow($salesOrder, $linkedExpenseBySo, $financialCache);
            if ($row === null) {
                continue;
            }
            $gpTotal += $row['margin'];
            if ($detailed) {
                $gpRows[] = $row;
            }
        }

        $allOrderRows = [];
        $outstandingRows = [];
        $soAllTotal = 0.0;
        $soOutstandingTotal = 0.0;
        $soOverdueCount = 0;

        $invoiceAllRows = [];
        $invoiceOutstandingRows = [];
        $invoiceCompleteRows = [];
        $invoiceAllTotal = 0.0;
        $invoiceOutstandingTotal = 0.0;
        $invoiceCompleteTotal = 0.0;

        if ($detailed) {
            foreach ($allOrders as $salesOrder) {
                $row = $this->buildSalesOrderListRow($salesOrder, $financialCache);
                if ($row === null) {
                    continue;
                }
                $allOrderRows[] = $row;
                $soAllTotal += $row['amount'];

                // SO Outstanding = dibuat di periode, status belum complete.
                if (! ($row['is_complete'] ?? false)) {
                    $outstandingRows[] = $row;
                    $soOutstandingTotal += $row['amount'];
                    if ($row['is_overdue']) {
                        $soOverdueCount++;
                    }
                }
            }

            // Invoice buckets: reuse SO yang sudah difilter by tanggal invoice.
            foreach ($gpOrders as $salesOrder) {
                $row = $this->buildSalesOrderListRow($salesOrder, $financialCache);
                if ($row === null) {
                    continue;
                }
                $invoiceAllRows[] = $row;
                $invoiceAllTotal += $row['amount'];

                if ($row['is_unpaid']) {
                    $invoiceOutstandingRows[] = $row;
                    $invoiceOutstandingTotal += $row['amount'];
                } else {
                    $invoiceCompleteRows[] = $row;
                    $invoiceCompleteTotal += $row['amount'];
                }
            }
        }

        $entertainmentsInPeriod = $this->completedEntertainmentsForPeriod(
            $sales->id,
            $quarter['start'],
            $quarter['end']
        );
        $entertainmentTotal = round((float) $entertainmentsInPeriod->sum('amount'), 2);
        $entertainmentsPending = $detailed
            ? $this->pendingEntertainmentsForPeriod($sales->id, $quarter['start'], $quarter['end'])
            : collect();
        $entertainments = $detailed
            ? $entertainmentsInPeriod
                ->sortByDesc(fn (OpportunityEntertainment $item) => $this->entertainmentPeriodDate($item)?->timestamp ?? 0)
                ->values()
            : collect();

        $expenseLinkedTotal = round((float) $expenses->whereNotNull('sales_order_id')->sum('amount'), 2);
        $expenseUnlinkedTotal = round((float) $expenses->whereNull('sales_order_id')->sum('amount'), 2);
        $expenseTotal = round($expenseLinkedTotal + $expenseUnlinkedTotal, 2);

        // Biaya terikat SO sudah dipotong di margin SO (gp_total).
        $deductionTotal = round($entertainmentTotal + $expenseUnlinkedTotal, 2);
        $netGp = round($gpTotal - $deductionTotal, 2);
        $target = $profile->resolvedSalesTarget();
        $targetProgress = $target > 0
            ? round(($netGp / $target) * 100, 1)
            : 0.0;
        $targetMet = $target > 0 && $netGp >= $target;
        $commission = $this->commissionFromGp($netGp, $target);

        return [
            'sales' => $sales,
            'profile' => $profile,
            'year' => $year,
            'quarters' => $profile->commissionQuarters($year),
            'quarter' => $quarter,
            'target' => $target,
            'target_period' => $profile->resolvedSalesTargetPeriod(),
            'target_period_label' => $profile->salesTargetPeriodLabel(),
            'target_progress' => $targetProgress,
            'target_met' => $targetMet,
            'sales_orders' => $orderRows,
            'so_count' => $orders->count(),
            'so_total' => round($soTotal, 2),
            'so_complete_margin_total' => round($soCompleteMarginTotal, 2),
            'gp_orders' => $gpRows,
            'gp_order_count' => $gpOrders->count(),
            'sales_orders_all' => $allOrderRows,
            'so_all_count' => count($allOrderRows),
            'so_all_total' => round($soAllTotal, 2),
            'sales_orders_outstanding' => $outstandingRows,
            'so_outstanding_count' => count($outstandingRows),
            'so_outstanding_total' => round($soOutstandingTotal, 2),
            'so_overdue_count' => $soOverdueCount,
            'invoices_all' => $invoiceAllRows,
            'invoice_all_count' => count($invoiceAllRows),
            'invoice_all_total' => round($invoiceAllTotal, 2),
            'invoices_outstanding' => $invoiceOutstandingRows,
            'invoice_outstanding_count' => count($invoiceOutstandingRows),
            'invoice_outstanding_total' => round($invoiceOutstandingTotal, 2),
            'invoices_complete' => $invoiceCompleteRows,
            'invoice_complete_count' => count($invoiceCompleteRows),
            'invoice_complete_total' => round($invoiceCompleteTotal, 2),
            'gp_total' => round($gpTotal, 2),
            'entertainments' => $entertainments,
            'entertainments_pending' => $entertainmentsPending,
            'entertainment_pending_total' => round((float) $entertainmentsPending->sum('amount'), 2),
            'entertainment_pending_count' => $entertainmentsPending->count(),
            'entertainment_total' => $entertainmentTotal,
            'expenses' => $expenses,
            'expense_total' => $expenseTotal,
            'expense_linked_total' => $expenseLinkedTotal,
            'expense_unlinked_total' => $expenseUnlinkedTotal,
            'deduction_total' => $deductionTotal,
            'net_gp' => $netGp,
            'commission_rate' => $commission['rate'],
            'commission_rate_percent' => $commission['rate_percent'],
            'commission_raw' => round($commission['raw'], 2),
            'commission' => $commission['amount'],
            'commission_round_to' => $this->roundUnit(),
            'commission_tiers' => array_reverse($this->rateTiers()),
        ];
    }

    /**
     * Cari SO milik sales berdasarkan kode SO (opsional untuk biaya lain).
     */
    public function findSalesOrderForSales(User $sales, string $soCode): ?OpportunitySalesOrder
    {
        $code = trim($soCode);
        if ($code === '') {
            return null;
        }

        $candidates = OpportunitySalesOrder::query()
            ->with(['opportunity.assignedUser', 'assignedUser'])
            ->forCommissionOwner($sales->id)
            ->where(function ($q) use ($code) {
                $q->where('number', $code)
                    ->orWhere('agc_payload->sales_order_code', $code)
                    ->orWhere('agc_payload->code', $code);
            })
            ->orderByDesc('id')
            ->get();

        foreach ($candidates as $so) {
            if (strcasecmp($so->displayNumber(), $code) === 0) {
                return $so;
            }
        }

        return $candidates->first();
    }

    /**
     * Setiap SO complete dihitung sendiri (bukan digabung per opportunity).
     * Periode komisi mengikuti tanggal dibuat SO — bukan tanggal complete.
     * Contoh: dibuat Q3, selesai Q4 → tetap masuk perhitungan Q3.
     *
     * @return Collection<int, OpportunitySalesOrder>
     */
    protected function completedSalesOrders(string $salesUserId, Carbon $start, Carbon $end): Collection
    {
        return OpportunitySalesOrder::query()
            ->with([
                'assignedUser',
                'opportunity.account',
                'opportunity.assignedUser',
                'opportunity.salesOrders',
                'opportunity.purchaseOrders.items',
            ])
            ->forCommissionOwner($salesUserId)
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('id')
            ->get()
            ->filter(function (OpportunitySalesOrder $so) {
                if ($so->isCancelled()) {
                    return false;
                }

                // Pakai soStatus() agar status nested ikut terbaca.
                return in_array($so->soStatus(), ['completed', 'complete'], true);
            })
            ->values();
    }

    /**
     * Semua SO non-cancelled milik sales yang dibuat di rentang periode.
     *
     * @return Collection<int, OpportunitySalesOrder>
     */
    protected function salesOrdersInPeriod(string $salesUserId, Carbon $start, Carbon $end): Collection
    {
        return OpportunitySalesOrder::query()
            ->with([
                'assignedUser',
                'opportunity.account',
                'opportunity.assignedUser',
                'opportunity.salesOrders',
                'opportunity.purchaseOrders.items',
            ])
            ->forCommissionOwner($salesUserId)
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('id')
            ->get()
            ->filter(fn (OpportunitySalesOrder $so) => ! $so->isCancelled())
            ->values();
    }

    /**
     * SO yang punya tanggal invoice di rentang periode (periodisasi invoice).
     *
     * @return Collection<int, OpportunitySalesOrder>
     */
    protected function invoicedSalesOrdersInPeriod(string $salesUserId, Carbon $start, Carbon $end): Collection
    {
        return OpportunitySalesOrder::query()
            ->with([
                'assignedUser',
                'opportunity.account',
                'opportunity.assignedUser',
                'opportunity.salesOrders',
                'opportunity.purchaseOrders.items',
            ])
            ->forCommissionOwner($salesUserId)
            ->orderByDesc('id')
            ->get()
            ->filter(function (OpportunitySalesOrder $so) use ($start, $end) {
                if ($so->isCancelled()) {
                    return false;
                }

                $invoiceAt = $this->invoiceAt($so);
                if (! $invoiceAt) {
                    return false;
                }

                $from = $start->copy()->startOfDay();
                $to = $end->copy()->endOfDay();

                return $invoiceAt->gte($from) && $invoiceAt->lte($to);
            })
            ->sortByDesc(fn (OpportunitySalesOrder $so) => $this->invoiceAt($so)?->timestamp ?? 0)
            ->values();
    }

    /**
     * Baris margin/GP untuk satu SO (dipakai SO Complete detail & Gross Profit).
     *
     * @param  array<string, float>  $linkedExpenseBySo
     * @param  array<string, array{amount: float, margin: float}>  $financialCache
     * @return array<string, mixed>|null
     */
    protected function buildMarginRow(
        OpportunitySalesOrder $salesOrder,
        array $linkedExpenseBySo,
        array &$financialCache
    ): ?array {
        $opportunity = $salesOrder->opportunity;
        if (! $opportunity) {
            return null;
        }

        $cacheKey = $opportunity->id.'|'.$salesOrder->id;
        if (! isset($financialCache[$cacheKey])) {
            $financialCache[$cacheKey] = $this->salesOrderFinancials($opportunity, $salesOrder);
        }

        $amount = $financialCache[$cacheKey]['amount'];
        $marginRaw = $financialCache[$cacheKey]['margin'];
        $linkedExpense = round((float) ($linkedExpenseBySo[(string) $salesOrder->id] ?? 0), 2);
        $margin = round($marginRaw - $linkedExpense, 2);

        return [
            'id' => $salesOrder->id,
            'number' => $salesOrder->displayNumber(),
            'opportunity_id' => $opportunity->id,
            'reassigned' => $salesOrder->isCommissionReassigned(),
            'opportunity_owner' => $opportunity->assignedUser?->display_name,
            'customer' => $opportunity->account?->name,
            'created_at' => $salesOrder->created_at,
            'invoice_at' => $this->invoiceAt($salesOrder),
            'paid_at' => $this->paidAt($salesOrder),
            'completed_at' => $this->completedAt($salesOrder),
            'amount' => $amount,
            'margin_raw' => $marginRaw,
            'linked_expense' => $linkedExpense,
            'margin' => $margin,
        ];
    }

    /**
     * @param  array<string, array{amount: float, margin: float}>  $financialCache
     * @return array<string, mixed>|null
     */
    protected function buildSalesOrderListRow(OpportunitySalesOrder $salesOrder, array &$financialCache): ?array
    {
        $opportunity = $salesOrder->opportunity;
        if (! $opportunity) {
            return null;
        }

        $cacheKey = $opportunity->id.'|'.$salesOrder->id;
        if (! isset($financialCache[$cacheKey])) {
            $financialCache[$cacheKey] = $this->salesOrderFinancials($opportunity, $salesOrder);
        }

        $isUnpaid = $this->isUnpaid($salesOrder);
        $topDays = $this->paymentTopDays($salesOrder);
        $invoiceAt = $this->invoiceAt($salesOrder) ?? $salesOrder->created_at?->copy();
        $dueAt = $invoiceAt?->copy()->startOfDay()->addDays($topDays);
        $isOverdue = $isUnpaid && $dueAt !== null && Carbon::now()->startOfDay()->gt($dueAt);
        $overdueDays = ($isOverdue && $dueAt)
            ? (int) $dueAt->diffInDays(Carbon::now()->startOfDay())
            : 0;

        return [
            'id' => $salesOrder->id,
            'number' => $salesOrder->displayNumber(),
            'opportunity_id' => $opportunity->id,
            'opportunity_name' => $opportunity->name,
            'customer' => $opportunity->account?->name,
            'reassigned' => $salesOrder->isCommissionReassigned(),
            'opportunity_owner' => $opportunity->assignedUser?->display_name,
            'so_status' => $salesOrder->soStatus(),
            'payment_status' => strtolower(trim((string) ($salesOrder->statusSnapshot()['payment_status'] ?? ''))) ?: 'unpaid',
            'payment_label' => $salesOrder->paymentLabel(),
            'top_days' => $topDays,
            'created_at' => $salesOrder->created_at,
            'invoice_at' => $invoiceAt,
            'due_at' => $dueAt,
            'paid_at' => $this->paidAt($salesOrder),
            'completed_at' => $this->completedAt($salesOrder),
            'amount' => $financialCache[$cacheKey]['amount'],
            'is_unpaid' => $isUnpaid,
            'is_overdue' => $isOverdue,
            'overdue_days' => $overdueDays,
            'is_complete' => in_array($salesOrder->soStatus(), ['completed', 'complete'], true),
        ];
    }

    protected function isUnpaid(OpportunitySalesOrder $salesOrder): bool
    {
        $status = strtolower(trim((string) ($salesOrder->statusSnapshot()['payment_status'] ?? '')));

        return ! in_array($status, ['paid', 'settlement'], true);
    }

    protected function paymentTopDays(OpportunitySalesOrder $salesOrder): int
    {
        $raw = trim((string) $salesOrder->payment);
        if ($raw === '') {
            $raw = trim((string) (
                $salesOrder->payloadValue('payment_method')
                ?? $salesOrder->payloadValue('payment')
                ?? ''
            ));
        }
        if ($raw === '') {
            return CustomerTop::days($salesOrder->opportunity?->top());
        }
        if (preg_match('/^top(\d+)$/i', $raw, $m)) {
            return (int) $m[1];
        }

        return CustomerTop::days(CustomerTop::canonicalize($raw));
    }

    protected function invoiceAt(OpportunitySalesOrder $salesOrder): ?Carbon
    {
        $raw = $salesOrder->payloadValue('invoice_dt')
            ?? $salesOrder->payloadValue('invoiced_dt')
            ?? $salesOrder->payloadValue('invoice_date');
        if (! filled($raw)) {
            return null;
        }

        try {
            return Carbon::parse((string) $raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Entertainment complete di periode: ikut tanggal entertainment (bukan tanggal complete).
     *
     * @return Collection<int, OpportunityEntertainment>
     */
    protected function completedEntertainmentsForPeriod(string $salesUserId, Carbon $start, Carbon $end): Collection
    {
        return $this->entertainmentsForPeriod(
            $salesUserId,
            $start,
            $end,
            OpportunityEntertainment::STATUS_COMPLETE
        );
    }

    /**
     * Entertainment pending di periode (menunggu approval Finance).
     *
     * @return Collection<int, OpportunityEntertainment>
     */
    protected function pendingEntertainmentsForPeriod(string $salesUserId, Carbon $start, Carbon $end): Collection
    {
        return $this->entertainmentsForPeriod(
            $salesUserId,
            $start,
            $end,
            OpportunityEntertainment::STATUS_PENDING
        );
    }

    /**
     * @return Collection<int, OpportunityEntertainment>
     */
    protected function entertainmentsForPeriod(
        string $salesUserId,
        Carbon $start,
        Carbon $end,
        string $status
    ): Collection {
        return OpportunityEntertainment::query()
            ->with(['opportunity.account', 'category:id,name'])
            ->where('status', $status)
            ->whereHas('opportunity', function ($q) use ($salesUserId) {
                $q->where('assigned_user_id', $salesUserId)
                    ->where('deleted', 0);
            })
            ->get()
            ->filter(function (OpportunityEntertainment $item) use ($start, $end) {
                $date = $this->entertainmentPeriodDate($item);
                if (! $date) {
                    return false;
                }

                return $date->between($start, $end);
            })
            ->sortByDesc(fn (OpportunityEntertainment $item) => $this->entertainmentPeriodDate($item)?->timestamp ?? 0)
            ->values();
    }

    /**
     * Tanggal yang menentukan Q komisi entertainment.
     */
    public function entertainmentPeriodDate(OpportunityEntertainment $item): ?Carbon
    {
        if ($item->entertainment_date) {
            return $item->entertainment_date->copy()->startOfDay();
        }

        return $item->completed_at?->copy()
            ?? $item->created_at?->copy();
    }

    protected function completedAt(OpportunitySalesOrder $salesOrder): ?Carbon
    {
        $raw = $salesOrder->payloadValue('completed_dt')
            ?? $salesOrder->payloadValue('completed_date');
        if (filled($raw)) {
            try {
                return Carbon::parse((string) $raw);
            } catch (\Throwable) {
                // lanjut fallback
            }
        }

        // Status sudah complete tapi tanggal complete kosong → jangan drop SO.
        // Pakai tanggal bayar / invoice / update / dibuat agar tetap masuk periode.
        if (! in_array($salesOrder->soStatus(), ['completed', 'complete'], true)) {
            return null;
        }

        return $this->paidAt($salesOrder)
            ?? $this->invoiceAt($salesOrder)
            ?? $salesOrder->updated_at?->copy()
            ?? $salesOrder->created_at?->copy();
    }

    protected function paidAt(OpportunitySalesOrder $salesOrder): ?Carbon
    {
        $raw = $salesOrder->payloadValue('paid_date')
            ?? $salesOrder->payloadValue('settlement_dt')
            ?? $salesOrder->payloadValue('paid_dt');
        if (! filled($raw)) {
            return null;
        }

        try {
            return Carbon::parse((string) $raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Nilai jual SO + margin/keuntungan setelah modal, diskon tambahan, PPh, dll.
     *
     * @return array{amount: float, margin: float}
     */
    protected function salesOrderFinancials(Opportunity $opportunity, OpportunitySalesOrder $salesOrder): array
    {
        try {
            $perSo = $this->poReports->build($opportunity, $salesOrder);
            $summary = $perSo['summary'] ?? [];
            $amount = $this->nilaiJualFromSummary($summary);
            if ($amount <= 0) {
                $amount = $this->salesOrderAmountFallback($opportunity, $salesOrder);
            }

            $hasLinkedPo = count($perSo['po_rows'] ?? []) > 0
                || (float) ($summary['modal_excl'] ?? 0) > 0
                || (float) ($summary['modal_incl'] ?? 0) > 0;

            if ($hasLinkedPo) {
                return [
                    'amount' => $amount,
                    'margin' => $this->profitFromSummary($summary),
                ];
            }

            // PO opportunity belum ter-link ke SO: ambil profit laporan keseluruhan,
            // lalu alokasikan proporsional ke SO ini (1 SO = 100%).
            $overall = $this->poReports->build($opportunity, null);
            $overallSummary = $overall['summary'] ?? [];
            $overallProfit = $this->profitFromSummary($overallSummary);
            $salesOrders = $opportunity->relationLoaded('salesOrders')
                ? $opportunity->salesOrders
                : $opportunity->salesOrders()->get();

            if ($salesOrders->count() <= 1) {
                return [
                    'amount' => $amount,
                    'margin' => $overallProfit,
                ];
            }

            $weights = [];
            $totalWeight = 0.0;
            foreach ($salesOrders as $so) {
                $soReport = ((string) $so->id === (string) $salesOrder->id)
                    ? $perSo
                    : $this->poReports->build($opportunity, $so);
                $weight = $this->nilaiJualFromSummary($soReport['summary'] ?? []);
                if ($weight <= 0) {
                    $weight = $this->salesOrderAmountFallback($opportunity, $so);
                }
                $weights[(string) $so->id] = max(0.0, $weight);
                $totalWeight += $weights[(string) $so->id];
            }

            $share = $totalWeight > 0
                ? round($overallProfit * ($weights[(string) $salesOrder->id] / $totalWeight), 2)
                : round($overallProfit / max(1, $salesOrders->count()), 2);

            return [
                'amount' => $amount,
                'margin' => $share,
            ];
        } catch (\Throwable) {
            // fallback di bawah
        }

        $amount = $this->salesOrderAmountFallback($opportunity, $salesOrder);
        $oppMargin = (float) ($opportunity->crm_won_margin ?? 0);
        $oppAmount = (float) ($opportunity->amount ?? 0);
        $margin = 0.0;
        if ($oppMargin > 0 && $oppAmount > 0 && $amount > 0) {
            $margin = round($oppMargin * ($amount / $oppAmount), 2);
        }

        return [
            'amount' => $amount,
            'margin' => $margin,
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    protected function profitFromSummary(array $summary): float
    {
        if (array_key_exists('net_profit', $summary) && $summary['net_profit'] !== null) {
            return round((float) $summary['net_profit'], 2);
        }

        return round((float) ($summary['total_profit'] ?? 0), 2);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    protected function nilaiJualFromSummary(array $summary): float
    {
        // Tampilkan nilai SO sama seperti Total di dokumen SO (include PPN).
        $incl = (float) ($summary['nilai_jual_incl'] ?? 0);
        if ($incl > 0) {
            return round($incl, 2);
        }

        $excl = (float) ($summary['nilai_jual_excl'] ?? 0);

        return $excl > 0 ? round($excl, 2) : 0.0;
    }

    protected function salesOrderAmountFallback(Opportunity $opportunity, OpportunitySalesOrder $salesOrder): float
    {
        $items = is_array($salesOrder->items) ? $salesOrder->items : [];
        $sum = 0.0;
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $qty = (float) ($item['qty'] ?? $item['quantity'] ?? 0);
            $price = (float) ($item['price'] ?? $item['sell_exclude'] ?? $item['price_display'] ?? 0);
            if ($qty > 0 && $price > 0) {
                $sum += $qty * $price;
            }
        }
        if ($sum > 0) {
            return round($sum, 2);
        }

        $payload = is_array($salesOrder->agc_payload) ? $salesOrder->agc_payload : [];
        foreach (['total', 'grand_total', 'amount'] as $key) {
            if (isset($payload[$key]) && is_numeric($payload[$key])) {
                return round((float) $payload[$key], 2);
            }
        }

        return round((float) ($opportunity->amount ?? 0), 2);
    }
}
