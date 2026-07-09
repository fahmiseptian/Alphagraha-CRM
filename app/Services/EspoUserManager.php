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

        UserProfile::ensureForUser($id, ($data['type'] ?? '') === 'regular');

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

        return $user->fresh();
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
