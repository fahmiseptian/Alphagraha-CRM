<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rename internal company labels to official names.
 */
return new class extends Migration
{
    public function up(): void
    {
        $renames = [
            'Elite Proxy' => 'Elite Proxy Sistem',
            'Elite Proxy System' => 'Elite Proxy Sistem',
            'Power Sistem' => 'Power Sistem Integrasi',
            'POWER SISTEM INTEGRASI' => 'Power Sistem Integrasi',
        ];

        if (Schema::hasTable('opportunity') && Schema::hasColumn('opportunity', 'company')) {
            foreach ($renames as $from => $to) {
                DB::table('opportunity')->where('company', $from)->update(['company' => $to]);
            }
        }

        if (Schema::hasTable('crm_quotation_templates') && Schema::hasColumn('crm_quotation_templates', 'category')) {
            foreach ($renames as $from => $to) {
                DB::table('crm_quotation_templates')->where('category', $from)->update(['category' => $to]);
            }
        }
    }

    public function down(): void
    {
        $renames = [
            'Elite Proxy Sistem' => 'Elite Proxy',
            'Power Sistem Integrasi' => 'Power Sistem',
        ];

        if (Schema::hasTable('opportunity') && Schema::hasColumn('opportunity', 'company')) {
            foreach ($renames as $from => $to) {
                DB::table('opportunity')->where('company', $from)->update(['company' => $to]);
            }
        }

        if (Schema::hasTable('crm_quotation_templates') && Schema::hasColumn('crm_quotation_templates', 'category')) {
            foreach ($renames as $from => $to) {
                DB::table('crm_quotation_templates')->where('category', $from)->update(['category' => $to]);
            }
        }
    }
};
