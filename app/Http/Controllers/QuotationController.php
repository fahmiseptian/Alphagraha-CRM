<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Espo\Account;
use App\Models\Espo\Opportunity;
use App\Models\Quotation;
use App\Models\QuotationRevision;
use App\Models\QuotationTemplate;
use App\Services\NotificationService;
use App\Support\OpportunityProductPricing;
use App\Services\QuotationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuotationController extends Controller
{
    use ScopesToUser;

    public function __construct(
        protected QuotationService $service,
        protected NotificationService $notifications
    ) {
    }

    public function index(Request $request)
    {
        if (! auth()->user()?->canCreateQuotation()) {
            abort(403, 'Anda tidak memiliki akses ke Quotations.');
        }

        $search = trim((string) ($request->get('q') ?: $request->get('search')));
        $status = $request->get('status');

        $query = Quotation::with(['creator', 'opportunity']);

        if (! $this->isAdmin()) {
            $query->where('created_by', auth()->id());
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('crm_quotations.number', 'like', $like)
                    ->orWhere('crm_quotations.base_number', 'like', $like)
                    ->orWhere('crm_quotations.customer_name', 'like', $like)
                    ->orWhere('crm_quotations.company_name', 'like', $like)
                    ->orWhere('crm_quotations.customer_email', 'like', $like)
                    ->orWhereHas('opportunity', function ($oq) use ($like) {
                        $oq->where('opportunity.name', 'like', $like)
                            ->orWhere('opportunity.company', 'like', $like)
                            ->orWhereHas('account', fn ($aq) => $aq->where('account.name', 'like', $like));
                    });
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $quotations = $query->latest()->paginate(15)->withQueryString();

        return view('quotations.index', [
            'quotations' => $quotations,
            'search' => $search,
            'status' => $status,
            'statuses' => Quotation::STATUSES,
        ]);
    }

    public function create(Request $request)
    {
        if (! auth()->user()?->canCreateQuotation()) {
            abort(403, 'Anda tidak memiliki akses untuk membuat Quotation.');
        }

        $opportunityId = $request->get('opportunity_id');
        $salesContext = $this->service->resolveSalesCodeContext(
            $opportunityId ? (string) $opportunityId : null,
            auth()->user()
        );

        if ($salesContext['code'] === '') {
            $who = $this->service->salesCodeOwnerLabel($salesContext['user']);

            return redirect()->back()
                ->with('error', 'Sales Code untuk '.$who.' belum diisi. Minta admin mengisi Sales Code di menu Users, lalu coba lagi.');
        }

        $quotation = new Quotation([
            'quotation_date' => now()->toDateString(),
            'valid_until' => now()->addDays(14)->toDateString(),
            'currency' => config('crm.default_currency', 'IDR'),
            'tax_percent' => \App\Support\OpportunityProductPricing::ppnPercent(),
            'status' => 'draft',
        ]);

        $seedItems = null;
        $opportunityCompany = null;

        // Prefill dari Opportunity (1 opportunity : 1 penawaran).
        if ($opportunityId) {
            $opportunity = Opportunity::query()->with(['account', 'quotation']);
            if (auth()->user()?->isSales()) {
                $opportunity->where('assigned_user_id', auth()->id());
            }
            $opportunity = $opportunity->find($opportunityId);

            if ($opportunity) {
                if ($error = $this->paymentLevelBlockMessage($opportunity->account)) {
                    return redirect()->route('opportunities.show', $opportunity)
                        ->with('error', $error);
                }

                if ($error = $this->opportunityMarginBlockMessage($opportunity)) {
                    return redirect()->route('opportunities.show', $opportunity)
                        ->with('error', $error);
                }

                if ($opportunity->quotation) {
                    return redirect()->route('quotations.show', $opportunity->quotation)
                        ->with('success', 'This opportunity already has a quotation.');
                }

                $opportunityCompany = $opportunity->company;
                $account = $opportunity->account;
                $quotation->opportunity_id = $opportunity->id;
                $quotation->currency = $opportunity->amount_currency ?: $quotation->currency;
                $quotation->customer_name = $account?->name ?: ($opportunity->company ?: $opportunity->name);
                $quotation->company_name = $opportunity->company ?: $account?->name;

                if ($account) {
                    $quotation->account_id = $account->id;
                    $quotation->customer_email = $account->email;
                    $quotation->customer_phone = $account->phone;
                    $quotation->customer_address = $account->billing_address;
                }

                // Isi item penawaran dari daftar produk opportunity.
                // Unit price di QO = harga jual EXCLUDE (PPN dihitung terpisah di ringkasan).
                $products = $opportunity->products;
                if ($products->isNotEmpty()) {
                    $seedItems = $products->map(fn ($p) => [
                        'name' => $p['name'],
                        'description' => '',
                        'quantity' => $p['quantity'] ?: 1,
                        'unit' => '',
                        'unit_price' => (float) ($p['effective_sell_exclude'] ?? $p['sell_exclude']),
                        'tax_category' => $p['tax_category'],
                        'item_kind' => $p['item_kind'],
                        'sell_exclude' => (float) $p['sell_exclude'],
                        'discount_exclude' => (float) ($p['discount_exclude'] ?? 0),
                        'cost_exclude' => (float) $p['cost_exclude'],
                        'vendor' => $p['vendor'],
                    ])->all();
                } elseif ((float) $opportunity->amount > 0) {
                    $seedItems = [[
                        'name' => $opportunity->name,
                        'description' => '',
                        'quantity' => 1,
                        'unit' => '',
                        'unit_price' => OpportunityProductPricing::excludeFromInclude((float) $opportunity->amount),
                    ]];
                }

                $templateId = $this->templateIdForCompany($opportunity->company);
                if ($templateId) {
                    $quotation->template_id = $templateId;
                }
            }
        }
        // Prefill dari pelanggan bila datang dari halaman detail pelanggan.
        elseif ($accountId = $request->get('account_id')) {
            $account = $this->scopeAssigned(Account::query())->find($accountId);
            if ($account) {
                if ($error = $this->paymentLevelBlockMessage($account)) {
                    return redirect()->route('customers.show', $account->id)
                        ->with('error', $error);
                }

                $quotation->account_id = $account->id;
                $quotation->customer_name = $account->name;
                $quotation->company_name = $account->name;
                $quotation->customer_email = $account->email;
                $quotation->customer_phone = $account->phone;
                $quotation->customer_address = $account->billing_address;
            }
        }

        return view('quotations.create', $this->formData($opportunityCompany) + [
            'quotation' => $quotation,
            'seedItems' => $seedItems,
            'resolvedSalesCode' => $salesContext['code'],
            'resolvedSalesOwner' => $this->service->salesCodeOwnerLabel($salesContext['user']),
        ]);
    }

    public function store(Request $request)
    {
        if (! auth()->user()?->canCreateQuotation()) {
            abort(403, 'Anda tidak memiliki akses untuk membuat Quotation.');
        }

        $data = $this->validateData($request);

        if ($error = $this->paymentLevelBlockForPayload($data)) {
            return back()->withInput()->with('error', $error);
        }

        if ($error = $this->opportunityMarginBlockForPayload($data)) {
            return back()->withInput()->with('error', $error);
        }

        try {
            $quotation = DB::transaction(function () use ($data) {
                $salesContext = $this->service->resolveSalesCodeContext(
                    $data['opportunity_id'] ?? null,
                    auth()->user()
                );

                $number = $this->service->generateNumber(
                    $salesContext['code'],
                    isset($data['quotation_date']) ? Carbon::parse($data['quotation_date']) : null,
                    $salesContext['user']
                );

                $quotation = new Quotation($data);
                $quotation->number = $number;
                $quotation->base_number = $number;
                $quotation->document_revision = 0;
                // Pemilik dokumen = sales assign opportunity (bukan admin yang membantu membuat).
                $quotation->created_by = $salesContext['user']?->id ?: auth()->id();
                $quotation->revision = 1;

                $becamePending = $this->applyMarginApprovalState($quotation, isNew: true);
                if ($quotation->isMarginLocked() && ($data['status'] ?? '') === 'sent') {
                    $quotation->status = 'draft';
                    $quotation->sent_at = null;
                } elseif (($data['status'] ?? '') === 'sent') {
                    $quotation->sent_at = now();
                }

                $quotation->save();

                $this->syncItems($quotation, $data['items']);
                $quotation->load('items');
                $quotation->recalculateTotals();
                $quotation->save();

                $this->snapshotRevision($quotation, 'Quotation created');

                if ($becamePending) {
                    $this->notifications->notifyMarginRequested($quotation);
                }

                return $quotation;
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $msg = 'Quotation ' . $quotation->number . ' created successfully.';
        if ($quotation->marginNeedsApproval()) {
            $msg .= ' Margin di bawah minimal — menunggu approval Superadmin.';
        }

        return redirect()->route('quotations.show', $quotation)
            ->with('success', $msg);
    }

    public function show(Quotation $quotation)
    {
        $this->authorizeAccess($quotation);

        // Rapikan histori ganda yang sempat terbentuk saat masih draft murni.
        // Jangan jalankan bila sudah pernah Sent / sudah ada R1+ (sent_at atau document_revision).
        if (
            $quotation->sent_at === null
            && (int) $quotation->document_revision === 0
            && $quotation->status !== 'sent'
            && $quotation->revisions()->count() > 1
        ) {
            $this->refreshDraftSnapshot($quotation);
        }

        $quotation->load(['items', 'creator', 'template', 'revisions.creator', 'account']);

        return view('quotations.show', compact('quotation'));
    }

    public function edit(Quotation $quotation)
    {
        $this->authorizeAccess($quotation);
        $quotation->load(['items', 'opportunity']);

        return view('quotations.edit', $this->formData($quotation->opportunity?->company) + ['quotation' => $quotation]);
    }

    public function update(Request $request, Quotation $quotation)
    {
        $this->authorizeAccess($quotation);
        $data = $this->validateData($request, $quotation);

        if ($error = $this->paymentLevelBlockForPayload($data, $quotation)) {
            return back()->withInput()->with('error', $error);
        }

        $becamePending = false;
        $becameRevision = false;

        try {
            $result = DB::transaction(function () use ($quotation, $data, &$becamePending, &$becameRevision) {
                $before = $this->contentFingerprint($quotation);
                $everSent = $quotation->hasBeenSent();
                // Naik R hanya jika status SAAT INI Sent. Draft setelah R1 tidak boleh jadi R2.
                $isCurrentlySent = $quotation->status === 'sent';

                // Nomor tidak boleh diubah manual; pertahankan yang ada.
                unset($data['number']);

                $quotation->fill($data);
                $becamePending = $this->applyMarginApprovalState($quotation, isNew: false);

                if ($quotation->isMarginLocked() && ($data['status'] ?? '') === 'sent') {
                    $quotation->status = $everSent ? $quotation->getOriginal('status') : 'draft';
                    if (! $everSent) {
                        $quotation->sent_at = null;
                    }
                } elseif (($data['status'] ?? '') === 'sent' && ! $quotation->sent_at) {
                    $quotation->sent_at = now();
                }
                $quotation->save();

                $this->syncItems($quotation, $data['items']);
                $quotation->load('items');
                $quotation->recalculateTotals();
                $quotation->save();

                $after = $this->contentFingerprint($quotation->fresh(['items']));
                $contentChanged = $before !== $after;

                if ($isCurrentlySent && $contentChanged) {
                    $quotation->document_revision = (int) $quotation->document_revision + 1;
                    $base = $quotation->base_number ?: $this->service->stripDocumentRevision($quotation->number);
                    $quotation->base_number = $base;
                    $quotation->number = $this->service->withDocumentRevision($base, (int) $quotation->document_revision);
                    $quotation->revision = (int) $quotation->revision + 1;
                    // Revisi baru harus dikirim ulang → kembali ke draft, tapi sent_at tetap
                    // sebagai penanda "pernah sent" agar histori aman.
                    $quotation->status = 'draft';
                    if (! $quotation->sent_at) {
                        $quotation->sent_at = now();
                    }
                    $quotation->save();
                    $this->snapshotRevision($quotation, 'Revisi dokumen R'.$quotation->document_revision);
                    $becameRevision = true;
                } elseif (! $everSent) {
                    $this->refreshDraftSnapshot($quotation);
                } elseif ($contentChanged) {
                    // Sudah pernah sent / sudah R*, tapi status masih Draft: simpan isi tanpa naik R.
                    $this->refreshLatestRevisionSnapshot($quotation);
                }

                return $quotation->fresh(['items']);
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->with(
                'error',
                'Gagal menyimpan quotation'.($e->getMessage() ? ': '.$e->getMessage() : '.')
            );
        }

        if ($becamePending) {
            $this->notifications->notifyMarginRequested($result);
        }

        $msg = 'Quotation updated successfully.';
        if ($becameRevision) {
            $msg = 'Quotation diperbarui sebagai revisi dokumen R'.$result->document_revision.' ('.$result->number.'). Status dikembalikan ke Draft — kirim ulang setelah dicek.';
        }
        if ($result->marginNeedsApproval()) {
            $msg .= ' Margin di bawah minimal — menunggu approval Superadmin.';
        }

        return redirect()->route('quotations.show', $result)->with('success', $msg);
    }

    public function destroy(Quotation $quotation)
    {
        $this->authorizeAccess($quotation);
        $quotation->delete();

        return redirect()->route('quotations.index')->with('success', 'Quotation deleted.');
    }

    public function preview(Quotation $quotation)
    {
        $this->authorizeAccess($quotation);
        $this->ensureMarginUnlocked($quotation);
        $this->ensureCreatorSignature($quotation);

        $template = $this->resolveTemplate($quotation);
        $html = $this->service->render($quotation, $template->body_html, $template);

        return view('quotations.preview', compact('quotation', 'html'));
    }

    public function pdf(Quotation $quotation)
    {
        $this->authorizeAccess($quotation);
        $this->ensureMarginUnlocked($quotation);
        $this->ensureCreatorSignature($quotation);

        $template = $this->resolveTemplate($quotation);
        $content = $this->service->prepareHtmlForPdf(
            $this->service->render($quotation, $template->body_html, $template)
        );

        $pdf = Pdf::loadView('quotations.pdf', ['content' => $content])
            ->setPaper('a4');

        $filename = str_replace(['/', '\\'], '-', $quotation->number) . '.pdf';

        return $pdf->download($filename);
    }

    public function updateStatus(Request $request, Quotation $quotation)
    {
        $this->authorizeAccess($quotation);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Quotation::STATUSES))],
        ]);

        if ($data['status'] === 'sent' && $quotation->isMarginLocked() && ! auth()->user()?->canApproveMargin()) {
            return back()->with('error', 'Quotation terkunci: margin menunggu / ditolak approval Superadmin.');
        }

        $quotation->status = $data['status'];
        if ($data['status'] === 'sent' && ! $quotation->sent_at) {
            $quotation->sent_at = now();
        }
        $quotation->save();

        return back()->with('success', 'Quotation status updated to "' . $quotation->statusLabel() . '".');
    }

    public function approveMargin(Request $request, Quotation $quotation)
    {
        $this->authorizeAccess($quotation);

        if (! auth()->user()?->canApproveMargin()) {
            abort(403, 'Hanya Superadmin yang dapat approve margin.');
        }

        if ($quotation->crm_margin_status !== Quotation::MARGIN_PENDING) {
            return back()->with('error', 'Quotation ini tidak menunggu approval margin.');
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $quotation->crm_margin_status = Quotation::MARGIN_APPROVED;
        $quotation->crm_margin_reviewed_by = auth()->id();
        $quotation->crm_margin_reviewed_at = now();
        $quotation->crm_margin_note = $data['note'] ?? null;
        $quotation->save();

        $this->notifications->notifyMarginApproved($quotation, $data['note'] ?? null);

        return back()->with('success', 'Margin quotation disetujui.');
    }

    public function rejectMargin(Request $request, Quotation $quotation)
    {
        $this->authorizeAccess($quotation);

        if (! auth()->user()?->canApproveMargin()) {
            abort(403, 'Hanya Superadmin yang dapat menolak margin.');
        }

        if ($quotation->crm_margin_status !== Quotation::MARGIN_PENDING) {
            return back()->with('error', 'Quotation ini tidak menunggu approval margin.');
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $quotation->crm_margin_status = Quotation::MARGIN_REJECTED;
        $quotation->crm_margin_reviewed_by = auth()->id();
        $quotation->crm_margin_reviewed_at = now();
        $quotation->crm_margin_note = $data['note'] ?? null;
        $quotation->save();

        $this->notifications->notifyMarginRejected($quotation, $data['note'] ?? null);

        return back()->with('success', 'Margin quotation ditolak.');
    }

    public function previewRevision(Quotation $quotation, QuotationRevision $revision)
    {
        $this->authorizeAccess($quotation);
        $this->ensureMarginUnlocked($quotation);

        if ((int) $revision->quotation_id !== (int) $quotation->id) {
            abort(404);
        }

        $html = $revision->rendered_html;
        if (! $html) {
            abort(404, 'Snapshot dokumen revisi tidak tersedia.');
        }

        return view('quotations.preview-revision', compact('quotation', 'revision', 'html'));
    }

    public function duplicate(Quotation $quotation)
    {
        $this->authorizeAccess($quotation);
        $quotation->load('items');

        try {
            $copy = DB::transaction(function () use ($quotation) {
                $copy = $quotation->replicate(['number', 'base_number', 'sent_at', 'revision', 'document_revision']);
                $number = $this->service->generateNumber();
                $copy->number = $number;
                $copy->base_number = $number;
                $copy->document_revision = 0;
                $copy->status = 'draft';
                $copy->revision = 1;
                $copy->created_by = auth()->id();
                $copy->quotation_date = now()->toDateString();
                $copy->opportunity_id = null;
                $copy->crm_margin_status = null;
                $copy->crm_margin_percent = null;
                $copy->crm_margin_threshold = null;
                $copy->crm_margin_requested_at = null;
                $copy->crm_margin_reviewed_by = null;
                $copy->crm_margin_reviewed_at = null;
                $copy->crm_margin_note = null;
                $copy->save();

                foreach ($quotation->items as $item) {
                    $newItem = $item->replicate(['quotation_id']);
                    $newItem->quotation_id = $copy->id;
                    $newItem->save();
                }

                $this->snapshotRevision($copy, 'Disalin dari ' . $quotation->number);

                return $copy;
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('quotations.edit', $copy)
            ->with('success', 'Quotation duplicated as ' . $copy->number . '.');
    }

    // ---------------------------------------------------------------------

    protected function validateData(Request $request, ?Quotation $quotation = null): array
    {
        $isCreate = $quotation === null;

        $rules = [
            'account_id' => ['nullable', 'string'],
            'opportunity_id' => ['nullable', 'string', Rule::unique('crm_quotations', 'opportunity_id')->ignore($quotation?->id)],
            'customer_name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => [$isCreate ? 'required' : 'nullable', 'email', 'max:255'],
            'customer_phone' => [$isCreate ? 'required' : 'nullable', 'string', 'max:50'],
            'customer_address' => [$isCreate ? 'required' : 'nullable', 'string'],
            'quotation_date' => ['required', 'date'],
            'valid_until' => [$isCreate ? 'required' : 'nullable', 'date', 'after_or_equal:quotation_date'],
            'currency' => ['required', 'string', 'max:6'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'template_id' => ['nullable', 'exists:crm_quotation_templates,id'],
            'notes' => ['nullable', 'string'],
            'terms' => [$isCreate ? 'required' : 'nullable', 'string'],
            'status' => ['required', Rule::in(array_keys(Quotation::STATUSES))],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'numeric', $isCreate ? 'min:0.01' : 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.sell_exclude' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_exclude' => ['nullable', 'numeric', 'min:0'],
            'items.*.cost_exclude' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_category' => ['nullable', 'string', 'max:20'],
            'items.*.item_kind' => ['nullable', 'string', 'max:20'],
            'items.*.vendor' => ['nullable', 'string', 'max:255'],
        ];

        if (! $isCreate) {
            $rules['number'] = ['nullable', 'string', 'max:255'];
        }

        $data = $request->validate($rules);

        $data['discount'] = $data['discount'] ?? 0;
        $data['tax_percent'] = array_key_exists('tax_percent', $data) && $data['tax_percent'] !== null && $data['tax_percent'] !== ''
            ? (float) $data['tax_percent']
            : ($quotation?->tax_percent ?? OpportunityProductPricing::ppnPercent());

        if ($isCreate) {
            unset($data['number']);
            // Quotation baru selalu ikut tarif PPN terkini dari settings.
            $data['tax_percent'] = OpportunityProductPricing::ppnPercent();
        }

        return $data;
    }

    /**
     * Fingerprint konten untuk mendeteksi apakah ada perubahan substantif.
     */
    protected function contentFingerprint(Quotation $quotation): string
    {
        $quotation->loadMissing('items');

        $payload = [
            'customer_name' => $quotation->customer_name,
            'company_name' => $quotation->company_name,
            'customer_email' => $quotation->customer_email,
            'customer_phone' => $quotation->customer_phone,
            'customer_address' => $quotation->customer_address,
            'quotation_date' => optional($quotation->quotation_date)->format('Y-m-d'),
            'valid_until' => optional($quotation->valid_until)->format('Y-m-d'),
            'currency' => $quotation->currency,
            'discount' => (float) $quotation->discount,
            'tax_percent' => (float) $quotation->tax_percent,
            'notes' => (string) $quotation->notes,
            'terms' => (string) $quotation->terms,
            'template_id' => $quotation->template_id,
            'items' => $quotation->items->map(fn ($i) => [
                'name' => $i->name,
                'description' => (string) $i->description,
                'quantity' => round((float) $i->quantity, 4),
                'unit' => (string) $i->unit,
                'unit_price' => round((float) $i->unit_price, 2),
                'sell_exclude' => round((float) ($i->sell_exclude ?? 0), 2),
                'discount_exclude' => round((float) ($i->discount_exclude ?? 0), 2),
                'cost_exclude' => round((float) ($i->cost_exclude ?? 0), 2),
                'tax_category' => (string) ($i->tax_category ?? ''),
                'item_kind' => (string) ($i->item_kind ?? ''),
                'vendor' => (string) ($i->vendor ?? ''),
            ])->values()->all(),
        ];

        return md5(json_encode($payload));
    }

    protected function syncItems(Quotation $quotation, array $items): void
    {
        $quotation->items()->delete();

        $oppProducts = null;
        if ($quotation->opportunity_id) {
            $opportunity = Opportunity::query()->find($quotation->opportunity_id);
            $oppProducts = $opportunity?->products;
        }

        foreach (array_values($items) as $index => $item) {
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $incomingDiscount = (float) ($item['discount_exclude'] ?? 0);
            $incomingList = isset($item['sell_exclude']) && $item['sell_exclude'] !== '' && $item['sell_exclude'] !== null
                ? (float) $item['sell_exclude']
                : null;
            $incomingCost = isset($item['cost_exclude']) && $item['cost_exclude'] !== '' && $item['cost_exclude'] !== null
                ? (float) $item['cost_exclude']
                : null;

            // Opportunity hanya sebagai fallback bila form tidak mengirim nilai.
            // Jangan timpa harga yang diubah user di Quotation.
            $opp = $oppProducts?->values()->get($index);
            if ($opp) {
                if (($incomingList === null || $incomingList <= 0) && (float) ($opp['sell_exclude'] ?? 0) > 0) {
                    $incomingList = (float) $opp['sell_exclude'];
                }
                if ($incomingDiscount <= 0 && (float) ($opp['discount_exclude'] ?? 0) > 0) {
                    $incomingDiscount = (float) $opp['discount_exclude'];
                }
                if ($incomingCost === null && isset($opp['cost_exclude'])) {
                    $incomingCost = (float) $opp['cost_exclude'];
                }
            }

            // unit_price di form = harga yang ditagihkan (setelah diskon item bila ada).
            if ($incomingDiscount > 0) {
                $listPrice = $incomingList !== null && $incomingList > 0 ? $incomingList : max($unitPrice, $incomingDiscount);

                if (abs($unitPrice - $incomingDiscount) < 0.009) {
                    // Tagihan masih = diskon item opportunity/form.
                    $discountExclude = $incomingDiscount;
                } elseif ($listPrice > 0 && abs($unitPrice - $listPrice) < 0.009) {
                    // User set tagihan = harga list → anggap tanpa diskon efektif di QO.
                    $discountExclude = 0;
                    $listPrice = $unitPrice;
                } else {
                    // User mengubah harga tagihan di form QO.
                    $discountExclude = $unitPrice;
                    if ($listPrice < $discountExclude) {
                        $listPrice = $discountExclude;
                    }
                }
            } else {
                // Tanpa diskon item: harga form = list = tagihan (hormati edit user).
                $listPrice = $unitPrice;
                $discountExclude = 0;
            }

            $enriched = OpportunityProductPricing::enrichRow([
                'name' => $item['name'],
                'quantity' => $quantity,
                'vendor' => $item['vendor'] ?? '',
                'tax_category' => $item['tax_category'] ?? OpportunityProductPricing::TAX_NON_WAPU,
                'item_kind' => $item['item_kind'] ?? OpportunityProductPricing::KIND_BARANG,
                'sell_exclude' => $listPrice,
                'cost_exclude' => (float) ($incomingCost ?? 0),
                'discount_exclude' => $discountExclude,
            ]);

            $billedPrice = $discountExclude > 0 ? $discountExclude : $listPrice;

            $quotation->items()->create([
                'name' => $item['name'],
                'description' => $item['description'] ?? null,
                'quantity' => $quantity,
                'unit' => $item['unit'] ?? null,
                'unit_price' => $billedPrice,
                'total' => round($quantity * $billedPrice, 2),
                'tax_category' => $enriched['tax_category'],
                'item_kind' => $enriched['item_kind'],
                'sell_exclude' => $listPrice,
                'cost_exclude' => $enriched['cost_exclude'],
                'discount_exclude' => $discountExclude > 0 ? $discountExclude : null,
                'vendor' => $enriched['vendor'] ?: null,
                'sort_order' => $index,
            ]);
        }
    }

    protected function snapshotRevision(Quotation $quotation, string $note): void
    {
        $quotation->load('items');
        $template = $this->resolveTemplate($quotation);
        $rendered = $this->service->render($quotation, $template->body_html, $template);

        $quotation->revisions()->create([
            'revision' => $quotation->revision,
            'snapshot' => $this->revisionSnapshotPayload($quotation),
            'rendered_html' => $rendered,
            'note' => $note,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Saat masih draft: update snapshot pertama saja, hapus entri histori ekstra.
     */
    protected function refreshDraftSnapshot(Quotation $quotation): void
    {
        $quotation->load('items');
        $template = $this->resolveTemplate($quotation);
        $rendered = $this->service->render($quotation, $template->body_html, $template);
        $payload = $this->revisionSnapshotPayload($quotation);

        $existing = $quotation->revisions()->orderBy('id')->first();

        if ($existing) {
            $existing->update([
                'revision' => 1,
                'snapshot' => $payload,
                'rendered_html' => $rendered,
                'note' => 'Quotation created',
            ]);

            $quotation->revisions()->where('id', '!=', $existing->id)->delete();
        } else {
            $quotation->revision = 1;
            $quotation->save();
            $this->snapshotRevision($quotation, 'Quotation created');
        }

        if ((int) $quotation->revision !== 1) {
            $quotation->revision = 1;
            $quotation->save();
        }
    }

    /**
     * Update snapshot revisi terkini tanpa menaikkan nomor R
     * (edit isi saat status masih Draft setelah R1/R2/…).
     */
    protected function refreshLatestRevisionSnapshot(Quotation $quotation): void
    {
        $quotation->load('items');
        $template = $this->resolveTemplate($quotation);
        $rendered = $this->service->render($quotation, $template->body_html, $template);
        $payload = $this->revisionSnapshotPayload($quotation);

        $latest = $quotation->revisions()->orderByDesc('id')->first();

        if ($latest) {
            $note = (int) $quotation->document_revision > 0
                ? 'Revisi dokumen R'.$quotation->document_revision
                : (string) $latest->note;

            $latest->update([
                'revision' => max(1, (int) $quotation->revision),
                'snapshot' => $payload,
                'rendered_html' => $rendered,
                'note' => $note,
            ]);

            return;
        }

        $this->snapshotRevision(
            $quotation,
            (int) $quotation->document_revision > 0
                ? 'Revisi dokumen R'.$quotation->document_revision
                : 'Quotation created'
        );
    }

    protected function revisionSnapshotPayload(Quotation $quotation): array
    {
        return [
            'number' => $quotation->number,
            'base_number' => $quotation->base_number,
            'document_revision' => $quotation->document_revision,
            'customer_name' => $quotation->customer_name,
            'company_name' => $quotation->company_name,
            'total' => $quotation->total,
            'items' => $quotation->items->map->only(['name', 'quantity', 'unit_price', 'total'])->all(),
        ];
    }

    protected function templateHtml(Quotation $quotation): string
    {
        return $this->resolveTemplate($quotation)->body_html;
    }

    protected function resolveTemplate(Quotation $quotation): QuotationTemplate
    {
        if (! $quotation->relationLoaded('opportunity')) {
            $quotation->load('opportunity');
        }

        $company = $quotation->opportunity?->company;
        $template = $quotation->template;

        // Jika template tidak cocok dengan company opportunity, cari default kategori tersebut.
        if (! $template || ($company && $template->category && $template->category !== $company)) {
            $template = QuotationTemplate::query()
                ->where('is_active', true)
                ->forCompany($company)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->first();
        }

        if (! $template) {
            $template = QuotationTemplate::where('is_default', true)->where('is_active', true)->first()
                ?? QuotationTemplate::where('is_active', true)->first();
        }

        if (! $template) {
            $template = new QuotationTemplate([
                'code' => 'agc-indo',
                'category' => 'Alpha Graha Computindo',
                'body_html' => '<p>{{ customer_name }}</p>{{ items_table_idr }}<p>Total: {{ total_price }}</p>',
            ]);
        }

        return $template;
    }

    protected function templateIdForCompany(?string $company): ?int
    {
        if (! $company) {
            return null;
        }

        return QuotationTemplate::query()
            ->where('is_active', true)
            ->forCompany($company)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->value('id');
    }

    protected function ensureCreatorSignature(Quotation $quotation): void
    {
        $creator = $quotation->creator;

        if ($creator && $creator->isSales() && ! $creator->hasDigitalSignature()) {
            $name = $creator->display_name ?: $creator->user_name;
            $message = 'Sales '.$name.' belum mengunggah tanda tangan digital di Profile. Minta sales mengunggah TTD sebelum preview/PDF.';

            // Admin yang membantu: jangan redirect ke profile admin sendiri.
            if ($this->isAdmin() && $creator->id !== auth()->id()) {
                throw new HttpResponseException(
                    redirect()->back()->with('error', $message)
                );
            }

            throw new HttpResponseException(
                redirect()
                    ->route('profile.edit')
                    ->with('error', $message)
            );
        }
    }

    protected function formData(?string $company = null): array
    {
        $accounts = $this->scopeAssigned(Account::query())
            ->orderBy('name')
            ->get(['id', 'name', 'billing_address_street', 'billing_address_city', 'billing_address_state', 'billing_address_country', 'billing_address_postal_code']);

        $templates = QuotationTemplate::query()
            ->where('is_active', true)
            ->forCompany($company)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return [
            'accounts' => $accounts,
            'templates' => $templates,
            'statuses' => Quotation::STATUSES,
            'templateCompany' => $company,
        ];
    }

    protected function authorizeAccess(Quotation $quotation): void
    {
        if (! $this->isAdmin() && $quotation->created_by !== auth()->id()) {
            abort(403, 'You do not have access to this quotation.');
        }
    }

    protected function paymentLevelBlockMessage(?Account $account): ?string
    {
        if (! $account) {
            return null;
        }

        if ($account->isPaymentSuspended()) {
            return 'Customer "'.$account->name.'" berstatus Suspend — tidak dapat membuat Quotation.';
        }

        return null;
    }

    protected function opportunityMarginBlockMessage(Opportunity $opportunity): ?string
    {
        if (! $opportunity->isMarginLocked()) {
            return null;
        }

        if ($opportunity->crm_margin_status === Opportunity::MARGIN_PENDING) {
            return 'Opportunity menunggu approval margin — tidak dapat membuat Quotation. Hubungi Superadmin.';
        }

        if ($opportunity->crm_margin_status === Opportunity::MARGIN_REJECTED) {
            return 'Margin opportunity ditolak — tidak dapat membuat Quotation. Perbarui opportunity atau minta approval ulang.';
        }

        return 'Opportunity tidak dapat membuat Quotation: '.$opportunity->marginStatusLabel().'.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function opportunityMarginBlockForPayload(array $data): ?string
    {
        if (empty($data['opportunity_id'])) {
            return null;
        }

        $query = Opportunity::query();
        if (auth()->user()?->isSales()) {
            $query->where('assigned_user_id', auth()->id());
        }

        $opportunity = $query->find($data['opportunity_id']);
        if (! $opportunity) {
            return null;
        }

        return $this->opportunityMarginBlockMessage($opportunity);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function paymentLevelBlockForPayload(array $data, ?Quotation $existing = null): ?string
    {
        $account = null;

        if (! empty($data['account_id'])) {
            $account = Account::query()->find($data['account_id']);
        } elseif (! empty($data['opportunity_id'])) {
            $account = Opportunity::query()->with('account')->find($data['opportunity_id'])?->account;
        } elseif ($existing) {
            $existing->loadMissing(['account', 'opportunity.account']);
            $account = $existing->account ?: $existing->opportunity?->account;
        }

        return $this->paymentLevelBlockMessage($account);
    }

    /**
     * Evaluasi margin opportunity vs threshold level customer.
     * Return true jika baru masuk status pending (untuk trigger notifikasi).
     */
    protected function applyMarginApprovalState(Quotation $quotation, bool $isNew): bool
    {
        $quotation->loadMissing(['account', 'opportunity.account']);
        $account = $quotation->account ?: $quotation->opportunity?->account;
        $opportunity = $quotation->opportunity;

        if (! $account || ! $opportunity) {
            $quotation->crm_margin_status = null;
            $quotation->crm_margin_percent = null;
            $quotation->crm_margin_threshold = null;
            $quotation->crm_margin_nominal = null;
            $quotation->crm_margin_nominal_threshold = null;

            return false;
        }

        $pctThreshold = $account->minMarginPercent();
        $marginPct = $opportunity->overallMarginPercent();
        $marginNominal = $opportunity->totalProductsMargin();
        $nominalThreshold = $opportunity->requiredMarginNominalThreshold();

        $quotation->crm_margin_percent = $marginPct;
        $quotation->crm_margin_threshold = $pctThreshold;
        $quotation->crm_margin_nominal = $marginNominal;
        $quotation->crm_margin_nominal_threshold = $nominalThreshold;

        // Suspend: gate pembayaran sudah di-block sebelumnya.
        $belowPct = $pctThreshold !== null && ($marginPct === null || $marginPct < $pctThreshold);
        $belowNominal = $nominalThreshold > 0 && $marginNominal < $nominalThreshold;
        $below = $belowPct || $belowNominal;

        $wasPending = $quotation->crm_margin_status === Quotation::MARGIN_PENDING;
        $wasApproved = $quotation->crm_margin_status === Quotation::MARGIN_APPROVED;

        if (! $below) {
            $quotation->crm_margin_status = null;
            $quotation->crm_margin_requested_at = null;
            $quotation->crm_margin_reviewed_by = null;
            $quotation->crm_margin_reviewed_at = null;
            $quotation->crm_margin_note = null;

            return false;
        }

        // Sudah approved: biarkan tetap approved (kecuali create baru).
        if (! $isNew && $wasApproved) {
            return false;
        }

        $quotation->crm_margin_status = Quotation::MARGIN_PENDING;
        if (! $quotation->crm_margin_requested_at || ! $wasPending) {
            $quotation->crm_margin_requested_at = now();
        }
        $quotation->crm_margin_reviewed_by = null;
        $quotation->crm_margin_reviewed_at = null;

        return ! $wasPending;
    }

    protected function ensureMarginUnlocked(Quotation $quotation): void
    {
        if (! $quotation->isMarginLocked()) {
            return;
        }

        if (auth()->user()?->canApproveMargin()) {
            return;
        }

        $message = 'Quotation terkunci: '.$quotation->marginStatusLabel().'. Hubungi Superadmin untuk approval.';

        throw new HttpResponseException(
            redirect()->route('quotations.show', $quotation)->with('error', $message)
        );
    }
}
