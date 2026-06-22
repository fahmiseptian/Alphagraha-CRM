<?php

namespace App\Models\Espo;

use Illuminate\Database\Eloquent\Model;

class PhoneNumber extends Model
{
    protected $table = 'phone_number';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
}
