<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opportunity EspoCRM memakai id varchar(24), bukan bigint.
 * Sesuaikan kolom morph model_id pada tabel media agar Spatie Media
 * Library bisa menautkan file ke entitas EspoCRM.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE media MODIFY model_id VARCHAR(24) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE media MODIFY model_id BIGINT UNSIGNED NOT NULL');
    }
};
