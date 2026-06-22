<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuotationTemplate;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function index()
    {
        $templates = QuotationTemplate::with('creator')->latest()->get();

        return view('admin.templates.index', compact('templates'));
    }

    public function create()
    {
        return view('admin.templates.create', ['template' => new QuotationTemplate(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['created_by'] = auth()->id();

        $template = QuotationTemplate::create($data);
        $this->ensureSingleDefault($template);

        return redirect()->route('templates.index')->with('success', 'Template berhasil dibuat.');
    }

    public function edit(QuotationTemplate $template)
    {
        return view('admin.templates.edit', compact('template'));
    }

    public function update(Request $request, QuotationTemplate $template)
    {
        $template->update($this->validateData($request));
        $this->ensureSingleDefault($template);

        return redirect()->route('templates.index')->with('success', 'Template berhasil diperbarui.');
    }

    public function destroy(QuotationTemplate $template)
    {
        $template->delete();

        return back()->with('success', 'Template dihapus.');
    }

    public function show(QuotationTemplate $template)
    {
        return redirect()->route('templates.edit', $template);
    }

    protected function validateData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'body_html' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['is_default'] = $request->boolean('is_default');

        return $data;
    }

    /**
     * Pastikan hanya ada satu template default.
     */
    protected function ensureSingleDefault(QuotationTemplate $template): void
    {
        if ($template->is_default) {
            QuotationTemplate::where('id', '<>', $template->id)->update(['is_default' => false]);
        }
    }
}
