<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalesOrderLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesOrderLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeSuperadmin();

        $action = (string) $request->get('action', 'all');
        if (! in_array($action, ['all', ...array_keys(SalesOrderLog::ACTIONS)], true)) {
            $action = 'all';
        }

        $search = trim((string) $request->get('q', ''));
        if (mb_strlen($search) > 200) {
            $search = mb_substr($search, 0, 200);
        }

        $query = SalesOrderLog::query()
            ->with(['actor', 'salesOrder', 'opportunity.account'])
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

        return view('admin.sales-order-logs.index', [
            'logs' => $logs,
            'action' => $action,
            'search' => $search,
        ]);
    }

    public function show(SalesOrderLog $log): View
    {
        $this->authorizeSuperadmin();

        $log->load(['actor', 'salesOrder.opportunity.account', 'opportunity.account']);

        return view('admin.sales-order-logs.show', compact('log'));
    }

    protected function authorizeSuperadmin(): void
    {
        if (! auth()->user()?->canViewSalesOrderLogs()) {
            abort(403, 'Log Sales Order hanya untuk Superadmin.');
        }
    }
}
