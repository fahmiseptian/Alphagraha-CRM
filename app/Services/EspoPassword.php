<?php

namespace App\Services;

/**
 * Verifikasi password sesuai algoritma hashing EspoCRM.
 *
 * EspoCRM menyimpan: substr(crypt(md5(password), '$6$<salt>$'), strlen('$6$<salt>$'))
 * Salt diambil dari konfigurasi (data/config.php -> passwordSalt) milik instalasi EspoCRM.
 */
class EspoPassword
{
    public static function salt(): string
    {
        return (string) config('crm.espo_password_salt');
    }

    public static function verify(string $plainPassword, ?string $storedHash): bool
    {
        if (empty($storedHash)) {
            return false;
        }

        return hash_equals($storedHash, self::hash($plainPassword));
    }

    /**
     * Hasilkan hash password sesuai format penyimpanan EspoCRM.
     */
    public static function hash(string $plainPassword): string
    {
        $salt = self::salt();

        $generatedHash = crypt(md5($plainPassword), $salt);

        return str_replace($salt, '', $generatedHash);
    }
}
