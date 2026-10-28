<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            if (! Schema::hasColumn('opportunity', 'crm_top_status')) {
                $table->string('crm_top_status', 20)->nullable()->after('crm_top'); // pending|approved|rejected
                $table->string('crm_top_customer', 20)->nullable()->after('crm_top_status');
                $table->string('crm_top_requested_by', 24)->nullable()->after('crm_top_customer');
                $table->timestamp('crm_top_requested_at')->nullable()->after('crm_top_requested_by');
                $table->string('crm_top_reviewed_by', 24)->nullable()->after('crm_top_requested_at');
                $table->timestamp('crm_top_reviewed_at')->nullable()->after('crm_top_reviewed_by');
                $table->text('crm_top_note')->nullable()->after('crm_top_reviewed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            if (Schema::hasColumn('opportunity', 'crm_top_status')) {
                $table->dropColumn([
                    'crm_top_status',
                    'crm_top_customer',
                    'crm_top_requested_by',
                    'crm_top_requested_at',
                    'crm_top_reviewed_by',
                    'crm_top_reviewed_at',
                    'crm_top_note',
                ]);
            }
        });
    }
};
