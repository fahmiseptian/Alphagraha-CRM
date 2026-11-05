<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrderTemplate;
use App\Services\PurchaseOrderDocumentService;
use Illuminate\Http\Request;

class PurchaseOrderTemplateController extends Controller
{
    public function index()
    {
        $templates = PurchaseOrderTemplate::with('creator')->latest()->get();

        return view('admin.po-templates.index', compact('templates'));
    }

    public function create()
    {
        $template = new PurchaseOrderTemplate([
            'is_active' => true,
            'body_html' => app(PurchaseOrderDocumentService::class)->defaultBodyHtml(),
        ]);

        return view('admin.po-templates.create', compact('template'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['created_by'] = auth()->id();

        $template = PurchaseOrderTemplate::create($data);
        $this->ensureSingleDefault($template);

        return redirect()->route('po-templates.index')->with('success', 'Template PO berhasil dibuat.');
    }

    public function edit(PurchaseOrderTemplate $poTemplate)
    {
        return view('admin.po-templates.edit', ['template' => $poTemplate]);
    }

    public function update(Request $request, PurchaseOrderTemplate $poTemplate)
    {
        $poTemplate->update($this->validateData($request));
        $this->ensureSingleDefault($poTemplate);

        return redirect()->route('po-templates.index')->with('success', 'Template PO berhasil disimpan.');
    }

    public function destroy(PurchaseOrderTemplate $poTemplate)
    {
        $poTemplate->delete();

        return back()->with('success', 'Template PO dihapus.');
    }

    protected function validateData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:'.implode(',', PurchaseOrderTemplate::CATEGORIES)],
            'description' => ['nullable', 'string'],
            'body_html' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ], [
            'category.required' => 'Pilih kategori perusahaan untuk template ini.',
            'category.in' => 'Kategori tidak valid.',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['is_default'] = $request->boolean('is_default');

        return $data;
    }

    protected function ensureSingleDefault(PurchaseOrderTemplate $template): void
    {
        if ($template->is_default) {
            PurchaseOrderTemplate::query()
                ->where('id', '<>', $template->id)
                ->where('category', $template->category)
                ->update(['is_default' => false]);
        }
    }
}
