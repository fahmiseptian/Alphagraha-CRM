<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menghapus tabel crm_users dan mengarahkan seluruh referensi pengguna
 * (created_by / assigned_to) ke tabel `user` milik EspoCRM (id varchar(24)).
 * Autentikasi & identitas pengguna kini sepenuhnya memakai tabel `user`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Lepas foreign key yang menunjuk ke crm_users.
        Schema::table('crm_quotation_templates', fn (Blueprint $t) => $t->dropForeign(['created_by']));
        Schema::table('crm_quotations', fn (Blueprint $t) => $t->dropForeign(['created_by']));
        Schema::table('crm_quotation_revisions', fn (Blueprint $t) => $t->dropForeign(['created_by']));
        Schema::table('crm_activities', function (Blueprint $t) {
            $t->dropForeign(['assigned_to']);
            $t->dropForeign(['created_by']);
        });

        // 2. Ubah tipe kolom menjadi varchar(24) agar cocok dengan user.id EspoCRM.
        DB::statement('ALTER TABLE crm_quotation_templates MODIFY created_by VARCHAR(24) NULL');
        DB::statement('ALTER TABLE crm_quotations MODIFY created_by VARCHAR(24) NULL');
        DB::statement('ALTER TABLE crm_quotation_revisions MODIFY created_by VARCHAR(24) NULL');
        DB::statement('ALTER TABLE crm_activities MODIFY assigned_to VARCHAR(24) NULL');
        DB::statement('ALTER TABLE crm_activities MODIFY created_by VARCHAR(24) NULL');

        // 3. Kosongkan referensi numerik lama (peninggalan crm_users) agar tidak orphan.
        DB::table('crm_quotation_templates')->update(['created_by' => null]);
        DB::table('crm_quotations')->update(['created_by' => null]);
        DB::table('crm_quotation_revisions')->update(['created_by' => null]);
        DB::table('crm_activities')->update(['assigned_to' => null, 'created_by' => null]);

        // 4. Hapus tabel crm_users.
        Schema::dropIfExists('crm_users');
    }

    public function down(): void
    {
        // Buat ulang crm_users (struktur minimal) bila rollback diperlukan.
        Schema::create('crm_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('username')->nullable()->unique();
            $table->string('password');
            $table->enum('role', ['admin', 'sales'])->default('sales');
            $table->string('espo_user_id', 24)->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE crm_quotation_templates MODIFY created_by BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE crm_quotations MODIFY created_by BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE crm_quotation_revisions MODIFY created_by BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE crm_activities MODIFY assigned_to BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE crm_activities MODIFY created_by BIGINT UNSIGNED NULL');
    }
};
