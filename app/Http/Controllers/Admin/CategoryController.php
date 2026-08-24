<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\CatalogExcel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::query()->ordered()->orderByDesc('id')->get();

        return view('admin.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.categories.create', [
            'category' => new Category(['is_active' => true]),
        ]);
    }

    public function store(Request $request)
    {
        Category::create($this->validated($request));

        return redirect()
            ->route('categories.index')
            ->with('success', 'Category berhasil ditambahkan.');
    }

    public function edit(Category $category)
    {
        return view('admin.categories.edit', compact('category'));
    }

    public function update(Request $request, Category $category)
    {
        $category->update($this->validated($request, $category));

        return redirect()
            ->route('categories.index')
            ->with('success', 'Category berhasil diperbarui.');
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return redirect()
            ->route('categories.index')
            ->with('success', 'Category berhasil dihapus.');
    }

    public function export()
    {
        $categories = Category::query()->ordered()->get();

        return CatalogExcel::export('categories.xlsx', $categories, 'Categories');
    }

    public function template()
    {
        return CatalogExcel::template('template-categories.xlsx', 'Contoh Category', 'Categories');
    }

    public function import(Request $request)
    {
        $file = $this->validatedImportFile($request);

        try {
            $result = CatalogExcel::import($file, Category::class);
        } catch (\Throwable $e) {
            return redirect()
                ->route('categories.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('categories.index')
            ->with('success', CatalogExcel::resultMessage($result, 'category'));
    }

    /**
     * @return array{name: string, is_active: bool, sort_order: int}
     */
    protected function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('crm_categories', 'name')->ignore($category?->id),
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
