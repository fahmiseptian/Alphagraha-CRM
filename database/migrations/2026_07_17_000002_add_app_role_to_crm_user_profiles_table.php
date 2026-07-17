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
            $table->string('app_role', 30)->nullable()->after('user_id');
        });

        // Admin Espo lama → Superadmin (full). Regular → Sales.
        User::query()->with('profile')->orderBy('id')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                $role = $user->type === 'admin' ? 'superadmin' : 'sales';
                $profile = UserProfile::ensureForUser($user->id, $role === 'sales');
                if (! $profile->app_role) {
                    $profile->app_role = $role;
                    $profile->save();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_user_profiles', function (Blueprint $table) {
            $table->dropColumn('app_role');
        });
    }
};
