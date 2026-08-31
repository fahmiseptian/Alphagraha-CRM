<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Models\VendorStock;
use App\Services\VendorStockService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VendorStockController extends Controller
{
    public function __construct(
        protected VendorStockService $stocks
    ) {}

    public function index(Request $request)
    {
        $this->authorizeManage();

        $search = trim((string) $request->query('q', ''));
        $vendorId = (int) $request->query('vendor_id', 0);
        $status = (string) $request->query('status', '');

        $query = VendorStock::query()
            ->with(['vendor:id,name', 'creator'])
            ->ordered();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('product_name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%')
                    ->orWhere('note', 'like', '%'.$search.'%')
                    ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', '%'.$search.'%'));
            });
        }

        if ($vendorId > 0) {
            $query->where('vendor_id', $vendorId);
        }

        if (in_array($status, [VendorStock::STATUS_READY, VendorStock::STATUS_INDENT], true)) {
            $query->where('status', $status);
        }

        $stocks = $query->orderByDesc('updated_at')->paginate(25)->withQueryString();
        $vendors = Vendor::query()->ordered()->get(['id', 'name']);

        return view('vendor-stocks.index', compact('stocks', 'vendors', 'search', 'vendorId', 'status'));
    }

    public function create()
    {
        $this->authorizeManage();

        return view('vendor-stocks.create', [
            'stock' => new VendorStock([
                'status' => VendorStock::STATUS_READY,
                'is_active' => true,
            ]),
            'vendors' => Vendor::query()->active()->ordered()->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeManage();

        $data = $this->validated($request);

        $this->stocks->upsert(
            (int) $data['vendor_id'],
            $data['product_name'],
            $data['status'],
            (float) $data['price'],
            $data['sku'] ?? null,
            $data['note'] ?? null,
            auth()->id()
        );

        $stock = VendorStock::query()
            ->where('vendor_id', $data['vendor_id'])
            ->where('product_key', VendorStock::makeProductKey($data['product_name']))
            ->first();

        if ($stock && array_key_exists('is_active', $data)) {
            $stock->update(['is_active' => (bool) $data['is_active']]);
        }

        return redirect()
            ->route('vendor-stocks.index')
            ->with('success', 'Ketersediaan barang vendor berhasil disimpan.');
    }

    public function edit(VendorStock $vendorStock)
    {
        $this->authorizeManage();

        return view('vendor-stocks.edit', [
            'stock' => $vendorStock,
            'vendors' => Vendor::query()->ordered()->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, VendorStock $vendorStock)
    {
        $this->authorizeManage();

        $data = $this->validated($request, $vendorStock);

        $duplicate = VendorStock::query()
            ->where('vendor_id', $data['vendor_id'])
            ->where('product_key', VendorStock::makeProductKey($data['product_name']))
            ->whereKeyNot($vendorStock->id)
            ->exists();

        if ($duplicate) {
            return back()
                ->withInput()
                ->withErrors(['product_name' => 'Produk ini sudah tercatat untuk vendor tersebut.']);
        }

        $vendorStock->update([
            'vendor_id' => $data['vendor_id'],
            'product_name' => $data['product_name'],
            'sku' => $data['sku'] ?? null,
            'status' => $data['status'],
            'price' => $data['price'],
            'note' => $data['note'] ?? null,
            'is_active' => (bool) $data['is_active'],
        ]);

        return redirect()
            ->route('vendor-stocks.index')
            ->with('success', 'Ketersediaan barang vendor berhasil diperbarui.');
    }

    public function destroy(VendorStock $vendorStock)
    {
        $this->authorizeManage();

        $vendorStock->delete();

        return redirect()
            ->route('vendor-stocks.index')
            ->with('success', 'Ketersediaan barang vendor berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?VendorStock $existing = null): array
    {
        $data = $request->validate([
            'vendor_id' => ['required', 'integer', 'exists:crm_vendors,id'],
            'product_name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in([VendorStock::STATUS_READY, VendorStock::STATUS_INDENT])],
            'price' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['product_name'] = trim($data['product_name']);
        $data['is_active'] = $request->boolean('is_active');

        if ($existing === null) {
            $exists = VendorStock::query()
                ->where('vendor_id', $data['vendor_id'])
                ->where('product_key', VendorStock::makeProductKey($data['product_name']))
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'product_name' => 'Produk ini sudah tercatat untuk vendor tersebut. Buka data yang ada untuk mengubahnya.',
                ]);
            }
        }

        return $data;
    }

    protected function authorizeManage(): void
    {
        if (! auth()->user()?->canManageVendorStocks()) {
            abort(403, 'Hanya Purchasing / Superadmin yang dapat mengelola ketersediaan barang vendor.');
        }
    }
}
