<?php

use App\Models\Espo\Opportunity;
use Illuminate\Database\Migrations\Migration;

/**
 * Recalculate Closed Won margin: product margin − diskon tambahan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Opportunity::query()
            ->where('stage', Opportunity::WON_STAGE)
            ->where('deleted', 0)
            ->orderBy('id')
            ->chunkById(100, function ($opportunities) {
                foreach ($opportunities as $opportunity) {
                    $opportunity->syncWonMargin();
                    $opportunity->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        // Tidak bisa mengembalikan nilai lama secara akurat.
    }
};
