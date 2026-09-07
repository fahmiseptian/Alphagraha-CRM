<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Espo\Account;
use App\Models\Espo\EspoUser;
use App\Models\Espo\Opportunity;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    use ScopesToUser;

    public function index(Request $request): View
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        if (! $user?->canViewPurchaseOrders()) {
            abort(403);
        }

        $search = trim((string) $request->get('q', ''));
        $accountId = trim((string) $request->get('account_id', ''));
        if ($accountId !== '' && ! $this->scopeAssigned(Account::query())->where('id', $accountId)->exists()) {
            $accountId = '';
        }
        $companyFilter = trim((string) $request->get('company', ''));
        if ($companyFilter !== '' && ! in_array($companyFilter, Opportunity::COMPANIES, true)) {
            $companyFilter = '';
        }
        $vendorId = (int) $request->get('vendor_id', 0);
        if ($vendorId > 0 && ! Vendor::query()->whereKey($vendorId)->exists()) {
            $vendorId = 0;
        }
        $paymentTerm = trim((string) $request->get('payment_term', ''));
        if (! in_array($paymentTerm, [PurchaseOrder::PAYMENT_TOP, PurchaseOrder::PAYMENT_CASH], true)) {
            $paymentTerm = '';
        }
        $selectedUserId = $this->resolveAssignedUserFilter($request);
        $period = $this->resolvePeriodFilter($request);
        $periodRange = $this->periodDateRange($period);
        $periodLabel = $this->periodLabel($period);

        $query = PurchaseOrder::query()
            ->with(['opportunity.account', 'opportunity.assignedUser', 'vendor', 'creator', 'items.vendorQuotes'])
            ->withCount('items')
            ->whereHas('opportunity', function ($q) use ($user, $accountId, $companyFilter, $selectedUserId) {
                if ($user->canViewAllOpportunities()) {
                    if ($user->isPurchasing() || $user->isFinance()) {
                        $q->where('stage', Opportunity::WON_STAGE);
                    }
                } else {
                    $q->where('assigned_user_id', $user->id);
                }

                if ($accountId !== '') {
                    $q->where('account_id', $accountId);
                }

                if ($companyFilter !== '') {
                    $q->where('company', $companyFilter);
                }

                if ($selectedUserId !== null) {
                    $q->where('assigned_user_id', $selectedUserId);
                }
            })
            ->orderByDesc('created_at');

        if ($vendorId > 0) {
            $query->where('vendor_id', $vendorId);
        }

        if ($paymentTerm !== '') {
            $query->where('payment_term', $paymentTerm);
        }

        if ($periodRange !== null) {
            [$start, $end] = $periodRange;
            $query->whereBetween(
                (new PurchaseOrder)->getTable().'.created_at',
                [$start, $end]
            );
        }

        if ($search !== '') {
            $this->applyPurchaseOrderSearch($query, $search);
        }

        $purchaseOrders = $query->paginate(20)->withQueryString();

        $filterAccounts = $this->scopeAssigned(Account::query())
            ->orderBy('name')
            ->get(['id', 'name']);

        $filterVendors = Vendor::query()
            ->ordered()
            ->get(['id', 'name']);

        $canFilterSales = ! $user->isSales();
        $salesUsers = $canFilterSales
            ? EspoUser::query()->activeSales()->orderBy('name')->get(['id', 'name', 'first_name', 'last_name', 'user_name'])
            : collect();

        return view('purchase-orders.index', [
            'purchaseOrders' => $purchaseOrders,
            'search' => $search,
            'accountId' => $accountId,
            'companyFilter' => $companyFilter,
            'vendorId' => $vendorId,
            'paymentTerm' => $paymentTerm,
            'filterAccounts' => $filterAccounts,
            'filterVendors' => $filterVendors,
            'selectedUserId' => $selectedUserId,
            'period' => $period,
            'periodLabel' => $periodLabel,
            'salesUsers' => $salesUsers,
            'canFilterSales' => $canFilterSales,
            'companies' => Opportunity::COMPANIES,
        ]);
    }

    /**
     * @return string|null null = semua sales (non-sales role).
     */
    protected function resolveAssignedUserFilter(Request $request): ?string
    {
        if (auth()->user()?->isSales()) {
            return null;
        }

        $raw = $request->input('assigned_user_id');

        if (is_array($raw)) {
            $raw = $raw[0] ?? '';
        }

        $id = trim((string) $raw);

        if ($id === '') {
            return null;
        }

        $exists = EspoUser::query()->activeSales()->where('id', $id)->exists();

        return $exists ? $id : null;
    }

    protected function resolvePeriodFilter(Request $request): string
    {
        $period = (string) $request->get('period', 'year');

        if (! in_array($period, ['year', 'month', '3months', '6months', 'alltime'], true)) {
            return 'year';
        }

        return $period;
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

    protected function applyPurchaseOrderSearch($query, string $search): void
    {
        $table = (new PurchaseOrder)->getTable();
        $like = '%'.$search.'%';

        $query->where(function ($q) use ($table, $like) {
            $q->where($table.'.number', 'like', $like)
                ->orWhere($table.'.vendor_name', 'like', $like)
                ->orWhereHas('vendor', fn ($vq) => $vq->where('name', 'like', $like))
                ->orWhereHas('items', fn ($iq) => $iq->where('product_name', 'like', $like)
                    ->orWhere('opportunity_product_name', 'like', $like))
                ->orWhereHas('opportunity', function ($oq) use ($like) {
                    $oq->where('opportunity.name', 'like', $like)
                        ->orWhere('opportunity.company', 'like', $like)
                        ->orWhereHas('account', fn ($aq) => $aq->where('account.name', 'like', $like))
                        ->orWhereHas('assignedUser', function ($uq) use ($like) {
                            $uq->where('name', 'like', $like)
                                ->orWhere('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhere('user_name', 'like', $like);
                        });
                });
        });
    }
}
