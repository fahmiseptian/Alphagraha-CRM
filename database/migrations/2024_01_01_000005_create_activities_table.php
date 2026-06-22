<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aktivitas & task sales: jadwal follow-up, reminder, dan catatan.
 * Disimpan di tabel milik aplikasi agar tidak mengganggu data EspoCRM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['call', 'meeting', 'email', 'task', 'followup', 'note'])
                ->default('task');
            $table->string('subject');
            $table->text('description')->nullable();

            // Relasi opsional ke entitas EspoCRM.
            $table->string('account_id', 24)->nullable()->index();
            $table->string('lead_id', 24)->nullable()->index();
            $table->foreignId('quotation_id')->nullable()
                ->constrained('crm_quotations')->nullOnDelete();

            $table->enum('status', ['planned', 'in_progress', 'completed', 'cancelled'])
                ->default('planned');
            $table->enum('priority', ['low', 'normal', 'high'])->default('normal');

            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('reminder_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->foreignId('assigned_to')->nullable()->constrained('crm_users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('crm_users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
    }
};
