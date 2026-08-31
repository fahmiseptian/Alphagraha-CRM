<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Vendor;

/**
 * Master brand, category & vendor lokal CRM (bukan API AGC).
 */
class CatalogService
{
    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function brandOptions(): array
    {
        return Brand::query()
            ->active()
            ->ordered()
            ->get(['id', 'name'])
            ->map(fn (Brand $brand) => [
                'id' => (string) $brand->id,
                'name' => $brand->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function categoryOptions(): array
    {
        return Category::query()
            ->active()
            ->ordered()
            ->get(['id', 'name'])
            ->map(fn (Category $category) => [
                'id' => (string) $category->id,
                'name' => $category->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function vendorOptions(): array
    {
        return Vendor::query()
            ->active()
            ->ordered()
            ->get(['id', 'name'])
            ->map(fn (Vendor $vendor) => [
                'id' => (string) $vendor->id,
                'name' => $vendor->name,
            ])
            ->values()
            ->all();
    }
}
