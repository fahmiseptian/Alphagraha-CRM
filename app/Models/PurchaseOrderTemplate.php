<?php

namespace App\Models;

use App\Models\Espo\Opportunity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderTemplate extends Model
{
    protected $table = 'crm_purchase_order_templates';

    protected $fillable = [
        'name', 'code', 'category', 'description', 'body_html', 'is_active', 'is_default', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /** Kategori = perusahaan internal opportunity, sama seperti template quotation. */
    public const CATEGORIES = Opportunity::COMPANIES;

    public const PLACEHOLDERS = [
        'company_letterhead',
        'company_legal_name',
        'company_address',
        'po_number',
        'po_date',
        'po_time',
        'expected_date',
        'ship_via',
        'vendor_name',
        'vendor_detail',
        'ship_to',
        'currency',
        'items_table',
        'items_rows',
        'subtotal',
        'discount',
        'ppn_percent',
        'ppn_amount',
        'total',
        'notes',
        'prepared_by',
        'approved_by',
        'footer_note',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForCompany(Builder $query, ?string $company): Builder
    {
        if ($company && in_array($company, self::CATEGORIES, true)) {
            $query->where('category', $company);
        }

        return $query;
    }
}
