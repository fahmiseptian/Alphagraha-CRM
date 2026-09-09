<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesCatalog;
use App\Http\Controllers\Controller;
use App\Models\Industry;
use App\Support\CatalogExcel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IndustryController extends Controller
{
    use AuthorizesCatalog;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->authorizeIndustryManagement();

            return $next($request);
        });
    }

    public function index()
    {
        $industries = Industry::query()->ordered()->orderByDesc('id')->get();

        return view('admin.industries.index', compact('industries'));
    }

    public function create()
    {
        return view('admin.industries.create', [
            'industry' => new Industry(['is_active' => true]),
        ]);
    }

    public function store(Request $request)
    {
        Industry::create($this->validated($request));

        return redirect()
            ->route('industries.index')
            ->with('success', 'Industri berhasil ditambahkan.');
    }

    public function edit(Industry $industry)
    {
        return view('admin.industries.edit', compact('industry'));
    }

    public function update(Request $request, Industry $industry)
    {
        $oldName = $industry->name;
        $data = $this->validated($request, $industry);
        $industry->update($data);

        if ($oldName !== $data['name']) {
            $this->renameIndustryOnRecords($oldName, $data['name']);
        }

        return redirect()
            ->route('industries.index')
            ->with('success', 'Industri berhasil diperbarui.');
    }

    public function destroy(Industry $industry)
    {
        $name = $industry->name;
        $industry->delete();
        $this->clearIndustryOnRecords($name);

        return redirect()
            ->route('industries.index')
            ->with('success', 'Industri berhasil dihapus.');
    }

    public function export()
    {
        $industries = Industry::query()->ordered()->get();

        return CatalogExcel::export('industries.xlsx', $industries, 'Industries');
    }

    public function template()
    {
        return CatalogExcel::template('template-industries.xlsx', 'Contoh Industri', 'Industries');
    }

    public function import(Request $request)
    {
        $file = $this->validatedImportFile($request);

        try {
            $result = CatalogExcel::import($file, Industry::class);
        } catch (\Throwable $e) {
            return redirect()
                ->route('industries.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('industries.index')
            ->with('success', CatalogExcel::resultMessage($result, 'industri'));
    }

    /**
     * @return array{name: string, is_active: bool, sort_order: int}
     */
    protected function validated(Request $request, ?Industry $industry = null): array
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('crm_industries', 'name')->ignore($industry?->id),
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

    protected function renameIndustryOnRecords(string $oldName, string $newName): void
    {
        if ($oldName === $newName) {
            return;
        }

        DB::table('account')->where('industry', $oldName)->update(['industry' => $newName]);

        if (Schema::hasTable('lead')) {
            DB::table('lead')->where('industry', $oldName)->update(['industry' => $newName]);
        }
    }

    protected function clearIndustryOnRecords(string $name): void
    {
        DB::table('account')->where('industry', $name)->update(['industry' => null]);

        if (Schema::hasTable('lead')) {
            DB::table('lead')->where('industry', $name)->update(['industry' => null]);
        }
    }
}
