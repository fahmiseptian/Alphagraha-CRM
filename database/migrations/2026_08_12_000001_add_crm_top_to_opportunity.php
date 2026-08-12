<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TOP (Terms of Payment) per opportunity.
 * Default mengikuti customer, tetapi bisa diubah per deal.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('opportunity', 'crm_top')) {
            Schema::table('opportunity', function (Blueprint $table) {
                $table->string('crm_top', 10)->nullable()->after('account_id');
            });
        }

        if (Schema::hasColumn('account', 'crm_top')) {
            DB::update('
                UPDATE opportunity o
                INNER JOIN account a ON a.id = o.account_id
                SET o.crm_top = a.crm_top
                WHERE (o.crm_top IS NULL OR o.crm_top = ?)
                  AND a.crm_top IS NOT NULL
                  AND a.crm_top != ?
            ', ['', '']);
        }

        DB::table('opportunity')
            ->where(function ($q) {
                $q->whereNull('crm_top')->orWhere('crm_top', '');
            })
            ->update(['crm_top' => 'cash']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('opportunity', 'crm_top')) {
            Schema::table('opportunity', function (Blueprint $table) {
                $table->dropColumn('crm_top');
            });
        }
    }
};
