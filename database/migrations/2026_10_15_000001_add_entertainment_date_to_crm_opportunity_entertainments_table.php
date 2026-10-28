<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('crm_opportunity_entertainments', 'entertainment_date')) {
            Schema::table('crm_opportunity_entertainments', function (Blueprint $table) {
                $table->date('entertainment_date')->nullable()->index()->after('amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('crm_opportunity_entertainments', 'entertainment_date')) {
            Schema::table('crm_opportunity_entertainments', function (Blueprint $table) {
                $table->dropColumn('entertainment_date');
            });
        }
    }
};
