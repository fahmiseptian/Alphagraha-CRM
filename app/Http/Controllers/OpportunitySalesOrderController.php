<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Espo\Account;
use App\Models\Espo\EspoUser;
use App\Models\Espo\Opportunity;
use App\Models\OpportunityLog;
use App\Models\OpportunitySalesOrder;
use App\Models\SalesOrderLog;
use App\Models\User;
use App\Services\AccountReceivableService;
use App\Services\CustomerAddressService;
use App\Services\OpportunityLogService;
use App\Services\SalesOrderService;
use App\Support\AccountReceivableExcel;
use App\Support\CustomerTop;
use App\Support\OpportunityProductPricing;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;

class OpportunitySalesOrderController extends Controller
{
    use ScopesToUser;

    public function __construct(
        protected SalesOrderService $salesOrders,
        protected CustomerAddressService $customerAddresses
    ) {}

    public function destroy(Opportunity $opportunity, OpportunitySalesOrder $salesOrder): RedirectResponse
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if (! $user || ! $user->canAccessAdministration()) {
            abort(403);
        }

        abort_unless((string) $salesOrder->opportunity_id === (string) $opportunity->id, 404);

        $number = $salesOrder->displayNumber();
        $this->salesOrders->archive($salesOrder, $opportunity, $user);
        app(OpportunityLogService::class)->record(
            $opportunity,
            OpportunityLog::ACTION_SALES_ORDER_ARCHIVED,
            null,
            [
                'force' => true,
                'summary' => 'Sales Order diarsipkan: '.$number,
                'actor' => $user,
            ]
        );

