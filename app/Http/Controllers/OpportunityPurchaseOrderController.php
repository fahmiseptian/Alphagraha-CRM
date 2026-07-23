<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Espo\Opportunity;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderReportService;
use App\Services\PurchaseOrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OpportunityPurchaseOrderController extends Controller
{
    use ScopesToUser;

    public function __construct(
        protected PurchaseOrderService $purchaseOrders,
        protected PurchaseOrderReportService $reports
    ) {}

    public function preview(Opportunity $opportunity)
    {
        $this->authorizeManage($opportunity);

        $report = $this->reports->build($opportunity);

        return view('opportunities.purchase-orders.preview', compact('opportunity', 'report'));
    }

    public function pdf(Opportunity $opportunity)
    {
        $this->authorizeManage($opportunity);

        $report = $this->reports->build($opportunity);
        $filename = 'Laporan-PO-'.str_replace(['/', '\\', ' '], '-', $opportunity->name ?: $opportunity->id).'.pdf';

        return Pdf::loadView('opportunities.purchase-orders.pdf', compact('opportunity', 'report'))
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    public function store(Request $request, Opportunity $opportunity)
    {
        $this->authorizeManage($opportunity);

        $data = $this->validatePayload($request);

        $this->purchaseOrders->create(
            $opportunity,
            $data['number'],
            $data['items'],
            $data['payment_term'],
            auth()->id()
        );

        return back()->with('success', 'Purchase Order berhasil ditambahkan.');
    }

    public function update(Request $request, Opportunity $opportunity, PurchaseOrder $purchaseOrder)
    {
        $this->authorizeManage($opportunity);
        $this->ensureBelongsToOpportunity($opportunity, $purchaseOrder);

        $data = $this->validatePayload($request, $purchaseOrder);

        $this->purchaseOrders->update(
            $purchaseOrder,
            $data['number'],
            $data['items'],
            $data['payment_term']
        );

        return back()->with('success', 'Purchase Order berhasil diperbarui.');
    }

    public function destroy(Opportunity $opportunity, PurchaseOrder $purchaseOrder)
    {
        $this->authorizeManage($opportunity);
        $this->ensureBelongsToOpportunity($opportunity, $purchaseOrder);

        $purchaseOrder->delete();

        return back()->with('success', 'Purchase Order berhasil dihapus.');
    }

    /**
     * Simpan ongkir opportunity (1 opp = 1 ongkir), dari area PO.
     */
    public function updateShipping(Request $request, Opportunity $opportunity)
    {
        $this->authorizeManage($opportunity);

        $data = $request->validate([
            'crm_shipping_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $raw = $data['crm_shipping_cost'] ?? null;
        $opportunity->crm_shipping_cost = ($raw === null || $raw === '')
            ? null
            : round((float) $raw, 2);
        $opportunity->save();

        return back()->with('success', 'Ongkir berhasil disimpan.');
    }

    /**
     * @return array{number:string,payment_term:string,items:list<array{product_name:string,quantity:float|int|string,description?:?string,note?:?string,unit_price:float|int|string}>}
     */
    protected function validatePayload(Request $request, ?PurchaseOrder $existing = null): array
    {
        $data = $request->validate([
            'number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('crm_purchase_orders', 'number')->ignore($existing?->id),
            ],
            'payment_term' => ['required', Rule::in([PurchaseOrder::PAYMENT_TOP, PurchaseOrder::PAYMENT_CASH])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.description' => ['nullable', 'string', 'max:5000'],
            'items.*.note' => ['nullable', 'string', 'max:5000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $data['number'] = trim($data['number']);

        $items = [];
        foreach ($data['items'] as $item) {
            $name = trim((string) $item['product_name']);
            if ($name === '') {
                continue;
            }
            $items[] = [
                'product_name' => $name,
                'quantity' => $item['quantity'],
                'description' => $item['description'] ?? null,
                'note' => $item['note'] ?? null,
                'unit_price' => $item['unit_price'],
            ];
        }

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Minimal satu item produk wajib diisi.',
            ]);
        }

        $data['items'] = $items;

        return $data;
    }

    protected function authorizeManage(Opportunity $opportunity): void
    {
        $user = auth()->user();

        if (! $user || ! $user->canManagePurchaseOrders()) {
            abort(403, 'Hanya Purchasing / Superadmin yang dapat mengelola Purchase Order.');
        }

        if ($opportunity->stage !== Opportunity::WON_STAGE) {
            abort(403, 'Purchase Order hanya untuk opportunity Closed Won.');
        }

        $this->authorizeAccess($opportunity);
    }

    protected function authorizeAccess(Opportunity $opportunity): void
    {
        // Purchasing/superadmin melihat semua via canViewAllOpportunities;
        // tetap hormati scoping sales bila role lain ikut (harusnya tidak).
        if ($this->isAdmin() || auth()->user()?->canViewAllOpportunities()) {
            return;
        }

        if ($opportunity->assigned_user_id !== auth()->id()) {
            abort(403, 'You do not have access to this opportunity.');
        }
    }

    protected function ensureBelongsToOpportunity(Opportunity $opportunity, PurchaseOrder $purchaseOrder): void
    {
        if ($purchaseOrder->opportunity_id !== $opportunity->id) {
            abort(404);
        }
    }
}
