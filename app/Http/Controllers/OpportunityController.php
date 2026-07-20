<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Espo\Account;
use App\Models\Espo\Contact;
use App\Models\Espo\EspoUser;
use App\Models\Espo\Opportunity;
use App\Models\Espo\Team;
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
        $selectedUserId = $this->resolveAssignedUserFilter($request);

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

        $allOpportunities = $query->orderByDesc('created_at')->get();

        $kanbanOpportunities = $allOpportunities->whereIn('stage', $kanbanStages);

        $grouped = collect($kanbanStages)->mapWithKeys(
            fn (string $stage) => [$stage => $kanbanOpportunities->where('stage', $stage)->values()]
        );

        return view('opportunities.index', [
            'grouped' => $grouped,
            'kanbanStages' => $kanbanStages,
            'duplicateMap' => Opportunity::duplicateMap($kanbanOpportunities),
            'summary' => $this->buildOpportunitySummary($allOpportunities, $kanbanStages),
            'salesUsers' => $this->isAdmin()
                ? EspoUser::query()->activeRegular()->orderBy('name')->get(['id', 'name', 'first_name', 'last_name', 'user_name'])
                : collect(),
            'selectedUserId' => $selectedUserId,
        ]);
    }

    public function show(Opportunity $opportunity)
    {
        $this->authorizeAccess($opportunity);
        $opportunity->load(['account', 'assignedUser', 'contact', 'teams', 'quotation.creator', 'legacyDocuments.folder', 'notes.creator']);
        $mediaDocuments = $opportunity->getMedia('documents');
        $nextStage = $opportunity->nextStage();
        $closingStages = $opportunity->closingStageOptions();
        $duplicates = $opportunity->potentialDuplicates();

        return view('opportunities.show', compact('opportunity', 'mediaDocuments', 'nextStage', 'closingStages', 'duplicates'));
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

        return view('opportunities.create', $this->formData($opportunity) + compact('opportunity'));
    }

    public function store(Request $request)
    {
        if (! auth()->user()?->canCreateOpportunity()) {
            abort(403, 'Anda tidak memiliki akses untuk membuat Opportunity.');
        }

        $data = $this->validateData($request);

        $now = Carbon::now()->format('Y-m-d H:i:s');
        $opportunity = new Opportunity();
        $opportunity->id = $this->generateId();
        $opportunity->deleted = 0;
        $opportunity->created_at = $now;
        $opportunity->modified_at = $now;
        $opportunity->created_by_id = auth()->id();

        $this->applyValidatedData($opportunity, $data, $request, isNew: true);
        $notifyDiscount = $opportunity->crm_discount_status === Opportunity::DISCOUNT_PENDING;
        $opportunity->save();
        $this->syncTeams($opportunity, $data['team_ids'] ?? []);

        if ($notifyDiscount) {
            app(\App\Services\NotificationService::class)->notifyDiscountRequested($opportunity);
        }

        $message = 'Opportunity created successfully.';
        if ($opportunity->crm_discount_status === Opportunity::DISCOUNT_PENDING) {
            $message .= ' Diskon menunggu approval Superadmin.';
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

        $data = $this->validateData($request);
        $this->applyValidatedData($opportunity, $data, $request);
        $notifyDiscount = $opportunity->crm_discount_status === Opportunity::DISCOUNT_PENDING
            && ($opportunity->isDirty('crm_discount_status') || $opportunity->isDirty('crm_discount_amount'));
        $opportunity->modified_at = Carbon::now()->format('Y-m-d H:i:s');
        $opportunity->modified_by_id = auth()->id();
        $opportunity->save();
        $this->syncTeams($opportunity, $data['team_ids'] ?? []);

        if ($notifyDiscount) {
            app(\App\Services\NotificationService::class)->notifyDiscountRequested($opportunity);
        }

        $message = 'Opportunity updated successfully.';
        if ($opportunity->crm_discount_status === Opportunity::DISCOUNT_PENDING) {
            $message .= ' Diskon menunggu approval Superadmin.';
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
            $opportunity->modified_at = Carbon::now()->format('Y-m-d H:i:s');
            $opportunity->modified_by_id = auth()->id();
            $opportunity->save();

            app(\App\Services\NotificationService::class)->notifyDiscountApproved(
                $opportunity,
                $approvedAmount,
                revised: $revised,
                note: $note,
            );

            $message = $revised
                ? 'Diskon disesuaikan & disetujui. Sales mendapat notifikasi.'
                : 'Diskon disetujui. Sales mendapat notifikasi.';

            return back()->with('success', $message);
        }

        $opportunity->crm_discount_status = Opportunity::DISCOUNT_APPROVED;
        $opportunity->crm_discount_reviewed_by = auth()->id();
        $opportunity->crm_discount_reviewed_at = now();
        $opportunity->crm_discount_note = $note;
        $opportunity->modified_at = Carbon::now()->format('Y-m-d H:i:s');
        $opportunity->modified_by_id = auth()->id();
        $opportunity->save();

        app(\App\Services\NotificationService::class)->notifyDiscountApproved(
            $opportunity,
            $requestedAmount,
            revised: false,
            note: $note,
        );

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
        $opportunity->modified_at = Carbon::now()->format('Y-m-d H:i:s');
        $opportunity->modified_by_id = auth()->id();
        $opportunity->save();

        app(\App\Services\NotificationService::class)->notifyDiscountRejected(
            $opportunity,
            $approvedAmount,
            $note,
            $requestedAmount,
        );

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
        $opportunity->modified_at = Carbon::now()->format('Y-m-d H:i:s');
        $opportunity->modified_by_id = auth()->id();
        $opportunity->save();

        app(\App\Services\NotificationService::class)->notifyDiscountReverted(
            $opportunity,
            $data['note'] ?? null,
        );

        return back()->with('success', 'Diskon dikembalikan ke menunggu approval.');
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
            'products.*.discount_exclude' => ['nullable', 'numeric', 'min:0'],
            'products.*.vendor' => ['nullable', 'string', 'max:255'],
            'products.*.tax_category' => ['nullable', Rule::in([OpportunityProductPricing::TAX_WAPU, OpportunityProductPricing::TAX_NON_WAPU])],
            'products.*.item_kind' => ['nullable', Rule::in([OpportunityProductPricing::KIND_BARANG, OpportunityProductPricing::KIND_JASA])],
        ]);

        $existing = $opportunity->products->values();
        $rows = collect($data['products'] ?? [])->values()->map(function ($p, $i) use ($existing) {
            $prev = $existing->get($i, []);

            return OpportunityProductPricing::enrichRow([
                'name' => $prev['name'] ?? ($p['name'] ?? ''),
                'quantity' => $prev['quantity'] ?? ($p['quantity'] ?? 1),
                'vendor' => $p['vendor'] ?? ($prev['vendor'] ?? ''),
                'tax_category' => $prev['tax_category'] ?? OpportunityProductPricing::TAX_NON_WAPU,
                'item_kind' => $prev['item_kind'] ?? OpportunityProductPricing::KIND_BARANG,
                // Harga jual & diskon item dikunci; purchasing hanya ubah modal & vendor.
                'sell_exclude' => $prev['sell_exclude'] ?? ($p['sell_exclude'] ?? 0),
                'cost_exclude' => $p['cost_exclude'] ?? ($prev['cost_exclude'] ?? 0),
                'discount_exclude' => $prev['discount_exclude'] ?? ($p['discount_exclude'] ?? 0),
            ]);
        })->filter(fn ($p) => filled($p['name'] ?? null))->values();

        $opportunity->item = $rows->pluck('name')->map(fn ($v) => (string) $v)->all();
        $opportunity->quantity = $rows->map(fn ($p) => (string) ($p['quantity'] ?? 1))->all();
        $opportunity->price = $rows->map(fn ($p) => (string) ($p['price'] ?? 0))->all();
        $opportunity->cost = $rows->map(fn ($p) => (string) ($p['cost'] ?? 0))->all();
        $opportunity->vendor = $rows->map(fn ($p) => (string) ($p['vendor'] ?? ''))->all();
        $opportunity->crm_tax_category = $rows->pluck('tax_category')->all();
        $opportunity->crm_item_kind = $rows->pluck('item_kind')->all();
        $opportunity->crm_sell_exclude = $rows->map(fn ($p) => (string) ($p['sell_exclude'] ?? 0))->all();
        $opportunity->crm_cost_exclude = $rows->map(fn ($p) => (string) ($p['cost_exclude'] ?? 0))->all();
        $opportunity->crm_item_discount = $rows->map(fn ($p) => (string) ($p['discount_exclude'] ?? 0))->all();
        $opportunity->syncWonMargin();
        $opportunity->modified_at = Carbon::now()->format('Y-m-d H:i:s');
        $opportunity->modified_by_id = auth()->id();
        $opportunity->save();

        return redirect()->route('opportunities.show', $opportunity)
            ->with('success', 'Harga modal & vendor berhasil diperbarui.');
    }

    public function updateStage(Request $request, Opportunity $opportunity)
    {
        $this->authorizeAccess($opportunity);

        if (! auth()->user()?->canEditOpportunityFully()) {
            abort(403, 'Anda tidak dapat mengubah stage.');
        }

        $data = $request->validate([
            'stage' => ['required', 'string', Rule::in(Opportunity::STAGES)],
        ]);

        if ($data['stage'] !== $opportunity->stage) {
            $opportunity->stage = $data['stage'];
            $opportunity->probability = Opportunity::defaultProbabilityForStage($data['stage']);
            $opportunity->syncWonMargin();
            $opportunity->modified_at = Carbon::now()->format('Y-m-d H:i:s');
            $opportunity->modified_by_id = auth()->id();
            $opportunity->save();
        }

        return redirect()->route('opportunities.index')
            ->with('success', 'Stage dipindahkan ke '.$data['stage'].'.');
    }

    public function destroy(Opportunity $opportunity)
    {
        $this->authorizeAccess($opportunity);

        if (! auth()->user()?->canEditOpportunityFully()) {
            abort(403, 'Anda tidak dapat menghapus opportunity.');
        }

        if ($opportunity->quotation()->exists()) {
            return back()->with('error', 'Deal tidak bisa dihapus karena masih terhubung ke penawaran.');
        }

        $opportunity->deleted = 1;
        $opportunity->modified_at = Carbon::now()->format('Y-m-d H:i:s');
        $opportunity->modified_by_id = auth()->id();
        $opportunity->save();

        return redirect()->route('opportunities.index')
            ->with('success', 'Deal duplikat berhasil dihapus.');
    }

    protected function validateData(Request $request): array
    {
        $data = $request->validate([
            'company' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(Opportunity::TYPES)],
            'name' => ['required', 'string', 'max:255'],
            'account_id' => ['nullable', 'string', Rule::exists('account', 'id')->where('deleted', 0)],
            'stage' => ['required', 'string', 'max:255'],
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
            'products.*.discount_exclude' => ['nullable', 'numeric', 'min:0'],
            'products.*.vendor' => ['nullable', 'string', 'max:255'],
            'products.*.tax_category' => ['nullable', Rule::in([OpportunityProductPricing::TAX_WAPU, OpportunityProductPricing::TAX_NON_WAPU])],
            'products.*.item_kind' => ['nullable', Rule::in([OpportunityProductPricing::KIND_BARANG, OpportunityProductPricing::KIND_JASA])],
            'has_discount' => ['nullable', 'boolean'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $data['has_discount'] = $request->boolean('has_discount');
        if (! $data['has_discount']) {
            $data['discount_amount'] = 0;
        } else {
            $data['discount_amount'] = (float) ($data['discount_amount'] ?? 0);
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

        if ($this->isAdmin()) {
            $opportunity->assigned_user_id = ($data['assigned_user_id'] ?? null) ?: null;
        } elseif ($isNew) {
            $opportunity->assigned_user_id = auth()->id();
        }

        if ($request->has('products')) {
            $rows = collect($data['products'] ?? [])
                ->filter(fn ($p) => filled($p['name'] ?? null))
                ->map(fn ($p) => OpportunityProductPricing::enrichRow([
                    'name' => $p['name'] ?? '',
                    'quantity' => $p['quantity'] ?? 1,
                    'vendor' => $p['vendor'] ?? '',
                    'tax_category' => $p['tax_category'] ?? OpportunityProductPricing::TAX_NON_WAPU,
                    'item_kind' => $p['item_kind'] ?? OpportunityProductPricing::KIND_BARANG,
                    'sell_exclude' => $p['sell_exclude'] ?? 0,
                    'cost_exclude' => $p['cost_exclude'] ?? 0,
                    'discount_exclude' => $p['discount_exclude'] ?? 0,
                ]))
                ->values();

            $opportunity->item = $rows->pluck('name')->map(fn ($v) => (string) $v)->all();
            $opportunity->quantity = $rows->map(fn ($p) => (string) ($p['quantity'] ?? 1))->all();
            $opportunity->price = $rows->map(fn ($p) => (string) ($p['price'] ?? 0))->all();
            $opportunity->cost = $rows->map(fn ($p) => (string) ($p['cost'] ?? 0))->all();
            $opportunity->vendor = $rows->map(fn ($p) => (string) ($p['vendor'] ?? ''))->all();
            $opportunity->crm_tax_category = $rows->pluck('tax_category')->all();
            $opportunity->crm_item_kind = $rows->pluck('item_kind')->all();
            $opportunity->crm_sell_exclude = $rows->map(fn ($p) => (string) ($p['sell_exclude'] ?? 0))->all();
            $opportunity->crm_cost_exclude = $rows->map(fn ($p) => (string) ($p['cost_exclude'] ?? 0))->all();
            $opportunity->crm_item_discount = $rows->map(fn ($p) => (string) ($p['discount_exclude'] ?? 0))->all();

            if ($rows->isNotEmpty()) {
                $opportunity->amount = $rows->sum(fn ($p) => (float) ($p['quantity'] ?? 1) * (float) ($p['price'] ?? 0));
            }
        }

        $this->applyDiscountData($opportunity, $data, $isNew);

        $opportunity->syncWonMargin();
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

        if ($changed) {
            // Setiap diskon > 0 wajib approval Superadmin.
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

        return [
            'accounts' => $this->scopeAssigned(Account::query())->orderBy('name')->get(['id', 'name']),
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
            'stages' => Opportunity::STAGES,
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

        $exists = EspoUser::query()->activeRegular()->where('id', $id)->exists();

        return $exists ? $id : null;
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
