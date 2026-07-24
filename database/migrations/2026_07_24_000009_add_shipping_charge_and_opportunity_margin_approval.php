<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ongkir jual (checkbox) + approval margin opportunity (% & nominal).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            if (! Schema::hasColumn('opportunity', 'crm_has_shipping_charge')) {
                $table->boolean('crm_has_shipping_charge')->default(false)->after('crm_shipping_cost');
            }
            if (! Schema::hasColumn('opportunity', 'crm_shipping_sell')) {
                $table->decimal('crm_shipping_sell', 18, 2)->nullable()->after('crm_has_shipping_charge');
            }
            if (! Schema::hasColumn('opportunity', 'crm_margin_status')) {
                $table->string('crm_margin_status', 20)->nullable()->after('crm_discount_note');
            }
            if (! Schema::hasColumn('opportunity', 'crm_margin_percent')) {
                $table->decimal('crm_margin_percent', 8, 2)->nullable()->after('crm_margin_status');
            }
            if (! Schema::hasColumn('opportunity', 'crm_margin_threshold')) {
                $table->decimal('crm_margin_threshold', 8, 2)->nullable()->after('crm_margin_percent');
            }
            if (! Schema::hasColumn('opportunity', 'crm_margin_nominal')) {
                $table->decimal('crm_margin_nominal', 18, 2)->nullable()->after('crm_margin_threshold');
            }
            if (! Schema::hasColumn('opportunity', 'crm_margin_nominal_threshold')) {
                $table->decimal('crm_margin_nominal_threshold', 18, 2)->nullable()->after('crm_margin_nominal');
            }
            if (! Schema::hasColumn('opportunity', 'crm_margin_requested_at')) {
                $table->timestamp('crm_margin_requested_at')->nullable()->after('crm_margin_nominal_threshold');
            }
            if (! Schema::hasColumn('opportunity', 'crm_margin_reviewed_by')) {
                $table->string('crm_margin_reviewed_by', 24)->nullable()->after('crm_margin_requested_at');
            }
            if (! Schema::hasColumn('opportunity', 'crm_margin_reviewed_at')) {
                $table->timestamp('crm_margin_reviewed_at')->nullable()->after('crm_margin_reviewed_by');
            }
            if (! Schema::hasColumn('opportunity', 'crm_margin_note')) {
                $table->text('crm_margin_note')->nullable()->after('crm_margin_reviewed_at');
            }
        });

        Schema::table('crm_quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_quotations', 'crm_margin_nominal')) {
                $table->decimal('crm_margin_nominal', 18, 2)->nullable()->after('crm_margin_threshold');
            }
            if (! Schema::hasColumn('crm_quotations', 'crm_margin_nominal_threshold')) {
                $table->decimal('crm_margin_nominal_threshold', 18, 2)->nullable()->after('crm_margin_nominal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('opportunity', function (Blueprint $table) {
            foreach ([
                'crm_has_shipping_charge', 'crm_shipping_sell',
                'crm_margin_status', 'crm_margin_percent', 'crm_margin_threshold',
                'crm_margin_nominal', 'crm_margin_nominal_threshold',
                'crm_margin_requested_at', 'crm_margin_reviewed_by',
                'crm_margin_reviewed_at', 'crm_margin_note',
            ] as $col) {
                if (Schema::hasColumn('opportunity', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('crm_quotations', function (Blueprint $table) {
            foreach (['crm_margin_nominal', 'crm_margin_nominal_threshold'] as $col) {
                if (Schema::hasColumn('crm_quotations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
