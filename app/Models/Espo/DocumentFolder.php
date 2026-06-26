<?php

namespace App\Models\Espo;

use App\Models\Espo\Concerns\EspoEntity;
use Illuminate\Database\Eloquent\Model;

class DocumentFolder extends Model
{
    use EspoEntity;

    protected $table = 'document_folder';

    public function espoEntityType(): string
    {
        return 'DocumentFolder';
    }
}
