<?php

use App\Models\Espo\Opportunity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Margin deal yang disimpan saat opportunity Closed Won, untuk atribusi ke sales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            $table->decimal('crm_won_margin', 15, 2)->nullable()->after('crm_cost_exclude');
        });

        Opportunity::query()
            ->where('stage', Opportunity::WON_STAGE)
            ->where('deleted', 0)
            ->orderBy('created_at')
            ->chunk(100, function ($opportunities) {
                foreach ($opportunities as $opportunity) {
                    $opportunity->syncWonMargin();
                    $opportunity->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            $table->dropColumn('crm_won_margin');
        });
    }
};
