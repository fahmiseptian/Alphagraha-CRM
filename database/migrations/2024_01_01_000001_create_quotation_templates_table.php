<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Template penawaran berbasis HTML yang distandarisasi perusahaan.
 * Sales hanya mengisi data; sistem melakukan merge ke template ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_quotation_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->longText('body_html');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('crm_users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_quotation_templates');
    }
};
