<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    protected $table = 'crm_expense_categories';

    public const ENTERTAINMENT_NAME = 'Biaya Entertainment';

    protected $fillable = [
        'name',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return Collection<int, self>
     */
    public static function options(): Collection
    {
        return static::query()->active()->ordered()->get(['id', 'name']);
    }

    public static function defaultEntertainmentId(): ?int
    {
        $id = static::query()->active()->where('name', self::ENTERTAINMENT_NAME)->value('id')
            ?? static::query()->active()->ordered()->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function usageCount(): int
    {
        return CommissionExpense::query()->where('category_id', $this->id)->count()
            + OpportunityEntertainment::query()->where('category_id', $this->id)->count();
    }
}
