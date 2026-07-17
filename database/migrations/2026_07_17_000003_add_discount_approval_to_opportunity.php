<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            $table->boolean('crm_has_discount')->default(false)->after('crm_won_margin');
            $table->decimal('crm_discount_amount', 15, 2)->nullable()->after('crm_has_discount');
            $table->string('crm_discount_status', 20)->nullable()->after('crm_discount_amount'); // pending|approved|rejected
            $table->string('crm_discount_requested_by', 24)->nullable()->after('crm_discount_status');
            $table->timestamp('crm_discount_requested_at')->nullable()->after('crm_discount_requested_by');
            $table->string('crm_discount_reviewed_by', 24)->nullable()->after('crm_discount_requested_at');
            $table->timestamp('crm_discount_reviewed_at')->nullable()->after('crm_discount_reviewed_by');
            $table->text('crm_discount_note')->nullable()->after('crm_discount_reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            $table->dropColumn([
                'crm_has_discount',
                'crm_discount_amount',
                'crm_discount_status',
                'crm_discount_requested_by',
                'crm_discount_requested_at',
                'crm_discount_reviewed_by',
                'crm_discount_reviewed_at',
                'crm_discount_note',
            ]);
        });
    }
};
