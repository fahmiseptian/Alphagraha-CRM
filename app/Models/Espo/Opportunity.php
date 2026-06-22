<?php

namespace App\Models\Espo;

use App\Models\Espo\Concerns\EspoEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Peluang penjualan / deal (EspoCRM: Opportunity).
 */
class Opportunity extends Model
{
    use EspoEntity;

    protected $table = 'opportunity';

    public const OPEN_STAGES = ['Prospecting', 'Qualification', 'Proposal', 'Negotiation'];
    public const WON_STAGE = 'Closed Won';
    public const LOST_STAGE = 'Closed Lost';

    public function espoEntityType(): string
    {
        return 'Opportunity';
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(EspoUser::class, 'assigned_user_id');
    }
}
