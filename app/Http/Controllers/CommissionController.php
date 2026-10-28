<?php

namespace App\Http\Controllers;

use App\Models\CommissionExpense;
use App\Models\Espo\Account;
use App\Models\ExpenseCategory;
use App\Models\OpportunityEntertainment;
use App\Models\OpportunityLog;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\NotificationService;
use App\Services\OpportunityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommissionController extends Controller
{
    public function __construct(
        protected CommissionService $commissions
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $this->authorizeView();

        // Sales langsung ke detail komisi miliknya
        if (! auth()->user()->canManageCommissions()) {
            return redirect()->route('commissions.show', [
                'user' => auth()->id(),
                'year' => $request->get('year', now()->year),
                'period' => $request->get('period'),
            ]);
        }

        $year = (int) $request->get('year', now()->year);
        if ($year < 2000 || $year > 2100) {
            $year = (int) now()->year;
        }

        $search = trim((string) $request->get('q', ''));

        $salesUsers = $this->commissions->salesUsers();
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $salesUsers = $salesUsers->filter(function (User $sales) use ($needle) {
                $haystack = mb_strtolower(trim(
                    ($sales->display_name ?? '').' '.
                    ($sales->name ?? '').' '.
                    ($sales->user_name ?? '')
                ));

                return $haystack !== '' && str_contains($haystack, $needle);
            })->values();
        }

        $rows = $salesUsers->map(function (User $sales) use ($year) {
            $summary = $this->commissions->buildForSales($sales, $year, detailed: false);

            return [
                'sales' => $sales,
                'target' => $summary['target'] ?? 0,
                'target_period_label' => $summary['target_period_label'] ?? '—',
                'target_progress' => $summary['target_progress'] ?? 0,
                'target_met' => $summary['target_met'] ?? false,
                'quarter' => $summary['quarter'] ?? null,
                'so_count' => $summary['so_count'] ?? 0,
                'gp_total' => $summary['gp_total'] ?? 0,
                'deduction_total' => round(
                    (float) ($summary['entertainment_total'] ?? 0) + (float) ($summary['expense_total'] ?? 0),
                    2
                ),
                'net_gp' => $summary['net_gp'] ?? 0,
                'commission' => $summary['commission'] ?? 0,
                'commission_rate_percent' => $summary['commission_rate_percent'] ?? 0,
            ];
        });

        return view('commissions.index', [
            'year' => $year,
            'search' => $search,
            'rows' => $rows,
        ]);
    }

    public const DETAIL_SECTIONS = [
        'so-all' => 'SO Keseluruhan',
        'so-outstanding' => 'SO Outstanding',
        'so-complete' => 'SO Complete',
        'invoice-all' => 'Invoice Keseluruhan',
        'invoice-outstanding' => 'Invoice Outstanding',
        'invoice-complete' => 'Invoice Complete',
        'gross-profit' => 'Gross Profit',
        'biaya-tambahan' => 'Biaya Tambahan',
        'nett-profit' => 'Nett Profit',
        'komisi' => 'Komisi',
        'target' => 'Pencapaian Target',
    ];

    public function show(Request $request, string $userId): View
    {
        $this->authorizeView($userId);

        [$sales, $year, $summary] = $this->loadCommissionSummary($request, $userId);

        $accounts = auth()->user()->canManageCommissions()
            ? Account::query()->where('deleted', 0)->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('commissions.show', [
            'summary' => $summary,
            'year' => $year,
            'canManage' => auth()->user()->canManageCommissions(),
            'accounts' => $accounts,
            'expenseCategories' => ExpenseCategory::options(),
            'defaultCategoryId' => ExpenseCategory::defaultEntertainmentId(),
        ]);
    }

    public function detail(Request $request, string $userId, string $section): View
    {
        $this->authorizeView($userId);
        abort_unless(array_key_exists($section, self::DETAIL_SECTIONS), 404);

        [$sales, $year, $summary] = $this->loadCommissionSummary($request, $userId);

        return view('commissions.detail', [
            'summary' => $summary,
            'year' => $year,
            'section' => $section,
            'sectionTitle' => self::DETAIL_SECTIONS[$section],
            'canManage' => auth()->user()->canManageCommissions(),
        ]);
    }

    /**
     * @return array{0: User, 1: int, 2: array<string, mixed>}
     */
    protected function loadCommissionSummary(Request $request, string $userId): array
    {
        $sales = User::query()
            ->where('deleted', 0)
            ->whereHas('profile', fn ($q) => $q->where('app_role', User::ROLE_SALES))
            ->with('profile')
            ->findOrFail($userId);

        $year = (int) $request->get('year', now()->year);
        if ($year < 2000 || $year > 2100) {
            $year = (int) now()->year;
        }
        $periodKey = $request->get('period');

        $summary = $this->commissions->buildForSales($sales, $year, $periodKey ? (string) $periodKey : null);

        return [$sales, $year, $summary];
    }

    public function storeExpense(Request $request, string $userId): RedirectResponse
    {
        $this->authorizeManage();

        $sales = User::query()
            ->where('deleted', 0)
            ->whereHas('profile', fn ($q) => $q->where('app_role', User::ROLE_SALES))
            ->with('profile')
            ->findOrFail($userId);

        $data = $request->validate([
            'expense_date' => ['required', 'date'],
            'category_id' => ['required', 'integer', Rule::exists('crm_expense_categories', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:2000'],
            'so_code' => ['nullable', 'string', 'max:100'],
            'account_id' => ['nullable', 'string', Rule::exists('account', 'id')->where('deleted', 0)],
            'file' => ['required', 'file', 'max:20480'], // 20 MB, wajib
        ], [
            'category_id.required' => 'Pilih kategori biaya.',
            'category_id.exists' => 'Kategori biaya tidak valid atau sudah nonaktif.',
        ]);

        $profile = $sales->profile;
        $expenseDate = Carbon::parse($data['expense_date'])->startOfDay();
        $quarter = $profile
            ? $this->commissions->resolveQuarterFromDate($profile, $expenseDate)
            : null;
        if ($quarter === null) {
            return back()
                ->withInput()
                ->withErrors(['expense_date' => 'Tanggal di luar periode komisi sales ini.']);
        }

        $salesOrderId = null;
        $salesOrderNumber = null;
        $soCode = trim((string) ($data['so_code'] ?? ''));
        if ($soCode !== '') {
            $salesOrder = $this->commissions->findSalesOrderForSales($sales, $soCode);
            if (! $salesOrder) {
                return back()
                    ->withInput()
                    ->withErrors(['so_code' => 'Kode SO tidak ditemukan untuk sales ini.']);
            }
            $salesOrderId = $salesOrder->id;
            $salesOrderNumber = $salesOrder->displayNumber();
        }

        $file = $request->file('file');
        $original = $file->getClientOriginalName();
        $ext = $file->getClientOriginalExtension();
        $safeBase = Str::slug(pathinfo($original, PATHINFO_FILENAME)) ?: 'bukti-biaya';
        $filename = $safeBase.'-'.Str::lower(Str::random(8)).($ext ? '.'.$ext : '');
        $dir = 'commission-expenses/'.$sales->id.'/'.$quarter['key'];
        $path = $file->storeAs($dir, $filename, 'public');

        CommissionExpense::query()->create([
            'user_id' => $sales->id,
            'year' => (int) $expenseDate->year,
            'period_key' => $quarter['key'],
            'category_id' => (int) $data['category_id'],
            'sales_order_id' => $salesOrderId,
            'sales_order_number' => $salesOrderNumber,
            'account_id' => filled($data['account_id'] ?? null) ? $data['account_id'] : null,
            'expense_date' => $expenseDate->toDateString(),
            'name' => trim($data['name']),
            'amount' => round((float) $data['amount'], 2),
            'note' => $data['note'] ?? null,
            'created_by' => auth()->id(),
            'file_path' => $path,
            'original_name' => $original,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
        ]);

        return redirect()
            ->route('commissions.show', [
                'user' => $sales->id,
                'year' => $expenseDate->year,
                'period' => $quarter['key'],
            ])
            ->with('success', 'Biaya berhasil ditambahkan.');
    }

    public function downloadExpense(string $userId, CommissionExpense $expense): StreamedResponse
    {
        $this->authorizeView($userId);
        abort_unless((string) $expense->user_id === (string) $userId, 404);
        abort_unless($expense->hasFile(), 404);

        $downloadName = $expense->original_name
            ?: ($expense->name.(($ext = pathinfo((string) $expense->file_path, PATHINFO_EXTENSION)) ? '.'.$ext : ''));

        return Storage::disk('public')->download((string) $expense->file_path, $downloadName);
    }

    public function destroyExpense(string $userId, CommissionExpense $expense): RedirectResponse
    {
        $this->authorizeManage();
        abort_unless((string) $expense->user_id === (string) $userId, 404);

        $year = $expense->year;
        $periodKey = $expense->period_key;
        $expense->deleteFile();
        $expense->delete();

        return redirect()
            ->route('commissions.show', [
                'user' => $userId,
                'year' => $year,
                'period' => $periodKey,
            ])
            ->with('success', 'Biaya dihapus.');
    }

    /**
     * Approval entertainment dari halaman komisi.
     * Setelah complete, nominal otomatis mengurangi Nett Profit / komisi di Q sesuai tanggal entertainment.
     */
    public function approveEntertainment(
        string $userId,
        OpportunityEntertainment $entertainment
    ): RedirectResponse {
        $this->authorizeManage();
        abort_unless(auth()->user()?->canCompleteEntertainment(), 403);

        $sales = User::query()
            ->where('deleted', 0)
            ->whereHas('profile', fn ($q) => $q->where('app_role', User::ROLE_SALES))
            ->with('profile')
            ->findOrFail($userId);

        $entertainment->loadMissing('opportunity');
        $opportunity = $entertainment->opportunity;
        abort_unless($opportunity !== null, 404);
        abort_unless(
            (string) ($opportunity->assigned_user_id ?? '') === (string) $sales->id,
            404
        );

        if ($entertainment->isComplete()) {
            return back()->with('error', 'Entertainment ini sudah di-approve.');
        }

        $entertainment->status = OpportunityEntertainment::STATUS_COMPLETE;
        $entertainment->completed_by = auth()->id();
        $entertainment->completed_at = now();
        $entertainment->save();

        app(OpportunityLogService::class)->record(
            $opportunity,
            OpportunityLog::ACTION_ENTERTAINMENT_COMPLETED,
            null,
            [
                'force' => true,
                'summary' => 'Entertainment complete (dari komisi): '.$entertainment->name,
            ]
        );

        app(NotificationService::class)->markEntertainmentApprovalActioned($entertainment);

        $periodDate = $this->commissions->entertainmentPeriodDate($entertainment) ?? now();
        $year = (int) $periodDate->year;
        $quarter = $sales->profile
            ? $this->commissions->resolveQuarterFromDate($sales->profile, $periodDate)
            : null;

        return redirect()
            ->route('commissions.show', [
                'user' => $sales->id,
                'year' => $year,
                'period' => $quarter['key'] ?? null,
            ])
            ->with('success', 'Entertainment di-approve. Komisi periode terkait otomatis berkurang.');
    }

    protected function authorizeView(?string $userId = null): void
    {
        $user = auth()->user();
        if (! $user?->canViewCommissions()) {
            abort(403, 'Anda tidak memiliki akses ke perhitungan komisi sales.');
        }

        if ($userId !== null && ! $user->canManageCommissions() && (string) $user->id !== (string) $userId) {
            abort(403, 'Anda hanya dapat melihat komisi milik sendiri.');
        }
    }

    protected function authorizeManage(): void
    {
        if (! auth()->user()?->canManageCommissions()) {
            abort(403, 'Anda tidak memiliki akses untuk mengelola komisi sales.');
        }
    }
}
