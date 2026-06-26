<?php

namespace App\Models\Espo;

use App\Models\Espo\Concerns\EspoEntity;
use Illuminate\Database\Eloquent\Model;

/**
 * Tim EspoCRM (untuk assignment entitas).
 */
class Team extends Model
{
    use EspoEntity;

    protected $table = 'team';

    public function espoEntityType(): string
    {
        return 'Team';
    }
}
