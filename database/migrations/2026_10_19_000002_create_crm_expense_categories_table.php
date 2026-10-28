<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $defaults = ['Biaya Entertainment', 'Bank Garansi', 'Biaya Transport'];
        foreach ($defaults as $i => $name) {
            DB::table('crm_expense_categories')->insert([
                'name' => $name,
                'sort_order' => $i + 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $entertainmentId = DB::table('crm_expense_categories')->where('name', 'Biaya Entertainment')->value('id');

        Schema::table('crm_commission_expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('period_key')->index();
        });
        Schema::table('crm_opportunity_entertainments', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('opportunity_id')->index();
        });

        DB::table('crm_commission_expenses')->whereNull('category_id')->update(['category_id' => $entertainmentId]);
        DB::table('crm_opportunity_entertainments')->whereNull('category_id')->update(['category_id' => $entertainmentId]);
    }

    public function down(): void
    {
        Schema::table('crm_opportunity_entertainments', function (Blueprint $table) {
            $table->dropColumn('category_id');
        });
        Schema::table('crm_commission_expenses', function (Blueprint $table) {
            $table->dropColumn('category_id');
        });
        Schema::dropIfExists('crm_expense_categories');
    }
};
