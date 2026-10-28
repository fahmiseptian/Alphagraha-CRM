<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller
{
    public function index(): View
    {
        $this->authorizeManage();

        $categories = ExpenseCategory::query()->ordered()->get();
        $usage = $categories->mapWithKeys(fn (ExpenseCategory $c) => [$c->id => $c->usageCount()]);

        return view('expense-categories.index', compact('categories', 'usage'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $data = $this->validated($request);
        $data['sort_order'] = ((int) ExpenseCategory::query()->max('sort_order')) + 1;

        ExpenseCategory::query()->create($data + ['is_active' => true]);

        return redirect()->route('expense-categories.index')->with('success', 'Kategori biaya ditambahkan.');
    }

    public function update(Request $request, ExpenseCategory $category): RedirectResponse
    {
        $this->authorizeManage();

        $data = $this->validated($request, $category);
        $data['is_active'] = $request->boolean('is_active');

        $category->update($data);

        return redirect()->route('expense-categories.index')->with('success', 'Kategori biaya diperbarui.');
    }

    public function destroy(ExpenseCategory $category): RedirectResponse
    {
        $this->authorizeManage();

        if ($category->usageCount() > 0) {
            $category->update(['is_active' => false]);

            return redirect()->route('expense-categories.index')
                ->with('success', 'Kategori "'.$category->name.'" sudah dipakai di data biaya, jadi dinonaktifkan (tidak muncul lagi di form). Data lama tetap memakai kategori ini.');
        }

        $category->delete();

        return redirect()->route('expense-categories.index')->with('success', 'Kategori biaya dihapus.');
    }

    /**
     * @return array{name: string}
     */
    protected function validated(Request $request, ?ExpenseCategory $category = null): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('crm_expense_categories', 'name')->ignore($category?->id),
            ],
        ], [
            'name.unique' => 'Nama kategori sudah ada.',
        ]);
        $data['name'] = trim($data['name']);

        return $data;
    }

    protected function authorizeManage(): void
    {
        if (! auth()->user()?->canManageExpenseCategories()) {
            abort(403, 'Hanya Superadmin dan Finance yang dapat mengelola kategori biaya.');
        }
    }
}
