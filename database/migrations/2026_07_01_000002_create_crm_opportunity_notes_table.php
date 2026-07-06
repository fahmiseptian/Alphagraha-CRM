<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_opportunity_notes', function (Blueprint $table) {
            $table->id();
            $table->string('opportunity_id', 24)->index();
            $table->text('body');
            $table->string('created_by', 24)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_opportunity_notes');
    }
};
