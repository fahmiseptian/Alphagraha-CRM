<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Industry extends Model
{
    protected $table = 'crm_industries';

    public const DEFAULT_NAMES = [
        'Government & State-Owned Enterprises',
        'Banking & Financial Services',
        'Education',
        'Healthcare',
        'Manufacturing',
        'Mining & Energy',
        'Oil & Gas',
        'Telecommunications & Technology',
        'Data Center & Cloud',
        'Retail & E-Commerce',
        'Logistics & Transportation',
        'Construction & Real Estate',
        'Hospitality & Tourism',
        'Agribusiness',
        'Professional Services & Others',
        'Media & Broadcasting',
        'Advertising',
        'System Integrator',
    ];

    protected $fillable = [
        'name',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Opsi dropdown: industri aktif + nilai lama jika sudah nonaktif.
     *
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function optionsForSelect(?string $currentName = null)
    {
        $query = static::query()->ordered();

        if (filled($currentName)) {
            $query->where(function (Builder $q) use ($currentName) {
                $q->where('is_active', true)->orWhere('name', $currentName);
            });
        } else {
            $query->active();
        }

        return $query->get();
    }

    public static function existsRule(?string $currentName = null): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists('crm_industries', 'name')->where(function ($query) use ($currentName) {
            $query->where('is_active', true);
            if (filled($currentName)) {
                $query->orWhere('name', $currentName);
            }
        });
    }
}
