<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('crm_commission_expenses', 'file_path')) {
            Schema::table('crm_commission_expenses', function (Blueprint $table) {
                $table->string('file_path', 500)->nullable()->after('note');
                $table->string('original_name', 255)->nullable()->after('file_path');
                $table->string('mime_type', 150)->nullable()->after('original_name');
                $table->unsignedBigInteger('file_size')->nullable()->after('mime_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('crm_commission_expenses', 'file_path')) {
            Schema::table('crm_commission_expenses', function (Blueprint $table) {
                $table->dropColumn(['file_path', 'original_name', 'mime_type', 'file_size']);
            });
        }
    }
};
