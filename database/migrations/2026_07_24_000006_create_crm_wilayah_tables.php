<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master wilayah Indonesia (provinsi → kota/kab → kecamatan).
 * Diisi via sync API wilayah.id atau input manual admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_wilayah_provinces', function (Blueprint $table) {
            $table->string('code', 10)->primary();
            $table->string('name');
            $table->string('source', 20)->default('api'); // api | manual
            $table->timestamps();
        });

        Schema::create('crm_wilayah_regencies', function (Blueprint $table) {
            $table->string('code', 10)->primary();
            $table->string('province_code', 10);
            $table->string('name');
            $table->string('source', 20)->default('api');
            $table->timestamps();

            $table->index('province_code');
            $table->foreign('province_code')
                ->references('code')
                ->on('crm_wilayah_provinces')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        Schema::create('crm_wilayah_districts', function (Blueprint $table) {
            $table->string('code', 15)->primary();
            $table->string('regency_code', 10);
            $table->string('name');
            $table->string('source', 20)->default('api');
            $table->timestamps();

            $table->index('regency_code');
            $table->foreign('regency_code')
                ->references('code')
                ->on('crm_wilayah_regencies')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_wilayah_districts');
        Schema::dropIfExists('crm_wilayah_regencies');
        Schema::dropIfExists('crm_wilayah_provinces');
    }
};
