<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationRevision extends Model
{
    protected $table = 'crm_quotation_revisions';

    protected $fillable = [
        'quotation_id', 'revision', 'snapshot', 'rendered_html', 'note', 'created_by',
    ];

    protected $casts = [
        'snapshot' => 'array',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
