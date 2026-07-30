<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TOP (Terms of Payment) per customer: cash | 7 | 14 | 30 | 45 | 60.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account', function (Blueprint $table) {
            if (! Schema::hasColumn('account', 'crm_top')) {
                $table->string('crm_top', 10)->default('cash')->after('crm_payment_level');
            }
        });
    }

    public function down(): void
    {
        Schema::table('account', function (Blueprint $table) {
            if (Schema::hasColumn('account', 'crm_top')) {
                $table->dropColumn('crm_top');
            }
        });
    }
};
