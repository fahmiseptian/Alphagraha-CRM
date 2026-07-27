<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Perbaiki typo tipe opportunity: Quatation → Quotation.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('opportunity')) {
            return;
        }

        DB::table('opportunity')
            ->where('type', 'Quatation')
            ->update(['type' => 'Quotation']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('opportunity')) {
            return;
        }

        DB::table('opportunity')
            ->where('type', 'Quotation')
            ->update(['type' => 'Quatation']);
    }
};
