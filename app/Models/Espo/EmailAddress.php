<?php

namespace App\Models\Espo;

use Illuminate\Database\Eloquent\Model;

class EmailAddress extends Model
{
    protected $table = 'email_address';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
}
