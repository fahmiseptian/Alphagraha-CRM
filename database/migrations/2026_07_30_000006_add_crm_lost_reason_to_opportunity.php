<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            if (! Schema::hasColumn('opportunity', 'crm_lost_reason')) {
                $table->text('crm_lost_reason')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            if (Schema::hasColumn('opportunity', 'crm_lost_reason')) {
                $table->dropColumn('crm_lost_reason');
            }
        });
    }
};
