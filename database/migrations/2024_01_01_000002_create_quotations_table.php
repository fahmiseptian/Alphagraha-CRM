<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penawaran (quotation) yang dibuat oleh sales.
 * Terhubung ke pelanggan EspoCRM melalui kolom `account_id` (account.id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_quotations', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();

            // Relasi ke pelanggan EspoCRM (account.id berformat varchar(24)).
            $table->string('account_id', 24)->nullable()->index();

            // Snapshot data pelanggan agar penawaran tetap konsisten
            // walau data pelanggan di EspoCRM berubah.
            $table->string('customer_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->text('customer_address')->nullable();

            $table->date('quotation_date');
            $table->date('valid_until')->nullable();

            $table->enum('status', ['draft', 'sent', 'accepted', 'rejected', 'expired'])
                ->default('draft');

            $table->string('currency', 6)->default('IDR');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount', 18, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);

            $table->text('notes')->nullable();
            $table->text('terms')->nullable();

            $table->foreignId('template_id')->nullable()
                ->constrained('crm_quotation_templates')->nullOnDelete();
            $table->foreignId('created_by')->nullable()
                ->constrained('crm_users')->nullOnDelete();

            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_quotations');
    }
};
