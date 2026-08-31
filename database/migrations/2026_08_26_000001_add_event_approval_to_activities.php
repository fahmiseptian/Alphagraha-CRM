<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Approval Superadmin khusus activity Event/Training.
 * Event yang tanggalnya sudah lewat langsung dianggap approved.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_activities')) {
            return;
        }

        Schema::table('crm_activities', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_activities', 'approval_status')) {
                $table->string('approval_status', 20)->nullable()->after('status')->index();
            }
            if (! Schema::hasColumn('crm_activities', 'approved_by')) {
                $table->string('approved_by', 24)->nullable()->after('approval_status');
            }
            if (! Schema::hasColumn('crm_activities', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('crm_activities', 'approval_note')) {
                $table->text('approval_note')->nullable()->after('approved_at');
            }
        });

        $now = now();

        DB::table('crm_activities')
            ->where('type', 'event_training')
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->update([
                'approval_status' => 'approved',
                'approved_at' => $now,
            ]);

        DB::table('crm_activities')
            ->where('type', 'event_training')
            ->where(function ($query) use ($now) {
                $query->whereNull('due_at')
                    ->orWhere('due_at', '>=', $now);
            })
            ->whereNull('approval_status')
            ->update([
                'approval_status' => 'pending',
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_activities')) {
            return;
        }

        Schema::table('crm_activities', function (Blueprint $table) {
            foreach (['approval_note', 'approved_at', 'approved_by', 'approval_status'] as $column) {
                if (Schema::hasColumn('crm_activities', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
