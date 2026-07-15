<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_user_profiles', function (Blueprint $table) {
            $table->string('sales_code', 20)->nullable()->unique()->after('job_position');
        });
    }

    public function down(): void
    {
        Schema::table('crm_user_profiles', function (Blueprint $table) {
            $table->dropUnique(['sales_code']);
            $table->dropColumn('sales_code');
        });
    }
};
