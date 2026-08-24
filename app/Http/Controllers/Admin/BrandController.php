<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Support\CatalogExcel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BrandController extends Controller
{
    public function index()
    {
        $brands = Brand::query()->ordered()->orderByDesc('id')->get();

        return view('admin.brands.index', compact('brands'));
    }

    public function create()
    {
        return view('admin.brands.create', [
            'brand' => new Brand(['is_active' => true]),
        ]);
    }

    public function store(Request $request)
    {
        Brand::create($this->validated($request));

        return redirect()
            ->route('brands.index')
            ->with('success', 'Brand berhasil ditambahkan.');
    }

    public function edit(Brand $brand)
    {
        return view('admin.brands.edit', compact('brand'));
    }

    public function update(Request $request, Brand $brand)
    {
        $brand->update($this->validated($request, $brand));

        return redirect()
            ->route('brands.index')
            ->with('success', 'Brand berhasil diperbarui.');
    }

    public function destroy(Brand $brand)
    {
        $brand->delete();

        return redirect()
            ->route('brands.index')
            ->with('success', 'Brand berhasil dihapus.');
    }

    public function export()
    {
        $brands = Brand::query()->ordered()->get();

        return CatalogExcel::export('brands.xlsx', $brands, 'Brands');
    }

    public function template()
    {
        return CatalogExcel::template('template-brands.xlsx', 'Contoh Brand', 'Brands');
    }

    public function import(Request $request)
    {
        $file = $this->validatedImportFile($request);

        try {
            $result = CatalogExcel::import($file, Brand::class);
        } catch (\Throwable $e) {
            return redirect()
                ->route('brands.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('brands.index')
            ->with('success', CatalogExcel::resultMessage($result, 'brand'));
    }

    /**
     * @return array{name: string, is_active: bool, sort_order: int}
     */
    protected function validated(Request $request, ?Brand $brand = null): array
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('crm_brands', 'name')->ignore($brand?->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        return [
            'name' => trim($data['name']),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
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
