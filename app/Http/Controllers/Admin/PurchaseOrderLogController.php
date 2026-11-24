<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrderLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseOrderLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeView();

        $action = (string) $request->get('action', 'all');
        if (! in_array($action, ['all', ...array_keys(PurchaseOrderLog::ACTIONS)], true)) {
            $action = 'all';
        }

        $search = trim((string) $request->get('q', ''));
        if (mb_strlen($search) > 200) {
            $search = mb_substr($search, 0, 200);
        }

        $query = PurchaseOrderLog::query()
            ->with(['actor', 'purchaseOrder', 'opportunity.account'])
            ->orderByDesc('id');

        if ($action !== 'all') {
            $query->where('action', $action);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('number', 'like', $like)
                    ->orWhere('actor_name', 'like', $like)
                    ->orWhere('snapshot', 'like', $like);
            });
        }

        $logs = $query->paginate(30)->withQueryString();

        return view('admin.purchase-order-logs.index', [
            'logs' => $logs,
            'action' => $action,
            'search' => $search,
        ]);
    }

    public function show(PurchaseOrderLog $log): View
    {
        $this->authorizeView();

        $log->load(['actor', 'purchaseOrder.opportunity.account', 'opportunity.account']);

        return view('admin.purchase-order-logs.show', compact('log'));
    }

    protected function authorizeView(): void
    {
        if (! auth()->user()?->canViewPurchaseOrderLogs()) {
            abort(403, 'Log Purchase Order hanya untuk Superadmin dan Purchasing.');
        }
    }
};
