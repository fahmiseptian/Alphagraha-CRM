<?php

use App\Models\Industry;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Industri customer menjadi master data (superadmin),
 * nilai lama di account dikosongkan agar sales mengisi ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_industries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique('name');
        });

        $now = now();
        $rows = [];
        foreach (Industry::DEFAULT_NAMES as $index => $name) {
            $rows[] = [
                'name' => $name,
                'is_active' => true,
                'sort_order' => ($index + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('crm_industries')->insert($rows);

        if (Schema::hasTable('account') && Schema::hasColumn('account', 'industry')) {
            DB::table('account')->update(['industry' => null]);
        }

        try {
            $this->notifySalesToUpdateIndustry();
        } catch (\Throwable $e) {
            // Notifikasi gagal tidak boleh menggagalkan migrasi.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_industries');
    }

    protected function notifySalesToUpdateIndustry(): void
    {
        if (! Schema::hasTable('crm_notifications') || ! Schema::hasTable('user')) {
            return;
        }

        $title = 'Perbarui industri customer';
        $body = 'Daftar industri customer sudah distandarkan. Mohon perbarui data customer Anda sesuai industri yang tersedia.';
        $link = '/customers?missing_industry=1';
        $now = now();

        $salesIds = User::query()
            ->where('deleted', 0)
            ->where('is_active', 1)
            ->whereHas('profile', fn ($q) => $q->where('app_role', User::ROLE_SALES))
            ->pluck('id');

        foreach ($salesIds as $userId) {
            $uniqueKey = 'customer_industry_update:'.$userId;
            $exists = DB::table('crm_notifications')
                ->where('user_id', $userId)
                ->where('unique_key', $uniqueKey)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('crm_notifications')->insert([
                'user_id' => $userId,
                'type' => 'customer_industry_update',
                'title' => $title,
                'body' => $body,
                'link' => $link,
                'unique_key' => $uniqueKey,
                'data' => json_encode(['scope' => 'all']),
                'show_popup' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
