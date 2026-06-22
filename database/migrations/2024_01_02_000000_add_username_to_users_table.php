<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan kolom username agar pengguna dapat login
 * menggunakan email maupun username.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('crm_users', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
