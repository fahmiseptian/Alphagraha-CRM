<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_quotation_items')) {
            return;
        }

        Schema::table('crm_quotation_items', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_quotation_items', 'brand')) {
                $table->string('brand')->nullable()->after('vendor');
            }
            if (! Schema::hasColumn('crm_quotation_items', 'image')) {
                $table->string('image', 500)->nullable()->after('brand');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_quotation_items')) {
            return;
        }

        Schema::table('crm_quotation_items', function (Blueprint $table) {
            if (Schema::hasColumn('crm_quotation_items', 'image')) {
                $table->dropColumn('image');
            }
            if (Schema::hasColumn('crm_quotation_items', 'brand')) {
                $table->dropColumn('brand');
            }
        });
    }
};
