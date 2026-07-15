<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_quotations', function (Blueprint $table) {
            $table->string('base_number')->nullable()->after('number');
            $table->unsignedInteger('document_revision')->default(0)->after('revision');
        });

        DB::table('crm_quotations')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $base = preg_replace('/^(\d+)-R\d+(\/.*)$/', '$1$2', (string) $row->number) ?: $row->number;
                $docRev = 0;
                if (preg_match('/^(\d+)-R(\d+)\//', (string) $row->number, $m)) {
                    $docRev = (int) $m[2];
                }

                DB::table('crm_quotations')->where('id', $row->id)->update([
                    'base_number' => $base,
                    'document_revision' => $docRev,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_quotations', function (Blueprint $table) {
            $table->dropColumn(['base_number', 'document_revision']);
        });
    }
};
