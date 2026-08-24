<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Buku alamat customer: 1 account bisa banyak alamat (billing/shipping SO).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('account_id', 24)->index();
            $table->string('label', 100);
            $table->string('contact_name', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('street')->nullable();
            $table->string('province_code', 10)->nullable();
            $table->string('regency_code', 10)->nullable();
            $table->string('district_code', 15)->nullable();
            $table->string('province', 255)->nullable();
            $table->string('city', 255)->nullable();
            $table->string('district', 255)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->nullable();
            $table->boolean('is_default_billing')->default(false);
            $table->boolean('is_default_shipping')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('account')
            ->where('deleted', 0)
            ->orderBy('id')
            ->chunk(200, function ($accounts) use ($now) {
                $rows = [];
                foreach ($accounts as $account) {
                    $street = trim((string) ($account->billing_address_street ?? ''));
                    $city = trim((string) ($account->billing_address_city ?? ''));
                    $state = trim((string) ($account->billing_address_state ?? ''));
                    $district = trim((string) ($account->crm_billing_district ?? ''));
                    $postal = trim((string) ($account->billing_address_postal_code ?? ''));
                    if ($street === '' && $city === '' && $state === '' && $district === '') {
                        continue;
                    }

                    $rows[] = [
                        'account_id' => $account->id,
                        'label' => 'Alamat utama',
                        'contact_name' => null,
                        'phone' => null,
                        'street' => $street !== '' ? $street : null,
                        'province_code' => $this->clip((string) ($account->crm_province_code ?? ''), 10),
                        'regency_code' => $this->clip((string) ($account->crm_regency_code ?? ''), 10),
                        'district_code' => $this->clip((string) ($account->crm_district_code ?? ''), 15),
                        'province' => $this->clip($state, 255),
                        'city' => $this->clip($city, 255),
                        'district' => $this->clip($district, 255),
                        'postal_code' => $this->clip($postal, 20),
                        'country' => $this->clip((string) ($account->billing_address_country ?: 'Indonesia'), 100),
                        'is_default_billing' => 1,
                        'is_default_shipping' => 1,
                        'sort_order' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('crm_customer_addresses')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_customer_addresses');
    }

    protected function clip(?string $value, int $max): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }
};
