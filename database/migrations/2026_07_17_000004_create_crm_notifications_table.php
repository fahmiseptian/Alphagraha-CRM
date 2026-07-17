<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 24)->index();
            $table->string('type', 50)->index();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('link')->nullable();
            $table->string('unique_key', 191)->nullable();
            $table->json('data')->nullable();
            $table->boolean('show_popup')->default(false);
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['user_id', 'unique_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_notifications');
    }
};