        return redirect()
            ->route('sales-orders.index')
            ->with('success', 'Sales Order diarsipkan. Data tetap ada di Log SO (Superadmin).');
    }

    public function index(Request $request): View
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        if (! $user?->canViewSalesOrders()) {
            abort(403);
        }

        $canSeeInvoice = $user->canEditSalesOrderInvoice();
        $context = $this->salesOrderIndexContext($request);
        $query = $context['query']
            ->with(['opportunity.account', 'opportunity.assignedUser', 'creator', 'assignedUser'])
            ->withCount('purchaseOrders')
            ->orderByDesc('created_at');

        $salesOrders = $query->paginate(20)->withQueryString();

        $filterAccounts = $this->scopeAssigned(Account::query())
            ->orderBy('name')
            ->get(['id', 'name']);

        $canFilterSales = ! $user->isSales();
        $salesUsers = $canFilterSales
            ? EspoUser::query()->activeSales()->orderBy('name')->get(['id', 'name', 'first_name', 'last_name', 'user_name'])
            : collect();

        return view('sales-orders.index', [
            'salesOrders' => $salesOrders,
            'search' => $context['search'],
            'invoiceSearch' => $context['invoiceSearch'],
            'canSeeInvoice' => $canSeeInvoice,
            'canEditDelivery' => $user->canEditSalesOrderDelivery(),
            'canViewAr' => $user->canViewAccountReceivable(),
            'accountId' => $context['accountId'],
            'companyFilter' => $context['companyFilter'],
            'filterAccounts' => $filterAccounts,
            'selectedUserId' => $context['selectedUserId'],
            'period' => $context['period'],
            'periodLabel' => $context['periodLabel'],
            'queue' => $context['queue'],
            'salesUsers' => $salesUsers,
            'canFilterSales' => $canFilterSales,
            'companies' => Opportunity::COMPANIES,
        ]);
    }

    public function accountReceivable(Request $request, AccountReceivableService $receivables)
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if (! $user?->canViewAccountReceivable()) {
            abort(403, 'Anda tidak memiliki akses ke Account Receivable.');
        }

        $context = $this->salesOrderIndexContext($request);
        $ids = (clone $context['query'])->reorder()->pluck('id');
        $rows = $receivables->rowsBySalesOrderIds($ids);

        $filterQuery = array_filter([
            'period' => $context['period'] !== 'year' ? $context['period'] : null,
            'assigned_user_id' => $context['selectedUserId'],
            'q' => $context['search'] !== '' ? $context['search'] : null,
            'invoice' => $context['invoiceSearch'] !== '' ? $context['invoiceSearch'] : null,
            'company' => $context['companyFilter'] !== '' ? $context['companyFilter'] : null,
            'account_id' => $context['accountId'] !== '' ? $context['accountId'] : null,
            'queue' => $context['queue'] !== '' ? $context['queue'] : null,
        ], fn ($v) => $v !== null && $v !== '');

        if ($request->get('format') === 'xlsx') {
            return AccountReceivableExcel::export($rows, 'account-receivable-'.now()->format('Y-m-d').'.xlsx');
        }

        $range = $this->periodDateRange($context['period']);

        return view('opportunities.account-receivable', [
            'rows' => $rows,
            'totals' => $receivables->totals($rows),
            'periodLabel' => $context['periodLabel'],
            'rangeStart' => $range[0] ?? $rows->min('invoice_at'),
            'rangeEnd' => $range ? min($range[1], now()) : now(),
            'filterQuery' => $filterQuery,
            'filterSummary' => $this->salesOrderArFilterSummary($context),
            'backUrl' => route('sales-orders.index', $filterQuery),
            'exportUrl' => route('sales-orders.account-receivable', $filterQuery + ['format' => 'xlsx']),
        ]);
    }

    /**
     * @return array{
     *     query: \Illuminate\Database\Eloquent\Builder,
     *     search: string,
     *     invoiceSearch: string,
     *     accountId: string,
     *     companyFilter: string,
     *     selectedUserId: ?string,
     *     period: string,
     *     periodLabel: string,
     *     queue: string
     * }
     */
    protected function salesOrderIndexContext(Request $request): array
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $search = trim((string) $request->get('q', ''));
        $invoiceSearch = trim((string) $request->get('invoice', ''));
        $canSeeInvoice = $user->canEditSalesOrderInvoice();
        $accountId = trim((string) $request->get('account_id', ''));
        if ($accountId !== '' && ! $this->scopeAssigned(Account::query())->where('id', $accountId)->exists()) {
            $accountId = '';
        }
        $companyFilter = trim((string) $request->get('company', ''));
        if ($companyFilter !== '' && ! in_array($companyFilter, Opportunity::COMPANIES, true)) {
            $companyFilter = '';
        }
        $selectedUserId = $this->resolveAssignedUserFilter($request);
        $period = $this->resolvePeriodFilter($request);
        $periodRange = $this->periodDateRange($period);
        $queue = $this->resolveSalesOrderQueueFilter($request);

        $query = OpportunitySalesOrder::query()
            ->whereHas('opportunity', function ($q) use ($user, $accountId, $companyFilter) {
                if ($user->canViewAllOpportunities()
                    || $user->isProduct()
                    || $user->isEkspedisi()) {
                    if ($user->isPurchasing()
                        || $user->isFinance()
                        || $user->isInvoice()
                        || $user->isProduct()
                        || $user->isEkspedisi()) {
                        $q->where('stage', Opportunity::WON_STAGE);
                    }
                } elseif (! $user->isSales()) {
                    $q->whereRaw('1 = 0');
                }

                if ($accountId !== '') {
                    $q->where('account_id', $accountId);
                }

                if ($companyFilter !== '') {
                    $q->where('company', $companyFilter);
                }
            });

        if ($user->isSales()) {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_user_id', $user->id)
                    ->orWhereHas('opportunity', fn ($oq) => $oq->where('assigned_user_id', $user->id));
            });
        }

        if ($selectedUserId !== null) {
            $query->forCommissionOwner($selectedUserId);
        }

        if ($periodRange !== null) {
            [$start, $end] = $periodRange;
            $query->whereBetween(
                (new OpportunitySalesOrder)->getTable().'.created_at',
                [$start, $end]
            );
        }

        $this->applySalesOrderQueueFilter($query, $queue);

        if ($search !== '') {
            $this->applySalesOrderSearch($query, $search);
        }

        if ($canSeeInvoice && $invoiceSearch !== '') {
            $this->applySalesOrderInvoiceSearch($query, $invoiceSearch);
        }

        return [
            'query' => $query,
            'search' => $search,
            'invoiceSearch' => $invoiceSearch,
            'accountId' => $accountId,
            'companyFilter' => $companyFilter,
            'selectedUserId' => $selectedUserId,
            'period' => $period,
            'periodLabel' => $this->periodLabel($period),
            'queue' => $queue,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    protected function salesOrderArFilterSummary(array $context): array
    {
        $parts = [];
        if ($context['search'] !== '') {
            $parts[] = 'Cari: "'.$context['search'].'"';
        }
        if ($context['invoiceSearch'] !== '') {
            $parts[] = 'Invoice: "'.$context['invoiceSearch'].'"';
        }
        if ($context['accountId'] !== '') {
            $parts[] = 'Customer: '.(Account::query()->whereKey($context['accountId'])->value('name') ?: '—');
        }
        if ($context['companyFilter'] !== '') {
            $parts[] = 'Perusahaan: '.$context['companyFilter'];
        }
        if ($context['selectedUserId']) {
            $user = EspoUser::query()->find($context['selectedUserId']);
            $parts[] = 'Sales: '.($user?->display_name ?: '—');
        }

        return $parts;
    }

    protected function resolveSalesOrderQueueFilter(Request $request): string
    {
        $queue = (string) $request->get('queue', '');

        return in_array($queue, ['needs_invoice', 'unpaid', 'paid', 'delivery_pending'], true)
            ? $queue
            : '';
    }

    protected function applySalesOrderQueueFilter($query, string $queue): void
    {
        match ($queue) {
            'needs_invoice' => $query->where(function ($q) {
                $q->whereNull('agc_payload->invoice_no')
                    ->orWhere('agc_payload->invoice_no', '');
            }),
            'unpaid' => $query->where(function ($q) {
                $q->whereNull('agc_payload->payment_status')
                    ->orWhere('agc_payload->payment_status', '')
                    ->orWhere('agc_payload->payment_status', 'unpaid')
                    ->orWhere('agc_payload->payment_status', 'pending');
            }),
            'paid' => $query->where(function ($q) {
                $q->where('agc_payload->payment_status', 'paid')
                    ->orWhere('agc_payload->payment_status', 'settlement');
            }),
            'delivery_pending' => $query->where(function ($q) {
                $q->whereNull('agc_payload->delivery_status')
                    ->orWhere('agc_payload->delivery_status', '')
                    ->orWhere('agc_payload->delivery_status', 'pending');
            }),
            default => null,
        };
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

    protected function applySalesOrderSearch($query, string $search): void
    {
        $table = (new OpportunitySalesOrder)->getTable();
        $like = '%'.$search.'%';
        $psoAsSo = preg_replace('/^pso/i', 'SO', $search) ?? $search;
        $soAsPso = preg_replace('/^so/i', 'PSO', $search) ?? $search;

        $query->where(function ($q) use ($table, $like, $search, $psoAsSo, $soAsPso) {
            $q->where($table.'.number', 'like', $like)
                ->orWhere($table.'.nomor_ref', 'like', $like)
                ->orWhere($table.'.po_number', 'like', $like)
                ->orWhere($table.'.payment', 'like', $like)
                ->orWhere($table.'.agc_payload->invoice_no', 'like', $like)
                ->orWhere($table.'.agc_payload', 'like', $like)
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
                })
                ->orWhereHas('assignedUser', function ($uq) use ($like) {
                    $uq->where('name', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('user_name', 'like', $like);
                });

            if (strcasecmp($psoAsSo, $search) !== 0) {
                $q->orWhere($table.'.number', 'like', '%'.$psoAsSo.'%');
            }

            if (strcasecmp($soAsPso, $search) !== 0) {
                $q->orWhere($table.'.agc_payload', 'like', '%'.$soAsPso.'%');
            }
        });
    }

    protected function applySalesOrderInvoiceSearch($query, string $invoice): void
    {
        $like = '%'.$invoice.'%';
        $table = (new OpportunitySalesOrder)->getTable();

        $query->where(function ($q) use ($table, $like) {
            $q->where($table.'.agc_payload->invoice_no', 'like', $like);
        });
    }

    public function create(Opportunity $opportunity): View
    {
        $this->authorizeCreate($opportunity);

        $opportunity->load([
            'account.emailAddresses',
            'account.phoneNumbers',
            'account.contacts.emailAddresses',
            'account.contacts.phoneNumbers',
            'account.addresses',
            'contact.emailAddresses',
            'contact.phoneNumbers',
            'quotation',
        ]);
        $this->customerAddresses->ensurePrimaryFromAccount($opportunity->account);

        return view('opportunities.sales-orders.create', [
            'opportunity' => $opportunity,
            'form' => $this->formPayload($opportunity),
        ]);
    }

    public function store(Request $request, Opportunity $opportunity): RedirectResponse|JsonResponse
    {
        $this->authorizeCreate($opportunity);

        $opportunity->load([
            'account.emailAddresses',
            'account.phoneNumbers',
            'account.contacts.emailAddresses',
            'account.contacts.phoneNumbers',
            'contact.emailAddresses',
            'contact.phoneNumbers',
        ]);

        $opportunity->loadMissing('account.addresses');
        $this->customerAddresses->ensurePrimaryFromAccount($opportunity->account);
        if ($reason = $opportunity->brandAndCategoryBlockReason()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $reason], 422);
            }

            return redirect()
                ->route('opportunities.edit', $opportunity)
                ->with('error', $reason);
        }
        $allowedTop = array_keys(CustomerTop::optionsAllowedFor($opportunity->salesOrderMaxTop()));
        $accountId = (string) ($opportunity->account_id ?? '');
        $addressRule = Rule::exists('crm_customer_addresses', 'id')->where('account_id', $accountId);

        $request->merge([
            'payment' => CustomerTop::canonicalize($request->input('payment')),
        ]);

        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'payment' => ['required', 'string', Rule::in($allowedTop)],
            'po_number' => ['required', 'string', 'max:100'],
            'required_delivery' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'billing_address_id' => ['required', 'integer', $addressRule],
            'shipping_address_id' => ['nullable', 'integer', $addressRule],
            'same_as_billing' => ['nullable', 'boolean'],
            'shipping_method' => ['nullable', 'string', 'max:150'],
            'po_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.index' => ['required', 'integer', 'min:0'],
            'items.*.sku' => ['nullable', 'string', 'max:100'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:10000'],
        ], [
            'payment.in' => 'TOP melebihi batas yang diizinkan untuk customer ini. TOP di atas TOP customer harus di-approve Superadmin/Finance di Opportunity.',
            'po_number.required' => 'No. PO customer wajib diisi.',
            'billing_address_id.required' => 'Pilih alamat billing. Tambah alamat di data customer jika belum ada.',
            'billing_address_id.exists' => 'Alamat billing tidak valid untuk customer ini.',
            'shipping_address_id.exists' => 'Alamat shipping tidak valid untuk customer ini.',
            'po_file.required' => 'File PO wajib diunggah.',
            'po_file.mimes' => 'File PO harus PDF, JPG, JPEG, atau PNG.',
            'po_file.max' => 'Ukuran file PO maksimal 5MB.',
        ]);

        $itemsSnapshot = $this->mapPostedSalesOrderItems($opportunity, $data['items']);

        try {
            $record = DB::transaction(function () use ($request, $opportunity, $data, $itemsSnapshot) {
                $generated = $this->salesOrders->generateDocumentNumbers(auth()->user(), null, $opportunity);
                $number = $generated['number'];
                $nomorRef = $generated['nomor_ref'];
                $data['number'] = $number;
                $data['pso_number'] = $generated['pso_number'];
                $data['nomor_ref'] = $nomorRef;
                $data['pso_nomor_ref'] = $generated['pso_nomor_ref'];
                $data['email'] = $data['email'] ?: $opportunity->customerEmail();
                $data['same_as_billing'] = $request->boolean('same_as_billing');
                if ($data['same_as_billing'] || empty($data['shipping_address_id'])) {
                    $data['shipping_address_id'] = $data['billing_address_id'];
                }

                $snapshot = $this->salesOrders->buildSnapshot($opportunity, $data, $itemsSnapshot);

                $record = $opportunity->salesOrders()->create([
                    'agc_sales_order_id' => null,
                    'number' => $number,
                    'email' => $snapshot['email'] ?? null,
                    'payment' => $snapshot['payment'] ?? null,
                    'billing_address_id' => $data['billing_address_id'] ?? null,
                    'shipping_address_id' => $data['shipping_address_id'] ?? null,
                    'po_number' => $data['po_number'] ?? null,
                    'nomor_ref' => $nomorRef,
                    'required_delivery' => $data['required_delivery'] ?? null,
                    'note' => $data['note'] ?? null,
                    'items' => $itemsSnapshot,
                    'agc_payload' => $snapshot,
                    'created_by' => auth()->id(),
                ]);

                $poFile = $request->file('po_file');
                if ($poFile instanceof UploadedFile && $poFile->isValid()) {
                    $url = $this->salesOrders->storeDocument($poFile, $record, 'po');
                    $this->salesOrders->mergeSnapshot($record, ['po_file' => $url]);
                }

                $opportunity->crm_sales_order_id = $record->id;
                $opportunity->crm_sales_order_no = $number;
                $opportunity->modified_at = Carbon::now()->format('Y-m-d H:i:s');
                $opportunity->modified_by_id = auth()->id();
                $opportunity->save();

                return $record;
            });
        } catch (\RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        }

        $this->salesOrders->recordLog($record, SalesOrderLog::ACTION_CREATED);
        app(OpportunityLogService::class)->record(
            $opportunity,
            OpportunityLog::ACTION_SALES_ORDER_CREATED,
            null,
            [
                'force' => true,
                'summary' => 'Sales Order dibuat: '.$record->displayNumber(),
            ]
        );

        app(\App\Services\NotificationService::class)
            ->notifySalesOrderCreated($record->loadMissing('creator'), $opportunity);

        $message = 'Sales Order berhasil dibuat ('.$record->number.').';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'redirect' => route('opportunities.show', $opportunity),
            ]);
        }

        return redirect()->route('opportunities.show', $opportunity)->with('success', $message);
    }

    public function edit(Opportunity $opportunity, OpportunitySalesOrder $salesOrder): View
    {
        $this->authorizeCoreEdit($opportunity, $salesOrder);

        $opportunity->load([
            'account.emailAddresses',
            'account.phoneNumbers',
            'account.contacts.emailAddresses',
            'account.contacts.phoneNumbers',
            'account.addresses',
            'contact.emailAddresses',
            'contact.phoneNumbers',
            'quotation',
        ]);
        $this->customerAddresses->ensurePrimaryFromAccount($opportunity->account);

        return view('opportunities.sales-orders.create', [
            'opportunity' => $opportunity,
            'salesOrder' => $salesOrder,
            'isEdit' => true,
            'form' => $this->formPayload($opportunity, $salesOrder),
        ]);
    }

    public function updateCore(Request $request, Opportunity $opportunity, OpportunitySalesOrder $salesOrder): RedirectResponse|JsonResponse
    {
        $this->authorizeCoreEdit($opportunity, $salesOrder);

        $opportunity->load([
            'account.emailAddresses',
            'account.phoneNumbers',
            'account.contacts.emailAddresses',
            'account.contacts.phoneNumbers',
            'contact.emailAddresses',
            'contact.phoneNumbers',
            'account.addresses',
        ]);
        $this->customerAddresses->ensurePrimaryFromAccount($opportunity->account);

        $allowedTop = array_keys(CustomerTop::optionsAllowedFor($opportunity->salesOrderMaxTop()));
        $accountId = (string) ($opportunity->account_id ?? '');
        $addressRule = Rule::exists('crm_customer_addresses', 'id')->where('account_id', $accountId);
        $hasExistingPoFile = filled(data_get($salesOrder->agc_payload, 'po_file'));

        $request->merge([
            'payment' => CustomerTop::canonicalize($request->input('payment')),
        ]);

        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'payment' => ['required', 'string', Rule::in($allowedTop)],
            'po_number' => ['required', 'string', 'max:100'],
            'required_delivery' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'billing_address_id' => ['required', 'integer', $addressRule],
            'shipping_address_id' => ['nullable', 'integer', $addressRule],
            'same_as_billing' => ['nullable', 'boolean'],
            'shipping_method' => ['nullable', 'string', 'max:150'],
            'po_file' => [$hasExistingPoFile ? 'nullable' : 'required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.index' => ['required', 'integer', 'min:0'],
            'items.*.sku' => ['nullable', 'string', 'max:100'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:10000'],
        ], [
            'payment.in' => 'TOP melebihi batas yang diizinkan untuk customer ini. TOP di atas TOP customer harus di-approve Superadmin/Finance di Opportunity.',
            'po_number.required' => 'No. PO customer wajib diisi.',
            'billing_address_id.required' => 'Pilih alamat billing. Tambah alamat di data customer jika belum ada.',
            'billing_address_id.exists' => 'Alamat billing tidak valid untuk customer ini.',
            'shipping_address_id.exists' => 'Alamat shipping tidak valid untuk customer ini.',
            'po_file.required' => 'File PO wajib diunggah.',
            'po_file.mimes' => 'File PO harus PDF, JPG, JPEG, atau PNG.',
            'po_file.max' => 'Ukuran file PO maksimal 5MB.',
        ]);

        $itemsSnapshot = $this->mapPostedSalesOrderItems($opportunity, $data['items']);

        try {
            $record = DB::transaction(function () use ($request, $opportunity, $salesOrder, $data, $itemsSnapshot) {
                $data['same_as_billing'] = $request->boolean('same_as_billing');
                if ($data['same_as_billing'] || empty($data['shipping_address_id'])) {
                    $data['shipping_address_id'] = $data['billing_address_id'];
                }
                $data['email'] = $data['email'] ?: $opportunity->customerEmail();

                $poFile = $request->file('po_file');
                if ($poFile instanceof UploadedFile && $poFile->isValid()) {
                    $data['po_file'] = $this->salesOrders->storeDocument($poFile, $salesOrder, 'po');
                }

                return $this->salesOrders->applyCoreUpdate($salesOrder, $opportunity, $data, $itemsSnapshot);
            });
        } catch (\RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        }

        $this->salesOrders->recordLog(
            $record,
            SalesOrderLog::ACTION_UPDATED,
            ['payment', 'billing_address_id', 'shipping_address_id', 'po_number', 'items', 'note', 'required_delivery']
        );
        app(OpportunityLogService::class)->record(
            $opportunity,
            OpportunityLog::ACTION_SALES_ORDER_UPDATED,
            null,
            [
                'force' => true,
                'summary' => 'Sales Order diubah: '.$record->displayNumber(),
            ]
        );

        $message = 'Sales Order berhasil diperbarui ('.$record->displayNumber().').';
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'redirect' => route('opportunities.sales-orders.show', [$opportunity, $record]),
            ]);
        }

        return redirect()
            ->route('opportunities.sales-orders.show', [$opportunity, $record])
            ->with('success', $message);
    }

    public function show(Opportunity $opportunity, OpportunitySalesOrder $salesOrder): View
    {
        abort_unless((string) $salesOrder->opportunity_id === (string) $opportunity->id, 404);

        $opportunity->loadMissing(['account', 'quotation', 'contact', 'purchaseOrders', 'assignedUser']);
        $salesOrder->loadMissing(['creator', 'assignedUser']);

        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        $ownsCommission = $user
            && trim((string) $salesOrder->assigned_user_id) !== ''
            && (string) $salesOrder->assigned_user_id === (string) $user->id;
        if (! $ownsCommission) {
            $this->authorizeView($opportunity);
        }

        $detail = $this->salesOrders->present($salesOrder, $opportunity);
        $isWon = $opportunity->stage === Opportunity::WON_STAGE;
        $canEdit = (bool) ($user?->canCreateSalesOrder() && $isWon)
            && $salesOrder->isFieldEditable();
        $canEditInvoice = (bool) ($user?->canEditSalesOrderInvoice() && $isWon)
            && ! $salesOrder->isCancelled()
            && ! $salesOrder->isCancelPending();
        $canEditDelivery = (bool) ($user?->canEditSalesOrderDelivery() && $isWon)
            && ! $salesOrder->isCancelled()
            && ! $salesOrder->isCancelPending();
        $canCompleteSo = (bool) ($user?->canCreateSalesOrder() && $isWon)
            && ! $salesOrder->isCancelled()
            && ! $salesOrder->isCancelPending()
            && ! in_array($salesOrder->soStatus(), ['completed', 'complete'], true);
        $canRequestCancel = (bool) ($user?->canRequestSalesOrderCancel() && $isWon)
            && ! $salesOrder->isCancelled()
            && (! $salesOrder->isCancelPending() || $user->canApproveSalesOrderCancel());
        $canApproveCancel = (bool) ($user?->canApproveSalesOrderCancel() && $salesOrder->isCancelPending());
        $showEditUi = $canEdit || $canEditInvoice || $canEditDelivery || $canCompleteSo;
        $canAssignOwner = (bool) $user?->canManageCommissions();
        $commissionSalesUsers = $canAssignOwner
            ? EspoUser::query()->activeSales()->orderBy('name')->get(['id', 'name', 'first_name', 'last_name', 'user_name'])
            : collect();

        return view('opportunities.sales-orders.show', [
            'opportunity' => $opportunity,
            'salesOrder' => $salesOrder,
            'detail' => $detail,
            'canEdit' => $canEdit,
            'canEditInvoice' => $canEditInvoice,
            'canEditDelivery' => $canEditDelivery,
            'canCompleteSo' => $canCompleteSo,
            'showEditUi' => $showEditUi,
            'canRequestCancel' => $canRequestCancel,
            'canApproveCancel' => $canApproveCancel,
            'canAssignOwner' => $canAssignOwner,
            'commissionSalesUsers' => $commissionSalesUsers,
            'editUrl' => $canEdit
                ? route('opportunities.sales-orders.edit', [$opportunity, $salesOrder])
                : null,
            'editForm' => [
                'updateUrl' => route('opportunities.sales-orders.update', [$opportunity, $salesOrder]),
                'email' => (string) ($detail['email'] ?? ''),
                'poNumber' => (string) ($detail['po_number'] ?? ''),
                'poAgc' => (string) ($detail['po_agc'] ?? ''),
                'invoiceNo' => (string) ($detail['invoice_no'] ?? ''),
                'invoiceDt' => ! empty($detail['invoice_dt'])
                    ? Carbon::parse($detail['invoice_dt'])->format('Y-m-d')
                    : '',
                'nomorRef' => (string) ($detail['nomor_ref'] ?? ''),
                'requiredDelivery' => $detail['required_delivery']
                    ? Carbon::parse($detail['required_delivery'])->format('Y-m-d')
                    : '',
                'noResi' => (string) ($detail['no_resi'] ?? ''),
                'shippingMethod' => (string) ($detail['courier_name'] ?? $detail['shipping_name'] ?? ''),
                'fakturPajak' => (string) ($detail['faktur_pajak'] ?? ''),
                'fotoSerahTerima' => (string) ($detail['foto_serah_terima'] ?? ''),
                'note' => (string) ($detail['note'] ?? ''),
            ],
        ]);
    }

    public function assignOwner(Request $request, Opportunity $opportunity, OpportunitySalesOrder $salesOrder): RedirectResponse
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if (! $user?->canManageCommissions()) {
            abort(403, 'Hanya Finance atau Superadmin yang boleh mengubah pemilik komisi Sales Order.');
        }

        abort_unless((string) $salesOrder->opportunity_id === (string) $opportunity->id, 404);

        $data = $request->validate([
            'assigned_user_id' => ['nullable', 'string', Rule::exists('user', 'id')->where('deleted', 0)],
        ]);

        $newId = trim((string) ($data['assigned_user_id'] ?? ''));
        $newId = $newId !== '' ? $newId : null;

        if ($newId !== null && ! EspoUser::query()->activeSales()->where('id', $newId)->exists()) {
            return back()->with('error', 'Pemilik komisi harus sales yang masih aktif.');
        }

        $oldId = $salesOrder->assigned_user_id ?: null;
        if ((string) $oldId === (string) $newId) {
            return back()->with('success', 'Kepemilikan komisi tidak berubah.');
        }

        $salesOrder->assigned_user_id = $newId;
        $salesOrder->save();
        $salesOrder->load(['assignedUser', 'opportunity.assignedUser']);

        $fromName = $oldId
            ? (User::query()->find($oldId)?->display_name ?: $oldId)
            : 'pemilik opportunity';
        $toName = $newId
            ? ($salesOrder->assignedUser?->display_name ?: $newId)
            : 'pemilik opportunity ('.$salesOrder->opportunity?->assignedUser?->display_name.')';

        $this->salesOrders->recordLog($salesOrder, SalesOrderLog::ACTION_UPDATED, [
            'assigned_user_id' => ['from' => $oldId, 'to' => $newId],
            'summary' => 'Kepemilikan komisi diubah dari '.$fromName.' ke '.$toName,
        ], $user);

        return back()->with('success', 'Kepemilikan komisi diperbarui. Margin SO ini masuk ke '.$salesOrder->commissionOwnerName().'.');
    }

    public function preview(Opportunity $opportunity, OpportunitySalesOrder $salesOrder): View
    {
        $this->authorizeView($opportunity);
        abort_unless((string) $salesOrder->opportunity_id === (string) $opportunity->id, 404);

        $detail = $this->salesOrders->present($salesOrder, $opportunity);

        return view('opportunities.sales-orders.preview', [
            'opportunity' => $opportunity,
            'salesOrder' => $salesOrder,
            'detail' => $detail,
            'currency' => $opportunity->amount_currency ?: 'IDR',
            'soCode' => $detail['code'] ?: $salesOrder->displayNumber(),
        ]);
    }

    public function pdf(Opportunity $opportunity, OpportunitySalesOrder $salesOrder)
    {
        $this->authorizeView($opportunity);
        abort_unless((string) $salesOrder->opportunity_id === (string) $opportunity->id, 404);

        $detail = $this->salesOrders->present($salesOrder, $opportunity);
        $filename = 'Sales-Order-'.str_replace(['/', '\\', ' '], '-', ($detail['code'] ?: $salesOrder->displayNumber())).'.pdf';

        return Pdf::loadView('opportunities.sales-orders.pdf', [
            'opportunity' => $opportunity,
            'salesOrder' => $salesOrder,
            'detail' => $detail,
            'currency' => $opportunity->amount_currency ?: 'IDR',
        ])
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    public function update(Request $request, Opportunity $opportunity, OpportunitySalesOrder $salesOrder): JsonResponse|RedirectResponse
    {
        $this->authorizeUpdate($opportunity);
        abort_unless((string) $salesOrder->opportunity_id === (string) $opportunity->id, 404);

        if ($salesOrder->isCancelled()) {
            $message = 'Sales Order yang dibatalkan tidak dapat diubah.';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $data = $request->validate([
            'po_number' => ['nullable', 'string', 'max:100'],
            'po_agc' => ['nullable', 'string', 'max:100'],
            'invoice_no' => ['nullable', 'string', 'max:100'],
            'invoice_dt' => ['nullable', 'date', 'before_or_equal:today'],
            'no_resi' => ['nullable', 'string', 'max:100'],
            'shipping_method' => ['nullable', 'string', 'max:150'],
            'po_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'faktur_pajak_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'foto_serah_terima_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'file_do_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'mark_complete' => ['nullable', 'boolean'],
            'mark_paid' => ['nullable', 'boolean'],
            'paid_date' => ['nullable', 'required_if:mark_paid,1', 'date', 'before_or_equal:today'],
            'payment_proof_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'mark_so_complete' => ['nullable', 'boolean'],
            'nomor_ref' => ['nullable', 'string', 'max:100'],
            'required_delivery' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'paid_date.required_if' => 'Tanggal bayar wajib diisi.',
            'paid_date.before_or_equal' => 'Tanggal bayar tidak boleh lebih dari hari ini.',
            'payment_proof_file.mimes' => 'Bukti bayar harus PDF, JPG, JPEG, PNG, atau WEBP.',
            'payment_proof_file.max' => 'Bukti bayar maksimal 5MB.',
        ]);

        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if (filled($data['po_agc'] ?? null) && ! ($user?->isSuperAdmin() ?? false)) {
            abort(403, 'Hanya Superadmin yang boleh mengubah PO AGC.');
        }
        if ((array_key_exists('invoice_no', $data) || filled($data['invoice_dt'] ?? null))
            && ! ($user?->canEditSalesOrderInvoice() ?? false)) {
            abort(403, 'Anda tidak boleh mengubah Invoice.');
        }
        if ((array_key_exists('no_resi', $data)
                || array_key_exists('shipping_method', $data)
                || $request->hasFile('file_do_file')
                || $request->boolean('mark_complete'))
            && ! ($user?->canEditSalesOrderDelivery() ?? false)
            && ! ($user?->canCreateSalesOrder() ?? false)) {
            abort(403, 'Anda tidak boleh mengubah data DO / pengiriman.');
        }

        $pendingOnlyKeys = ['po_number', 'nomor_ref', 'note', 'required_delivery'];
        $touchesPendingOnlyFields = collect($pendingOnlyKeys)->contains(fn ($key) => array_key_exists($key, $data))
            || $request->hasFile('po_file');
        if ($touchesPendingOnlyFields && ! $salesOrder->isFieldEditable()) {
            $message = 'Sales Order hanya bisa diedit saat status masih pending. Status saat ini: '.$salesOrder->soStatus().'.';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $snap = is_array($salesOrder->agc_payload) ? $salesOrder->agc_payload : [];
        $oldPoAgc = $snap['po_agc'] ?? null;
        $wasPoAgcEmpty = $oldPoAgc === null || trim((string) $oldPoAgc) === '';
        $requestFillingPoAgc = filled($data['po_agc'] ?? null);
        $oldInvoiceNo = $snap['invoice_no'] ?? null;
        $wasInvoiceNoEmpty = $oldInvoiceNo === null || trim((string) $oldInvoiceNo) === '';
        $requestFillingInvoiceNo = filled($data['invoice_no'] ?? null);
        $currentPaymentStatus = strtolower((string) ($snap['payment_status'] ?? ''));
        $currentDeliveryStatus = strtolower((string) ($snap['delivery_status'] ?? ''));

        $payload = [];
        foreach (['po_number', 'po_agc', 'invoice_no', 'no_resi', 'nomor_ref', 'note', 'required_delivery'] as $key) {
            if (array_key_exists($key, $data)) {
                $payload[$key] = filled($data[$key]) ? $data[$key] : null;
            }
        }
        if (array_key_exists('shipping_method', $data)) {
            $method = filled($data['shipping_method']) ? $data['shipping_method'] : null;
            $payload['courier_name'] = $method;
            $payload['shipping_name'] = $method;
        }

        if ($request->hasFile('po_file')) {
            $payload['po_file'] = $this->salesOrders->storeDocument($request->file('po_file'), $salesOrder, 'po');
        }
        if ($request->hasFile('faktur_pajak_file')) {
            $payload['faktur_pajak'] = $this->salesOrders->storeDocument($request->file('faktur_pajak_file'), $salesOrder, 'faktur');
        }
        if ($request->hasFile('foto_serah_terima_file')) {
            $payload['foto_serah_terima'] = $this->salesOrders->storeDocument($request->file('foto_serah_terima_file'), $salesOrder, 'bast');
        }
        if ($request->hasFile('file_do_file')) {
            $payload['file_do'] = $this->salesOrders->storeDocument($request->file('file_do_file'), $salesOrder, 'do');
        }

        $markComplete = $request->boolean('mark_complete') || filled($payload['file_do'] ?? null);
        $markPaid = $request->boolean('mark_paid');
        $markSoComplete = $request->boolean('mark_so_complete');
        $fillingResi = array_key_exists('no_resi', $payload) && filled($payload['no_resi']);

        if ($markPaid) {
            $payload['payment_status'] = 'paid';
            $payload['paid_date'] = filled($data['paid_date'] ?? null)
                ? Carbon::parse($data['paid_date'])->format('Y-m-d 00:00:00')
                : now()->format('Y-m-d H:i:s');
            if ($request->hasFile('payment_proof_file')) {
                $payload['payment_proof'] = $this->salesOrders->storeDocument($request->file('payment_proof_file'), $salesOrder, 'payment');
            }
        }
        if ($markComplete) {
            if (blank($payload['file_do'] ?? null) && ! $request->hasFile('file_do_file') && blank($snap['file_do'] ?? null)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Upload file DO wajib untuk update ke complete.',
                ], 422);
            }
            $payload['delivery_status'] = 'completed';
            $payload['delivery_date'] = now()->format('Y-m-d H:i:s');
        } elseif ($fillingResi && ! in_array($currentDeliveryStatus, ['completed', 'complete'], true)) {
            $payload['delivery_status'] = 'ondelivery';
        }

        if ($markSoComplete) {
            $paymentForSo = strtolower((string) ($payload['payment_status'] ?? $currentPaymentStatus));
            $deliveryForSo = strtolower((string) ($payload['delivery_status'] ?? $currentDeliveryStatus));
            if (! in_array($paymentForSo, ['paid', 'settlement'], true)
                || ! in_array($deliveryForSo, ['completed', 'complete'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Status SO belum bisa di-complete. Pastikan pembayaran sudah paid dan pengiriman sudah complete.',
                ], 422);
            }
            $payload['so_status'] = 'completed';
            $payload['completed_dt'] = now()->format('Y-m-d H:i:s');
        }

        $requestFillingInvoiceDt = filled($data['invoice_dt'] ?? null);
        if ($wasInvoiceNoEmpty && $requestFillingInvoiceNo && ! $requestFillingInvoiceDt) {
            $payload['invoice_dt'] = now()->format('Y-m-d H:i:s');
        }
        if ($requestFillingInvoiceDt) {
            $payload['invoice_dt'] = Carbon::parse($data['invoice_dt'])->format('Y-m-d 00:00:00');
        }
        if ($wasPoAgcEmpty && $requestFillingPoAgc) {
            $payload['so_status'] = 'onprocess';
        }

        if ($payload === []) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data untuk diperbarui.',
            ], 422);
        }

        $this->salesOrders->mergeSnapshot($salesOrder, $payload);
        $this->salesOrders->recordLog(
            $salesOrder->fresh(),
            SalesOrderLog::ACTION_UPDATED,
            array_keys($payload)
        );
        app(OpportunityLogService::class)->record(
            $opportunity,
            OpportunityLog::ACTION_SALES_ORDER_UPDATED,
            null,
            [
                'force' => true,
                'summary' => 'Sales Order diubah: '.$salesOrder->displayNumber(),
            ]
        );
        $detail = $this->salesOrders->present($salesOrder->fresh(), $opportunity);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Sales Order berhasil diperbarui.',
                'detail' => $detail,
            ]);
        }

        return redirect()
            ->route('opportunities.sales-orders.show', [$opportunity, $salesOrder])
            ->with('success', 'Sales Order berhasil diperbarui.');
    }

    public function requestCancel(Request $request, Opportunity $opportunity, OpportunitySalesOrder $salesOrder): RedirectResponse
    {
        $this->authorizeView($opportunity);
        abort_unless((string) $salesOrder->opportunity_id === (string) $opportunity->id, 404);

        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if (! $user?->canRequestSalesOrderCancel()) {
            abort(403, 'Anda tidak dapat mengajukan pembatalan Sales Order.');
        }
        if ($opportunity->stage !== Opportunity::WON_STAGE) {
            abort(403, 'Sales Order hanya untuk opportunity Closed Won.');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'reason.required' => 'Alasan pembatalan wajib diisi.',
            'reason.min' => 'Alasan pembatalan minimal 5 karakter.',
        ]);

        try {
            $result = $this->salesOrders->requestCancel($salesOrder, $user, $data['reason']);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $salesOrder = $salesOrder->fresh();
        $number = $salesOrder->displayNumber();
        $notifications = app(\App\Services\NotificationService::class);

        if ($result === 'cancelled') {
            app(OpportunityLogService::class)->record(
                $opportunity,
                OpportunityLog::ACTION_SALES_ORDER_CANCEL_APPROVED,
                null,
                [
                    'force' => true,
                    'summary' => 'Sales Order dibatalkan: '.$number,
                    'note' => $data['reason'],
                    'actor' => $user,
                ]
            );
            $notifications->markSalesOrderCancelRequestActioned($salesOrder->id);
            $notifications->markSalesOrderCreatedActioned($salesOrder->id);
            $notifications->notifySalesOrderCancelApproved($salesOrder, $opportunity, $data['reason']);

            return redirect()
                ->route('opportunities.sales-orders.show', [$opportunity, $salesOrder])
                ->with('success', 'Sales Order '.$number.' dibatalkan.');
        }

        app(OpportunityLogService::class)->record(
            $opportunity,
            OpportunityLog::ACTION_SALES_ORDER_CANCEL_REQUESTED,
            null,
            [
                'force' => true,
                'summary' => 'Request pembatalan SO: '.$number,
                'note' => $data['reason'],
                'actor' => $user,
            ]
        );
        $notifications->notifySalesOrderCancelRequested($salesOrder, $opportunity, $user, $data['reason']);

        return redirect()
            ->route('opportunities.sales-orders.show', [$opportunity, $salesOrder])
            ->with('success', 'Permintaan pembatalan SO dikirim. Menunggu approval admin.');
    }

    public function approveCancel(Request $request, Opportunity $opportunity, OpportunitySalesOrder $salesOrder): RedirectResponse
    {
        $this->authorizeCancelApproval($opportunity, $salesOrder);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var \App\Models\User $user */
        $user = auth()->user();

        try {
            $this->salesOrders->approveCancel($salesOrder, $user, $data['note'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $salesOrder = $salesOrder->fresh();
        $number = $salesOrder->displayNumber();
        $note = trim((string) ($data['note'] ?? ''));

        app(OpportunityLogService::class)->record(
            $opportunity,
            OpportunityLog::ACTION_SALES_ORDER_CANCEL_APPROVED,
            null,
            [
                'force' => true,
                'summary' => 'Pembatalan SO disetujui: '.$number,
                'note' => $note !== '' ? $note : $salesOrder->cancelReason(),
                'actor' => $user,
            ]
        );

        $notifications = app(\App\Services\NotificationService::class);
        $notifications->markSalesOrderCancelRequestActioned($salesOrder->id);
        $notifications->markSalesOrderCreatedActioned($salesOrder->id);
        $notifications->notifySalesOrderCancelApproved($salesOrder, $opportunity, $note !== '' ? $note : null);

        return redirect()
            ->route('opportunities.sales-orders.show', [$opportunity, $salesOrder])
            ->with('success', 'Pembatalan Sales Order '.$number.' disetujui.');
    }

    public function rejectCancel(Request $request, Opportunity $opportunity, OpportunitySalesOrder $salesOrder): RedirectResponse
    {
        $this->authorizeCancelApproval($opportunity, $salesOrder);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var \App\Models\User $user */
        $user = auth()->user();

        try {
            $this->salesOrders->rejectCancel($salesOrder, $user, $data['note'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $salesOrder = $salesOrder->fresh();
        $number = $salesOrder->displayNumber();
        $note = trim((string) ($data['note'] ?? ''));

        app(OpportunityLogService::class)->record(
            $opportunity,
            OpportunityLog::ACTION_SALES_ORDER_CANCEL_REJECTED,
            null,
            [
                'force' => true,
                'summary' => 'Pembatalan SO ditolak: '.$number,
                'note' => $note,
                'actor' => $user,
            ]
        );

        $notifications = app(\App\Services\NotificationService::class);
        $notifications->markSalesOrderCancelRequestActioned($salesOrder->id);
        $notifications->notifySalesOrderCancelRejected($salesOrder, $opportunity, $note !== '' ? $note : null);

        return redirect()
            ->route('opportunities.sales-orders.show', [$opportunity, $salesOrder])
            ->with('success', 'Permintaan pembatalan Sales Order '.$number.' ditolak.');
    }

    protected function authorizeCancelApproval(Opportunity $opportunity, OpportunitySalesOrder $salesOrder): void
    {
        $this->authorizeView($opportunity);
        abort_unless((string) $salesOrder->opportunity_id === (string) $opportunity->id, 404);

        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if (! $user?->canApproveSalesOrderCancel()) {
            abort(403, 'Hanya admin yang dapat menyetujui pembatalan Sales Order.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function formPayload(Opportunity $opportunity, ?OpportunitySalesOrder $salesOrder = null): array
    {
        $opportunity->loadMissing(['quotation.items']);
        $qoList = $opportunity->quotation?->items?->values();
        $opportunityProducts = $opportunity->products->values();
        $sourceItems = $opportunityProducts->map(function ($p, $i) use ($qoList) {
            $list = (float) ($p['sell_exclude'] ?? 0);
            $discount = (float) ($p['discount_exclude'] ?? $p['item_discount'] ?? 0);
            $qo = $this->quotationItemAt($qoList, $i);
            $name = trim((string) ($qo?->name ?? $p['name'] ?? ''));
            $description = $this->specForForm((string) ($qo?->description ?? $p['description'] ?? ''));

            return [
                'index' => $i,
                'name' => $name !== '' ? $name : (string) ($p['name'] ?? ''),
                'description' => $description,
                'sku' => (string) ($p['sku'] ?? ''),
                'qty' => (float) ($p['quantity'] ?? 1),
                'qty_origin' => (float) ($p['quantity'] ?? 1),
                'brand' => (string) ($p['brand'] ?? ($qo?->brand ?? '')),
                'category' => (string) ($p['category'] ?? ''),
                'sell_exclude' => $this->soUnitExclude($p),
                'list_sell_exclude' => $list,
                'has_item_discount' => $discount > 0,
            ];
        })->all();

        $items = $sourceItems;
        if ($salesOrder) {
            $soItems = is_array($salesOrder->items) ? $salesOrder->items : [];
            if ($soItems === [] && is_array($salesOrder->agc_payload['items'] ?? null)) {
                $soItems = $salesOrder->agc_payload['items'];
            }
            $items = collect($soItems)->map(function ($item) use ($opportunityProducts, $sourceItems, $qoList) {
                $index = (int) ($item['index'] ?? 0);
                $product = $opportunityProducts->get($index) ?? [];
                $source = $sourceItems[$index] ?? null;
                $qo = $this->quotationItemAt($qoList, $index);
                $list = (float) ($product['sell_exclude'] ?? ($source['list_sell_exclude'] ?? 0));
                $discount = (float) ($product['discount_exclude'] ?? $product['item_discount'] ?? 0);
                $customName = ! empty($item['name_custom']);
                $customSpec = ! empty($item['description_custom']);
                $name = $customName
                    ? (string) ($item['name'] ?? '')
                    : (string) ($qo?->name ?? $item['name'] ?? $product['name'] ?? ($source['name'] ?? ''));
                $description = $customSpec
                    ? $this->specForForm((string) ($item['description'] ?? ''))
                    : $this->specForForm((string) ($qo?->description ?? $item['description'] ?? ($source['description'] ?? '')));

                return [
                    'index' => $index,
                    'name' => $name,
                    'description' => $description,
                    'sku' => (string) ($item['sku'] ?? $product['sku'] ?? ($source['sku'] ?? '')),
                    'qty' => (float) ($item['qty'] ?? 1),
                    'qty_origin' => (float) ($source['qty_origin'] ?? $product['quantity'] ?? ($item['qty'] ?? 1)),
                    'brand' => (string) ($item['brand'] ?? $product['brand'] ?? ($source['brand'] ?? '')),
                    'category' => (string) ($item['category'] ?? $product['category'] ?? ($source['category'] ?? '')),
                    'sell_exclude' => $product !== []
                        ? $this->soUnitExclude($product)
                        : (float) ($item['price'] ?? $item['sell_exclude'] ?? ($source['sell_exclude'] ?? 0)),
                    'list_sell_exclude' => $list > 0 ? $list : (float) ($source['list_sell_exclude'] ?? 0),
                    'has_item_discount' => $discount > 0 || (bool) ($source['has_item_discount'] ?? false),
                ];
            })->values()->all();
            if ($items === []) {
                $items = $sourceItems;
            }
        }

        $account = $opportunity->account;
        $customerTop = $account?->top() ?? CustomerTop::DEFAULT;
        $maxTop = $opportunity->salesOrderMaxTop();
        $addresses = $this->customerAddresses->optionsForAccount($account);
        $addressList = collect($addresses);
        $defaultBilling = data_get($addressList->firstWhere('is_default_billing') ?: $addressList->first(), 'id');
        $defaultShipping = data_get($addressList->firstWhere('is_default_shipping') ?: $addressList->first(), 'id') ?: $defaultBilling;
        $salesContext = $this->salesOrders->salesCodeContext($opportunity, auth()->user());
        $preview = $this->salesOrders->previewDocumentNumbers($opportunity, auth()->user());
        $salesCodeError = null;
        if ($salesContext['code'] === '' && ! $salesOrder) {
            $salesCodeError = 'Sales Code untuk '.$salesContext['owner'].' belum diisi. Minta admin mengisi Sales Code di menu Users.';
        }

        $detail = $salesOrder ? $this->salesOrders->present($salesOrder, $opportunity) : [];
        $billingId = $salesOrder?->billing_address_id ?: $defaultBilling;
        $shippingId = $salesOrder?->shipping_address_id ?: $defaultShipping;
        $payment = $salesOrder
            ? CustomerTop::clamp((string) ($salesOrder->payment ?: ($detail['payment'] ?? '')), $maxTop)
            : CustomerTop::clamp($opportunity->top(), $maxTop);
        $requiredDelivery = $salesOrder?->required_delivery
            ? optional($salesOrder->required_delivery)->format('Y-m-d')
            : Carbon::now()->format('Y-m-d');
        if ($salesOrder && ! empty($detail['required_delivery']) && ! $salesOrder->required_delivery) {
            try {
                $requiredDelivery = Carbon::parse($detail['required_delivery'])->format('Y-m-d');
            } catch (\Throwable $e) {
                // keep default
            }
        }

        return [
            'email' => old('email', $salesOrder?->email ?: ($detail['email'] ?? $opportunity->customerEmail() ?? '')),
            'customerName' => optional($account)->name ?: $opportunity->company ?: '',
            'customerEditUrl' => $opportunity->account_id ? route('customers.edit', $opportunity->account_id) : null,
            'customerShowUrl' => $opportunity->account_id ? route('customers.show', $opportunity->account_id) : null,
            'payment' => old('payment', $payment),
            'topOptions' => CustomerTop::optionsAllowedFor($maxTop),
            'customerTop' => $customerTop,
            'customerTopLabel' => CustomerTop::label($customerTop),
            'opportunityTopApproved' => $opportunity->topIsApproved(),
            'opportunityTopPending' => $opportunity->topNeedsApproval(),
            'opportunityTopRejected' => $opportunity->topIsRejected(),
            'opportunityTopLabel' => $opportunity->topLabel(),
            'poNumber' => old('po_number', $salesOrder?->po_number ?: ($detail['po_number'] ?? '')),
            'requiredDelivery' => old('required_delivery', $requiredDelivery),
            'note' => old('note', $salesOrder?->note ?? ($detail['note'] ?? (string) ($opportunity->description ?? ''))),
            'addresses' => $addresses,
            'billingAddressId' => (string) old('billing_address_id', $billingId),
            'shippingAddressId' => (string) old('shipping_address_id', $shippingId),
            'sameAsBilling' => old('same_as_billing') === null
                ? (string) $billingId === (string) $shippingId
                : filter_var(old('same_as_billing'), FILTER_VALIDATE_BOOLEAN),
            'shippingMethod' => old(
                'shipping_method',
                (string) ($detail['courier_name'] ?? $detail['shipping_name'] ?? '')
            ),
            'items' => old('items', $items) ?: $items,
            'sourceItems' => $sourceItems,
            'currency' => $opportunity->amount_currency ?: 'IDR',
            'brandCategoryError' => $opportunity->brandAndCategoryBlockReason(),
            'editOpportunityUrl' => route('opportunities.edit', $opportunity),
            'previewSoNumber' => $salesOrder?->displayNumber() ?: ($preview['number'] ?? null),
            'previewPsoNumber' => $salesOrder
                ? ($detail['pre_code'] ?? $salesOrder->displayPsoNumber())
                : ($preview['pso_number'] ?? null),
            'previewSoRef' => $salesOrder?->nomor_ref ?: ($detail['nomor_ref'] ?? ($preview['nomor_ref'] ?? null)),
            'salesCode' => $salesContext['code'],
            'salesCodeOwner' => $salesContext['owner'],
            'salesCodeError' => $salesCodeError,
            'existingPoFile' => $salesOrder ? (string) ($detail['po_file'] ?? data_get($salesOrder->agc_payload, 'po_file', '')) : '',
        ];
    }

    protected function authorizeCoreEdit(Opportunity $opportunity, OpportunitySalesOrder $salesOrder): void
    {
        $this->authorizeView($opportunity);
        abort_unless((string) $salesOrder->opportunity_id === (string) $opportunity->id, 404);

        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if (! $user?->canCreateSalesOrder()) {
            abort(403, 'Anda tidak dapat mengubah Sales Order.');
        }

        if ($opportunity->stage !== Opportunity::WON_STAGE) {
            abort(403, 'Sales Order hanya untuk opportunity Closed Won.');
        }

        if (! $salesOrder->isFieldEditable()) {
            abort(403, 'Sales Order hanya bisa diedit saat status masih pending.');
        }
    }

    protected function authorizeCreate(Opportunity $opportunity): void
    {
        $this->authorizeView($opportunity);

        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if (! $user?->canCreateSalesOrder()) {
            abort(403, 'Anda tidak dapat membuat Sales Order.');
        }

        if ($opportunity->stage !== Opportunity::WON_STAGE) {
            abort(403, 'Sales Order hanya untuk opportunity Closed Won.');
        }
    }

    protected function authorizeUpdate(Opportunity $opportunity): void
    {
        $this->authorizeView($opportunity);

        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if (! $user?->canUpdateSalesOrderFields()) {
            abort(403, 'Anda tidak dapat mengubah Sales Order.');
        }

        if ($opportunity->stage !== Opportunity::WON_STAGE) {
            abort(403, 'Sales Order hanya untuk opportunity Closed Won.');
        }
    }

    protected function authorizeView(Opportunity $opportunity): void
    {
        $this->authorizeAccess($opportunity);
    }

    protected function authorizeAccess(Opportunity $opportunity): void
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if (! $user) {
            abort(403);
        }

        if ($user->isSuperAdmin() || $user->role === \App\Models\User::ROLE_ADMIN) {
            return;
        }

        if ($user->isPurchasing()
            || $user->isFinance()
            || $user->isInvoice()
            || $user->isProduct()
            || $user->isEkspedisi()) {
            if ($opportunity->stage !== Opportunity::WON_STAGE) {
                abort(403, 'Akses hanya untuk deal Closed Won.');
            }

            return;
        }

        if ($user->isSales() && $opportunity->assigned_user_id === $user->id) {
            return;
        }

        abort(403, 'You do not have access to this opportunity.');
    }

    /**
     * Harga exclude untuk SO: Diskon Item jika ada, selain itu Jual Excl.
     *
     * @param  array<string, mixed>  $product
     */
    protected function soUnitExclude(array $product): float
    {
        return OpportunityProductPricing::effectiveSellExclude(
            (float) ($product['sell_exclude'] ?? 0),
            (float) ($product['discount_exclude'] ?? $product['item_discount'] ?? 0)
        );
    }

    /**
     * @param  list<array<string, mixed>>  $postedItems
     * @return list<array<string, mixed>>
     */
    protected function mapPostedSalesOrderItems(Opportunity $opportunity, array $postedItems): array
    {
        $products = $opportunity->products->values();
        $opportunity->loadMissing(['quotation.items']);
        $qoList = $opportunity->quotation?->items?->values();

        return collect($postedItems)->map(function ($item) use ($products, $qoList) {
            $index = (int) $item['index'];
            $product = $products->get($index) ?? [];
            $qo = $this->quotationItemAt($qoList, $index);
            $defaultName = trim((string) ($qo?->name ?? $product['name'] ?? ''));
            $defaultSpec = (string) ($qo?->description ?? '');
            $name = trim((string) ($item['name'] ?? ''));
            $description = (string) ($item['description'] ?? '');
            $nameCustom = $name !== '' && ! $this->sameSpec($name, $defaultName);
            $specCustom = ! $this->sameSpec($description, $defaultSpec);

            return [
                'index' => $index,
                'sku' => trim((string) ($item['sku'] ?? ($product['sku'] ?? ''))),
                'qty' => (float) $item['qty'],
                'name' => $name !== '' ? $name : ($defaultName !== '' ? $defaultName : ($product['name'] ?? null)),
                'name_custom' => $nameCustom,
                'description' => $specCustom ? $description : $defaultSpec,
                'description_custom' => $specCustom,
                'brand' => $product['brand'] ?? ($qo?->brand ?? ''),
                'category' => $product['category'] ?? '',
                'sell_exclude' => $this->soUnitExclude($product),
                'discount_exclude' => (float) ($product['discount_exclude'] ?? $product['item_discount'] ?? 0),
            ];
        })->values()->all();
    }

    protected function quotationItemAt($qoList, int $index): mixed
    {
        if (! $qoList || $qoList->isEmpty()) {
            return null;
        }

        foreach ($qoList as $k => $candidate) {
            if ((int) ($candidate->sort_order ?? $k) === $index) {
                return $candidate;
            }
        }

        return $qoList->get($index);
    }

    protected function sameSpec(string $a, string $b): bool
    {
        return $this->normalizeSpec($a) === $this->normalizeSpec($b);
    }

    protected function normalizeSpec(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return mb_strtolower(trim($value));
    }

    protected function specForForm(?string $description): string
    {
        $description = trim((string) $description);
        if ($description === '' || $description === '<p><br></p>' || $description === '<br>') {
            return '';
        }
        if (strip_tags($description) === $description) {
            return $description;
        }

        $withBreaks = str_replace(
            ['<br>', '<br/>', '<br />', '</p>', '</li>', '</div>'],
            ["\n", "\n", "\n", "\n", "\n", "\n"],
            $description
        );
        $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
