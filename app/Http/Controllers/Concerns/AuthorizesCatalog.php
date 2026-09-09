<?php

namespace App\Http\Controllers\Concerns;

trait AuthorizesCatalog
{
    protected function authorizeBrandManagement(): void
    {
        abort_unless(auth()->user()?->canManageBrands(), 403, 'Anda tidak memiliki akses untuk mengelola brand.');
    }

    protected function authorizeCategoryManagement(): void
    {
        abort_unless(auth()->user()?->canManageCategories(), 403, 'Anda tidak memiliki akses untuk mengelola category.');
    }

    protected function authorizeVendorManagement(): void
    {
        abort_unless(auth()->user()?->canManageVendors(), 403, 'Anda tidak memiliki akses untuk mengelola vendor.');
    }

    protected function authorizeIndustryManagement(): void
    {
        abort_unless(auth()->user()?->canManageIndustries(), 403, 'Anda tidak memiliki akses untuk mengelola industri.');
    }
}
