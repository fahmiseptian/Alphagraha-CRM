<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Espo\Account;
use App\Models\Espo\Contact;
use App\Models\Espo\EspoUser;
use App\Models\Espo\Opportunity;
use App\Models\Espo\Team;
use App\Support\OpportunityProductBulkExcel;
use App\Support\OpportunityProductPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Opportunity / Deal EspoCRM. Bisa dilihat & diedit, serta ditautkan
 * ke satu penawaran (1 opportunity : 1 penawaran).
 */
class OpportunityController extends Controller
{
    use ScopesToUser;

    public function index(Request $request)
    {
        $kanbanStages = Opportunity::KANBAN_STAGES;
        $view = $request->get('view') === 'list' ? 'list' : 'kanban';
        $selectedUserId = $this->resolveAssignedUserFilter($request);
        $period = $this->resolvePeriodFilter($request);
        $periodRange = $this->periodDateRange($period);
        $periodLabel = $this->periodLabel($period);

        $search = trim((string) $request->get('q', ''));
        $stageFilter = (string) $request->get('stage', '');
        if ($stageFilter !== '' && ! in_array($stageFilter, Opportunity::STAGES, true)) {
            $stageFilter = '';
        }
        $companyFilter = trim((string) $request->get('company', ''));
        if ($companyFilter !== '' && ! in_array($companyFilter, Opportunity::COMPANIES, true)) {
            $companyFilter = '';
        }

        $this->rememberOpportunitiesIndexQuery($request);

        $query = Opportunity::query()->with(['account', 'assignedUser']);
        $user = $this->currentUser();

        if ($user?->canViewAllOpportunities()) {
            if ($user->isPurchasing() || $user->isFinance()) {
                $query->where('stage', Opportunity::WON_STAGE);
            } elseif ($this->isAdmin() && $selectedUserId !== null) {
                $query->where($query->getModel()->getTable().'.assigned_user_id', $selectedUserId);
            }
        } else {
            $query = $this->scopeAssigned($query);
        }

        $this->applyPeriodToOpportunityQuery($query, $periodRange);
        $this->applyOpportunityIndexFilters($query, $search, $stageFilter, $companyFilter);

        $salesUsers = $this->isAdmin()
            ? EspoUser::query()->activeSales()->orderBy('name')->get(['id', 'name', 'first_name', 'last_name', 'user_name'])
            : collect();

        $filterState = [
            'view' => $view,
            'search' => $search,
            'stageFilter' => $stageFilter,
            'companyFilter' => $companyFilter,
            'selectedUserId' => $selectedUserId,
            'period' => $period,
            'periodLabel' => $periodLabel,
            'salesUsers' => $salesUsers,
            'stages' => Opportunity::STAGES,
            'companies' => Opportunity::COMPANIES,
            'kanbanStages' => $kanbanStages,
        ];

        if ($view === 'list') {
            $opportunities = (clone $query)
                ->orderByDesc('created_at')
                ->paginate(25)
                ->withQueryString();

            $summarySource = (clone $query)->orderByDesc('created_at')->get();

            return view('opportunities.index', $filterState + [
                'opportunities' => $opportunities,
                'grouped' => collect(),
                'duplicateMap' => Opportunity::duplicateMap($summarySource),
                'summary' => $this->buildOpportunitySummary($summarySource, $kanbanStages),
            ]);
        }

        $allOpportunities = $query->orderByDesc('created_at')->get();
        $kanbanOpportunities = $allOpportunities->whereIn('stage', $kanbanStages);
        $grouped = collect($kanbanStages)->mapWithKeys(
            fn (string $stage) => [$stage => $kanbanOpportunities->where('stage', $stage)->values()]
        );

        return view('opportunities.index', $filterState + [
            'opportunities' => null,
            'grouped' => $grouped,
            'duplicateMap' => Opportunity::duplicateMap($kanbanOpportunities),
            'summary' => $this->buildOpportunitySummary($allOpportunities, $kanbanStages),
        ]);
    }

    /**
     * Filter tambahan index: nama deal, stage, nama perusahaan/customer.
     */
    protected function applyOpportunityIndexFilters($query, string $search, string $stage, string $company): void
    {
        $table = $query->getModel()->getTable();

        if ($search !== '') {
            $query->where($table.'.name', 'like', '%'.$search.'%');
        }

        if ($stage !== '') {
            $query->where($table.'.stage', $stage);
        }

        if ($company !== '') {
            $query->where($table.'.company', $company);
        }
    }

    public function show(Opportunity $opportunity)
    {
        $this->authorizeAccess($opportunity);
        $opportunity->load(['account', 'assignedUser', 'contact', 'teams', 'quotation.creator', 'legacyDocuments.folder', 'notes.creator', 'purchaseOrders.creator', 'purchaseOrders.items']);

        // Self-heal: QO bisa tetap pending jika margin naik di atas threshold tanpa sync.
        if ($opportunity->quotation && $opportunity->syncLinkedQuotationMarginApproval()) {
            $opportunity->load('quotation.creator');
            if (! $opportunity->isMarginLocked() && $opportunity->quotation) {
                app(\App\Services\NotificationService::class)
                    ->markQuotationMarginRequestActioned($opportunity->quotation);
            }
        }

        $mediaDocuments = $opportunity->getMedia('documents');
        $nextStage = $opportunity->nextStage();
        $closingStages = $opportunity->closingStageOptions();
        $duplicates = $opportunity->potentialDuplicates();

        return view('opportunities.show', [
            'opportunity' => $opportunity,
            'mediaDocuments' => $mediaDocuments,
            'nextStage' => $nextStage,
            'closingStages' => $closingStages,
            'duplicates' => $duplicates,
            'indexUrl' => $this->opportunitiesIndexUrl(),
        ]);
    }

