<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Status Quotation hanya draft & sent.
 * Data lama accepted → sent; rejected/expired → draft.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('crm_quotations')->where('status', 'accepted')->update(['status' => 'sent']);
        DB::table('crm_quotations')->whereIn('status', ['rejected', 'expired'])->update(['status' => 'draft']);

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE crm_quotations MODIFY COLUMN status ENUM('draft', 'sent') NOT NULL DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE crm_quotations MODIFY COLUMN status ENUM('draft', 'sent', 'accepted', 'rejected', 'expired') NOT NULL DEFAULT 'draft'");
        }
    }
};
