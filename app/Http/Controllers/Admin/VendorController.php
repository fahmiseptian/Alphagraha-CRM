<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Vendor;
use App\Support\CustomerTop;
use App\Support\VendorExcel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VendorController extends Controller
{
    public function index()
    {
        $vendors = Vendor::query()->with(['pics', 'brands'])->ordered()->orderByDesc('id')->get();

        return view('admin.vendors.index', compact('vendors'));
    }

    public function create()
    {
        return view('admin.vendors.create', [
            'vendor' => new Vendor(['is_active' => true, 'top' => CustomerTop::DAYS_30]),
            'brandOptions' => $this->brandOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $pics = $this->validatedPics($request);
        $brandIds = $this->validatedBrandIds($request);

        DB::transaction(function () use ($data, $pics, $brandIds) {
            $vendor = Vendor::create($data);
            $vendor->syncPics($pics);
            $vendor->syncBrands($brandIds);
        });

        return redirect()
            ->route('vendors.index')
            ->with('success', 'Vendor berhasil ditambahkan.');
    }

    public function edit(Vendor $vendor)
    {
        $vendor->load(['pics', 'brands']);

        return view('admin.vendors.edit', [
            'vendor' => $vendor,
            'brandOptions' => $this->brandOptions(),
        ]);
    }

    public function update(Request $request, Vendor $vendor)
    {
        $data = $this->validated($request, $vendor);
        $pics = $this->validatedPics($request);
        $brandIds = $this->validatedBrandIds($request);

        DB::transaction(function () use ($vendor, $data, $pics, $brandIds) {
            $vendor->update($data);
            $vendor->syncPics($pics);
            $vendor->syncBrands($brandIds);
        });

        return redirect()
            ->route('vendors.index')
            ->with('success', 'Vendor berhasil diperbarui.');
    }

    public function destroy(Vendor $vendor)
    {
        $vendor->delete();

        return redirect()
            ->route('vendors.index')
            ->with('success', 'Vendor berhasil dihapus.');
    }

    public function export()
    {
        $vendors = Vendor::query()->with('pics')->ordered()->get();

        return VendorExcel::export($vendors);
    }

    public function template()
    {
        return VendorExcel::template();
    }

    public function import(Request $request)
    {
        $file = $this->validatedImportFile($request);

        try {
            $result = VendorExcel::import($file);
        } catch (\Throwable $e) {
            return redirect()
                ->route('vendors.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('vendors.index')
            ->with('success', VendorExcel::resultMessage($result));
    }

    /**
     * @return array{name: string, company_status: ?string, top: string, is_active: bool, sort_order: int}
     */
    protected function validated(Request $request, ?Vendor $vendor = null): array
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('crm_vendors', 'name')->ignore($vendor?->id),
            ],
            'company_status' => ['nullable', 'string', 'max:100'],
            'top' => ['required', 'string', Rule::in(CustomerTop::OPTIONS)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $status = trim((string) ($data['company_status'] ?? ''));

        return [
            'name' => trim($data['name']),
            'company_status' => $status !== '' ? $status : null,
            'top' => CustomerTop::isValid($data['top'] ?? null)
                ? (string) $data['top']
                : CustomerTop::DAYS_30,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    /**
     * @return list<array{id: int, name: string, job_role: string, phone: string, email: string}>
     */
    protected function validatedPics(Request $request): array
    {
        $data = $request->validate([
            'pics' => ['nullable', 'array'],
            'pics.*.id' => ['nullable', 'integer'],
            'pics.*.name' => ['nullable', 'string', 'max:255'],
            'pics.*.job_role' => ['nullable', 'string', 'max:150'],
            'pics.*.phone' => ['nullable', 'string', 'max:50'],
            'pics.*.email' => ['nullable', 'email', 'max:150'],
        ]);

        return array_values($data['pics'] ?? []);
    }

    /**
     * @return list<int>
     */
    protected function validatedBrandIds(Request $request): array
    {
        $data = $request->validate([
            'brand_ids' => ['nullable', 'array'],
            'brand_ids.*' => ['integer', 'exists:crm_brands,id'],
        ]);

        return array_values(array_map('intval', $data['brand_ids'] ?? []));
    }

    /**
     * @return \Illuminate\Support\Collection<int, Brand>
     */
    protected function brandOptions()
    {
        return Brand::query()->ordered()->get(['id', 'name', 'is_active']);
    }

    protected function validatedImportFile(Request $request): \Illuminate\Http\UploadedFile
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ], [
            'file.required' => 'Pilih file Excel (.xlsx) atau CSV.',
        ]);

        $file = $request->file('file');
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'csv'], true)) {
            throw ValidationException::withMessages([
                'file' => 'Format file harus .xlsx atau .csv.',
            ]);
        }

        return $file;
    }
}
