<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Espo\Account;
use App\Models\Espo\EspoUser;
use App\Models\Espo\Opportunity;
use App\Models\OpportunitySalesOrder;
use App\Models\SalesOrderLog;
use App\Services\CustomerAddressService;
use App\Services\SalesOrderService;
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

        $this->salesOrders->archive($salesOrder, $opportunity, $user);

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

        $search = trim((string) $request->get('q', ''));
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
        $periodLabel = $this->periodLabel($period);

        $query = OpportunitySalesOrder::query()
            ->with(['opportunity.account', 'opportunity.assignedUser', 'opportunity.purchaseOrders', 'creator'])
            ->whereHas('opportunity', function ($q) use ($user, $accountId, $companyFilter, $selectedUserId) {
                if ($user->canViewAllOpportunities()
                    || $user->isProduct()
                    || $user->isEkspedisi()) {
                    if ($user->isPurchasing()
                        || $user->isFinance()
                        || $user->isProduct()
                        || $user->isEkspedisi()) {
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

        if ($periodRange !== null) {
            [$start, $end] = $periodRange;
            $query->whereBetween(
                (new OpportunitySalesOrder)->getTable().'.created_at',
                [$start, $end]
            );
        }

        if ($search !== '') {
            $this->applySalesOrderSearch($query, $search);
        }

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
            'search' => $search,
            'accountId' => $accountId,
            'companyFilter' => $companyFilter,
            'filterAccounts' => $filterAccounts,
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
                });

            if (strcasecmp($psoAsSo, $search) !== 0) {
                $q->orWhere($table.'.number', 'like', '%'.$psoAsSo.'%');
            }

            if (strcasecmp($soAsPso, $search) !== 0) {
                $q->orWhere($table.'.agc_payload', 'like', '%'.$soAsPso.'%');
            }
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
        $customerTop = $opportunity->account?->top() ?? CustomerTop::DEFAULT;
        $allowedTop = array_keys(CustomerTop::optionsAllowedFor($customerTop));
        $accountId = (string) ($opportunity->account_id ?? '');
        $addressRule = Rule::exists('crm_customer_addresses', 'id')->where('account_id', $accountId);

        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'payment' => ['required', 'string', Rule::in($allowedTop)],
            'po_number' => ['nullable', 'string', 'max:100'],
            'required_delivery' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'billing_address_id' => ['required', 'integer', $addressRule],
            'shipping_address_id' => ['nullable', 'integer', $addressRule],
            'same_as_billing' => ['nullable', 'boolean'],
            'shipping_method' => ['nullable', 'string', 'max:150'],
            'po_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.index' => ['required', 'integer', 'min:0'],
            'items.*.sku' => ['nullable', 'string', 'max:100'],
            'items.*.qty' => ['required', 'numeric', 'min:1'],
        ], [
            'payment.in' => 'TOP melebihi batas yang diizinkan untuk customer ini.',
            'billing_address_id.required' => 'Pilih alamat billing. Tambah alamat di data customer jika belum ada.',
            'billing_address_id.exists' => 'Alamat billing tidak valid untuk customer ini.',
            'shipping_address_id.exists' => 'Alamat shipping tidak valid untuk customer ini.',
            'po_file.mimes' => 'File PO harus PDF, JPG, JPEG, atau PNG.',
            'po_file.max' => 'Ukuran file PO maksimal 5MB.',
        ]);

        $products = $opportunity->products->values();
        $itemsSnapshot = collect($data['items'])->map(function ($item) use ($products) {
            $product = $products->get((int) $item['index']) ?? [];

            return [
                'index' => (int) $item['index'],
                'sku' => trim((string) ($item['sku'] ?? ($product['sku'] ?? ''))),
                'qty' => (float) $item['qty'],
                'name' => $product['name'] ?? null,
                'brand' => $product['brand'] ?? '',
                'category' => $product['category'] ?? '',
                'sell_exclude' => $this->soUnitExclude($product),
                'discount_exclude' => (float) ($product['discount_exclude'] ?? $product['item_discount'] ?? 0),
            ];
        })->values()->all();

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

    public function show(Opportunity $opportunity, OpportunitySalesOrder $salesOrder): View
    {
        $this->authorizeView($opportunity);
        abort_unless((string) $salesOrder->opportunity_id === (string) $opportunity->id, 404);

        $opportunity->loadMissing(['account', 'quotation', 'contact', 'purchaseOrders']);
        $salesOrder->loadMissing('creator');
        $detail = $this->salesOrders->present($salesOrder, $opportunity);

        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        $canEdit = (bool) ($user?->canUpdateSalesOrderFields() && $opportunity->stage === Opportunity::WON_STAGE);
        $canEditInvoice = (bool) ($user?->canEditSalesOrderInvoice() && $opportunity->stage === Opportunity::WON_STAGE);
        $canEditDelivery = (bool) ($user?->canEditSalesOrderDelivery() && $opportunity->stage === Opportunity::WON_STAGE);

        return view('opportunities.sales-orders.show', [
            'opportunity' => $opportunity,
            'salesOrder' => $salesOrder,
            'detail' => $detail,
            'canEdit' => $canEdit,
            'canEditInvoice' => $canEditInvoice,
            'canEditDelivery' => $canEditDelivery,
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
            'mark_so_complete' => ['nullable', 'boolean'],
            'nomor_ref' => ['nullable', 'string', 'max:100'],
            'required_delivery' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
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

        if ($markPaid) {
            $payload['payment_status'] = 'paid';
            $payload['paid_date'] = now()->format('Y-m-d H:i:s');
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

    /**
     * @return array<string, mixed>
     */
    protected function formPayload(Opportunity $opportunity): array
    {
        $items = $opportunity->products->values()->map(function ($p, $i) {
            $list = (float) ($p['sell_exclude'] ?? 0);
            $discount = (float) ($p['discount_exclude'] ?? $p['item_discount'] ?? 0);

            return [
                'index' => $i,
                'name' => (string) ($p['name'] ?? ''),
                'sku' => (string) ($p['sku'] ?? ''),
                'qty' => (float) ($p['quantity'] ?? 1),
                'qty_origin' => (float) ($p['quantity'] ?? 1),
                'brand' => (string) ($p['brand'] ?? ''),
                'category' => (string) ($p['category'] ?? ''),
                'sell_exclude' => $this->soUnitExclude($p),
                'list_sell_exclude' => $list,
                'has_item_discount' => $discount > 0,
            ];
        })->all();

        $account = $opportunity->account;
        $customerTop = $account?->top() ?? CustomerTop::DEFAULT;
        $addresses = $this->customerAddresses->optionsForAccount($account);
        $addressList = collect($addresses);
        $defaultBilling = data_get($addressList->firstWhere('is_default_billing') ?: $addressList->first(), 'id');
        $defaultShipping = data_get($addressList->firstWhere('is_default_shipping') ?: $addressList->first(), 'id') ?: $defaultBilling;
        $salesContext = $this->salesOrders->salesCodeContext($opportunity, auth()->user());
        $preview = $this->salesOrders->previewDocumentNumbers($opportunity, auth()->user());
        $salesCodeError = null;
        if ($salesContext['code'] === '') {
            $salesCodeError = 'Sales Code untuk '.$salesContext['owner'].' belum diisi. Minta admin mengisi Sales Code di menu Users.';
        }

        return [
            'email' => old('email', $opportunity->customerEmail() ?? ''),
            'customerName' => optional($account)->name ?: $opportunity->company ?: '',
            'customerEditUrl' => $opportunity->account_id ? route('customers.edit', $opportunity->account_id) : null,
            'customerShowUrl' => $opportunity->account_id ? route('customers.show', $opportunity->account_id) : null,
            'payment' => old('payment', CustomerTop::clamp($opportunity->top(), $customerTop)),
            'topOptions' => CustomerTop::optionsAllowedFor($customerTop),
            'customerTop' => $customerTop,
            'customerTopLabel' => CustomerTop::label($customerTop),
            'poNumber' => old('po_number', ''),
            'requiredDelivery' => old('required_delivery', Carbon::now()->format('Y-m-d')),
            'note' => old('note', (string) ($opportunity->description ?? '')),
            'addresses' => $addresses,
            'billingAddressId' => (string) old('billing_address_id', $defaultBilling),
            'shippingAddressId' => (string) old('shipping_address_id', $defaultShipping),
            'sameAsBilling' => old('same_as_billing') === null
                ? (string) $defaultBilling === (string) $defaultShipping
                : filter_var(old('same_as_billing'), FILTER_VALIDATE_BOOLEAN),
            'shippingMethod' => old('shipping_method', ''),
            'items' => old('items', $items) ?: $items,
            'currency' => $opportunity->amount_currency ?: 'IDR',
            'brandCategoryError' => $opportunity->brandAndCategoryBlockReason(),
            'editOpportunityUrl' => route('opportunities.edit', $opportunity),
            'previewSoNumber' => $preview['number'] ?? null,
            'previewPsoNumber' => $preview['pso_number'] ?? null,
            'previewSoRef' => $preview['nomor_ref'] ?? null,
            'salesCode' => $salesContext['code'],
            'salesCodeOwner' => $salesContext['owner'],
            'salesCodeError' => $salesCodeError,
        ];
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
}
