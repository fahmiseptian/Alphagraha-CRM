<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OpportunityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OpportunityLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeSuperadmin();

        $action = (string) $request->get('action', 'all');
        if (! in_array($action, ['all', ...array_keys(OpportunityLog::ACTIONS)], true)) {
            $action = 'all';
        }

        $search = trim((string) $request->get('q', ''));
        if (mb_strlen($search) > 200) {
            $search = mb_substr($search, 0, 200);
        }

        $query = OpportunityLog::query()
            ->with(['actor', 'opportunity.account'])
            ->orderByDesc('id');

        if ($action !== 'all') {
            $query->where('action', $action);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('actor_name', 'like', $like)
                    ->orWhere('summary', 'like', $like)
                    ->orWhere('opportunity_id', 'like', $like)
                    ->orWhere('snapshot', 'like', $like);
            });
        }

        $logs = $query->paginate(30)->withQueryString();

        return view('admin.opportunity-logs.index', [
            'logs' => $logs,
            'action' => $action,
            'search' => $search,
        ]);
    }

    public function show(OpportunityLog $log): View
    {
        $this->authorizeSuperadmin();

        $log->load(['actor', 'opportunity.account']);

        return view('admin.opportunity-logs.show', compact('log'));
    }

    protected function authorizeSuperadmin(): void
    {
        if (! auth()->user()?->canViewOpportunityLogs()) {
            abort(403, 'Log Opportunity hanya untuk Superadmin.');
        }
    }
}
