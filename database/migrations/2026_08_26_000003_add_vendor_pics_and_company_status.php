<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status perusahaan + banyak PIC per vendor.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_vendors') && ! Schema::hasColumn('crm_vendors', 'company_status')) {
            Schema::table('crm_vendors', function (Blueprint $table) {
                $table->string('company_status', 100)->nullable()->after('name');
            });
        }

        if (! Schema::hasTable('crm_vendor_pics')) {
            Schema::create('crm_vendor_pics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('crm_vendors')->cascadeOnDelete();
                $table->string('name');
                $table->string('job_role', 150)->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('email', 150)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['vendor_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_vendor_pics');

        if (Schema::hasTable('crm_vendors') && Schema::hasColumn('crm_vendors', 'company_status')) {
            Schema::table('crm_vendors', function (Blueprint $table) {
                $table->dropColumn('company_status');
            });
        }
    }
};
