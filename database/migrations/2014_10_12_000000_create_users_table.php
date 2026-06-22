<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel pengguna aplikasi CRM baru (terpisah dari tabel `user` milik EspoCRM).
 * Autentikasi & hak akses dikelola sepenuhnya oleh aplikasi Laravel ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['admin', 'sales'])->default('sales');
            // Pemetaan opsional ke user EspoCRM (user.id) agar filter
            // "pelanggan yang ditugaskan" dapat berjalan.
            $table->string('espo_user_id', 24)->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_users');
    }
};
