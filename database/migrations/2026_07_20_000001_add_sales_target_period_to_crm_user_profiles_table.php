<?php

use App\Models\UserProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_user_profiles', function (Blueprint $table) {
            $table->string('sales_target_period', 20)
                ->nullable()
                ->default(UserProfile::TARGET_PERIOD_1_YEAR)
                ->after('sales_target');
            $table->date('sales_target_deadline')
                ->nullable()
                ->after('sales_target_period');
        });

        UserProfile::query()
            ->whereNotNull('sales_target')
            ->where(function ($query) {
                $query->whereNull('sales_target_period')
                    ->orWhere('sales_target_period', '');
            })
            ->update(['sales_target_period' => UserProfile::TARGET_PERIOD_1_YEAR]);
    }

    public function down(): void
    {
        Schema::table('crm_user_profiles', function (Blueprint $table) {
            $table->dropColumn(['sales_target_period', 'sales_target_deadline']);
        });
    }
};
