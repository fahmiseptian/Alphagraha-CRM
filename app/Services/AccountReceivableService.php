<?php

namespace App\Services;

use App\Models\OpportunitySalesOrder;
use App\Support\CustomerTop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Account Receivable: Sales Order yang belum dibayar customer.
 */
class AccountReceivableService
{
    public const PAID_STATUSES = ['paid', 'settlement'];

    /**
     * @param  Builder  $opportunityIds  Subquery id opportunity hasil filter.
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(Builder $opportunityIds): Collection
    {
        return $this->rowsFromQuery(
            OpportunitySalesOrder::query()->whereIn('opportunity_id', $opportunityIds)
        );
    }

    /**
     * @param  iterable<int|string>  $salesOrderIds
     * @return Collection<int, array<string, mixed>>
     */
    public function rowsBySalesOrderIds(iterable $salesOrderIds): Collection
    {
        return $this->rowsFromQuery(
            OpportunitySalesOrder::query()->whereIn('id', $salesOrderIds)
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function rowsFromQuery(Builder $query): Collection
    {
        $today = Carbon::now()->startOfDay();

        return $query
            ->with(['opportunity.account', 'opportunity.assignedUser', 'assignedUser'])
            ->where(function (Builder $q) {
                $q->whereNull('agc_payload->payment_status')
                    ->orWhereNotIn('agc_payload->payment_status', self::PAID_STATUSES);
            })
            ->whereNotNull('agc_payload->invoice_no')
            ->where('agc_payload->invoice_no', '!=', '')
            ->get()
            ->reject(fn (OpportunitySalesOrder $so) => $so->isCancelled()
                || $this->isPaid($so)
                || trim((string) $so->payloadValue('invoice_no', '')) === '')
            ->map(fn (OpportunitySalesOrder $so) => $this->row($so, $today))
            ->sortBy([
                fn ($a, $b) => ($a['due_at']?->timestamp ?? PHP_INT_MAX) <=> ($b['due_at']?->timestamp ?? PHP_INT_MAX),
                fn ($a, $b) => strcmp((string) $a['customer'], (string) $b['customer']),
            ])
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{count: int, amount_include: float, amount_exclude: float, overdue_count: int, overdue_amount: float}
     */
    public function totals(Collection $rows): array
    {
        $overdue = $rows->where('is_overdue', true);

        return [
            'count' => $rows->count(),
            'amount_include' => (float) $rows->sum('amount_include'),
            'amount_exclude' => (float) $rows->sum('amount_exclude'),
            'overdue_count' => $overdue->count(),
            'overdue_amount' => (float) $overdue->sum('amount_include'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(OpportunitySalesOrder $so, Carbon $today): array
    {
        $opportunity = $so->opportunity;
        $invoiceAt = $this->date($so->payloadValue('invoice_dt') ?? $so->payloadValue('invoiced_dt') ?? $so->payloadValue('invoice_date'));
        $soDate = $this->date($so->payloadValue('so_date')) ?? $so->created_at?->copy();
        $termDays = $this->termDays($so);
        $dueBase = $invoiceAt ?? $soDate;
        $dueAt = $dueBase?->copy()->startOfDay()->addDays($termDays);

        $include = (float) ($so->payloadValue('grand_total') ?? 0);
        $ppn = (float) ($so->payloadValue('ppn_amount') ?? 0);
        $exclude = $include > 0 ? $include - $ppn : (float) ($so->payloadValue('subtotal_display') ?? 0);

        $isOverdue = $dueAt !== null && $today->gt($dueAt);

        return [
            'id' => $so->id,
            'opportunity_id' => $so->opportunity_id,
            'so_number' => $so->displayNumber(),
            'invoice_no' => trim((string) ($so->payloadValue('invoice_no') ?? '')),
            'invoice_at' => $invoiceAt,
            'so_date' => $soDate,
            'po_number' => trim((string) ($so->po_number ?: $so->payloadValue('po_number', ''))),
            'customer' => $opportunity?->account?->name ?: (string) ($so->payloadValue('customer') ?: $opportunity?->name),
            'amount_include' => round($include, 2),
            'amount_exclude' => round($exclude, 2),
            'term_days' => $termDays,
            'salesman' => $so->commissionOwnerName(),
            'due_at' => $dueAt,
            'due_estimated' => $invoiceAt === null,
            'is_overdue' => $isOverdue,
            'overdue_days' => $isOverdue ? (int) $dueAt->diffInDays($today) : 0,
            'so_status' => $so->soStatus(),
        ];
    }

    protected function isPaid(OpportunitySalesOrder $so): bool
    {
        $status = strtolower(trim((string) ($so->statusSnapshot()['payment_status'] ?? '')));

        return in_array($status, self::PAID_STATUSES, true);
    }

    protected function termDays(OpportunitySalesOrder $so): int
    {
        $raw = trim((string) ($so->payment ?: $so->payloadValue('payment_method') ?: $so->payloadValue('payment') ?: ''));
        if ($raw === '') {
            return CustomerTop::days($so->opportunity?->top());
        }
        if (preg_match('/^top(\d+)$/i', $raw, $m)) {
            return (int) $m[1];
        }

        return CustomerTop::days(CustomerTop::canonicalize($raw));
    }

    protected function date(mixed $raw): ?Carbon
    {
        if (! filled($raw)) {
            return null;
        }

        try {
            return Carbon::parse((string) $raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
