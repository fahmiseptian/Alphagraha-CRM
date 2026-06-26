<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationTemplate extends Model
{
    protected $table = 'crm_quotation_templates';

    protected $fillable = [
        'name', 'code', 'description', 'body_html', 'is_active', 'is_default', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
