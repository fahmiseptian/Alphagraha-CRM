<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPic extends Model
{
    protected $table = 'crm_vendor_pics';

    protected $fillable = [
        'vendor_id',
        'name',
        'job_role',
        'phone',
        'email',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
