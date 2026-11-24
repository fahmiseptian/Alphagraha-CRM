<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_activities', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_activities', 'contact_id')) {
                $table->string('contact_id', 24)->nullable()->index();
            }
            if (! Schema::hasColumn('crm_activities', 'starts_at')) {
                $table->timestamp('starts_at')->nullable();
            }
            if (! Schema::hasColumn('crm_activities', 'ends_at')) {
                $table->timestamp('ends_at')->nullable();
            }
            if (! Schema::hasColumn('crm_activities', 'location')) {
                $table->string('location')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_activities', function (Blueprint $table) {
            foreach (['contact_id', 'starts_at', 'ends_at', 'location'] as $column) {
                if (Schema::hasColumn('crm_activities', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
