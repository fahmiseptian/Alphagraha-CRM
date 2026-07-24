<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WilayahDistrict extends Model
{
    protected $table = 'crm_wilayah_districts';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['code', 'regency_code', 'name', 'source'];

    public function regency(): BelongsTo
    {
        return $this->belongsTo(WilayahRegency::class, 'regency_code', 'code');
    }
}
