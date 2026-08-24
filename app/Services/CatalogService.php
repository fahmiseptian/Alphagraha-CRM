<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;

/**
 * Master brand & category lokal CRM (bukan API AGC).
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
}
