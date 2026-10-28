<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dokumen customer: 1 account bisa banyak file (nama + path).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_customer_documents', function (Blueprint $table) {
            $table->id();
            $table->string('account_id', 24)->index();
            $table->string('name', 255);
            $table->string('file_path', 500);
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('uploaded_by', 24)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_customer_documents');
    }
};
