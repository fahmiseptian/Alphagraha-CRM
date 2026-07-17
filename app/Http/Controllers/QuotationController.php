<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Espo\Account;
use App\Models\Espo\Opportunity;
use App\Models\Quotation;
use App\Models\QuotationRevision;
use App\Models\QuotationTemplate;
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

    public function __construct(protected QuotationService $service)
    {
    }

    public function index(Request $request)
    {
        if (! auth()->user()?->canCreateQuotation()) {
            abort(403, 'Anda tidak memiliki akses ke Quotations.');
        }

        $search = trim((string) $request->get('q'));
        $status = $request->get('status');

        $query = Quotation::with('creator');

        if (! $this->isAdmin()) {
            $query->where('created_by', auth()->id());
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%");
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

        // Prefill dari Opportunity (1 opportunity : 1 penawaran).
        if ($opportunityId) {
            $opportunity = Opportunity::query()->with(['account', 'quotation']);
            if (auth()->user()?->isSales()) {
                $opportunity->where('assigned_user_id', auth()->id());
            }
            $opportunity = $opportunity->find($opportunityId);

            if ($opportunity) {
                if ($opportunity->quotation) {
                    return redirect()->route('quotations.show', $opportunity->quotation)
                        ->with('success', 'This opportunity already has a quotation.');
                }

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
                $products = $opportunity->products;
                if ($products->isNotEmpty()) {
                    $seedItems = $products->map(fn ($p) => [
                        'name' => $p['name'],
                        'description' => '',
                        'quantity' => $p['quantity'] ?: 1,
                        'unit' => '',
                        'unit_price' => $p['sell_include'],
                        'tax_category' => $p['tax_category'],
                        'item_kind' => $p['item_kind'],
                        'sell_exclude' => $p['sell_exclude'],
                        'cost_exclude' => $p['cost_exclude'],
                        'vendor' => $p['vendor'],
                    ])->all();
                } elseif ((float) $opportunity->amount > 0) {
                    $seedItems = [[
                        'name' => $opportunity->name,
                        'description' => '',
                        'quantity' => 1,
                        'unit' => '',
                        'unit_price' => (float) $opportunity->amount,
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
                $quotation->account_id = $account->id;
                $quotation->customer_name = $account->name;
                $quotation->company_name = $account->name;
                $quotation->customer_email = $account->email;
                $quotation->customer_phone = $account->phone;
                $quotation->customer_address = $account->billing_address;
            }
        }

        return view('quotations.create', $this->formData() + [
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
                if (($data['status'] ?? '') === 'sent') {
                    $quotation->sent_at = now();
                }
                $quotation->save();

                $this->syncItems($quotation, $data['items']);
                $quotation->load('items');
                $quotation->recalculateTotals();
                $quotation->save();

                $this->snapshotRevision($quotation, 'Quotation created');

                return $quotation;
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('quotations.show', $quotation)
            ->with('success', 'Quotation ' . $quotation->number . ' created successfully.');
    }

    public function show(Quotation $quotation)
    {
        $this->authorizeAccess($quotation);
        $quotation->load(['items', 'creator', 'template', 'revisions.creator', 'account']);

        return view('quotations.show', compact('quotation'));
    }

    public function edit(Quotation $quotation)
    {
        $this->authorizeAccess($quotation);
        $quotation->load('items');

        return view('quotations.edit', $this->formData() + ['quotation' => $quotation]);
    }

    public function update(Request $request, Quotation $quotation)
    {
        $this->authorizeAccess($quotation);
        $data = $this->validateData($request, $quotation);

        $result = DB::transaction(function () use ($quotation, $data) {
            $before = $this->contentFingerprint($quotation);
            $wasSent = $quotation->hasBeenSent();

            // Nomor tidak boleh diubah manual; pertahankan yang ada.
            unset($data['number']);

            $quotation->fill($data);
            if (($data['status'] ?? '') === 'sent' && ! $quotation->sent_at) {
                $quotation->sent_at = now();
            }
            $quotation->save();

            $this->syncItems($quotation, $data['items']);
            $quotation->load('items');
            $quotation->recalculateTotals();
            $quotation->save();

            $changed = $before !== $this->contentFingerprint($quotation);

            if (! $changed) {
                return ['changed' => false, 'document_revision' => (int) $quotation->document_revision];
            }

            if ($wasSent) {
                $quotation->document_revision = (int) $quotation->document_revision + 1;
                $base = $quotation->base_number ?: $this->service->stripDocumentRevision($quotation->number);
                $quotation->base_number = $base;
                $quotation->number = $this->service->withDocumentRevision($base, (int) $quotation->document_revision);
            }

            $quotation->revision = (int) $quotation->revision + 1;
            $quotation->save();

            $note = $wasSent
                ? 'Revisi dokumen R'.$quotation->document_revision
                : 'Quotation updated';
            $this->snapshotRevision($quotation, $note);

            return ['changed' => true, 'document_revision' => (int) $quotation->document_revision];
        });

        if (! $result['changed']) {
            return redirect()->route('quotations.show', $quotation)
                ->with('success', 'Tidak ada perubahan pada quotation.');
        }

        $msg = $result['document_revision'] > 0
            ? 'Quotation diperbarui menjadi '.$quotation->fresh()->number.'.'
            : 'Quotation updated successfully.';

        return redirect()->route('quotations.show', $quotation)->with('success', $msg);
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
        $this->ensureCreatorSignature($quotation);

        $template = $this->resolveTemplate($quotation);
        $html = $this->service->render($quotation, $template->body_html, $template);

        return view('quotations.preview', compact('quotation', 'html'));
    }

    public function pdf(Quotation $quotation)
    {
        $this->authorizeAccess($quotation);
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

        $quotation->status = $data['status'];
        if ($data['status'] === 'sent' && ! $quotation->sent_at) {
            $quotation->sent_at = now();
        }
        $quotation->save();

        return back()->with('success', 'Quotation status updated to "' . $quotation->statusLabel() . '".');
    }

    public function previewRevision(Quotation $quotation, QuotationRevision $revision)
    {
        $this->authorizeAccess($quotation);

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
                'quantity' => (float) $i->quantity,
                'unit' => (string) $i->unit,
                'unit_price' => (float) $i->unit_price,
            ])->values()->all(),
        ];

        return md5(json_encode($payload));
    }

    protected function syncItems(Quotation $quotation, array $items): void
    {
        $quotation->items()->delete();

        foreach (array_values($items) as $index => $item) {
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $sellExclude = isset($item['sell_exclude']) && $item['sell_exclude'] !== '' && $item['sell_exclude'] !== null
                ? (float) $item['sell_exclude']
                : OpportunityProductPricing::excludeFromInclude($unitPrice);

            $enriched = OpportunityProductPricing::enrichRow([
                'name' => $item['name'],
                'quantity' => $quantity,
                'vendor' => $item['vendor'] ?? '',
                'tax_category' => $item['tax_category'] ?? OpportunityProductPricing::TAX_NON_WAPU,
                'item_kind' => $item['item_kind'] ?? OpportunityProductPricing::KIND_BARANG,
                'sell_exclude' => $sellExclude,
                'cost_exclude' => (float) ($item['cost_exclude'] ?? 0),
            ]);

            $quotation->items()->create([
                'name' => $item['name'],
                'description' => $item['description'] ?? null,
                'quantity' => $quantity,
                'unit' => $item['unit'] ?? null,
                'unit_price' => $enriched['sell_include'],
                'total' => round($quantity * $enriched['sell_include'], 2),
                'tax_category' => $enriched['tax_category'],
                'item_kind' => $enriched['item_kind'],
                'sell_exclude' => $enriched['sell_exclude'],
                'cost_exclude' => $enriched['cost_exclude'],
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
            'snapshot' => [
                'number' => $quotation->number,
                'base_number' => $quotation->base_number,
                'document_revision' => $quotation->document_revision,
                'customer_name' => $quotation->customer_name,
                'company_name' => $quotation->company_name,
                'total' => $quotation->total,
                'items' => $quotation->items->map->only(['name', 'quantity', 'unit_price', 'total'])->all(),
            ],
            'rendered_html' => $rendered,
            'note' => $note,
            'created_by' => auth()->id(),
        ]);
    }

    protected function templateHtml(Quotation $quotation): string
    {
        return $this->resolveTemplate($quotation)->body_html;
    }

    protected function resolveTemplate(Quotation $quotation): QuotationTemplate
    {
        $template = $quotation->template
            ?? QuotationTemplate::where('is_default', true)->where('is_active', true)->first()
            ?? QuotationTemplate::where('is_active', true)->first();

        if (! $template) {
            $template = new QuotationTemplate([
                'code' => 'agc-indo',
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

        $map = config('crm.quotation_company_map', []);
        $key = $map[$company] ?? null;

        if (! $key) {
            return null;
        }

        $code = match ($key) {
            'agc' => 'agc-indo',
            'eps' => 'eps-indo',
            'psi' => 'psi-indo',
            default => null,
        };

        return $code
            ? QuotationTemplate::query()->where('code', $code)->where('is_active', true)->value('id')
            : null;
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

    protected function formData(): array
    {
        $accounts = $this->scopeAssigned(Account::query())
            ->orderBy('name')
            ->get(['id', 'name', 'billing_address_street', 'billing_address_city', 'billing_address_state', 'billing_address_country', 'billing_address_postal_code']);

        $templates = QuotationTemplate::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();

        return [
            'accounts' => $accounts,
            'templates' => $templates,
            'statuses' => Quotation::STATUSES,
        ];
    }

    protected function authorizeAccess(Quotation $quotation): void
    {
        if (! $this->isAdmin() && $quotation->created_by !== auth()->id()) {
            abort(403, 'You do not have access to this quotation.');
        }
    }
}
