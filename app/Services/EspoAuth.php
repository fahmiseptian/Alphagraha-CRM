<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Autentikasi terhadap tabel `user` EspoCRM.
 *
 *  1. Cari user berdasarkan user_name (atau email).
 *  2. Verifikasi password memakai algoritma EspoCRM (EspoPassword).
 *  3. Kembalikan model User (tabel `user`) untuk di-login-kan via Auth::login().
 */
class EspoAuth
{
    /** Tipe user EspoCRM yang tidak boleh login ke CRM ini. */
    protected array $blockedTypes = ['api', 'portal', 'system'];

    public function attempt(string $login, string $password): ?User
    {
        $user = $this->find($login);

        if (! $user || ! $user->is_active || in_array($user->type, $this->blockedTypes, true)) {
            return null;
        }

        if (! EspoPassword::verify($password, $user->password)) {
            return null;
        }

        return $user;
    }

    /**
     * Cari user EspoCRM berdasarkan user_name, fallback ke alamat email.
     */
    protected function find(string $login): ?User
    {
        $user = User::query()->where('user_name', $login)->first();

        if ($user) {
            return $user;
        }

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $emailId = DB::table('email_address')
                ->where('lower', Str::lower($login))
                ->where('deleted', 0)
                ->value('id');

            if ($emailId) {
                $entityId = DB::table('entity_email_address')
                    ->where('entity_type', 'User')
                    ->where('email_address_id', $emailId)
                    ->where('deleted', 0)
                    ->value('entity_id');

                if ($entityId) {
                    return User::query()->whereKey($entityId)->first();
                }
            }
        }

        return null;
    }
}
