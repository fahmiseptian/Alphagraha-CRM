<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leveling status pembayaran customer + threshold margin di crm_settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('account', 'crm_payment_level')) {
            Schema::table('account', function (Blueprint $table) {
                $table->string('crm_payment_level', 20)->default('lancar')->after('assigned_user_id');
            });
        }

        Schema::table('crm_quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_quotations', 'crm_margin_status')) {
                $table->string('crm_margin_status', 20)->nullable()->after('status');
            }
            if (! Schema::hasColumn('crm_quotations', 'crm_margin_percent')) {
                $table->decimal('crm_margin_percent', 8, 2)->nullable()->after('crm_margin_status');
            }
            if (! Schema::hasColumn('crm_quotations', 'crm_margin_threshold')) {
                $table->decimal('crm_margin_threshold', 8, 2)->nullable()->after('crm_margin_percent');
            }
            if (! Schema::hasColumn('crm_quotations', 'crm_margin_requested_at')) {
                $table->timestamp('crm_margin_requested_at')->nullable()->after('crm_margin_threshold');
            }
            if (! Schema::hasColumn('crm_quotations', 'crm_margin_reviewed_by')) {
                $table->string('crm_margin_reviewed_by', 24)->nullable()->after('crm_margin_requested_at');
            }
            if (! Schema::hasColumn('crm_quotations', 'crm_margin_reviewed_at')) {
                $table->timestamp('crm_margin_reviewed_at')->nullable()->after('crm_margin_reviewed_by');
            }
            if (! Schema::hasColumn('crm_quotations', 'crm_margin_note')) {
                $table->text('crm_margin_note')->nullable()->after('crm_margin_reviewed_at');
            }
        });

        $now = now();
        $rows = [
            [
                'key' => 'payment_level.margin_lancar',
                'value' => '5',
                'type' => 'number',
                'group' => 'payment_level',
                'label' => 'Minimal Margin Lancar (%)',
                'description' => 'Minimal margin opportunity (%) untuk customer level Lancar sebelum quotation perlu approval Superadmin.',
            ],
            [
                'key' => 'payment_level.margin_mandek',
                'value' => '8',
                'type' => 'number',
                'group' => 'payment_level',
                'label' => 'Minimal Margin Mandek (%)',
                'description' => 'Minimal margin opportunity (%) untuk customer level Mandek sebelum quotation perlu approval Superadmin.',
            ],
            [
                'key' => 'payment_level.margin_jelek',
                'value' => '15',
                'type' => 'number',
                'group' => 'payment_level',
                'label' => 'Minimal Margin Jelek (%)',
                'description' => 'Minimal margin opportunity (%) untuk customer level Jelek sebelum quotation perlu approval Superadmin.',
            ],
        ];

        foreach ($rows as $row) {
            $exists = DB::table('crm_settings')->where('key', $row['key'])->exists();
            if ($exists) {
                continue;
            }
            DB::table('crm_settings')->insert(array_merge($row, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        if (class_exists(\App\Models\CrmSetting::class)) {
            \App\Models\CrmSetting::forgetCache();
        }
    }

    public function down(): void
    {
        Schema::table('crm_quotations', function (Blueprint $table) {
            foreach ([
                'crm_margin_status',
                'crm_margin_percent',
                'crm_margin_threshold',
                'crm_margin_requested_at',
                'crm_margin_reviewed_by',
                'crm_margin_reviewed_at',
                'crm_margin_note',
            ] as $col) {
                if (Schema::hasColumn('crm_quotations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        if (Schema::hasColumn('account', 'crm_payment_level')) {
            Schema::table('account', function (Blueprint $table) {
                $table->dropColumn('crm_payment_level');
            });
        }

        DB::table('crm_settings')->where('group', 'payment_level')->delete();
    }
};
