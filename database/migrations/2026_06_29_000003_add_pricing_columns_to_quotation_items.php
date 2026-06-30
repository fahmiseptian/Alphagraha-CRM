<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_quotation_items', function (Blueprint $table) {
            $table->string('tax_category', 20)->nullable()->after('unit');
            $table->string('item_kind', 20)->nullable()->after('tax_category');
            $table->decimal('sell_exclude', 15, 2)->nullable()->after('item_kind');
            $table->decimal('cost_exclude', 15, 2)->nullable()->after('sell_exclude');
            $table->string('vendor')->nullable()->after('cost_exclude');
        });
    }

    public function down(): void
    {
        Schema::table('crm_quotation_items', function (Blueprint $table) {
            $table->dropColumn([
                'tax_category',
                'item_kind',
                'sell_exclude',
                'cost_exclude',
                'vendor',
            ]);
        });
    }
};
