<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('opportunity') && ! Schema::hasColumn('opportunity', 'crm_royalty_type')) {
            Schema::table('opportunity', function (Blueprint $table) {
                $table->mediumText('crm_royalty_type')->nullable()->after('crm_has_royalty');
            });
        }

        if (Schema::hasTable('crm_quotation_items') && ! Schema::hasColumn('crm_quotation_items', 'royalty_type')) {
            Schema::table('crm_quotation_items', function (Blueprint $table) {
                $table->string('royalty_type', 16)->nullable()->after('has_royalty');
            });
        }

        // Legacy has_royalty=true → luar negeri (rate lama default 20%).
        if (Schema::hasTable('opportunity') && Schema::hasColumn('opportunity', 'crm_has_royalty')) {
            $rows = DB::table('opportunity')
                ->select('id', 'crm_has_royalty')
                ->whereNotNull('crm_has_royalty')
                ->get();

            foreach ($rows as $row) {
                $flags = json_decode((string) $row->crm_has_royalty, true);
                if (! is_array($flags)) {
                    continue;
                }

                $types = array_map(function ($flag) {
                    $v = strtolower(trim((string) $flag));

                    return in_array($v, ['1', 'true', 'yes', 'on', 'luar'], true) ? 'luar' : '';
                }, $flags);

                DB::table('opportunity')->where('id', $row->id)->update([
                    'crm_royalty_type' => json_encode(array_values($types)),
                ]);
            }
        }

        if (Schema::hasTable('crm_quotation_items') && Schema::hasColumn('crm_quotation_items', 'has_royalty')) {
            DB::table('crm_quotation_items')
                ->where('has_royalty', true)
                ->where(function ($q) {
                    $q->whereNull('royalty_type')->orWhere('royalty_type', '');
                })
                ->update(['royalty_type' => 'luar']);
        }

        $dalamExists = DB::table('crm_settings')->where('key', 'tax.royalty_dalam_percent')->exists();
        if (! $dalamExists) {
            DB::table('crm_settings')->insert([
                'key' => 'tax.royalty_dalam_percent',
                'value' => '15',
                'type' => 'float',
                'group' => 'tax',
                'description' => 'Persentase royalti produk dalam negeri.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $luarExists = DB::table('crm_settings')->where('key', 'tax.royalty_luar_percent')->exists();
        if (! $luarExists) {
            $legacy = DB::table('crm_settings')->where('key', 'tax.royalty_percent')->value('value');
            DB::table('crm_settings')->insert([
                'key' => 'tax.royalty_luar_percent',
                'value' => $legacy !== null && $legacy !== '' ? (string) $legacy : '20',
                'type' => 'float',
                'group' => 'tax',
                'description' => 'Persentase royalti produk luar negeri.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('opportunity') && Schema::hasColumn('opportunity', 'crm_royalty_type')) {
            Schema::table('opportunity', function (Blueprint $table) {
                $table->dropColumn('crm_royalty_type');
            });
        }

        if (Schema::hasTable('crm_quotation_items') && Schema::hasColumn('crm_quotation_items', 'royalty_type')) {
            Schema::table('crm_quotation_items', function (Blueprint $table) {
                $table->dropColumn('royalty_type');
            });
        }

        DB::table('crm_settings')->whereIn('key', [
            'tax.royalty_dalam_percent',
            'tax.royalty_luar_percent',
        ])->delete();
    }
};
