<?php

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_user_profiles', function (Blueprint $table) {
            $table->decimal('sales_target', 18, 2)
                ->default(UserProfile::DEFAULT_SALES_TARGET)
                ->after('job_position');
        });

        $salesUserIds = User::query()
            ->where('type', 'regular')
            ->pluck('id');

        foreach ($salesUserIds as $userId) {
            UserProfile::query()->firstOrCreate(
                ['user_id' => $userId],
                ['sales_target' => UserProfile::DEFAULT_SALES_TARGET]
            );
        }

        UserProfile::query()
            ->whereIn('user_id', $salesUserIds)
            ->where(function ($query) {
                $query->whereNull('sales_target')
                    ->orWhere('sales_target', '<=', 0);
            })
            ->update(['sales_target' => UserProfile::DEFAULT_SALES_TARGET]);
    }

    public function down(): void
    {
        Schema::table('crm_user_profiles', function (Blueprint $table) {
            $table->dropColumn('sales_target');
        });
    }
};
