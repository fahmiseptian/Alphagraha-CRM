<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Espo\Account;
use App\Models\Quotation;
use App\Models\QuotationTemplate;
use App\Services\QuotationService;
use Barryvdh\DomPDF\Facade\Pdf;
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
        $quotation = new Quotation([
            'quotation_date' => now()->toDateString(),
            'valid_until' => now()->addDays(14)->toDateString(),
            'currency' => config('crm.default_currency', 'IDR'),
            'tax_percent' => 11,
            'status' => 'draft',
        ]);

        // Prefill dari pelanggan bila datang dari halaman detail pelanggan.
        if ($accountId = $request->get('account_id')) {
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

        return view('quotations.create', $this->formData() + ['quotation' => $quotation]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $quotation = DB::transaction(function () use ($data) {
            $quotation = new Quotation($data);
            $quotation->number = $this->service->generateNumber();
            $quotation->created_by = auth()->id();
            $quotation->revision = 1;
            $quotation->save();

            $this->syncItems($quotation, $data['items']);
            $quotation->load('items');
            $quotation->recalculateTotals();
            $quotation->save();

            $this->snapshotRevision($quotation, 'Penawaran dibuat');

            return $quotation;
        });

        return redirect()->route('quotations.show', $quotation)
            ->with('success', 'Penawaran ' . $quotation->number . ' berhasil dibuat.');
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
        $data = $this->validateData($request);

        DB::transaction(function () use ($quotation, $data) {
            $quotation->fill($data);
            $quotation->save();

            $this->syncItems($quotation, $data['items']);
            $quotation->load('items');
            $quotation->recalculateTotals();
            $quotation->increment('revision');
            $quotation->save();

            $this->snapshotRevision($quotation, 'Penawaran direvisi');
        });

        return redirect()->route('quotations.show', $quotation)
            ->with('success', 'Penawaran berhasil diperbarui (revisi ' . $quotation->revision . ').');
    }

    public function destroy(Quotation $quotation)
    {
        $this->authorizeAccess($quotation);
        $quotation->delete();

        return redirect()->route('quotations.index')->with('success', 'Penawaran dihapus.');
    }

    public function preview(Quotation $quotation)
    {
        $this->authorizeAccess($quotation);
        $html = $this->service->render($quotation, $this->templateHtml($quotation));

        return view('quotations.preview', compact('quotation', 'html'));
    }

    public function pdf(Quotation $quotation)
    {
        $this->authorizeAccess($quotation);
        $content = $this->service->render($quotation, $this->templateHtml($quotation));

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

        return back()->with('success', 'Status penawaran diperbarui menjadi "' . $quotation->statusLabel() . '".');
    }

    public function duplicate(Quotation $quotation)
    {
        $this->authorizeAccess($quotation);
        $quotation->load('items');

        $copy = DB::transaction(function () use ($quotation) {
            $copy = $quotation->replicate(['number', 'sent_at', 'revision']);
            $copy->number = $this->service->generateNumber();
            $copy->status = 'draft';
            $copy->revision = 1;
            $copy->created_by = auth()->id();
            $copy->quotation_date = now()->toDateString();
            $copy->save();

            foreach ($quotation->items as $item) {
                $newItem = $item->replicate(['quotation_id']);
                $newItem->quotation_id = $copy->id;
                $newItem->save();
            }

            $this->snapshotRevision($copy, 'Disalin dari ' . $quotation->number);

            return $copy;
        });

        return redirect()->route('quotations.edit', $copy)
            ->with('success', 'Penawaran disalin ke ' . $copy->number . '.');
    }

    // ---------------------------------------------------------------------

    protected function validateData(Request $request): array
    {
        $data = $request->validate([
            'account_id' => ['nullable', 'string'],
            'customer_name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_address' => ['nullable', 'string'],
            'quotation_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date'],
            'currency' => ['required', 'string', 'max:6'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'template_id' => ['nullable', 'exists:crm_quotation_templates,id'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'status' => ['required', Rule::in(array_keys(Quotation::STATUSES))],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $data['discount'] = $data['discount'] ?? 0;
        $data['tax_percent'] = $data['tax_percent'] ?? 0;

        return $data;
    }

    protected function syncItems(Quotation $quotation, array $items): void
    {
        $quotation->items()->delete();

        foreach (array_values($items) as $index => $item) {
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];

            $quotation->items()->create([
                'name' => $item['name'],
                'description' => $item['description'] ?? null,
                'quantity' => $quantity,
                'unit' => $item['unit'] ?? null,
                'unit_price' => $unitPrice,
                'total' => round($quantity * $unitPrice, 2),
                'sort_order' => $index,
            ]);
        }
    }

    protected function snapshotRevision(Quotation $quotation, string $note): void
    {
        $quotation->load('items');
        $rendered = $this->service->render($quotation, $this->templateHtml($quotation));

        $quotation->revisions()->create([
            'revision' => $quotation->revision,
            'snapshot' => [
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
        $template = $quotation->template
            ?? QuotationTemplate::where('is_default', true)->where('is_active', true)->first()
            ?? QuotationTemplate::where('is_active', true)->first();

        return $template?->body_html ?? '<p>{{ customer_name }}</p>{{ items_table }}<p>Total: {{ total_price }}</p>';
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
            abort(403, 'Anda tidak memiliki akses ke penawaran ini.');
        }
    }
}