    public function create(Request $request)
    {
        if (! auth()->user()?->canCreateOpportunity()) {
            abort(403, 'Anda tidak memiliki akses untuk membuat Opportunity.');
        }

        $accountId = $request->get('account_id');
        if ($accountId && ! $this->scopeAssigned(Account::query())->where('id', $accountId)->exists()) {
            $accountId = null;
        }

        $opportunity = new Opportunity([
            'company' => Opportunity::COMPANIES[0] ?? null,
            'stage' => 'Prospecting',
            'amount_currency' => config('crm.default_currency', 'IDR'),
            'probability' => 10,
            'assigned_user_id' => auth()->id(),
            'account_id' => $accountId,
        ]);

        return view('opportunities.create', $this->formData($opportunity) + [
            'opportunity' => $opportunity,
            'indexUrl' => $this->opportunitiesIndexUrl(),
        ]);
    }

    public function downloadProductTemplate()
    {
        if (! auth()->user()?->canCreateOpportunity() && ! auth()->user()?->canEditOpportunityFully()) {
            abort(403, 'Anda tidak memiliki akses untuk mengunduh template produk.');
        }

        return OpportunityProductBulkExcel::downloadTemplate();
    }

    public function store(Request $request)
    {
        if (! auth()->user()?->canCreateOpportunity()) {
            abort(403, 'Anda tidak memiliki akses untuk membuat Opportunity.');
        }

        $data = $this->validateData($request, creating: true);

        $now = Carbon::now()->format('Y-m-d H:i:s');
        $opportunity = new Opportunity();
        $opportunity->id = $this->generateId();
        $opportunity->deleted = 0;
        $opportunity->created_at = $now;
        $opportunity->modified_at = $now;
        $opportunity->created_by_id = auth()->id();

        $this->applyValidatedData($opportunity, $data, $request, isNew: true);
        $notifyMargin = $opportunity->refreshMarginApprovalState(isNew: true);
        $notifyDiscount = $opportunity->discountNeedsAttention();
        $opportunity->save();
        $this->syncTeams($opportunity, $data['team_ids'] ?? []);

        if ($notifyDiscount) {
            app(\App\Services\NotificationService::class)->notifyDiscountRequested($opportunity);
        }
        if ($notifyMargin) {
            app(\App\Services\NotificationService::class)->notifyOpportunityMarginRequested($opportunity);
        }

        $message = 'Opportunity created successfully.';
        if ($opportunity->discountNeedsAttention()) {
            $message .= ' Diskon menunggu approval Superadmin.';
        }
        if ($opportunity->marginNeedsApproval()) {
            $message .= ' Margin di bawah minimal — menunggu approval Superadmin.';
        }

        return redirect()->route('opportunities.show', $opportunity)->with('success', $message);
    }

    public function edit(Opportunity $opportunity)
    {
        $this->authorizeAccess($opportunity);
        $user = auth()->user();

        if ($user?->isFinance()) {
            return redirect()->route('opportunities.show', $opportunity)
                ->with('error', 'Finance hanya dapat melihat data perhitungan (read-only).');
        }

        if ($user?->isPurchasing() && $opportunity->stage !== Opportunity::WON_STAGE) {
            abort(403, 'Purchasing hanya dapat mengedit deal Closed Won.');
        }

        return view('opportunities.edit', $this->formData($opportunity) + [
            'opportunity' => $opportunity,
            'purchasingMode' => (bool) $user?->isPurchasing(),
        ]);
    }

    public function update(Request $request, Opportunity $opportunity)
    {
        $this->authorizeAccess($opportunity);
        $user = auth()->user();

        if ($user?->isFinance()) {
            abort(403, 'Finance tidak dapat mengedit opportunity.');
        }

        if ($user?->isPurchasing()) {
            return $this->updatePurchasingFields($request, $opportunity);
        }

        $data = $this->validateData($request, creating: false, opportunity: $opportunity);
        $this->applyValidatedData($opportunity, $data, $request);

        if ($opportunity->isDirty('stage')
            && $opportunity->stage === Opportunity::WON_STAGE
            && ! $opportunity->canMoveToClosedWon()) {
            return back()->withInput()->with('error', $opportunity->closedWonBlockReason());
        }

        $notifyDiscount = $opportunity->activateDiscountApprovalIfNeeded()
            || (
                $opportunity->crm_discount_status === Opportunity::DISCOUNT_PENDING
                && ($opportunity->isDirty('crm_discount_status') || $opportunity->isDirty('crm_discount_amount'))
            );
        $opportunity->loadMissing('quotation');
        $wasMarginPending = $opportunity->crm_margin_status === Opportunity::MARGIN_PENDING
            || $opportunity->quotation?->crm_margin_status === Opportunity::MARGIN_PENDING;
        $notifyMargin = $opportunity->refreshMarginApprovalState(isNew: false);
        $opportunity->clearPendingApprovalsForLost();
        $opportunity->modified_at = Carbon::now()->format('Y-m-d H:i:s');
        $opportunity->modified_by_id = auth()->id();
        $opportunity->save();
        $syncedQuotationItems = $opportunity->syncProductsToLinkedQuotation();
        $opportunity->syncLinkedQuotationMarginApproval();
        $this->syncTeams($opportunity, $data['team_ids'] ?? []);

        $notifications = app(\App\Services\NotificationService::class);

        if ($opportunity->stage === Opportunity::LOST_STAGE) {
            $notifications->dismissApprovalRequestsForOpportunity($opportunity);
        } else {
            if ($notifyDiscount && $opportunity->discountNeedsAttention()) {
                $notifications->notifyDiscountRequested($opportunity);
            }
            if ($notifyMargin) {
                $notifications->notifyOpportunityMarginRequested($opportunity);
            }
            if ($wasMarginPending && ! $opportunity->marginNeedsApproval()) {
                $notifications->markOpportunityMarginRequestActioned($opportunity);
                if ($opportunity->quotation) {
                    $notifications->markQuotationMarginRequestActioned($opportunity->quotation);
                }
            }
        }
        if (in_array($opportunity->stage, [Opportunity::WON_STAGE, Opportunity::LOST_STAGE], true)) {
            $notifications->markOpportunityDeadlineActioned($opportunity);
        }

        $message = 'Opportunity updated successfully.';
        if ($syncedQuotationItems) {
            $message .= ' Item Quotation disinkronkan; status QO menjadi Draft.';
        }
        if ($opportunity->discountNeedsAttention()) {
            $message .= ' Diskon menunggu approval Superadmin.';
        }
        if ($opportunity->marginNeedsApproval()) {
            $message .= ' Margin di bawah minimal — menunggu approval Superadmin.';
        }

        return redirect()->route('opportunities.show', $opportunity)->with('success', $message);
    }

