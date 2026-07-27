<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Job Role / jabatan PIC (contact person) pada customer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact', function (Blueprint $table) {
            if (! Schema::hasColumn('contact', 'job_role')) {
                $table->string('job_role', 100)->nullable()->after('last_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contact', function (Blueprint $table) {
            if (Schema::hasColumn('contact', 'job_role')) {
                $table->dropColumn('job_role');
            }
        });
    }
};
