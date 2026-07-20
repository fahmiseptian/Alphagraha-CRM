<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pembuatan, pembaruan, dan penghapusan pengguna langsung pada tabel `user`
 * EspoCRM (termasuk sinkronisasi alamat email ke email_address /
 * entity_email_address agar tetap konsisten dengan EspoCRM).
 */
class EspoUserManager
{
    /**
     * Buat user baru. $data: name, user_name, type, is_active, password, email.
     */
    public function create(array $data): User
    {
        $id = $this->generateId();
        [$first, $last] = $this->splitName($data['name'] ?? '');
        $now = Carbon::now()->format('Y-m-d H:i:s');

        DB::table('user')->insert([
            'id' => $id,
            'deleted' => 0,
            'user_name' => $data['user_name'],
            'type' => $data['type'],
            'password' => EspoPassword::hash($data['password']),
            'first_name' => $first,
            'last_name' => $last,
            'name' => trim($data['name'] ?? ''),
            'is_active' => ! empty($data['is_active']) ? 1 : 0,
            'created_at' => $now,
            'modified_at' => $now,
            'created_by_id' => auth()->id(),
        ]);

        if (! empty($data['email'])) {
            $this->syncPrimaryEmail($id, $data['email']);
        }

        UserProfile::ensureForUser(
            $id,
            ($data['app_role'] ?? '') === User::ROLE_SALES,
            $data['app_role'] ?? null
        );

        if ($this->shouldSyncProfile($data)) {
            $this->syncProfile(
                $id,
                $data['app_role'] ?? User::ROLE_SALES,
                $data['sales_code'] ?? null,
                $data['sales_target'] ?? null,
                $data['sales_target_period'] ?? null,
                $data['sales_target_deadline'] ?? null
            );
        }

        return User::query()->whereKey($id)->first();
    }

    /**
     * Perbarui user. Password & email opsional (null/empty = tidak diubah / dikosongkan).
     */
    public function update(User $user, array $data): User
    {
        [$first, $last] = $this->splitName($data['name'] ?? '');

        $payload = [
            'user_name' => $data['user_name'],
            'type' => $data['type'],
            'first_name' => $first,
            'last_name' => $last,
            'name' => trim($data['name'] ?? ''),
            'is_active' => ! empty($data['is_active']) ? 1 : 0,
            'modified_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ];

        if (! empty($data['password'])) {
            $payload['password'] = EspoPassword::hash($data['password']);
        }

        DB::table('user')->where('id', $user->id)->update($payload);

        if (array_key_exists('email', $data)) {
            $this->syncPrimaryEmail($user->id, $data['email']);
        }

        if ($this->shouldSyncProfile($data)) {
            $this->syncProfile(
                $user->id,
                $data['app_role'] ?? $user->role,
                $data['sales_code'] ?? null,
                $data['sales_target'] ?? null,
                $data['sales_target_period'] ?? null,
                $data['sales_target_deadline'] ?? null
            );
        }

        return $user->fresh(['profile']);
    }

    protected function shouldSyncProfile(array $data): bool
    {
        return array_key_exists('app_role', $data)
            || array_key_exists('sales_code', $data)
            || array_key_exists('sales_target', $data)
            || array_key_exists('sales_target_period', $data)
            || array_key_exists('sales_target_deadline', $data);
    }

    /**
     * Simpan app_role + sales_code + sales_target (+ periode & tenggat) ke profil.
     */
    protected function syncProfile(
        string $userId,
        string $appRole,
        ?string $salesCode,
        ?float $salesTarget = null,
        ?string $salesTargetPeriod = null,
        ?string $salesTargetDeadline = null
    ): void {
        $isSales = $appRole === User::ROLE_SALES;
        $profile = UserProfile::ensureForUser($userId, $isSales, $appRole);
        $profile->app_role = $appRole;
        $profile->sales_code = ($salesCode !== null && trim($salesCode) !== '')
            ? strtoupper(trim($salesCode))
            : null;
        if ($isSales) {
            $profile->sales_target = ($salesTarget !== null && $salesTarget > 0)
                ? $salesTarget
                : ($profile->sales_target ?: UserProfile::DEFAULT_SALES_TARGET);
            $profile->sales_target_period = ($salesTargetPeriod && array_key_exists($salesTargetPeriod, UserProfile::TARGET_PERIODS))
                ? $salesTargetPeriod
                : ($profile->sales_target_period ?: UserProfile::TARGET_PERIOD_1_YEAR);
            $profile->sales_target_deadline = $salesTargetDeadline ?: null;
        } else {
            $profile->sales_target = null;
            $profile->sales_target_period = null;
            $profile->sales_target_deadline = null;
        }
        $profile->save();
    }

    /**
     * @deprecated diganti syncProfile
     */
    protected function syncSalesCode(string $userId, ?string $salesCode, bool $isSales = true): void
    {
        $this->syncProfile($userId, $isSales ? User::ROLE_SALES : User::ROLE_ADMIN, $salesCode);
    }

    /**
     * Nonaktifkan & hapus (soft delete ala EspoCRM).
     */
    public function delete(User $user): void
    {
        DB::table('user')->where('id', $user->id)->update([
            'deleted' => 1,
            'is_active' => 0,
            'modified_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Sinkronkan satu alamat email primary untuk user.
     */
    protected function syncPrimaryEmail(string $userId, ?string $email): void
    {
        // Nonaktifkan seluruh tautan email lama milik user.
        DB::table('entity_email_address')
            ->where('entity_type', 'User')
            ->where('entity_id', $userId)
            ->update(['deleted' => 1, 'primary' => 0]);

        $email = $email ? trim($email) : null;
        if (! $email) {
            return;
        }

        $lower = Str::lower($email);

        $addressId = DB::table('email_address')->where('lower', $lower)->where('deleted', 0)->value('id');
        if (! $addressId) {
            $addressId = $this->generateId();
            DB::table('email_address')->insert([
                'id' => $addressId,
                'name' => $email,
                'lower' => $lower,
                'deleted' => 0,
                'invalid' => 0,
                'opt_out' => 0,
            ]);
        }

        // Hidupkan kembali tautan yang sudah ada, atau buat baru.
        $existing = DB::table('entity_email_address')
            ->where('entity_type', 'User')
            ->where('entity_id', $userId)
            ->where('email_address_id', $addressId)
            ->first();

        if ($existing) {
            DB::table('entity_email_address')->where('id', $existing->id)
                ->update(['deleted' => 0, 'primary' => 1]);
        } else {
            DB::table('entity_email_address')->insert([
                'entity_id' => $userId,
                'email_address_id' => $addressId,
                'entity_type' => 'User',
                'primary' => 1,
                'deleted' => 0,
            ]);
        }
    }

    protected function splitName(string $full): array
    {
        $full = trim($full);
        if ($full === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $full);
        $first = array_shift($parts);

        return [$first, implode(' ', $parts)];
    }

    protected function generateId(): string
    {
        return substr(bin2hex(random_bytes(12)), 0, 17);
    }
}
