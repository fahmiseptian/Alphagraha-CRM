<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WilayahRegency extends Model
{
    protected $table = 'crm_wilayah_regencies';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['code', 'province_code', 'name', 'source'];

    public function province(): BelongsTo
    {
        return $this->belongsTo(WilayahProvince::class, 'province_code', 'code');
    }

    public function districts(): HasMany
    {
        return $this->hasMany(WilayahDistrict::class, 'regency_code', 'code')->orderBy('name');
    }
}
