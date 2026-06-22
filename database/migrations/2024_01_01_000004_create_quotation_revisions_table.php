<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat revisi penawaran. Setiap perubahan signifikan menyimpan
 * snapshot data + HTML hasil merge agar histori tetap utuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_quotation_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('crm_quotations')->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->json('snapshot')->nullable();
            $table->longText('rendered_html')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('crm_users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_quotation_revisions');
    }
};
