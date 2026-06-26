<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tautkan penawaran ke Opportunity EspoCRM (1 opportunity : 1 penawaran).
 * opportunity_id menyimpan user/opportunity.id EspoCRM (varchar 24) dan dibuat
 * unik agar setiap opportunity hanya punya satu penawaran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_quotations', function (Blueprint $table) {
            $table->string('opportunity_id', 24)->nullable()->unique()->after('account_id');
        });
    }

    public function down(): void
    {
        Schema::table('crm_quotations', function (Blueprint $table) {
            $table->dropUnique(['opportunity_id']);
            $table->dropColumn('opportunity_id');
        });
    }
};
