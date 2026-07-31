<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Correct Elite Proxy System → Elite Proxy Sistem.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('opportunity') && Schema::hasColumn('opportunity', 'company')) {
            DB::table('opportunity')->where('company', 'Elite Proxy System')->update(['company' => 'Elite Proxy Sistem']);
        }

        if (Schema::hasTable('crm_quotation_templates') && Schema::hasColumn('crm_quotation_templates', 'category')) {
            DB::table('crm_quotation_templates')->where('category', 'Elite Proxy System')->update(['category' => 'Elite Proxy Sistem']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('opportunity') && Schema::hasColumn('opportunity', 'company')) {
            DB::table('opportunity')->where('company', 'Elite Proxy Sistem')->update(['company' => 'Elite Proxy System']);
        }

        if (Schema::hasTable('crm_quotation_templates') && Schema::hasColumn('crm_quotation_templates', 'category')) {
            DB::table('crm_quotation_templates')->where('category', 'Elite Proxy Sistem')->update(['category' => 'Elite Proxy System']);
        }
    }
};
