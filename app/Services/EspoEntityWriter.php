<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pembuatan ID dan sinkronisasi email/telepon primary
 * untuk entitas EspoCRM (Account, Lead, dll.).
 */
class EspoEntityWriter
{
    public function generateId(): string
    {
        return substr(bin2hex(random_bytes(12)), 0, 17);
    }

    public function syncPrimaryEmail(string $entityId, string $entityType, ?string $email): void
    {
        DB::table('entity_email_address')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
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

        $existing = DB::table('entity_email_address')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('email_address_id', $addressId)
            ->first();

        if ($existing) {
            DB::table('entity_email_address')->where('id', $existing->id)
                ->update(['deleted' => 0, 'primary' => 1]);
        } else {
            DB::table('entity_email_address')->insert([
                'entity_id' => $entityId,
                'email_address_id' => $addressId,
                'entity_type' => $entityType,
                'primary' => 1,
                'deleted' => 0,
            ]);
        }
    }

    public function syncPrimaryPhone(string $entityId, string $entityType, ?string $phone): void
    {
        DB::table('entity_phone_number')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->update(['deleted' => 1, 'primary' => 0]);

        $phone = $phone ? trim($phone) : null;
        if (! $phone) {
            return;
        }

        $numeric = preg_replace('/\D+/', '', $phone) ?: $phone;
        $phoneId = DB::table('phone_number')
            ->where('numeric', $numeric)
            ->where('deleted', 0)
            ->value('id');

        if (! $phoneId) {
            $phoneId = $this->generateId();
            DB::table('phone_number')->insert([
                'id' => $phoneId,
                'name' => $phone,
                'numeric' => $numeric,
                'type' => 'Mobile',
                'deleted' => 0,
                'invalid' => 0,
                'opt_out' => 0,
            ]);
        }

        $existing = DB::table('entity_phone_number')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('phone_number_id', $phoneId)
            ->first();

        if ($existing) {
            DB::table('entity_phone_number')->where('id', $existing->id)
                ->update(['deleted' => 0, 'primary' => 1]);
        } else {
            DB::table('entity_phone_number')->insert([
                'entity_id' => $entityId,
                'phone_number_id' => $phoneId,
                'entity_type' => $entityType,
                'primary' => 1,
                'deleted' => 0,
            ]);
        }
    }
}
