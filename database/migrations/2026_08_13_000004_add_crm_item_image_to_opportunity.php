<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('opportunity')) {
            return;
        }

        if (! Schema::hasColumn('opportunity', 'crm_item_image')) {
            Schema::table('opportunity', function (Blueprint $table) {
                $after = Schema::hasColumn('opportunity', 'crm_item_brand')
                    ? 'crm_item_brand'
                    : (Schema::hasColumn('opportunity', 'vendor') ? 'vendor' : null);

                if ($after) {
                    $table->mediumText('crm_item_image')->nullable()->after($after);
                } else {
                    $table->mediumText('crm_item_image')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('opportunity') && Schema::hasColumn('opportunity', 'crm_item_image')) {
            Schema::table('opportunity', function (Blueprint $table) {
                $table->dropColumn('crm_item_image');
            });
        }
    }
};
