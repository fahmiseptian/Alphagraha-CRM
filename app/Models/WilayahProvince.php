<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WilayahProvince extends Model
{
    protected $table = 'crm_wilayah_provinces';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['code', 'name', 'source'];

    public function regencies(): HasMany
    {
        return $this->hasMany(WilayahRegency::class, 'province_code', 'code')->orderBy('name');
    }
}
