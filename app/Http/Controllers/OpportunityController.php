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

        if ($this->isAdmin()) {
            if ($selectedUserId !== null) {
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
        $data = $this->validateData($request);

        $now = Carbon::now()->format('Y-m-d H:i:s');
        $opportunity = new Opportunity();
        $opportunity->id = $this->generateId();
        $opportunity->deleted = 0;
        $opportunity->created_at = $now;
        $opportunity->modified_at = $now;
        $opportunity->created_by_id = auth()->id();

        $this->applyValidatedData($opportunity, $data, $request, isNew: true);
        $opportunity->save();
        $this->syncTeams($opportunity, $data['team_ids'] ?? []);

        return redirect()->route('opportunities.show', $opportunity)
            ->with('success', 'Opportunity created successfully.');
    }

    public function edit(Opportunity $opportunity)
    {
        $this->authorizeAccess($opportunity);

        return view('opportunities.edit', $this->formData($opportunity) + compact('opportunity'));
    }

    public function update(Request $request, Opportunity $opportunity)
    {
        $this->authorizeAccess($opportunity);

        $data = $this->validateData($request);
        $this->applyValidatedData($opportunity, $data, $request);
        $opportunity->modified_at = Carbon::now()->format('Y-m-d H:i:s');
        $opportunity->modified_by_id = auth()->id();
        $opportunity->save();
        $this->syncTeams($opportunity, $data['team_ids'] ?? []);

        return redirect()->route('opportunities.show', $opportunity)
            ->with('success', 'Opportunity updated successfully.');
    }

    public function updateStage(Request $request, Opportunity $opportunity)
    {
        $this->authorizeAccess($opportunity);

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
            'products.*.vendor' => ['nullable', 'string', 'max:255'],
            'products.*.tax_category' => ['nullable', Rule::in([OpportunityProductPricing::TAX_WAPU, OpportunityProductPricing::TAX_NON_WAPU])],
            'products.*.item_kind' => ['nullable', Rule::in([OpportunityProductPricing::KIND_BARANG, OpportunityProductPricing::KIND_JASA])],
        ]);

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

            if ($rows->isNotEmpty()) {
                $opportunity->amount = $rows->sum(fn ($p) => (float) ($p['quantity'] ?? 1) * (float) ($p['price'] ?? 0));
            }
        }

        $opportunity->syncWonMargin();
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
        if (! $this->isAdmin() && $opportunity->assigned_user_id !== auth()->id()) {
            abort(403, 'You do not have access to this opportunity.');
        }
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
