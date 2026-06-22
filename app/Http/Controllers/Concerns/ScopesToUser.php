<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Membatasi data EspoCRM agar sales hanya melihat record
 * yang di-assign kepadanya (berdasarkan id user EspoCRM),
 * sedangkan admin melihat seluruh data.
 */
trait ScopesToUser
{
    protected function scopeAssigned(Builder $query, string $column = 'assigned_user_id'): Builder
    {
        $user = auth()->user();

        if ($user && $user->isSales()) {
            // Identitas auth = user EspoCRM, jadi id-nya langsung dipakai
            // untuk mencocokkan assigned_user_id pada data EspoCRM.
            $query->where($column, $user->getKey());
        }

        return $query;
    }

    protected function isAdmin(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
