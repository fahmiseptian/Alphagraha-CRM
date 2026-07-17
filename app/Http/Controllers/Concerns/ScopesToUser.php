<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Scoping data & helper role akses CRM.
 */
trait ScopesToUser
{
    protected function scopeAssigned(Builder $query, string $column = 'assigned_user_id'): Builder
    {
        $user = auth()->user();

        if ($user && $user->isSales()) {
            $query->where($column, $user->getKey());
        }

        return $query;
    }

    /**
     * Scope opportunity: sales = assigned; purchasing/finance = Closed Won saja;
     * admin/superadmin = semua.
     */
    protected function scopeOpportunitiesForRole(Builder $query): Builder
    {
        $user = auth()->user();
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->canViewAllOpportunities()) {
            if ($user->isPurchasing() || $user->isFinance()) {
                $query->where($query->getModel()->getTable().'.stage', \App\Models\Espo\Opportunity::WON_STAGE);
            }

            return $query;
        }

        return $query->where($query->getModel()->getTable().'.assigned_user_id', $user->getKey());
    }

    protected function isAdmin(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function isSuperAdmin(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected function currentUser()
    {
        return auth()->user();
    }
}
