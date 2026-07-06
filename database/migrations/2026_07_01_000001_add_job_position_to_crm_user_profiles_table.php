<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_user_profiles', function (Blueprint $table) {
            $table->string('job_position')->nullable()->after('signature_path');
        });
    }

    public function down(): void
    {
        Schema::table('crm_user_profiles', function (Blueprint $table) {
            $table->dropColumn('job_position');
        });
    }
};