    public function approveDiscount(Request $request, Opportunity $opportunity)
    {
        if (! auth()->user()?->canApproveDiscount()) {
            abort(403);
        }
        $this->authorizeAccess($opportunity);

        if ($opportunity->crm_discount_status !== Opportunity::DISCOUNT_PENDING) {
            return back()->with('error', 'Diskon tidak dalam status menunggu approval.');
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $note = $data['note'] ?? null;
        $requestedAmount = (float) $opportunity->crm_discount_amount;

        if ($request->filled('discount_amount')) {
            $approvedAmount = (float) $data['discount_amount'];

            if ($approvedAmount <= 0) {
                return $this->rejectDiscount($request, $opportunity);
            }

            $revised = abs($approvedAmount - $requestedAmount) > 0.009;

            $opportunity->crm_has_discount = true;
            $opportunity->crm_discount_amount = $approvedAmount;
            $opportunity->crm_discount_status = Opportunity::DISCOUNT_APPROVED;
            $opportunity->crm_discount_reviewed_by = auth()->id();
            $opportunity->crm_discount_reviewed_at = now();
            $opportunity->crm_discount_note = $note;
            $opportunity->syncWonMargin();
            $opportunity->modified_at = Carbon::now()->format('Y-m-d H:i:s');
            $opportunity->modified_by_id = auth()->id();
            $opportunity->save();

            app(\App\Services\NotificationService::class)->notifyDiscountApproved(
                $opportunity,
                $approvedAmount,
                revised: $revised,
                note: $note,
            );
            app(\App\Services\NotificationService::class)->markDiscountRequestActioned($opportunity);

            $message = $revised
                ? 'Diskon disesuaikan & disetujui. Sales mendapat notifikasi.'
                : 'Diskon disetujui. Sales mendapat notifikasi.';

            return back()->with('success', $message);
        }

        $opportunity->crm_discount_status = Opportunity::DISCOUNT_APPROVED;
        $opportunity->crm_discount_reviewed_by = auth()->id();
        $opportunity->crm_discount_reviewed_at = now();
        $opportunity->crm_discount_note = $note;
        $opportunity->syncWonMargin();
        $opportunity->modified_at = Carbon::now()->format('Y-m-d H:i:s');
        $opportunity->modified_by_id = auth()->id();
        $opportunity->save();

        app(\App\Services\NotificationService::class)->notifyDiscountApproved(
            $opportunity,
            $requestedAmount,
            revised: false,
            note: $note,
        );
        app(\App\Services\NotificationService::class)->markDiscountRequestActioned($opportunity);

        return back()->with('success', 'Diskon disetujui. Sales mendapat notifikasi.');
    }

    /**
     * Superadmin menolak request — wajib isi nominal diskon yang disetujui (counter-offer).
     */
    public function rejectDiscount(Request $request, Opportunity $opportunity)
    {
        if (! auth()->user()?->canApproveDiscount()) {
            abort(403);
        }
        $this->authorizeAccess($opportunity);

        if ($opportunity->crm_discount_status !== Opportunity::DISCOUNT_PENDING) {
            return back()->with('error', 'Diskon tidak dalam status menunggu approval.');
        }

        $data = $request->validate([
            'discount_amount' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'discount_amount.required' => 'Isi nominal diskon yang disetujui.',
        ]);

        $requestedAmount = (float) $opportunity->crm_discount_amount;
        $approvedAmount = (float) $data['discount_amount'];
        $note = $data['note'] ?? null;

        if ($approvedAmount <= 0) {
            $opportunity->crm_has_discount = false;
            $opportunity->crm_discount_amount = null;
            $opportunity->crm_discount_status = Opportunity::DISCOUNT_REJECTED;
        } else {
            $opportunity->crm_has_discount = true;
            $opportunity->crm_discount_amount = $approvedAmount;
            $opportunity->crm_discount_status = Opportunity::DISCOUNT_REJECTED;
        }

        $opportunity->crm_discount_reviewed_by = auth()->id();
        $opportunity->crm_discount_reviewed_at = now();
        $opportunity->crm_discount_note = $note;
        $opportunity->syncWonMargin();
        $opportunity->modified_at = Carbon::now()->format('Y-m-d H:i:s');
        $opportunity->modified_by_id = auth()->id();
        $opportunity->save();

        app(\App\Services\NotificationService::class)->notifyDiscountRejected(
            $opportunity,
            $approvedAmount,
            $note,
            $requestedAmount,
        );
        app(\App\Services\NotificationService::class)->markDiscountRequestActioned($opportunity);

        return back()->with('success', 'Diskon ditolak. Sales mendapat notifikasi nominal yang disetujui.');
    }

    /**
     * Superadmin mengembalikan keputusan approve/reject ke pending.
     */
    public function revertDiscount(Request $request, Opportunity $opportunity)
    {
        if (! auth()->user()?->canApproveDiscount()) {
            abort(403);
        }
        $this->authorizeAccess($opportunity);

        if (! in_array($opportunity->crm_discount_status, [
            Opportunity::DISCOUNT_APPROVED,
            Opportunity::DISCOUNT_REJECTED,
        ], true)) {
            return back()->with('error', 'Hanya diskon yang sudah disetujui/ditolak yang bisa dikembalikan.');
        }

        if (! $opportunity->hasActiveDiscount()) {
            return back()->with('error', 'Tidak ada diskon aktif untuk dikembalikan.');
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $opportunity->crm_discount_status = Opportunity::DISCOUNT_PENDING;
        $opportunity->crm_discount_reviewed_by = null;
        $opportunity->crm_discount_reviewed_at = null;
        $opportunity->crm_discount_note = $data['note'] ?? null;
        $opportunity->syncWonMargin();
        $opportunity->modified_at = Carbon::now()->format('Y-m-d H:i:s');
        $opportunity->modified_by_id = auth()->id();
        $opportunity->save();

        app(\App\Services\NotificationService::class)->notifyDiscountReverted(
            $opportunity,
            $data['note'] ?? null,
        );

        return back()->with('success', 'Diskon dikembalikan ke menunggu approval.');
    }

    public function approveMargin(Request $request, Opportunity $opportunity)
    {
        if (! auth()->user()?->canApproveMargin()) {
            abort(403);
        }
        $this->authorizeAccess($opportunity);

        if ($opportunity->crm_margin_status !== Opportunity::MARGIN_PENDING) {
            return back()->with('error', 'Margin tidak dalam status menunggu approval.');
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $opportunity->crm_margin_status = Opportunity::MARGIN_APPROVED;
        $opportunity->crm_margin_reviewed_by = auth()->id();
        $opportunity->crm_margin_reviewed_at = now();
        $opportunity->crm_margin_note = $data['note'] ?? null;
        $opportunity->modified_at = Carbon::now()->format('Y-m-d H:i:s');
        $opportunity->modified_by_id = auth()->id();
        $opportunity->save();
        $opportunity->syncLinkedQuotationMarginApproval();

        app(\App\Services\NotificationService::class)->notifyOpportunityMarginApproved(
            $opportunity,
            $data['note'] ?? null,
        );
        app(\App\Services\NotificationService::class)->markOpportunityMarginRequestActioned($opportunity);
        if ($opportunity->quotation) {
            app(\App\Services\NotificationService::class)
                ->markQuotationMarginRequestActioned($opportunity->quotation);
        }

        return back()->with('success', 'Margin opportunity disetujui. Sales mendapat notifikasi.');
    }

    public function rejectMargin(Request $request, Opportunity $opportunity)
    {
        if (! auth()->user()?->canApproveMargin()) {
            abort(403);
        }
        $this->authorizeAccess($opportunity);

        if ($opportunity->crm_margin_status !== Opportunity::MARGIN_PENDING) {
            return back()->with('error', 'Margin tidak dalam status menunggu approval.');
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $opportunity->crm_margin_status = Opportunity::MARGIN_REJECTED;
        $opportunity->crm_margin_reviewed_by = auth()->id();
        $opportunity->crm_margin_reviewed_at = now();
        $opportunity->crm_margin_note = $data['note'] ?? null;
        $opportunity->modified_at = Carbon::now()->format('Y-m-d H:i:s');
        $opportunity->modified_by_id = auth()->id();
        $opportunity->save();
        $opportunity->syncLinkedQuotationMarginApproval();

        app(\App\Services\NotificationService::class)->notifyOpportunityMarginRejected(
            $opportunity,
            $data['note'] ?? null,
        );
        app(\App\Services\NotificationService::class)->markOpportunityMarginRequestActioned($opportunity);
        if ($opportunity->quotation) {
            app(\App\Services\NotificationService::class)
                ->markQuotationMarginRequestActioned($opportunity->quotation);
        }

        return back()->with('success', 'Margin opportunity ditolak. Sales mendapat notifikasi.');
    }

    protected function updatePurchasingFields(Request $request, Opportunity $opportunity)
    {
        if ($opportunity->stage !== Opportunity::WON_STAGE) {
            abort(403, 'Purchasing hanya dapat mengedit deal Closed Won.');
        }

        $data = $request->validate([
            'products' => ['required', 'array', 'min:1'],
            'products.*.name' => ['nullable', 'string', 'max:255'],
            'products.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'products.*.sell_exclude' => ['nullable', 'numeric', 'min:0'],
            'products.*.cost_exclude' => ['nullable', 'numeric', 'min:0'],
            'products.*.cost_in_usd' => ['nullable'],
            'products.*.cost_foreign' => ['nullable'],
            'products.*.cost_fx_code' => ['nullable', 'string', 'max:10'],
            'products.*.cost_usd' => ['nullable', 'numeric', 'min:0'],
            'products.*.cost_fx' => ['nullable', 'numeric', 'min:0'],
            'products.*.usd_rate' => ['nullable', 'numeric', 'min:0'],
            'products.*.fx_rate' => ['nullable', 'numeric', 'min:0'],
            'products.*.discount_exclude' => ['nullable', 'numeric', 'min:0'],
            'products.*.vendor' => ['nullable', 'string', 'max:255'],
            'products.*.tax_category' => ['nullable', Rule::in(OpportunityProductPricing::taxCategories())],
            'products.*.item_kind' => ['nullable', Rule::in([OpportunityProductPricing::KIND_BARANG, OpportunityProductPricing::KIND_JASA])],
        ]);

        $existing = $opportunity->products->values();
        $rows = collect($data['products'] ?? [])->values()->map(function ($p, $i) use ($existing) {
            $prev = $existing->get($i, []);
            $normalized = Opportunity::normalizeProductInput(array_merge($prev, [
                'vendor' => $p['vendor'] ?? ($prev['vendor'] ?? ''),
                'cost_exclude' => $p['cost_exclude'] ?? ($prev['cost_exclude'] ?? 0),
                'cost_foreign' => $p['cost_foreign'] ?? $p['cost_in_usd'] ?? ($prev['cost_foreign'] ?? $prev['cost_in_usd'] ?? false),
                'cost_fx_code' => $p['cost_fx_code'] ?? ($prev['cost_fx_code'] ?? ''),
                'cost_fx' => $p['cost_fx'] ?? $p['cost_usd'] ?? ($prev['cost_fx'] ?? $prev['cost_usd'] ?? 0),
                'fx_rate' => $p['fx_rate'] ?? $p['usd_rate'] ?? ($prev['fx_rate'] ?? $prev['usd_rate'] ?? 0),
            ]));

            return OpportunityProductPricing::enrichRow([
                'name' => $prev['name'] ?? ($p['name'] ?? ''),
                'quantity' => $prev['quantity'] ?? ($p['quantity'] ?? 1),
                'vendor' => $normalized['vendor'] ?? '',
                'tax_category' => $prev['tax_category'] ?? OpportunityProductPricing::TAX_NON_WAPU,
                'item_kind' => $prev['item_kind'] ?? OpportunityProductPricing::KIND_BARANG,
                // Harga jual & diskon item dikunci; purchasing hanya ubah modal & vendor.
                'sell_exclude' => $prev['sell_exclude'] ?? ($p['sell_exclude'] ?? 0),
                'cost_exclude' => $normalized['cost_exclude'],
                'discount_exclude' => $prev['discount_exclude'] ?? ($p['discount_exclude'] ?? 0),
                'cost_foreign' => $normalized['cost_foreign'],
                'cost_in_usd' => $normalized['cost_foreign'],
                'cost_fx_code' => $normalized['cost_fx_code'],
                'cost_fx' => $normalized['cost_fx'],
                'cost_usd' => $normalized['cost_fx'],
                'fx_rate' => $normalized['fx_rate'],
                'usd_rate' => $normalized['fx_rate'],
            ]);
        })->filter(fn ($p) => filled($p['name'] ?? null))->values();

        $opportunity->applyProductRows($rows);
        $opportunity->syncWonMargin();
        $opportunity->loadMissing('quotation');
        $wasMarginPending = $opportunity->crm_margin_status === Opportunity::MARGIN_PENDING
            || $opportunity->quotation?->crm_margin_status === Opportunity::MARGIN_PENDING;
        $notifyMargin = $opportunity->refreshMarginApprovalState(isNew: false);
        $opportunity->modified_at = Carbon::now()->format('Y-m-d H:i:s');
        $opportunity->modified_by_id = auth()->id();
        $opportunity->save();
        $syncedQuotationItems = $opportunity->syncProductsToLinkedQuotation();
        $opportunity->syncLinkedQuotationMarginApproval();

        if ($notifyMargin) {
            app(\App\Services\NotificationService::class)->notifyOpportunityMarginRequested($opportunity);
        }
        if ($wasMarginPending && ! $opportunity->marginNeedsApproval()) {
            app(\App\Services\NotificationService::class)->markOpportunityMarginRequestActioned($opportunity);
            if ($opportunity->quotation) {
                app(\App\Services\NotificationService::class)
                    ->markQuotationMarginRequestActioned($opportunity->quotation);
            }
        }

        $message = 'Harga modal & vendor berhasil diperbarui.';
        if ($syncedQuotationItems) {
            $message .= ' Item Quotation disinkronkan; status QO menjadi Draft.';
        }

        return redirect()->route('opportunities.show', $opportunity)
            ->with('success', $message);
    }

    public function updateStage(Request $request, Opportunity $opportunity)
    {
        $this->authorizeAccess($opportunity);

        if (! auth()->user()?->canEditOpportunityFully()) {
            abort(403, 'Anda tidak dapat mengubah stage.');
        }

        $data = $this->validateStageUpdate($request, $opportunity);

        if ($data['stage'] === Opportunity::WON_STAGE && ! $opportunity->canMoveToClosedWon()) {
            return back()->with('error', $opportunity->closedWonBlockReason());
        }

        if ($data['stage'] !== $opportunity->stage) {
            $opportunity->stage = $data['stage'];
            $opportunity->probability = Opportunity::defaultProbabilityForStage($data['stage']);

            if ($data['stage'] === Opportunity::LOST_STAGE) {
                $opportunity->crm_lost_reason = $data['lost_reason'];
            }

            $opportunity->syncWonMargin();
            $opportunity->modified_at = Carbon::now()->format('Y-m-d H:i:s');
            $opportunity->modified_by_id = auth()->id();

            if ($opportunity->skipsApproval()
                && $opportunity->crm_discount_status === Opportunity::DISCOUNT_PENDING) {
                $opportunity->crm_discount_status = null;
            }

            $notifyDiscount = $opportunity->activateDiscountApprovalIfNeeded();
            $notifyMargin = $opportunity->refreshMarginApprovalState(isNew: true);
            $opportunity->clearPendingApprovalsForLost();
            $opportunity->save();
            $opportunity->syncLinkedQuotationMarginApproval();

            $notifications = app(\App\Services\NotificationService::class);

            if ($data['stage'] === Opportunity::LOST_STAGE) {
                $notifications->dismissApprovalRequestsForOpportunity($opportunity);
            } else {
                if ($notifyDiscount) {
                    $notifications->notifyDiscountRequested($opportunity);
                }
                if ($notifyMargin) {
                    $notifications->notifyOpportunityMarginRequested($opportunity);
                }
            }
            if (in_array($data['stage'], [Opportunity::WON_STAGE, Opportunity::LOST_STAGE], true)) {
                $notifications->markOpportunityDeadlineActioned($opportunity);
            }
        }

        return redirect()->to($this->opportunitiesIndexUrl())
            ->with('success', 'Stage dipindahkan ke '.$data['stage'].'.');
    }

    public function destroy(Opportunity $opportunity)
    {
        if (! auth()->user()?->canDeleteOpportunity()) {
            abort(403, 'Anda tidak memiliki izin menghapus opportunity.');
        }

        $this->authorizeAccess($opportunity);

        if ($opportunity->quotation()->exists()) {
            return back()->with('error', 'Opportunity tidak bisa dihapus karena masih terhubung ke quotation.');
        }

        $opportunity->deleted = 1;
        $opportunity->modified_at = Carbon::now()->format('Y-m-d H:i:s');
        $opportunity->modified_by_id = auth()->id();
        $opportunity->save();

        return redirect()->to($this->opportunitiesIndexUrl())
            ->with('success', 'Opportunity berhasil dihapus.');
    }

    /**
     * Simpan query filter index opportunity agar Back to list tetap mempertahankan filter.
     */
    protected function rememberOpportunitiesIndexQuery(Request $request): void
    {
        $query = array_filter(
            $request->only(['view', 'q', 'stage', 'company', 'assigned_user_id', 'period', 'page']),
            fn ($value) => $value !== null && $value !== ''
        );

        session(['opportunities.index_query' => $query]);
    }

    protected function opportunitiesIndexUrl(): string
    {
        $query = session('opportunities.index_query', []);

        if (! is_array($query)) {
            $query = [];
        }

        return route('opportunities.index', $query);
    }

    protected function validateData(Request $request, bool $creating = false, ?Opportunity $opportunity = null): array
    {
        $allowedStages = $creating ? Opportunity::CREATE_STAGES : Opportunity::STAGES;

        $rules = [
            'company' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(Opportunity::TYPES)],
            'name' => ['required', 'string', 'max:255'],
            'account_id' => ['nullable', 'string', Rule::exists('account', 'id')->where('deleted', 0)],
            'stage' => ['required', 'string', Rule::in($allowedStages)],
            'amount' => ['required', 'numeric', 'min:0'],
            'amount_currency' => ['nullable', 'string', 'max:6'],
            'close_date' => ['required', 'date'],
            'probability' => ['required', 'integer', 'min:0', 'max:100'],
            'contact_id' => ['nullable', 'string', Rule::exists('contact', 'id')->where('deleted', 0)],
            'lead_source' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_user_id' => ['nullable', 'string', Rule::exists('user', 'id')->where('deleted', 0)],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['string', Rule::exists('team', 'id')->where('deleted', 0)],
            'products' => ['nullable', 'array'],
            'products.*.name' => ['nullable', 'string', 'max:255'],
            'products.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'products.*.sell_exclude' => ['nullable', 'numeric', 'min:0'],
            'products.*.cost_exclude' => ['nullable', 'numeric', 'min:0'],
            'products.*.cost_in_usd' => ['nullable'],
            'products.*.cost_foreign' => ['nullable'],
            'products.*.cost_fx_code' => ['nullable', 'string', 'max:10'],
            'products.*.cost_usd' => ['nullable', 'numeric', 'min:0'],
            'products.*.cost_fx' => ['nullable', 'numeric', 'min:0'],
            'products.*.usd_rate' => ['nullable', 'numeric', 'min:0'],
            'products.*.fx_rate' => ['nullable', 'numeric', 'min:0'],
            'products.*.discount_exclude' => ['nullable', 'numeric', 'min:0'],
            'products.*.vendor' => ['nullable', 'string', 'max:255'],
            'products.*.tax_category' => ['nullable', Rule::in(OpportunityProductPricing::taxCategories())],
            'products.*.item_kind' => ['nullable', Rule::in([OpportunityProductPricing::KIND_BARANG, OpportunityProductPricing::KIND_JASA])],
            'has_discount' => ['nullable', 'boolean'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'has_shipping_charge' => ['nullable', 'boolean'],
            'shipping_sell' => ['nullable', 'numeric', 'min:0'],
        ];

        $stage = (string) $request->input('stage');
        $currentStage = $opportunity?->stage;
        if ($stage === Opportunity::LOST_STAGE && $currentStage !== Opportunity::LOST_STAGE) {
            $rules['lost_reason'] = ['required', 'string', 'min:10', 'max:5000'];
        }

        $messages = [
            'lost_reason.required' => 'Catatan kekalahan wajib diisi saat menutup deal sebagai Closed Lost.',
            'lost_reason.min' => 'Catatan kekalahan minimal 10 karakter.',
        ];

        $data = $request->validate($rules, $messages);

        $data['has_discount'] = $request->boolean('has_discount');
        if (! $data['has_discount']) {
            $data['discount_amount'] = 0;
        } else {
            $data['discount_amount'] = (float) ($data['discount_amount'] ?? 0);
        }

        $data['has_shipping_charge'] = $request->boolean('has_shipping_charge');
        if (! $data['has_shipping_charge']) {
            $data['shipping_sell'] = null;
        } else {
            $data['shipping_sell'] = (float) ($data['shipping_sell'] ?? 0);
        }

        if (! empty($data['contact_id']) && ! empty($data['account_id'])) {
            $belongsToAccount = Contact::query()
                ->where('id', $data['contact_id'])
                ->where('account_id', $data['account_id'])
                ->exists();

            if (! $belongsToAccount) {
                throw ValidationException::withMessages([
                    'contact_id' => 'Contact must belong to the selected account.',
                ]);
            }
        }

        if (! empty($data['contact_id']) && empty($data['account_id'])) {
            throw ValidationException::withMessages([
                'contact_id' => 'Select an account before choosing a contact.',
            ]);
        }

        return $data;
    }

    /**
     * @return array{stage: string, lost_reason?: string}
     */
    protected function validateStageUpdate(Request $request, Opportunity $opportunity): array
    {
        $rules = [
            'stage' => ['required', 'string', Rule::in(Opportunity::STAGES)],
        ];

        $stage = (string) $request->input('stage');
        if ($stage === Opportunity::LOST_STAGE && $stage !== $opportunity->stage) {
            $rules['lost_reason'] = ['required', 'string', 'min:10', 'max:5000'];
        }

        $messages = [
            'lost_reason.required' => 'Catatan kekalahan wajib diisi saat menutup deal sebagai Closed Lost.',
            'lost_reason.min' => 'Catatan kekalahan minimal 10 karakter.',
        ];

        return $request->validate($rules, $messages);
    }

    protected function applyValidatedData(Opportunity $opportunity, array $data, Request $request, bool $isNew = false): void
    {
        $opportunity->fill([
            'company' => $data['company'],
            'type' => $data['type'],
            'name' => $data['name'],
            'account_id' => ($data['account_id'] ?? null) ?: null,
            'stage' => $data['stage'],
            'amount' => $data['amount'] ?? null,
            'amount_currency' => ($data['amount_currency'] ?? null) ?: 'IDR',
            'close_date' => $data['close_date'] ?? null,
            'probability' => $data['probability'] ?? null,
            'contact_id' => ($data['contact_id'] ?? null) ?: null,
            'lead_source' => $data['lead_source'] ?? null,
            'description' => $data['description'] ?? null,
        ]);

        if (! empty($data['lost_reason'] ?? null)) {
            $opportunity->crm_lost_reason = $data['lost_reason'];
        }

        if ($this->isAdmin()) {
            $opportunity->assigned_user_id = ($data['assigned_user_id'] ?? null) ?: null;
        } elseif ($isNew) {
            $opportunity->assigned_user_id = auth()->id();
        }

        if ($request->has('products')) {
            $rows = collect($data['products'] ?? [])
                ->filter(fn ($p) => filled($p['name'] ?? null))
                ->map(function ($p) {
                    $normalized = Opportunity::normalizeProductInput($p);

                    return OpportunityProductPricing::enrichRow([
                        'name' => $normalized['name'] ?? '',
                        'quantity' => $normalized['quantity'] ?? 1,
                        'vendor' => $normalized['vendor'] ?? '',
                        'tax_category' => $normalized['tax_category'] ?? OpportunityProductPricing::TAX_NON_WAPU,
                        'item_kind' => $normalized['item_kind'] ?? OpportunityProductPricing::KIND_BARANG,
                        'sell_exclude' => $normalized['sell_exclude'] ?? 0,
                        'cost_exclude' => $normalized['cost_exclude'],
                        'discount_exclude' => $normalized['discount_exclude'] ?? 0,
                        'cost_foreign' => $normalized['cost_foreign'],
                        'cost_in_usd' => $normalized['cost_foreign'],
                        'cost_fx_code' => $normalized['cost_fx_code'],
                        'cost_fx' => $normalized['cost_fx'],
                        'cost_usd' => $normalized['cost_fx'],
                        'fx_rate' => $normalized['fx_rate'],
                        'usd_rate' => $normalized['fx_rate'],
                    ]);
                })
                ->values();

            $opportunity->applyProductRows($rows);

            if ($rows->isNotEmpty()) {
                $opportunity->amount = $rows->sum(fn ($p) => (float) ($p['quantity'] ?? 1) * (float) ($p['price'] ?? 0));
            }
        }

        $this->applyDiscountData($opportunity, $data, $isNew);
        $this->applyShippingChargeData($opportunity, $data);
        $opportunity->syncWonMargin();
    }

    protected function applyShippingChargeData(Opportunity $opportunity, array $data): void
    {
        $has = (bool) ($data['has_shipping_charge'] ?? false);
        $opportunity->crm_has_shipping_charge = $has;
        $opportunity->crm_shipping_sell = $has ? (float) ($data['shipping_sell'] ?? 0) : null;
    }

    protected function applyDiscountData(Opportunity $opportunity, array $data, bool $isNew): void
    {
        $hasDiscount = (bool) ($data['has_discount'] ?? false);
        $amount = (float) ($data['discount_amount'] ?? 0);

        if (! $hasDiscount || $amount <= 0) {
            $opportunity->crm_has_discount = false;
            $opportunity->crm_discount_amount = null;
            $opportunity->crm_discount_status = null;
            $opportunity->crm_discount_requested_by = null;
            $opportunity->crm_discount_requested_at = null;
            $opportunity->crm_discount_reviewed_by = null;
            $opportunity->crm_discount_reviewed_at = null;
            $opportunity->crm_discount_note = null;

            return;
        }

        $previousAmount = (float) ($opportunity->crm_discount_amount ?? 0);
        $previousStatus = $opportunity->crm_discount_status;
        $changed = $isNew
            || ! $opportunity->crm_has_discount
            || abs($previousAmount - $amount) > 0.009
            || $previousStatus === Opportunity::DISCOUNT_REJECTED;

        $opportunity->crm_has_discount = true;
        $opportunity->crm_discount_amount = $amount;

        // Prospecting / Qualification: diskon boleh tanpa approval.
        if ($opportunity->skipsApproval()) {
            if ($changed || $opportunity->crm_discount_status === Opportunity::DISCOUNT_PENDING) {
                $opportunity->crm_discount_status = null;
                $opportunity->crm_discount_requested_by = auth()->id();
                $opportunity->crm_discount_requested_at = now();
                $opportunity->crm_discount_reviewed_by = null;
                $opportunity->crm_discount_reviewed_at = null;
                $opportunity->crm_discount_note = null;
            }

            return;
        }

        if ($changed) {
            // Proposal ke atas: diskon > 0 wajib approval Superadmin.
            $opportunity->crm_discount_status = Opportunity::DISCOUNT_PENDING;
            $opportunity->crm_discount_requested_by = auth()->id();
            $opportunity->crm_discount_requested_at = now();
            $opportunity->crm_discount_reviewed_by = null;
            $opportunity->crm_discount_reviewed_at = null;
            $opportunity->crm_discount_note = null;
        }
    }

    protected function generateId(): string
    {
        return substr(bin2hex(random_bytes(12)), 0, 17);
    }

    protected function syncTeams(Opportunity $opportunity, array $teamIds): void
    {
        DB::table('entity_team')
            ->where('entity_type', 'Opportunity')
            ->where('entity_id', $opportunity->id)
            ->update(['deleted' => 1]);

        foreach (array_filter($teamIds) as $teamId) {
            $existing = DB::table('entity_team')
                ->where('entity_type', 'Opportunity')
                ->where('entity_id', $opportunity->id)
                ->where('team_id', $teamId)
                ->first();

            if ($existing) {
                DB::table('entity_team')->where('id', $existing->id)->update(['deleted' => 0]);
            } else {
                DB::table('entity_team')->insert([
                    'entity_id' => $opportunity->id,
                    'team_id' => $teamId,
                    'entity_type' => 'Opportunity',
                    'deleted' => 0,
                ]);
            }
        }
    }

    protected function formData(?Opportunity $opportunity = null): array
    {
        $selectedTeamIds = old('team_ids');
        if ($selectedTeamIds === null && $opportunity?->exists) {
            $selectedTeamIds = $opportunity->teams()->pluck('team.id')->all();
        }

        $accounts = $this->scopeAssigned(Account::query())
            ->orderBy('name')
            ->get(['id', 'name', 'crm_regency_code', 'billing_address_city', 'crm_payment_level']);

        $accountMarginMeta = $accounts->mapWithKeys(function (Account $account) {
            return [$account->id => [
                'free_shipping' => \App\Support\FreeShippingZone::isFreeForAccount($account),
                'min_margin_pct' => $account->minMarginPercent(),
            ]];
        })->all();

        return [
            'accounts' => $accounts,
            'accountMarginMeta' => $accountMarginMeta,
            'marginNominalUmum' => \App\Support\PaymentLevel::marginNominalUmum(),
            'marginNominalOngkirPribadi' => \App\Support\PaymentLevel::marginNominalOngkirPribadi(),
            'contacts' => Contact::query()
                ->whereIn('account_id', $this->scopeAssigned(Account::query())->select('id'))
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'name', 'first_name', 'last_name', 'account_id']),
            'salesUsers' => EspoUser::query()->activeRegular()->orderBy('name')->get(),
            'teams' => Team::query()->orderBy('name')->get(['id', 'name']),
            'selectedTeamIds' => (array) ($selectedTeamIds ?? []),
            'companies' => Opportunity::COMPANIES,
            'leadSources' => Opportunity::LEAD_SOURCES,
            'stages' => ($opportunity && $opportunity->exists)
                ? Opportunity::STAGES
                : Opportunity::CREATE_STAGES,
            'types' => Opportunity::TYPES,
        ];
    }

    protected function authorizeAccess(Opportunity $opportunity): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(403);
        }

        if ($user->isSuperAdmin() || $user->role === \App\Models\User::ROLE_ADMIN) {
            return;
        }

        if ($user->isPurchasing() || $user->isFinance()) {
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
     * @return string|null null = semua sales (hanya admin).
     */
    protected function resolveAssignedUserFilter(Request $request): ?string
    {
        if (! $this->isAdmin()) {
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

    /**
     * Closed Won/Lost → close_date; stage terbuka → created_at (sama seperti dashboard).
     */
    protected function applyPeriodToOpportunityQuery($query, ?array $range): void
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

    protected function buildOpportunitySummary(Collection $opportunities, array $kanbanStages): array
    {
        $open = $opportunities->whereIn('stage', Opportunity::OPEN_STAGES);
        $won = $opportunities->where('stage', Opportunity::WON_STAGE);
        $lost = $opportunities->where('stage', Opportunity::LOST_STAGE);

        $stageStats = collect($kanbanStages)->mapWithKeys(function (string $stage) use ($opportunities) {
            $items = $opportunities->where('stage', $stage);

            return [$stage => [
                'count' => $items->count(),
                'value' => (float) $items->sum('amount'),
            ]];
        });

        $wonCount = $won->count();
        $lostCount = $lost->count();
        $closedCount = $wonCount + $lostCount;

        return [
            'total' => $opportunities->count(),
            'total_value' => (float) $opportunities->sum('amount'),
            'open_count' => $open->count(),
            'open_value' => (float) $open->sum('amount'),
            'won_count' => $wonCount,
            'won_value' => (float) $won->sum('amount'),
            'lost_count' => $lostCount,
            'lost_value' => (float) $lost->sum('amount'),
            'win_rate' => $closedCount > 0 ? (int) round(($wonCount / $closedCount) * 100) : null,
            'stage_stats' => $stageStats,
        ];
    }
}
