<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE crm_activities MODIFY COLUMN type ENUM('call', 'meeting', 'email', 'task', 'followup', 'note', 'event_training') NOT NULL DEFAULT 'task'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE crm_activities MODIFY COLUMN type ENUM('call', 'meeting', 'email', 'task', 'followup', 'note') NOT NULL DEFAULT 'task'");
    }
};
