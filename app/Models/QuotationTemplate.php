<?php

namespace App\Models;

use App\Models\Espo\Opportunity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationTemplate extends Model
{
    protected $table = 'crm_quotation_templates';

    protected $fillable = [
        'name', 'code', 'category', 'description', 'body_html', 'is_active', 'is_default', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /** Kategori = daftar perusahaan internal opportunity. */
    public const CATEGORIES = Opportunity::COMPANIES;

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Filter template aktif untuk company opportunity (atau semua bila company kosong).
     */
    public function scopeForCompany(Builder $query, ?string $company): Builder
    {
        if ($company && in_array($company, self::CATEGORIES, true)) {
            $query->where('category', $company);
        }

        return $query;
    }
}
